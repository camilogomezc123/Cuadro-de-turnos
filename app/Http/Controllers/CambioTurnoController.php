<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCambioTurno;
use App\Models\TurnoMedico;
use App\Models\Medico;
use App\Models\ArchivoCargado;
use App\Models\AuditoriaSistema;
use App\Services\TurnoService;
use Illuminate\Http\Request;

class CambioTurnoController extends Controller
{
    public function __construct(private TurnoService $turnoService) {}

    public function index(Request $request)
    {
        $user    = auth()->user();
        $estado  = $request->string('estado', '');

        // Períodos disponibles
        $archivos  = ArchivoCargado::orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = (int)($request->archivo_id ?? $archivos->first()?->id ?? 0);
        $archivo   = $archivos->firstWhere('id', $archivoId);

        // Turnos del médico en el período seleccionado (para el formulario de solicitud)
        $turnos = collect();
        if ($user->medico_id && $archivo) {
            $turnos = TurnoMedico::where('medico_id', $user->medico_id)
                ->where('archivo_id', $archivoId)
                ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
                ->orderBy('fecha')
                ->with('medico')
                ->get();
        }

        $query = SolicitudCambioTurno::with([
                'turnoOrigen.medico', 'turnoOrigen.uci',
                'turnoDestino.medico',
                'medicoSolicitante', 'medicoReceptor',
            ])->orderByDesc('created_at');

        // Operativo: solo ve sus propias solicitudes
        if ($user->esMedico() && $user->medico_id) {
            $query->where(function($q) use ($user) {
                $q->where('medico_solicitante_id', $user->medico_id)
                  ->orWhere('medico_receptor_id',  $user->medico_id);
            });
        }

        if ($estado) $query->where('estado', $estado);

        if ($archivo) {
            $query->whereHas('turnoOrigen', fn($q) =>
                $q->whereYear('fecha', $archivo->anio)->whereMonth('fecha', $archivo->mes)
            );
        }

        $solicitudes = $query->paginate(25)->withQueryString();
        $medicos     = Medico::where('activo', true)->orderBy('nombre')->get();
        $esMaestro   = $user->esMaster();

        return view('cambios-turno.index', compact(
            'solicitudes','estado','medicos','esMaestro',
            'archivos','archivoId','turnos'
        ));
    }

