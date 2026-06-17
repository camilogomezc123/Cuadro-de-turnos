<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\Medico;
use App\Models\Uci;
use App\Models\AuditoriaSistema;
use Illuminate\Http\Request;

class AusenciaController extends Controller
{
    public function index(Request $request)
    {
        $ucis      = Uci::where('activa', true)->orderBy('nombre')->get();
        $uciId     = $request->integer('uci_id', 0);
        $estado    = $request->string('estado', '');
        $tipo      = $request->string('tipo', '');

        $query = Ausencia::with('medico.uci')
            ->orderByDesc('fecha_inicio');

        if ($uciId) $query->whereHas('medico', fn($q) => $q->where('uci_id', $uciId));
        if ($estado) $query->where('estado', $estado);
        if ($tipo)   $query->where('tipo', $tipo);

        $ausencias  = $query->paginate(30)->withQueryString();
        $medicos    = Medico::where('activo', true)->orderBy('nombre')->get();

        return view('ausencias.index', compact('ausencias', 'ucis', 'medicos', 'uciId', 'estado', 'tipo'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'medico_id'            => 'required|exists:medicos,id',
            'tipo'                 => 'required|in:vacaciones,permiso,incapacidad,licencia,otro',
            'fecha_inicio'         => 'required|date',
            'fecha_fin'            => 'required|date|after_or_equal:fecha_inicio',
            'descripcion'          => 'nullable|string|max:255',
            'documento_referencia' => 'nullable|string|max:120',
        ]);

        $ausencia = Ausencia::create($data + ['estado' => 'pendiente']);

        AuditoriaSistema::registrar('CREAR_AUSENCIA', 'ausencias', 'Ausencia', $ausencia->id,
            null, $data, "Nueva ausencia tipo {$data['tipo']} para médico #{$data['medico_id']}");

        return back()->with('success', 'Ausencia registrada correctamente. Pendiente de aprobación.');
    }

    public function destroy(Ausencia $ausencia)
    {
        AuditoriaSistema::registrar('ELIMINAR_AUSENCIA', 'ausencias', 'Ausencia', $ausencia->id);
        $ausencia->delete();
        return back()->with('success', 'Ausencia eliminada.');
    }

    public function aprobar(Ausencia $ausencia)
    {
        $ausencia->update(['estado' => 'aprobada', 'aprobada_por' => 'coordinador', 'aprobada_at' => now()]);
        AuditoriaSistema::registrar('APROBAR_AUSENCIA', 'ausencias', 'Ausencia', $ausencia->id);
        return back()->with('success', 'Ausencia aprobada.');
    }

    public function rechazar(Ausencia $ausencia)
    {
        $ausencia->update(['estado' => 'rechazada', 'aprobada_por' => 'coordinador', 'aprobada_at' => now()]);
        AuditoriaSistema::registrar('RECHAZAR_AUSENCIA', 'ausencias', 'Ausencia', $ausencia->id);
        return back()->with('success', 'Ausencia rechazada.');
    }
}
