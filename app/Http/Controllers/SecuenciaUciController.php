<?php

namespace App\Http\Controllers;

use App\Models\SecuenciaUci;
use App\Models\SecuenciaUciDetalle;
use App\Models\Uci;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\ArchivoCargado;
use App\Models\AuditoriaSistema;
use App\Services\HoraConsolidadoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SecuenciaUciController extends Controller
{
    const CODIGOS_VALIDOS = ['M','T','MT','N','MTN','MN','PER','INC','LIBRE',''];

    public function index(Request $request)
    {
        $ucis      = Uci::where('activa', true)->orderBy('nombre')->get();
        $uciId     = $request->uci_id ?? $ucis->first()?->id;
        $anio      = (int)($request->anio ?? now()->year);

        $secuencias = $uciId
            ? SecuenciaUci::where('uci_id', $uciId)->where('anio', $anio)
                ->with(['detalles.medico','uci'])->orderByDesc('activa')->get()
            : collect();

        $medicos = Medico::where('activo', true)->orderBy('nombre')->get();
        $anios   = range(now()->year - 1, now()->year + 2);

        return view('secuencias.index', compact(
            'ucis','uciId','anio','secuencias','medicos','anios'
        ));
    }

    // Crear secuencia con patrón semanal
    public function store(Request $request)
    {
        $request->validate([
            'uci_id'  => 'required|exists:ucis,id',
            'nombre'  => 'required|string|max:100',
            'anio'    => 'required|integer',
            'medicos' => 'required|array|min:1',
            'medicos.*'=> 'exists:medicos,id',
            'patrones'=> 'required|array',
            // [medico_id][0..6] => codigo (0=lun..6=dom)
        ]);

        DB::transaction(function () use ($request) {
            $secuencia = SecuenciaUci::create([
                'uci_id'              => $request->uci_id,
                'nombre'              => $request->nombre,
                'anio'                => $request->anio,
                'activa'              => true,
                'creada_por_usuario_id'=> Auth::id(),
            ]);

            $patrones = $request->patrones;

            foreach ($request->medicos as $medicoId) {
                $patron = $patrones[$medicoId] ?? [];
                foreach ($patron as $dia => $codigo) {
                    $codigo = strtoupper(trim($codigo ?? ''));
                    if (!in_array($codigo, self::CODIGOS_VALIDOS)) $codigo = '';

                    $esFinde = in_array((int)$dia, [5, 6]); // 5=sab, 6=dom
                    SecuenciaUciDetalle::create([
                        'secuencia_uci_id' => $secuencia->id,
                        'medico_id'        => $medicoId,
                        'dia_semana'       => (int)$dia,
                        'codigo_turno'     => $codigo,
                        'es_fin_de_semana' => $esFinde,
                    ]);
                }
            }

            AuditoriaSistema::registrar(
                'CREAR_SECUENCIA', 'secuencias', 'SecuenciaUci', $secuencia->id,
                null, ['uci_id'=>$request->uci_id,'nombre'=>$request->nombre,'anio'=>$request->anio],
                'Secuencia creada', Auth::user()->name
            );
        });

        return back()->with('success', 'Secuencia creada correctamente.');
    }

    // Aplicar secuencia a un mes específico
    public function aplicarMes(Request $request, SecuenciaUci $secuencia)
    {
        $request->validate([
            'mes'  => 'required|integer|between:1,12',
            'anio' => 'required|integer',
        ]);

        $mes  = (int)$request->mes;
        $anio = (int)$request->anio;

        $resultado = $this->generarTurnosDesdeSecuencia($secuencia, $mes, $anio);

        AuditoriaSistema::registrar(
            'APLICAR_SECUENCIA_MES', 'secuencias', 'SecuenciaUci', $secuencia->id,
            null, ['mes'=>$mes,'anio'=>$anio,'turnos'=>$resultado['turnos_creados']],
            "Aplicada a {$mes}/{$anio}", Auth::user()->name
        );

        return back()->with('success', "Secuencia aplicada: {$resultado['turnos_creados']} turnos generados para {$mes}/{$anio}.");
    }

    // Aplicar secuencia a todo el año (todos los meses del año de la secuencia)
    public function aplicarAnio(Request $request, SecuenciaUci $secuencia)
    {
        $anio    = (int)$secuencia->anio;
        $total   = 0;
        $errores = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            try {
                $r = $this->generarTurnosDesdeSecuencia($secuencia, $mes, $anio);
                $total += $r['turnos_creados'];
            } catch (\Throwable $e) {
                $errores[] = "Mes {$mes}: " . $e->getMessage();
            }
        }

        AuditoriaSistema::registrar(
            'APLICAR_SECUENCIA_ANIO', 'secuencias', 'SecuenciaUci', $secuencia->id,
            null, ['anio'=>$anio,'total_turnos'=>$total],
            "Aplicada año completo {$anio}", Auth::user()->name
        );

        $msg = "Secuencia aplicada a todo {$anio}: {$total} turnos generados.";
        if ($errores) $msg .= ' Errores: ' . implode('; ', $errores);

        return back()->with('success', $msg);
    }

    // Agregar médico nuevo a una secuencia existente (cierra vigencia del anterior si aplica)
    public function agregarMedico(Request $request, SecuenciaUci $secuencia)
    {
        $request->validate([
            'medico_id'          => 'nullable|exists:medicos,id',
            'nombre_nuevo'       => 'nullable|string|max:100',
            'apellido_nuevo'     => 'nullable|string|max:100',
            'patron'             => 'required|array',
            'reemplaza_medico_id'=> 'nullable|exists:medicos,id',
        ]);

        DB::transaction(function () use ($request, $secuencia) {
            // Crear médico si es nuevo
            $medicoId = $request->medico_id;
            if (!$medicoId && $request->nombre_nuevo) {
                $medico   = Medico::create([
                    'nombre'   => trim($request->nombre_nuevo),
                    'apellido' => trim($request->apellido_nuevo ?? ''),
                    'uci_id'   => $secuencia->uci_id,
                    'activo'   => true,
                ]);
                $medicoId = $medico->id;
            }

            // Si reemplaza a otro médico: cerrar vigencia del anterior
            if ($request->reemplaza_medico_id) {
                SecuenciaUciDetalle::where('secuencia_uci_id', $secuencia->id)
                    ->where('medico_id', $request->reemplaza_medico_id)
                    ->whereNull('fecha_fin_vigencia')
                    ->update(['fecha_fin_vigencia' => now()->toDateString()]);
            }

            // Agregar nuevo médico con patrón
            foreach ($request->patron as $dia => $codigo) {
                $codigo  = strtoupper(trim($codigo ?? ''));
                $esFinde = in_array((int)$dia, [5,6]);
                SecuenciaUciDetalle::create([
                    'secuencia_uci_id'    => $secuencia->id,
                    'medico_id'           => $medicoId,
                    'dia_semana'          => (int)$dia,
                    'codigo_turno'        => $codigo,
                    'es_fin_de_semana'    => $esFinde,
                    'fecha_inicio_vigencia'=> now()->toDateString(),
                ]);
            }
        });

        return back()->with('success', 'Médico agregado a la secuencia.');
    }

    public function destroy(SecuenciaUci $secuencia)
    {
        $secuencia->update(['activa' => false]);
        return back()->with('success', 'Secuencia desactivada.');
    }

    // ── Helper privado ──────────────────────────────────────────

    private function generarTurnosDesdeSecuencia(SecuenciaUci $secuencia, int $mes, int $anio): array
    {
        $diasEnMes   = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $uciId       = $secuencia->uci_id;
        $turnosCreados = 0;

        // Buscar o crear archivo del mes
        $archivo = ArchivoCargado::firstOrCreate(
            ['mes' => $mes, 'anio' => $anio],
            [
                'nombre_archivo' => "Secuencia {$secuencia->uci->codigo} — {$mes}/{$anio}",
                'ruta'           => '', 'procesado' => true,
                'total_medicos'  => 0, 'total_turnos' => 0,
            ]
        );

        // Obtener detalles de la secuencia (solo vigentes)
        $detalles = $secuencia->detalles()
            ->where(function ($q) use ($mes, $anio) {
                $q->whereNull('fecha_inicio_vigencia')
                  ->orWhere('fecha_inicio_vigencia', '<=', Carbon::create($anio,$mes,1)->lastOfMonth());
            })
            ->where(function ($q) use ($mes, $anio) {
                $q->whereNull('fecha_fin_vigencia')
                  ->orWhere('fecha_fin_vigencia', '>=', Carbon::create($anio,$mes,1)->firstOfMonth());
            })
            ->get();

        // Construir mapa: [medico_id][dia_semana] => codigo
        $patron = [];
        foreach ($detalles as $d) {
            $patron[$d->medico_id][$d->dia_semana] = $d->codigo_turno;
        }

        // Fines de semana rotativos: [semana_num][sabado/domingo][slot] => medico_id
        $finSemanaDet = $secuencia->detallesFinSemana()->get();
        $finSemanaMap = [];
        foreach ($finSemanaDet as $d) {
            $orden = $d->orden_rotacion_fin_semana ?? 0;
            $finSemanaMap[$orden][$d->dia_semana][] = ['medico_id'=>$d->medico_id,'codigo'=>$d->codigo_turno];
        }

        DB::transaction(function () use (
            $archivo, $uciId, $mes, $anio, $diasEnMes, $patron, $finSemanaMap, &$turnosCreados
        ) {
            // Borrar turnos previos de esta UCI en este archivo
            TurnoMedico::where('archivo_id', $archivo->id)->where('uci_id', $uciId)->delete();

            $filas = [];
            $semanaNum = 0;
            $ultimoLunes = null;

            for ($d = 1; $d <= $diasEnMes; $d++) {
                $fecha = Carbon::create($anio, $mes, $d);
                $dow   = $fecha->dayOfWeek; // 0=Dom, 1=Lun ... 6=Sab
                $idx   = ($dow === 0) ? 6 : $dow - 1; // 0=Lun..6=Dom

                if ($idx === 0) { // lunes nuevo → nueva semana
                    $semanaNum++;
                    $ultimoLunes = $d;
                }

                $esFinde = in_array($dow, [0, 6]);
                $diasNombreShort = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'][$idx];

                // Días hábiles (Lun-Vie)
                if (!$esFinde) {
                    foreach ($patron as $medicoId => $dias) {
                        $codigo = strtoupper($dias[$idx] ?? '');
                        $horas  = TurnoMedico::horasPorCodigo($codigo);

                        $filas[] = [
                            'archivo_id'      => $archivo->id,
                            'medico_id'       => $medicoId,
                            'uci_id'          => $uciId,
                            'fecha'           => $fecha->toDateString(),
                            'dia_numero'      => $d,
                            'dia_semana'      => $diasNombreShort,
                            'codigo_turno'    => $codigo,
                            'horas_diurnas'   => in_array($codigo,['M','T','MT','MTN']) ? min($horas,12):0,
                            'horas_nocturnas' => in_array($codigo,['N','MTN','MN']) ? 12:0,
                            'horas_total'     => $horas,
                            'es_fin_semana'   => false,
                            'es_domingo'      => false,
                            'estado_turno'    => 'programado',
                            'fue_laborado'    => true,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ];
                        $turnosCreados++;
                    }
                } else {
                    // Fin de semana rotativo
                    $semSlot = (($semanaNum - 1) % max(count($finSemanaMap),1));
                    $slotMedicos = $finSemanaMap[$semSlot][$idx] ?? [];

                    foreach ($slotMedicos as $slot) {
                        $codigo = strtoupper($slot['codigo'] ?? '');
                        $horas  = TurnoMedico::horasPorCodigo($codigo);

                        $filas[] = [
                            'archivo_id'      => $archivo->id,
                            'medico_id'       => $slot['medico_id'],
                            'uci_id'          => $uciId,
                            'fecha'           => $fecha->toDateString(),
                            'dia_numero'      => $d,
                            'dia_semana'      => $diasNombreShort,
                            'codigo_turno'    => $codigo,
                            'horas_diurnas'   => in_array($codigo,['M','T','MT','MTN']) ? min($horas,12):0,
                            'horas_nocturnas' => in_array($codigo,['N','MTN','MN']) ? 12:0,
                            'horas_total'     => $horas,
                            'es_fin_semana'   => true,
                            'es_domingo'      => ($dow === 0),
                            'estado_turno'    => 'programado',
                            'fue_laborado'    => true,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ];
                        $turnosCreados++;
                    }
                }
            }

            if ($filas) TurnoMedico::insert($filas);

            $archivo->update([
                'procesado'     => true,
                'total_turnos'  => ($archivo->total_turnos ?? 0) + $turnosCreados,
            ]);
        });

        return ['turnos_creados' => $turnosCreados];
    }
}