    public function misTurnos(Request $request)
    {
        $user      = auth()->user();
        $archivoId = (int)$request->archivo_id;

        if (!$archivoId) {
            return response()->json([]);
        }

        // Maestro sin medico_id: devuelve vacío (el maestro elige turno manualmente)
        if (!$user->medico_id) {
            return response()->json([]);
        }

        $turnos = TurnoMedico::where('medico_id', $user->medico_id)
            ->where('archivo_id', $archivoId)
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->orderBy('fecha')
            ->get(['id','fecha','codigo_turno','uci_id']);

        return response()->json($turnos->map(fn($t) => [
            'id'     => $t->id,
            'label'  => \Carbon\Carbon::parse($t->fecha)->format('d/m') . ' · ' . $t->codigo_turno,
            'fecha'  => \Carbon\Carbon::parse($t->fecha)->format('d/m/Y'),
        ]));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'turno_origen_id'    => 'required|exists:turno_medicos,id',
            'medico_receptor_id' => 'required|exists:medicos,id',
            'motivo'             => 'required|string|min:10|max:500',
        ]);

        $turnoOrigen = TurnoMedico::findOrFail($data['turno_origen_id']);

        // Operativo solo puede solicitar cambio de sus propios turnos
        if ($user->esMedico() && $user->medico_id && $turnoOrigen->medico_id !== $user->medico_id) {
            return back()->with('error', 'Solo puedes solicitar cambios de tus propios turnos.');
        }

        $solicitud = SolicitudCambioTurno::create([
            'turno_origen_id'       => $turnoOrigen->id,
            'medico_solicitante_id' => $turnoOrigen->medico_id,
            'medico_receptor_id'    => $data['medico_receptor_id'],
            'motivo'                => $data['motivo'],
            'estado'                => 'pendiente',
        ]);

        AuditoriaSistema::registrar('SOLICITAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $solicitud->id,
            null, $data, "Solicitud de cambio de turno del médico #{$turnoOrigen->medico_id}");

        return back()->with('success', 'Solicitud de cambio enviada. Esperando respuesta del colega.');
    }

    public function aceptar(Request $request, SolicitudCambioTurno $cambio)
    {
        $user = auth()->user();

        // Solo el médico receptor (o maestro) puede aceptar
        if ($user->esMedico() && $user->medico_id && $cambio->medico_receptor_id !== $user->medico_id) {
            return back()->with('error', 'Solo el médico receptor puede aceptar esta solicitud.');
        }

        if ($cambio->estado !== 'pendiente') {
            return back()->with('error', 'Esta solicitud ya fue respondida.');
        }

        $request->validate(['turno_destino_id' => 'nullable|exists:turno_medicos,id']);

        $cambio->update([
            'estado'               => 'aceptado_colega',
            'turno_destino_id'     => $request->turno_destino_id,
            'respuesta_colega'     => $request->respuesta ?? 'Aceptado',
            'respondido_colega_at' => now(),
        ]);
        AuditoriaSistema::registrar('ACEPTAR_CAMBIO_COLEGA', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Cambio aceptado. Esperando aprobación del coordinador.');
    }

    public function rechazarColega(Request $request, SolicitudCambioTurno $cambio)
    {
        $user = auth()->user();

        // Solo el médico receptor (o maestro) puede rechazar
        if ($user->esMedico() && $user->medico_id && $cambio->medico_receptor_id !== $user->medico_id) {
            return back()->with('error', 'Solo el médico receptor puede rechazar esta solicitud.');
        }

        if ($cambio->estado !== 'pendiente') {
            return back()->with('error', 'Esta solicitud ya fue respondida.');
        }

        $cambio->update([
            'estado'               => 'rechazado_colega',
            'respuesta_colega'     => $request->motivo ?? 'Rechazado',
            'respondido_colega_at' => now(),
        ]);
        AuditoriaSistema::registrar('RECHAZAR_CAMBIO_COLEGA', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Cambio rechazado.');
    }

    public function aprobar(SolicitudCambioTurno $cambio)
    {
        if ($cambio->estado !== 'aceptado_colega') {
            return back()->with('error', 'El cambio debe estar aceptado por el colega antes de ser aprobado.');
        }

        $tOrigen  = $cambio->turnoOrigen;
        $tDestino = $cambio->turnoDestino;

        if ($tOrigen && $tDestino) {
            // Intercambiar códigos de turno entre los dos médicos
            [$codigoA, $codigoB] = [$tOrigen->codigo_turno, $tDestino->codigo_turno];
            $horasA = TurnoService::horasPorCodigo($codigoB);
            $horasB = TurnoService::horasPorCodigo($codigoA);

            $tOrigen->update([
                'codigo_turno'        => $codigoB,
                'horas_diurnas'       => $horasA['diurnas'],
                'horas_nocturnas'     => $horasA['nocturnas'],
                'horas_total'         => $horasA['total'],
                'editado_manualmente' => true,
                'editado_por'         => 'coordinador',
                'editado_at'          => now(),
            ]);
            $tDestino->update([
                'codigo_turno'        => $codigoA,
                'horas_diurnas'       => $horasB['diurnas'],
                'horas_nocturnas'     => $horasB['nocturnas'],
                'horas_total'         => $horasB['total'],
                'editado_manualmente' => true,
                'editado_por'         => 'coordinador',
                'editado_at'          => now(),
            ]);

            // Recalcular indicadores (horas del mes) para ambos médicos
            $fechaO = \Carbon\Carbon::parse($tOrigen->fecha);
            $fechaD = \Carbon\Carbon::parse($tDestino->fecha);

            $this->turnoService->recalcularIndicadorMedico(
                $tOrigen->medico_id, $tOrigen->uci_id, $tOrigen->archivo_id,
                $fechaO->month, $fechaO->year
            );
            $this->turnoService->recalcularIndicadorMedico(
                $tDestino->medico_id, $tDestino->uci_id, $tDestino->archivo_id,
                $fechaD->month, $fechaD->year
            );
        }

        $cambio->update(['estado' => 'aprobado_coordinador', 'aprobado_por' => 'coordinador', 'resuelto_at' => now()]);
        AuditoriaSistema::registrar('APROBAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Cambio de turno aprobado y aplicado. Las horas han sido recalculadas.');
    }

    public function rechazar(Request $request, SolicitudCambioTurno $cambio)
    {
        $cambio->update([
            'estado'             => 'rechazado_coordinador',
            'motivo_coordinador' => $request->motivo ?? 'Sin motivo especificado',
            'aprobado_por'       => 'coordinador',
            'resuelto_at'        => now(),
        ]);
        AuditoriaSistema::registrar('RECHAZAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Cambio rechazado por el coordinador.');
    }
}
