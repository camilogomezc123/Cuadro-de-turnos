<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Medico;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Médicos van directo a su portal personal
            if ($user->esMedico()) {
                return redirect()->route('medico.portal');
            }

            return redirect()->intended(route('dashboard.index'))
                ->with('success', 'Bienvenido, ' . $user->name);
        }

        return back()->withErrors(['email' => 'Correo o contraseña incorrectos.'])
                     ->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    // ── Gestión de usuarios (solo master) ────────────────────────

    public function usuarios()
    {
        $usuarios = User::with('medico')->orderBy('rol')->orderBy('name')->get();

        $medicoIdsConUsuario = User::whereNotNull('medico_id')->pluck('medico_id');

        $medicosSinUsuario = Medico::where('activo', true)
            ->whereNotIn('id', $medicoIdsConUsuario)
            ->with('uci')
            ->orderBy('nombre')
            ->get();

        $medicosConUsuario = Medico::where('activo', true)
            ->whereIn('id', $medicoIdsConUsuario)
            ->with(['uci', 'user'])
            ->orderBy('nombre')
            ->get();

        return view('auth.usuarios', compact('usuarios', 'medicosSinUsuario', 'medicosConUsuario'));
    }

    public function crearUsuario(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|min:6|confirmed',
            'rol'       => 'required|in:master,medico',
            'medico_id' => 'nullable|exists:medicos,id',
        ]);

        $data = [
            'name'         => $request->name,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'rol'          => $request->rol,
            'uci_asignada' => $request->uci_asignada,
            'medico_id'    => $request->rol === 'medico' ? $request->medico_id : null,
        ];

        User::create($data);
        return back()->with('success', "Usuario {$request->name} creado correctamente.");
    }

    // Crear usuario médico masivamente desde lista de médicos sin cuenta
    public function crearUsuariosMedicos(Request $request)
    {
        $request->validate([
            'medico_ids'   => 'required|array',
            'medico_ids.*' => 'exists:medicos,id',
            'password_default' => 'required|min:6',
        ]);

        $creados = 0;
        foreach ($request->medico_ids as $medicoId) {
            $medico = Medico::find($medicoId);
            if (!$medico) continue;

            // Saltar si ya tiene usuario
            if (User::where('medico_id', $medicoId)->exists()) continue;

            // Generar email desde nombre
            $emailBase = strtolower(str_replace([' ', 'á','é','í','ó','ú','ñ'], ['.',  'a','e','i','o','u','n'], $medico->nombre));
            $email     = $emailBase . '@medico.uci.local';
            $sufijo    = 1;
            while (User::where('email', $email)->exists()) {
                $email = $emailBase . $sufijo . '@medico.uci.local';
                $sufijo++;
            }

            User::create([
                'name'      => $medico->nombre,
                'email'     => $email,
                'password'  => Hash::make($request->password_default),
                'rol'       => 'medico',
                'medico_id' => $medicoId,
            ]);
            $creados++;
        }

        return back()->with('success', "{$creados} usuario(s) médico(s) creado(s).");
    }

    public function eliminarUsuario(User $usuario)
    {
        if ($usuario->id === Auth::id()) {
            return back()->with('error', 'No puede eliminar su propio usuario.');
        }
        $usuario->delete();
        return back()->with('success', 'Usuario eliminado.');
    }

    public function cambiarPassword(Request $request, User $usuario)
    {
        $request->validate(['password' => 'required|min:6|confirmed']);
        $usuario->update(['password' => Hash::make($request->password)]);
        return back()->with('success', 'Contraseña actualizada.');
    }

    public function toggleUsuario(User $usuario)
    {
        if ($usuario->id === Auth::id()) {
            return back()->with('error', 'No puede desactivar su propio usuario.');
        }
        if ($usuario->medico_id && $usuario->medico) {
            $nuevo = !$usuario->medico->puede_ingresar_sistema;
            $usuario->medico->update(['puede_ingresar_sistema' => $nuevo]);
        }
        $activo = !is_null($usuario->email_verified_at);
        $usuario->update(['email_verified_at' => $activo ? null : now()]);
        return back()->with('success', 'Estado del usuario actualizado.');
    }
}
