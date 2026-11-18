<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCambioTurno;
use App\Models\TurnoMedico;
use App\Models\Medico;
use App\Models\ArchivoCargado;
use App\Models\AuditoriaSistema;
use App\Models\HorasAutorizacionExtra;
use App\Services\CambioTurnoAprobacionService;
use App\Services\TurnoService;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CambioTurnoController extends Controller
{
    public function __construct(
        private TurnoService $turnoService,
        private CambioTurnoAprobacionService $aprobacionService,
    ) {}

    // ── Vista principal ───────────────────────────────────────────

    public function index(Request $request)
    {
        $user      = auth()->user();
        $estado    = (string) $request->string('estado', '');
        $esMaestro = $user->esMaster();

        // Los médicos (no-master) solo ven meses activados para ellos; el master ve todos
        // para poder gestionar/activar los que falten.
        $archivos  = ArchivoCargado::when(!$esMaestro, fn($q) => $q->where('activo', true))
            ->orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = (int)($request->archivo_id ?? $archivos->first()?->id ?? 0);
        $archivo   = $archivos->firstWhere('id', $archivoId);

        // Turnos del médico en el período (para el formulario)
        $turnos = collect();
        if ($user->medico_id && $archivo) {
            $turnos = TurnoMedico::where('medico_id', $user->medico_id)
                ->where('archivo_id', $archivoId)
                ->whereIn('codigo_turno', ['M','T','MT','N','TN','MTN','MN'])
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

        // El maestro ve solicitudes de todos los períodos por defecto;
        // solo se filtra por período si él mismo elige uno explícitamente.
        $filtrarPorPeriodo = !$esMaestro || $request->filled('archivo_id');
        if ($archivo && $filtrarPorPeriodo) {
            $query->whereHas('turnoOrigen', fn($q) =>
                $q->whereYear('fecha', $archivo->anio)->whereMonth('fecha', $archivo->mes)
            );
        }

        $solicitudes = $query->paginate(25)->withQueryString();
        $medicos     = Medico::where('activo', true)->orderBy('nombre')->get();

        // Pre-cargar mis turnos disponibles (receptor) para evitar N+1 en el listado
        $misTurnosDisponibles = collect();
        if ($user->medico_id && $archivo) {
            $misTurnosDisponibles = TurnoMedico::where('medico_id', $user->medico_id)
                ->where('archivo_id', $archivoId)
                ->whereIn('codigo_turno', ['M','T','MT','N','TN','MTN','MN'])
                ->orderBy('fecha')->get();
        }

        return view('cambios-turno.index', compact(
            'solicitudes','estado','medicos','esMaestro',
            'archivos','archivoId','turnos','misTurnosDisponibles'
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
            ->whereIn('codigo_turno', ['M','T','MT','N','TN','MTN','MN'])
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

    // ── AJAX: turnos disponibles del médico receptor ─────────────

    public function turnosReceptor(Request $request)
    {
        $medicoId  = (int)$request->medico_id;
        $archivoId = (int)$request->archivo_id;

        if (!$medicoId || !$archivoId) {
            return response()->json([]);
        }

        $turnos = TurnoMedico::where('medico_id', $medicoId)
            ->where('archivo_id', $archivoId)
            ->whereIn('codigo_turno', ['M','T','MT','N','TN','MTN','MN'])
            ->orderBy('fecha')
            ->get(['id','fecha','codigo_turno']);

        return response()->json($turnos->map(fn($t) => [
            'id'    => $t->id,
            'dia'   => Carbon::parse($t->fecha)->format('d M'),
            'fecha' => Carbon::parse($t->fecha)->format('d/m/Y'),
            'codigo'=> $t->codigo_turno,
            'horas' => TurnoService::horasPorCodigo($t->codigo_turno)['total'] ?? 0,
        ]));
    }

    // ── Crear solicitud ───────────────────────────────────────────

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'turno_origen_id'    => 'required|exists:turno_medicos,id',
            'turno_destino_id'   => 'nullable|exists:turno_medicos,id',
            'componente_turno'   => 'nullable|string|max:10',
            'tipo_movimiento'    => 'nullable|in:cambio_directo,donacion_directa',
            'medico_receptor_id' => 'required|exists:medicos,id',
            'motivo'             => 'required|string|min:5|max:500',
        ]);

        $turnoOrigen = TurnoMedico::findOrFail($data['turno_origen_id']);

        if ($user->esMedico() && $user->medico_id && $turnoOrigen->medico_id !== $user->medico_id) {
            return back()->with('error', 'Solo puedes solicitar cambios de tus propios turnos.');
        }

        // Bug fix: no puedes enviarte una solicitud a ti mismo
        if ($user->medico_id && (int)$data['medico_receptor_id'] === (int)$user->medico_id) {
            return back()->with('error', 'No puedes enviarte una solicitud de cambio a ti mismo.');
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

        // ── Verificar límite 216h para el receptor ─────────────────
        $archivoId     = $turnoOrigen->archivo_id;
        $compOfrec     = $componente ?: $turnoOrigen->codigo_turno;
        $horasGanadas  = TurnoService::horasPorCodigo($compOfrec)['total'] ?? 0;
        $horasPerdidas = 0;
        if ($tipo === 'cambio_directo' && !empty($data['turno_destino_id'])) {
            $tDest         = TurnoMedico::find($data['turno_destino_id']);
            $horasPerdidas = $tDest?->horas_total ?? 0;
        }
        $horasActReceptor = $this->horasMedicoEnArchivo((int)$data['medico_receptor_id'], $archivoId);
        if (($horasActReceptor + $horasGanadas - $horasPerdidas) > 216
            && !$this->tieneAutorizacionHorasExtra((int)$data['medico_receptor_id'], $archivoId)) {
            return back()->with('error', 'El médico receptor alcanzaría el límite de 216 h en este período. Solicita autorización al coordinador primero.');
        }
        // ──────────────────────────────────────────────────────────

        $solicitud = SolicitudCambioTurno::create([
            'tipo_movimiento'       => $tipo,
            'turno_origen_id'       => $turnoOrigen->id,
            'turno_destino_id'      => $tipo === 'cambio_directo' ? ($data['turno_destino_id'] ?? null) : null,
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
        $receptor = Medico::find($data['medico_receptor_id']);
        try {
            if ($receptor && $receptor->email) {
                \Illuminate\Support\Facades\Mail::to($receptor->email)
                    ->send(new \App\Mail\SolicitudCambioTurnoMail($solicitud));
            }
        } catch (\Throwable $e) {
            // Email falla silenciosamente (driver puede ser 'log')
        }

        // Notificar al médico receptor por WhatsApp
        if ($receptor) {
            $solicitante = $turnoOrigen->medico;
            WhatsappService::notificarMedico(
                $receptor,
                "Hola {$receptor->nombre}, {$solicitante?->nombre_completo} te envió una solicitud de {$tipoLabel} de turno "
                . "para el " . Carbon::parse($turnoOrigen->fecha)->translatedFormat('d \d\e F') . ". "
                . "Ingresa al sistema para responder."
            );
        }

        $msg = $tipo === 'donacion_directa'
            ? 'Solicitud de cedencia enviada. El colega debe aceptar recibirla.'
            : 'Solicitud de cambio enviada. Esperando respuesta del colega.';

        return redirect()->route('cambios-turno.index', ['archivo_id' => $turnoOrigen->archivo_id])
            ->with('success', $msg);
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

        // Para cedencia no se necesita turno_destino.
        // Para cambio_directo: usar el ya propuesto por el solicitante o el que indique el receptor ahora.
        // Si el solicitante no propuso turno, el receptor debe indicar uno (si no, el maestro nunca podría aprobar).
        $reglaDestino = ($cambio->tipo_movimiento === 'cambio_directo' && !$cambio->turno_destino_id)
            ? 'required|exists:turno_medicos,id'
            : 'nullable|exists:turno_medicos,id';
        $request->validate(['turno_destino_id' => $reglaDestino]);

        $turnoDest = null;
        if ($cambio->tipo_movimiento === 'cambio_directo') {
            $turnoDest = $cambio->turno_destino_id ?? $request->turno_destino_id;
        }

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

        $solicitante = $cambio->medicoSolicitante;
        if ($solicitante) {
            WhatsappService::notificarMedico(
                $solicitante,
                "Hola {$solicitante->nombre}, tu colega rechazó la solicitud de cambio de turno que le enviaste. Puedes revisarla en el sistema."
            );
        }

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

        $resultado = $this->aprobacionService->aplicar($cambio);
        if (!$resultado['ok']) {
            return back()->with('error', $resultado['error']);
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

        $solicitante = $cambio->medicoSolicitante;
        if ($solicitante) {
            WhatsappService::notificarMedico(
                $solicitante,
                "Hola {$solicitante->nombre}, el coordinador rechazó tu solicitud de cambio de turno. Motivo: {$cambio->motivo_coordinador}"
            );
        }

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
            'TN'  => [
                ['valor'=>'TN',  'label'=>'TN completo — tarde + noche (18h)'],
                ['valor'=>'T',   'label'=>'Solo T — tarde (6h)'],
                ['valor'=>'N',   'label'=>'Solo N — noche (12h)'],
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
            'TN'  => ['T'=>'N',   'N'=>'T',    'TN'=>'LIBRE'],
            'MTN' => ['T'=>'MN',  'N'=>'MT',   'MT'=>'N',   'MTN'=>'LIBRE'],
            'MN'  => ['M'=>'N',   'N'=>'M',    'MN'=>'LIBRE'],
            'M'   => ['M'=>'LIBRE'],
            'T'   => ['T'=>'LIBRE'],
            'N'   => ['N'=>'LIBRE'],
        ];
        return $tabla[strtoupper($codigo)][strtoupper($componente)] ?? $codigo;
    }

    // ── Helpers de límite de horas ────────────────────────────────

    private function horasMedicoEnArchivo(int $medicoId, int $archivoId): float
    {
        return (float) TurnoMedico::where('medico_id', $medicoId)
            ->where('archivo_id', $archivoId)
            ->sum('horas_total');
    }

    private function tieneAutorizacionHorasExtra(int $medicoId, int $archivoId): bool
    {
        $archivo = ArchivoCargado::find($archivoId);
        if (!$archivo) return false;
        return HorasAutorizacionExtra::where('medico_id', $medicoId)
            ->where('mes',  $archivo->mes)
            ->where('anio', $archivo->anio)
            ->exists();
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
            'TN'=>['T','N'], 'MT'=>['M','T'], 'MTN'=>['M','T','N'], 'MN'=>['M','N'],
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
            'TN'=>'TN','MT'=>'MT','MN'=>'MN','MTN'=>'MTN',
            'MNT'=>'MTN', // variante de ordenamiento
        ];
        return $mapa[$clave] ?? ($nuevo ?: 'LIBRE');
    }
}