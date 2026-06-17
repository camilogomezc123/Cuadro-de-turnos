<?php

namespace App\Http\Controllers;

use App\Models\AlertaTurno;
use App\Models\ArchivoCargado;
use App\Models\Uci;
use App\Services\AlertService;
use Illuminate\Http\Request;

class AlertaController extends Controller
{
    public function __construct(private AlertService $alertService) {}

    public function index(Request $request)
    {
        $archivos   = ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->get();
        $ucis       = Uci::where('activa', true)->orderBy('nombre')->get();
        $archivoId  = $request->integer('archivo_id', $archivos->first()?->id ?? 0);
        $uciId      = $request->integer('uci_id', 0);
        $prioridad  = $request->string('prioridad', '');
        $estado     = $request->string('estado', 'abierta');
        $tipo       = $request->string('tipo', '');

        $query = AlertaTurno::with(['medico', 'uci'])
            ->orderByRaw("FIELD(prioridad, 'alta', 'media', 'baja')")
            ->orderByDesc('created_at');

        if ($archivoId) $query->where('archivo_id', $archivoId);
        if ($uciId)     $query->where('uci_id', $uciId);
        if ($prioridad) $query->where('prioridad', $prioridad);
        if ($estado)    $query->where('estado', $estado);
        if ($tipo)      $query->where('tipo', $tipo);

        $alertas     = $query->paginate(50)->withQueryString();
        $totalAbiertas = AlertaTurno::where('archivo_id', $archivoId)->where('estado', 'abierta')->count();
        $totalAltas    = AlertaTurno::where('archivo_id', $archivoId)->where('prioridad', 'alta')->where('estado', 'abierta')->count();
        $tipos         = AlertaTurno::TIPOS;

        return view('alertas.index', compact(
            'archivos', 'ucis', 'alertas',
            'archivoId', 'uciId', 'prioridad', 'estado', 'tipo',
            'totalAbiertas', 'totalAltas', 'tipos'
        ));
    }

    public function cambiarEstado(Request $request, AlertaTurno $alerta)
    {
        $request->validate(['estado' => 'required|in:abierta,en_revision,cerrada']);
        $alerta->update([
            'estado'          => $request->estado,
            'nota_resolucion' => $request->nota ?? null,
            'resuelta_por'    => $request->estado === 'cerrada' ? 'coordinador' : null,
            'resuelta_at'     => $request->estado === 'cerrada' ? now() : null,
        ]);
        return back()->with('success', 'Estado de alerta actualizado.');
    }

    public function destroy(AlertaTurno $alerta)
    {
        $alerta->delete();
        return back()->with('success', 'Alerta eliminada.');
    }

    public function ejecutarValidacion(int $archivoId)
    {
        $archivo = ArchivoCargado::findOrFail($archivoId);
        $total   = $this->alertService->validarArchivo($archivoId);
        return back()->with('success', "Validación completada. {$total} alertas generadas para {$archivo->nombre_mes} {$archivo->anio}.");
    }
}
