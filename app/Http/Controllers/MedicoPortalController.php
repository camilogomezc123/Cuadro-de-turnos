<?php

namespace App\Http\Controllers;

use App\Models\TurnoMedico;
use App\Models\SolicitudCambioTurno;
use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Models\Novedad;
use App\Models\AlertaTurno;
use App\Models\AuditoriaSistema;
use App\Services\HoraConsolidadoService;
use App\Services\ConflictoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MedicoPortalController extends Controller
{
    public function __construct(
        private HoraConsolidadoService $horaService,
        private ConflictoService       $conflictoService,
    ) {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            if (!$user->esMedico() || !$user->medico_id) {
                abort(403, 'Acceso solo para médicos con perfil vinculado.');
            }
            return $next($request);
        });
    }

    public function portal(Request $request)
    {
        $user   = Auth::user();
        $medico = $user->medico;

        $mes  = (int)($request->mes  ?? now()->month);
        $anio = (int)($request->anio ?? now()->year);
        $meses= ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                 'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        // Mis turnos del mes (multi-UCI)
        $misTurnos = TurnoMedico::where('medico_id', $medico->id)
            ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)
            ->with('uci')->orderBy('fecha')->get();

        // Resumen multi-UCI
        $resumen = $this->horaService->resumenMedico($medico->id, $mes, $anio);

        // Alerta 200h
        $supera200  = $resumen['supera_200h'];
        $alertas200 = AlertaTurno::where('medico_id', $medico->id)
            ->where('tipo', 'EXCESO_200H')
            ->whereYear('created_at', $anio)->whereMonth('created_at', $mes)
            ->where('estado', 'abierta')->count();

        // Alertas 12h hábil propias
        $alertas12h = AlertaTurno::where('medico_id', $medico->id)
            ->where('tipo', 'EXCESO_12H_HABIL')
            ->whereYear('created_at', $anio)->whereMonth('created_at', $mes)
            ->where('estado', 'abierta')->get();

        // Mis solicitudes enviadas
        $solicitudesEnviadas = SolicitudCambioTurno::with([
                'medicoReceptor','turnoOrigen.uci','turnoDestino'
            ])
            ->where('medico_solicitante_id', $medico->id)
            ->orderByDesc('created_at')->limit(20)->get();

        // Solicitudes recibidas pendientes de mi respuesta
        $solicitudesRecibidas = SolicitudCambioTurno::with([
                'medicoSolicitante','turnoOrigen.uci','turnoDestino'
            ])
            ->where('medico_receptor_id', $medico->id)
            ->whereIn('estado', ['pendiente','enviado_a_receptor'])
            ->orderByDesc('created_at')->get();

        $pendientesRecibidas = $solicitudesRecibidas->count();

        // Turnos disponibles (ofertas abiertas que yo puedo aceptar)
        $turnosDisponibles = SolicitudCambioTurno::ofertasAbiertas()
            ->with(['medicoSolicitante','turnoOrigen.uci'])
            ->where('medico_solicitante_id', '!=', $medico->id)
            ->get()
            ->filter(function ($sol) use ($medico) {
                $turno = $sol->turnoOrigen;
                if (!$turno) return false;
                return !TurnoMedico::where('medico_id', $medico->id)
                    ->where('fecha', $turno->fecha)
                    ->where('fue_laborado', true)
                    ->whereNotIn('codigo_turno', ['','LIBRE'])
                    ->exists();
            });

        // Novedades visibles para mí
        $misNovedades = Novedad::where('medico_id', $medico->id)
            ->where('visible_para_medico', true)
            ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)
            ->orderByDesc('fecha')->get();

        // Todos los médicos para solicitar cambio/donación
        $todosMedicos = Medico::where('activo', true)
            ->where('id', '!=', $medico->id)->orderBy('nombre')->get();

        // Meses disponibles
        $mesesDisponibles = ArchivoCargado::where('procesado', true)
            ->orderByDesc('anio')->orderByDesc('mes')->get(['mes','anio']);

        // Calendario del mes
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $calendario = [];
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $fecha = Carbon::create($anio, $mes, $d)->toDateString();
            $turno = $misTurnos->first(fn($t) => $t->fecha->toDateString() === $fecha);
            $calendario[$d] = [
                'fecha'   => $fecha,
                'dia_sem' => Carbon::create($anio,$mes,$d)->dayOfWeek,
                'turno'   => $turno,
                'pasado'  => Carbon::parse($fecha)->isPast(),
            ];
        }

        return view('medico.portal', compact(
            'medico', 'misTurnos', 'resumen', 'mes', 'anio', 'meses',
            'supera200', 'alertas200', 'alertas12h',
            'solicitudesEnviadas', 'solicitudesRecibidas', 'pendientesRecibidas',
            'turnosDisponibles', 'misNovedades',
            'todosMedicos', 'mesesDisponibles', 'calendario', 'diasEnMes'
        ));
    }

    // ── Ofrecer turno (oferta abierta) ──────────────────────────

    public function ofrecerTurno(Request $request)
    {
        $request->validate([
            'turno_id' => 'required|exists:turno_medicos,id',
            'motivo'   => 'nullable|string|max:300',
        ]);

        $medico = Auth::user()->medico;
        $turno  = TurnoMedico::findOrFail($request->turno_id);

        if ($turno->medico_id !== $medico->id) {
            return back()->with('error', 'Ese turno no te pertenece.');
        }
        if (Carbon::parse($turno->fecha)->isPast()) {
            return back()->with('error', 'No se puede ofrecer un turno pasado.');
        }

        DB::transaction(function () use ($turno, $medico, $request) {
            $turno->update(['estado_turno' => 'ofrecido']);

            SolicitudCambioTurno::create([
                'tipo_movimiento'       => 'oferta_abierta',
                'turno_origen_id'       => $turno->id,
                'medico_solicitante_id' => $medico->id,
                'medico_receptor_id'    => $medico->id,
                'motivo'                => $request->motivo,
                'estado'                => 'pendiente',
            ]);

            AuditoriaSistema::registrar(
                'OFRECER_TURNO','turnos','TurnoMedico',$turno->id,
                null,['estado'=>'ofrecido'],$request->motivo??'',Auth::user()->name
            );
        });

        return back()->with('success', 'Tu turno queda disponible para que otro médico lo tome.');
    }

    // ── Aceptar oferta abierta ───────────────────────────────────

    public function aceptarOferta(Request $request, SolicitudCambioTurno $solicitud)
    {
        $medico = Auth::user()->medico;

        if ($solicitud->medico_solicitante_id === $medico->id) {
            return back()->with('error', 'No puedes aceptar tu propia oferta.');
        }

        $turno = $solicitud->turnoOrigen;
        $check = $this->conflictoService->validarOferta($medico->id, $turno->fecha, $turno->codigo_turno);

        if ($check['tiene_conflicto']) {
            return back()->with('error', implode(' ', $check['conflictos']));
        }

        $solicitud->update([
            'medico_receptor_id'      => $medico->id,
            'estado'                  => 'pendiente_aprobacion_maestro',
            'fecha_respuesta_receptor'=> now(),
        ]);
        $turno->update(['estado_turno' => 'pendiente_aprobacion']);

        return back()->with('success', 'Oferta aceptada. Pendiente de aprobación del administrador.');
    }

    // ── Solicitar cambio/donación directa ───────────────────────

    public function solicitarCambio(Request $request)
    {
        $request->validate([
            'tipo_movimiento'    => 'required|in:cambio_directo,donacion_directa',
            'turno_origen_id'    => 'required|exists:turno_medicos,id',
            'medico_receptor_id' => 'required|exists:medicos,id',
            'turno_destino_id'   => 'nullable|exists:turno_medicos,id',
            'motivo'             => 'nullable|string|max:300',
        ]);

        $medico      = Auth::user()->medico;
        $turnoOrigen = TurnoMedico::findOrFail($request->turno_origen_id);

        if ($turnoOrigen->medico_id !== $medico->id) {
            return back()->with('error', 'Ese turno no te pertenece.');
        }

        $existe = SolicitudCambioTurno::where('turno_origen_id', $turnoOrigen->id)
            ->whereNotIn('estado', ['cancelado','rechazado_colega','rechazado_por_receptor',
                                    'rechazado_coordinador','rechazado_por_maestro'])->exists();
        if ($existe) {
            return back()->with('error', 'Ya existe una solicitud activa para ese turno.');
        }

        SolicitudCambioTurno::create([
            'tipo_movimiento'       => $request->tipo_movimiento,
            'turno_origen_id'       => $turnoOrigen->id,
            'turno_destino_id'      => $request->turno_destino_id,
            'medico_solicitante_id' => $medico->id,
            'medico_receptor_id'    => $request->medico_receptor_id,
            'motivo'                => $request->motivo,
            'estado'                => 'enviado_a_receptor',
        ]);

        return back()->with('success', 'Solicitud enviada. El médico receptor debe responderla.');
    }

    // ── Responder solicitud recibida ─────────────────────────────

    public function responderCambio(Request $request, SolicitudCambioTurno $solicitud)
    {
        $medico = Auth::user()->medico;

        if ($solicitud->medico_receptor_id !== $medico->id) abort(403);

        if ($request->accion === 'aceptar') {
            $solicitud->update([
                'estado'                  => 'pendiente_aprobacion_maestro',
                'respuesta_colega'        => $request->respuesta,
                'respondido_colega_at'    => now(),
                'fecha_respuesta_receptor'=> now(),
            ]);
            $msg = 'Aceptaste el cambio. Queda pendiente de aprobación del administrador.';
        } else {
            $solicitud->update([
                'estado'               => 'rechazado_por_receptor',
                'respuesta_colega'     => $request->respuesta,
                'respondido_colega_at' => now(),
            ]);
            $msg = 'Rechazaste la solicitud de cambio.';
        }

        return back()->with('success', $msg);
    }

    // ── Cancelar solicitud propia ────────────────────────────────

    public function cancelarCambio(SolicitudCambioTurno $solicitud)
    {
        $medico = Auth::user()->medico;
        if ($solicitud->medico_solicitante_id !== $medico->id) abort(403);

        if ($solicitud->tipo_movimiento === 'oferta_abierta') {
            $solicitud->turnoOrigen?->update(['estado_turno' => 'programado']);
        }

        $solicitud->update(['estado' => 'cancelado']);
        return back()->with('success', 'Solicitud cancelada.');
    }

    // ── Aprobar cambio (maestro) ─────────────────────────────────

    public function aprobarCambio(Request $request, SolicitudCambioTurno $solicitud)
    {
        DB::transaction(function () use ($request, $solicitud) {
            $solicitud->update([
                'estado'                  => 'aprobado_por_maestro',
                'aprobado_por'            => Auth::user()->name,
                'fecha_aprobacion_maestro'=> now(),
                'usuario_maestro_aprueba_id'=> Auth::id(),
                'observacion_maestro'     => $request->observacion,
                'resuelto_at'             => now(),
            ]);

            $to = $solicitud->turnoOrigen;
            $td = $solicitud->turnoDestino;

            if ($solicitud->tipo_movimiento === 'oferta_abierta' || $solicitud->tipo_movimiento === 'donacion_directa') {
                // Pasar el turno al receptor
                if ($to) {
                    $to->update([
                        'medico_id'         => $solicitud->medico_receptor_id,
                        'estado_turno'      => 'aceptado_por_otro',
                        'medico_original_id'=> $to->medico_id,
                        'fue_laborado'      => true,
                    ]);
                }
            } elseif ($solicitud->tipo_movimiento === 'cambio_directo' && $to && $td) {
                // Intercambiar médicos
                $medicoOrig = $to->medico_id;
                $medicoDest = $td->medico_id;

                $to->update([
                    'medico_id'    => $medicoDest,
                    'estado_turno' => 'reemplazado',
                ]);
                $td->update([
                    'medico_id'    => $medicoOrig,
                    'estado_turno' => 'reemplazado',
                ]);
            }

            AuditoriaSistema::registrar(
                'APROBAR_CAMBIO_TURNO','turnos','SolicitudCambioTurno',$solicitud->id,
                null,['aprobado_por'=>Auth::user()->name],
                $request->observacion??'',Auth::user()->name
            );
        });

        return back()->with('success', 'Cambio aprobado y cuadro de turnos actualizado.');
    }

    public function rechazarCambio(Request $request, SolicitudCambioTurno $solicitud)
    {
        $turno = $solicitud->turnoOrigen;
        if ($turno && $turno->estado_turno === 'ofrecido') {
            $turno->update(['estado_turno' => 'programado']);
        }

        $solicitud->update([
            'estado'                    => 'rechazado_por_maestro',
            'aprobado_por'              => Auth::user()->name,
            'fecha_aprobacion_maestro'  => now(),
            'usuario_maestro_aprueba_id'=> Auth::id(),
            'observacion_maestro'       => $request->observacion,
            'resuelto_at'               => now(),
        ]);

        return back()->with('success', 'Cambio rechazado.');
    }

    // ── API: turnos de un médico para un mes ─────────────────────

    public function turnosMedico(Request $request)
    {
        $medicoId = (int)$request->medico_id;
        $mes      = (int)($request->mes  ?? now()->month);
        $anio     = (int)($request->anio ?? now()->year);

        $turnos = TurnoMedico::where('medico_id', $medicoId)
            ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)
            ->whereNotIn('codigo_turno', ['','LIBRE','VAC'])
            ->where('horas_total', '>', 0)
            ->orderBy('fecha')
            ->get(['id','fecha','codigo_turno','dia_semana','uci_id'])
            ->map(fn($t) => [
                'id'           => $t->id,
                'fecha'        => $t->fecha->format('Y-m-d'),
                'label'        => $t->fecha->format('d/m') . ' — ' . $t->codigo_turno,
                'codigo_turno' => $t->codigo_turno,
                'dia_semana'   => $t->dia_semana,
            ]);

        return response()->json($turnos);
    }
}
