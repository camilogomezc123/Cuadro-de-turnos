<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
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
        ], [
            'email.required'    => 'El correo es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard.index'))
                ->with('success', 'Bienvenido, ' . Auth::user()->name);
        }

        return back()->withErrors([
            'email' => 'Correo o contraseña incorrectos.',
        ])->withInput($request->only('email'));
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
        $usuarios = User::orderBy('rol')->orderBy('name')->get();
        return view('auth.usuarios', compact('usuarios'));
    }

    public function crearUsuario(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'rol'      => 'required|in:master,coordinador,visualizador',
        ]);

        User::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'rol'          => $request->rol,
            'uci_asignada' => $request->uci_asignada,
        ]);

        return back()->with('success', "Usuario {$request->name} creado correctamente.");
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
        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);
        $usuario->update(['password' => Hash::make($request->password)]);
        return back()->with('success', 'Contraseña actualizada.');
    }
}
