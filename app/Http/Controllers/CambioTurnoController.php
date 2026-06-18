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
        $user      = auth()->user();
        $mes       = (int)($request->mes  ?? now()->month);
        $anio      = (int)($request->anio ?? now()->year);
        $estado    = $request->string('estado', '');

        $query = SolicitudCambioTurno::with([
                'turnoOrigen.medico', 'turnoOrigen.uci',
                'turnoDestino.medico',
                'medicoSolicitante', 'medicoReceptor'
            ])->orderByDesc('created_at');

        // Operativo: solo ve sus propias solicitudes
        if ($user->esMedico() && $user->medico_id) {
            $query->where(function($q) use ($user) {
                $q->where('medico_solicitante_id', $user->medico_id)
                  ->orWhere('medico_receptor_id',  $user->medico_id);
            });
        }

        if ($estado) $query->where('estado', $estado);

        $query->whereHas('turnoOrigen', fn($q) =>
            $q->whereYear('fecha', $anio)->whereMonth('fecha', $mes)
        );

        $solicitudes = $query->paginate(25)->withQueryString();
        $medicos     = Medico::where('activo', true)->orderBy('nombre')->get();
        $esMaestro   = $user->esMaster();
        $nombresMeses= ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return view('cambios-turno.index', compact(
            'solicitudes','estado','medicos','esMaestro','mes','anio','nombresMeses'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'turno_origen_id'    => 'required|exists:turno_medicos,id',
            'medico_receptor_id' => 'required|exists:medicos,id',
            'motivo'             => 'required|string|min:10|max:500',
        ]);

        $turnoOrigen = TurnoMedico::findOrFail($data['turno_origen_id']);

        $solicitud = SolicitudCambioTurno::create([
            'turno_origen_id'    => $turnoOrigen->id,
            'medico_solicitante_id' => $turnoOrigen->medico_id,
            'medico_receptor_id' => $data['medico_receptor_id'],
            'motivo'             => $data['motivo'],
            'estado'             => 'pendiente',
        ]);

        AuditoriaSistema::registrar('SOLICITAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $solicitud->id,
            null, $data, "Solicitud de cambio de turno del médico #{$turnoOrigen->medico_id}");

        return back()->with('success', 'Solicitud de cambio enviada. Esperando respuesta del colega.');
    }

    public function aceptar(Request $request, SolicitudCambioTurno $cambio)
    {
        $request->validate(['turno_destino_id' => 'nullable|exists:turno_medicos,id']);

        $cambio->update([
            'estado'                 => 'aceptado_colega',
            'turno_destino_id'       => $request->turno_destino_id,
            'respuesta_colega'       => $request->respuesta ?? 'Aceptado',
            'respondido_colega_at'   => now(),
        ]);
        AuditoriaSistema::registrar('ACEPTAR_CAMBIO_COLEGA', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Cambio aceptado. Esperando aprobación del coordinador.');
    }

    public function rechazarColega(Request $request, SolicitudCambioTurno $cambio)
    {
        $cambio->update([
            'estado'               => 'rechazado_colega',
            'respuesta_colega'     => $request->motivo ?? 'Rechazado',
            'respondido_colega_at' => now(),
        ]);
        AuditoriaSistema::registrar('RECHAZAR_CAMBIO_COLEGA', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Cambio rechazado por el colega.');
    }

    public function aprobar(SolicitudCambioTurno $cambio)
    {
        if ($cambio->estado !== 'aceptado_colega') {
            return back()->with('error', 'El cambio debe estar aceptado por el colega antes de ser aprobado.');
        }

        // Ejecutar el intercambio de turnos
        $tOrigen  = $cambio->turnoOrigen;
        $tDestino = $cambio->turnoDestino;

        if ($tOrigen && $tDestino) {
            // Intercambiar códigos de turno
            [$codigoA, $codigoB] = [$tOrigen->codigo_turno, $tDestino->codigo_turno];
            $horasA = TurnoService::horasPorCodigo($codigoB);
            $horasB = TurnoService::horasPorCodigo($codigoA);

            $tOrigen->update(['codigo_turno' => $codigoB, 'horas_diurnas' => $horasA['diurnas'],
                'horas_nocturnas' => $horasA['nocturnas'], 'horas_total' => $horasA['total'],
                'editado_manualmente' => true, 'editado_por' => 'coordinador', 'editado_at' => now()]);
            $tDestino->update(['codigo_turno' => $codigoA, 'horas_diurnas' => $horasB['diurnas'],
                'horas_nocturnas' => $horasB['nocturnas'], 'horas_total' => $horasB['total'],
                'editado_manualmente' => true, 'editado_por' => 'coordinador', 'editado_at' => now()]);
        }

        $cambio->update(['estado' => 'aprobado_coordinador', 'aprobado_por' => 'coordinador', 'resuelto_at' => now()]);
        AuditoriaSistema::registrar('APROBAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Cambio de turno aprobado y aplicado.');
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
