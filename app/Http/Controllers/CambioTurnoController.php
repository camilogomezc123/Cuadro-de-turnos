<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCambioTurno;
use App\Models\TurnoMedico;
use App\Models\Medico;
use App\Models\ArchivoCargado;
use App\Models\AuditoriaSistema;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CambioTurnoController extends Controller
{
    public function __construct(private TurnoService $turnoService) {}

    // ── Vista principal ───────────────────────────────────────────

    public function index(Request $request)
    {
        $user    = auth()->user();
        $estado  = $request->string('estado', '');

        $archivos  = ArchivoCargado::orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = (int)($request->archivo_id ?? $archivos->first()?->id ?? 0);
        $archivo   = $archivos->firstWhere('id', $archivoId);

        // Turnos del médico en el período (para el formulario)
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

    // ── AJAX: mis turnos del período con componentes ofrecibles ───

    public function misTurnos(Request $request)
    {
        $user      = auth()->user();
        $archivoId = (int)$request->archivo_id;

        if (!$archivoId || !$user->medico_id) {
            return response()->json([]);
        }

        $turnos = TurnoMedico::where('medico_id', $user->medico_id)
            ->where('archivo_id', $archivoId)
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->orderBy('fecha')
            ->get(['id','fecha','codigo_turno','uci_id']);

        return response()->json($turnos->map(fn($t) => [
            'id'          => $t->id,
            'label'       => Carbon::parse($t->fecha)->format('d/m') . ' · ' . $t->codigo_turno,
            'fecha'       => Carbon::parse($t->fecha)->format('d/m/Y'),
            'codigo'      => $t->codigo_turno,
            'componentes' => self::componentesOfrecibles($t->codigo_turno),
        ]));
    }

    // ── Crear solicitud ───────────────────────────────────────────

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'turno_origen_id'    => 'required|exists:turno_medicos,id',
            'componente_turno'   => 'nullable|string|max:10',
            'tipo_movimiento'    => 'nullable|in:cambio_directo,donacion_directa',
            'medico_receptor_id' => 'required|exists:medicos,id',
            'motivo'             => 'required|string|min:5|max:500',
        ]);

        $turnoOrigen = TurnoMedico::findOrFail($data['turno_origen_id']);

        if ($user->esMedico() && $user->medico_id && $turnoOrigen->medico_id !== $user->medico_id) {
            return back()->with('error', 'Solo puedes solicitar cambios de tus propios turnos.');
        }

        // Validar que el componente sea ofrecible desde el turno seleccionado
        $componente = strtoupper(trim($data['componente_turno'] ?? ''));
        if ($componente) {
            $ofrecibles = array_column(self::componentesOfrecibles($turnoOrigen->codigo_turno), 'valor');
            if (!in_array($componente, $ofrecibles)) {
                return back()->with('error', "El componente {$componente} no puede ofrecerse desde un turno {$turnoOrigen->codigo_turno}.");
            }
        }

        $tipo = $data['tipo_movimiento'] ?? 'cambio_directo';

        $solicitud = SolicitudCambioTurno::create([
            'tipo_movimiento'       => $tipo,
            'turno_origen_id'       => $turnoOrigen->id,
            'componente_turno'      => $componente ?: null,
            'medico_solicitante_id' => $turnoOrigen->medico_id,
            'medico_receptor_id'    => $data['medico_receptor_id'],
            'motivo'                => $data['motivo'],
            'estado'                => 'pendiente',
        ]);

        $tipoLabel = $tipo === 'donacion_directa' ? 'cedencia' : 'cambio';
        AuditoriaSistema::registrar('SOLICITAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $solicitud->id,
            null, $data, "Solicitud de {$tipoLabel} del médico #{$turnoOrigen->medico_id}");

        Cache::forget('sidebar_cambios_pendientes');
        Cache::forget('sidebar_medico_pendientes_' . $data['medico_receptor_id']);

        // Notificar al médico receptor por email
        try {
            $receptor = Medico::find($data['medico_receptor_id']);
            if ($receptor && $receptor->email) {
                \Illuminate\Support\Facades\Mail::to($receptor->email)
                    ->send(new \App\Mail\SolicitudCambioTurnoMail($solicitud));
            }
        } catch (\Throwable $e) {
            // Email falla silenciosamente (driver puede ser 'log')
        }

        $msg = $tipo === 'donacion_directa'
            ? 'Solicitud de cedencia enviada. El colega debe aceptar recibirla.'
            : 'Solicitud de cambio enviada. Esperando respuesta del colega.';

        return back()->with('success', $msg);
    }

    // ── Aceptar (colega receptor) ─────────────────────────────────

    public function aceptar(Request $request, SolicitudCambioTurno $cambio)
    {
        $user = auth()->user();

        if ($user->esMedico() && $user->medico_id && $cambio->medico_receptor_id !== $user->medico_id) {
            return back()->with('error', 'Solo el médico receptor puede aceptar esta solicitud.');
        }

        if ($cambio->estado !== 'pendiente') {
            return back()->with('error', 'Esta solicitud ya fue respondida.');
        }

        $request->validate(['turno_destino_id' => 'nullable|exists:turno_medicos,id']);

        // Para cedencia, no se necesita turno_destino
        $turnoDest = ($cambio->tipo_movimiento === 'cambio_directo')
            ? $request->turno_destino_id
            : null;

        $cambio->update([
            'estado'               => 'aceptado_colega',
            'turno_destino_id'     => $turnoDest,
            'respuesta_colega'     => $request->respuesta ?? 'Aceptado',
            'respondido_colega_at' => now(),
        ]);
        AuditoriaSistema::registrar('ACEPTAR_CAMBIO_COLEGA', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Aceptado. Pendiente de aprobación del coordinador.');
    }

    // ── Rechazar (colega receptor) ────────────────────────────────

    public function rechazarColega(Request $request, SolicitudCambioTurno $cambio)
    {
        $user = auth()->user();

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
        return back()->with('success', 'Solicitud rechazada.');
    }

    // ── Cancelar (solicitante cancela su propia solicitud) ────────

    public function cancelar(Request $request, SolicitudCambioTurno $cambio)
    {
        $user = auth()->user();

        if ($user->esMedico() && $user->medico_id && $cambio->medico_solicitante_id !== $user->medico_id) {
            return back()->with('error', 'Solo el solicitante puede cancelar esta solicitud.');
        }

        if (!in_array($cambio->estado, ['pendiente', 'rechazado_colega'])) {
            return back()->with('error', 'Esta solicitud no se puede cancelar en su estado actual.');
        }

        $cambio->update(['estado' => 'cancelado', 'resuelto_at' => now()]);
        AuditoriaSistema::registrar('CANCELAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        return back()->with('success', 'Solicitud cancelada correctamente.');
    }

    // ── Aprobar (coordinador/maestro) ─────────────────────────────

    public function aprobar(SolicitudCambioTurno $cambio)
    {
        if ($cambio->estado !== 'aceptado_colega') {
            return back()->with('error', 'El cambio debe estar aceptado por el colega antes de ser aprobado.');
        }

        $tOrigen = $cambio->turnoOrigen;
        if (!$tOrigen) {
            return back()->with('error', 'El turno de origen ya no existe.');
        }

        // Verificar que el turno sigue siendo compatible con lo que se ofreció
        $comp = $cambio->componente_turno;
        if ($comp) {
            $ofrecibles = array_column(self::componentesOfrecibles($tOrigen->codigo_turno), 'valor');
            if (!in_array($comp, $ofrecibles)) {
                return back()->with('error',
                    "El turno de origen ya no contiene el componente '{$comp}' ofrecido " .
                    "(código actual: {$tOrigen->codigo_turno}). El turno fue modificado tras crear la solicitud."
                );
            }
        } elseif (in_array($tOrigen->codigo_turno, ['LIBRE', ''])) {
            return back()->with('error', 'El turno de origen ya no existe (fue marcado como LIBRE).');
        }

        if ($cambio->tipo_movimiento === 'donacion_directa') {
            // ── Cedencia: el donante pierde el componente; el receptor lo gana ──
            $comp = $cambio->componente_turno ?? $tOrigen->codigo_turno;

            // Actualizar turno del donante
            $codigoRestante = self::restarComponente($tOrigen->codigo_turno, $comp);
            $horasRestantes = TurnoService::horasPorCodigo($codigoRestante);
            $tOrigen->update([
                'codigo_turno'        => $codigoRestante,
                'horas_diurnas'       => $horasRestantes['diurnas'],
                'horas_nocturnas'     => $horasRestantes['nocturnas'],
                'horas_total'         => $horasRestantes['total'],
                'editado_manualmente' => true,
                'editado_por'         => 'coordinador',
                'editado_at'          => now(),
            ]);

            // Buscar o crear turno del receptor en la misma fecha
            $tReceptor = TurnoMedico::where('medico_id', $cambio->medico_receptor_id)
                ->where('archivo_id', $tOrigen->archivo_id)
                ->where('fecha', $tOrigen->fecha)
                ->first();

            $codigoReceptorActual = $tReceptor?->codigo_turno ?? 'LIBRE';
            $codigoReceptorNuevo  = self::mergeComponente($codigoReceptorActual, $comp);
            $horasReceptor        = TurnoService::horasPorCodigo($codigoReceptorNuevo);

            if ($tReceptor) {
                $tReceptor->update([
                    'codigo_turno'        => $codigoReceptorNuevo,
                    'horas_diurnas'       => $horasReceptor['diurnas'],
                    'horas_nocturnas'     => $horasReceptor['nocturnas'],
                    'horas_total'         => $horasReceptor['total'],
                    'editado_manualmente' => true,
                    'editado_por'         => 'coordinador',
                    'editado_at'          => now(),
                ]);
            } else {
                // Crear nuevo turno para el receptor
                $fecha  = Carbon::parse($tOrigen->fecha);
                $dow    = $fecha->dayOfWeek;
                $idx    = ($dow === 0) ? 6 : $dow - 1;
                $dias   = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
                TurnoMedico::create([
                    'archivo_id'          => $tOrigen->archivo_id,
                    'medico_id'           => $cambio->medico_receptor_id,
                    'uci_id'              => $tOrigen->uci_id,
                    'fecha'               => $tOrigen->fecha,
                    'dia_numero'          => $fecha->day,
                    'dia_semana'          => $dias[$idx],
                    'codigo_turno'        => $codigoReceptorNuevo,
                    'horas_diurnas'       => $horasReceptor['diurnas'],
                    'horas_nocturnas'     => $horasReceptor['nocturnas'],
                    'horas_total'         => $horasReceptor['total'],
                    'es_fin_semana'       => in_array($dow, [0, 6]),
                    'es_domingo'          => ($dow === 0),
                    'estado_turno'        => 'programado',
                    'fue_laborado'        => true,
                    'editado_manualmente' => true,
                    'editado_por'         => 'coordinador',
                    'editado_at'          => now(),
                ]);
            }

            // Recalcular horas de ambos médicos
            $fecha = Carbon::parse($tOrigen->fecha);
            $this->turnoService->recalcularIndicadorMedico(
                $tOrigen->medico_id, $tOrigen->uci_id, $tOrigen->archivo_id,
                $fecha->month, $fecha->year
            );
            $this->turnoService->recalcularIndicadorMedico(
                $cambio->medico_receptor_id, $tOrigen->uci_id, $tOrigen->archivo_id,
                $fecha->month, $fecha->year
            );

        } else {
            // ── Cambio directo: intercambiar turnos completos ──
            $tDestino = $cambio->turnoDestino;

            if ($tOrigen && $tDestino) {
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

                $fechaO = Carbon::parse($tOrigen->fecha);
                $fechaD = Carbon::parse($tDestino->fecha);
                $this->turnoService->recalcularIndicadorMedico(
                    $tOrigen->medico_id, $tOrigen->uci_id, $tOrigen->archivo_id,
                    $fechaO->month, $fechaO->year
                );
                $this->turnoService->recalcularIndicadorMedico(
                    $tDestino->medico_id, $tDestino->uci_id, $tDestino->archivo_id,
                    $fechaD->month, $fechaD->year
                );
            }
        }

        $cambio->update(['estado' => 'aprobado_coordinador', 'aprobado_por' => 'coordinador', 'resuelto_at' => now()]);
        AuditoriaSistema::registrar('APROBAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        Cache::forget('sidebar_cambios_pendientes');
        return back()->with('success', 'Cambio aprobado y aplicado. Las horas han sido recalculadas.');
    }

    // ── Rechazar (coordinador/maestro) ────────────────────────────

    public function rechazar(Request $request, SolicitudCambioTurno $cambio)
    {
        $cambio->update([
            'estado'             => 'rechazado_coordinador',
            'motivo_coordinador' => $request->motivo ?? 'Sin motivo especificado',
            'aprobado_por'       => 'coordinador',
            'resuelto_at'        => now(),
        ]);
        AuditoriaSistema::registrar('RECHAZAR_CAMBIO', 'cambios_turno', 'SolicitudCambioTurno', $cambio->id);
        Cache::forget('sidebar_cambios_pendientes');
        return back()->with('success', 'Solicitud rechazada por el coordinador.');
    }

    // ── Helpers de componentes ────────────────────────────────────

    /**
     * Qué componentes puede ofrecer un médico con un turno dado.
     * Retorna array de ['valor'=>'M','label'=>'Solo M (mañana 6h)']
     */
    public static function componentesOfrecibles(string $codigo): array
    {
        return match(strtoupper($codigo)) {
            'M'   => [['valor'=>'M',   'label'=>'M — mañana (6h)']],
            'T'   => [['valor'=>'T',   'label'=>'T — tarde (6h)']],
            'N'   => [['valor'=>'N',   'label'=>'N — noche (12h)']],
            'MT'  => [
                ['valor'=>'MT',  'label'=>'MT completo — mañana + tarde (12h)'],
                ['valor'=>'M',   'label'=>'Solo M — mañana (6h)'],
                ['valor'=>'T',   'label'=>'Solo T — tarde (6h)'],
            ],
            'MTN' => [
                ['valor'=>'MTN', 'label'=>'MTN completo — todo el día (24h)'],
                ['valor'=>'MT',  'label'=>'MT — mañana + tarde (12h)'],
                ['valor'=>'T',   'label'=>'Solo T — tarde (6h)'],
                ['valor'=>'N',   'label'=>'Solo N — noche (12h)'],
            ],
            'MN'  => [
                ['valor'=>'MN',  'label'=>'MN completo — mañana + noche (18h)'],
                ['valor'=>'M',   'label'=>'Solo M — mañana (6h)'],
                ['valor'=>'N',   'label'=>'Solo N — noche (12h)'],
            ],
            default => [['valor'=>$codigo, 'label'=>"{$codigo} (turno completo)"]],
        };
    }

    /**
     * Remueve un componente de un turno compuesto y retorna lo que queda.
     * MT - M = T | MTN - N = MT | MTN - T = MN | MN - M = N | etc.
     */
    public static function restarComponente(string $codigo, string $componente): string
    {
        $tabla = [
            'MT'  => ['M'=>'T',   'T'=>'M',    'MT'=>'LIBRE'],
            'MTN' => ['T'=>'MN',  'N'=>'MT',   'MT'=>'N',   'MTN'=>'LIBRE'],
            'MN'  => ['M'=>'N',   'N'=>'M',    'MN'=>'LIBRE'],
            'M'   => ['M'=>'LIBRE'],
            'T'   => ['T'=>'LIBRE'],
            'N'   => ['N'=>'LIBRE'],
        ];
        return $tabla[strtoupper($codigo)][strtoupper($componente)] ?? $codigo;
    }

    /**
     * Combina el turno actual del receptor con el componente que recibe.
     * T + M = MT | LIBRE + N = N | MT + N = MTN | MN + T = MTN | etc.
     */
    public static function mergeComponente(string $existente, string $nuevo): string
    {
        $comps = [
            'LIBRE'=>[], ''=>[],
            'M'=>['M'], 'T'=>['T'], 'N'=>['N'],
            'MT'=>['M','T'], 'MTN'=>['M','T','N'], 'MN'=>['M','N'],
        ];
        $todas = array_unique(array_merge(
            $comps[strtoupper($existente)] ?? [],
            $comps[strtoupper($nuevo)]     ?? [strtoupper($nuevo)]
        ));
        sort($todas);
        $clave = implode('', $todas);

        // Mapa de combinaciones posibles
        $mapa = [
            'M'=>'M','T'=>'T','N'=>'N',
            'MT'=>'MT','MN'=>'MN','MTN'=>'MTN',
            'TN'=>'MTN', // T+N no tiene código propio → MTN (suma M también)
            'MNT'=>'MTN','MTC'=>'MT','MNT'=>'MTN',
        ];
        return $mapa[$clave] ?? ($nuevo ?: 'LIBRE');
    }
}