<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\Uci;
use App\Models\IndicadorMedico;
use App\Models\IndicadorUci;
use App\Services\TurnoCalculatorService;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TurnoEditorController extends Controller
{
    const CODIGOS   = ['M','T','MT','N','MTN','MN','VAC','PER','INC',''];
    const HORAS_MAP = ['M'=>6,'T'=>6,'MT'=>12,'N'=>12,'MTN'=>24,'MN'=>18,'VAC'=>0,'PER'=>0,'INC'=>0,''=>0];
    const DIAS_SEMANA = ['L','M','M','J','V','S','D']; // Lun=0 … Dom=6

    public function __construct(
        private TurnoCalculatorService $calculator,
        private AlertService           $alertService,
    ) {}

    // ── Vista principal ──────────────────────────────────────────

    public function index(Request $request)
    {
        $ucis    = Uci::where('activa', true)->orderBy('nombre')->get();
        $meses   = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $uciId   = (int)($request->uci_id  ?? $ucis->first()?->id);
        $mes     = (int)($request->mes     ?? now()->month);
        $anio    = (int)($request->anio    ?? now()->year);

        $uci     = $ucis->find($uciId);
        $archivo = ArchivoCargado::where('mes', $mes)->where('anio', $anio)
                        ->where('procesado', true)->first();

        // Médicos con turnos ya cargados en esta UCI/mes
        $medicosExistentes = collect();
        $grilla            = [];

        if ($archivo && $uci) {
            $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            $turnos    = TurnoMedico::where('archivo_id', $archivo->id)
                            ->where('uci_id', $uci->id)
                            ->with('medico')
                            ->orderBy('medico_id')->orderBy('fecha')
                            ->get();

            $medIds = $turnos->pluck('medico_id')->unique();
            $medicosExistentes = Medico::whereIn('id', $medIds)->orderBy('nombre')->get();

            foreach ($medicosExistentes as $m) {
                $grilla[$m->id] = array_fill(1, $diasEnMes, '');
            }
            foreach ($turnos as $t) {
                $grilla[$t->medico_id][$t->dia_numero] = $t->codigo_turno;
            }
        }

        // Todos los médicos disponibles para agregar
        $todosMedicos = Medico::orderBy('nombre')->get();

        // Meses con datos (para repetir secuencia)
        $mesesConDatos = ArchivoCargado::where('procesado', true)
            ->orderByDesc('anio')->orderByDesc('mes')->get(['id','mes','anio']);

        // Info de días del mes seleccionado
        $diasInfo = $this->generarDiasInfo($mes, $anio);

        return view('turno-editor.index', compact(
            'ucis', 'uci', 'uciId', 'meses', 'mes', 'anio',
            'archivo', 'medicosExistentes', 'grilla', 'diasInfo',
            'todosMedicos', 'mesesConDatos'
        ));
    }

    // ── Guardar secuencia semanal → generar mes ──────────────────

    public function guardarSecuencia(Request $request)
    {
        $request->validate([
            'uci_id'        => 'required|exists:ucis,id',
            'mes'           => 'required|integer|between:1,12',
            'anio'          => 'required|integer|between:2020,2035',
            'medicos'       => 'required|array|min:1',
            'medicos.*'     => 'exists:medicos,id',
            'patrones'      => 'required|array',
        ]);

        $uci       = Uci::findOrFail($request->uci_id);
        $mes       = (int)$request->mes;
        $anio      = (int)$request->anio;
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

        // Buscar o crear archivo del mes
        $archivo = ArchivoCargado::firstOrCreate(
            ['mes' => $mes, 'anio' => $anio],
            [
                'nombre_archivo' => "Manual UCI {$uci->codigo} — {$mes}/{$anio}",
                'ruta'           => '',
                'procesado'      => false,
            ]
        );

        DB::transaction(function () use ($request, $archivo, $uci, $mes, $anio, $diasEnMes) {
            // Eliminar turnos previos de esta UCI en este archivo
            TurnoMedico::where('archivo_id', $archivo->id)
                ->where('uci_id', $uci->id)
                ->delete();

            $patronesMed = $request->patrones; // [medico_id][0..6] = codigo_turno

            foreach ($request->medicos as $medicoId) {
                $medico  = Medico::find($medicoId);
                if (!$medico) continue;

                $patron  = $patronesMed[$medicoId] ?? array_fill(0, 7, '');
                $filas   = [];

                for ($d = 1; $d <= $diasEnMes; $d++) {
                    $fecha   = Carbon::create($anio, $mes, $d);
                    $dow     = $fecha->dayOfWeek; // 0=Dom, 1=Lun … 6=Sab
                    $idx     = ($dow === 0) ? 6 : $dow - 1; // 0=Lun…6=Dom
                    $codigo  = strtoupper(trim($patron[$idx] ?? ''));
                    if (!isset(self::HORAS_MAP[$codigo])) $codigo = '';

                    $horas   = self::HORAS_MAP[$codigo] ?? 0;
                    $esFinde = in_array($dow, [0, 6]);

                    $filas[] = [
                        'archivo_id'      => $archivo->id,
                        'medico_id'       => $medicoId,
                        'uci_id'          => $uci->id,
                        'fecha'           => $fecha->toDateString(),
                        'dia_numero'      => $d,
                        'dia_semana'      => $this->nombreDiaSemana($idx),
                        'codigo_turno'    => $codigo,
                        'horas_diurnas'   => in_array($codigo, ['M','T','MT','MTN']) ? min($horas, 12) : 0,
                        'horas_nocturnas' => in_array($codigo, ['N','MTN','MN'])     ? ($codigo==='MTN'?12:($codigo==='MN'?12:12)) : 0,
                        'horas_total'     => $horas,
                        'es_fin_semana'   => $esFinde,
                        'es_domingo'      => ($dow === 0),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];
                }

                TurnoMedico::insert($filas);

                // Asegurar que el médico está registrado en esta UCI
                $medico->update(['uci_id' => $uci->id]);
            }

            // Recalcular indicadores
            $this->recalcularArchivo($archivo, $mes, $anio);
        });

        return redirect()->route('turno-editor.index', [
            'uci_id' => $uci->id, 'mes' => $mes, 'anio' => $anio
        ])->with('success', "Secuencia generada para {$uci->nombre} — {$mes}/{$anio}");
    }

    // ── Repetir secuencia a meses siguientes ─────────────────────

    public function repetirSecuencia(Request $request)
    {
        $request->validate([
            'uci_id'       => 'required|exists:ucis,id',
            'mes_origen'   => 'required|integer|between:1,12',
            'anio_origen'  => 'required|integer',
            'meses_destino'=> 'required|array|min:1',
        ]);

        $uci        = Uci::findOrFail($request->uci_id);
        $mesOrigen  = (int)$request->mes_origen;
        $anioOrigen = (int)$request->anio_origen;

        $archivoOrigen = ArchivoCargado::where('mes', $mesOrigen)
            ->where('anio', $anioOrigen)->where('procesado', true)->first();

        if (!$archivoOrigen) {
            return back()->with('error', 'No existe un mes origen procesado con esos datos.');
        }

        // Extraer patrón semanal (día-de-semana → código) por médico
        $turnosOrigen = TurnoMedico::where('archivo_id', $archivoOrigen->id)
            ->where('uci_id', $uci->id)
            ->get();

        if ($turnosOrigen->isEmpty()) {
            return back()->with('error', "No hay turnos de {$uci->nombre} en el mes origen.");
        }

        // patron[medicoId][0..6] = código más frecuente para ese día de semana
        $patron = [];
        foreach ($turnosOrigen as $t) {
            $fecha = Carbon::parse($t->fecha);
            $dow   = $fecha->dayOfWeek; // 0=Dom
            $idx   = ($dow === 0) ? 6 : $dow - 1; // 0=Lun
            $patron[$t->medico_id][$idx][] = $t->codigo_turno;
        }

        // Votar por el código más frecuente por día
        $patronFinal = [];
        foreach ($patron as $medicoId => $dias) {
            foreach ($dias as $idx => $codigos) {
                $counts = array_count_values($codigos);
                arsort($counts);
                $patronFinal[$medicoId][$idx] = array_key_first($counts);
            }
        }

        $generados = 0;
        $errores   = [];

        foreach ($request->meses_destino as $mesAnio) {
            [$mesD, $anioD] = explode('-', $mesAnio);
            $mesD  = (int)$mesD;
            $anioD = (int)$anioD;

            try {
                $archivoDestino = ArchivoCargado::firstOrCreate(
                    ['mes' => $mesD, 'anio' => $anioD],
                    [
                        'nombre_archivo' => "Secuencia {$uci->codigo} — {$mesD}/{$anioD}",
                        'ruta'           => '', 'procesado' => false,
                    ]
                );

                DB::transaction(function () use ($archivoDestino, $uci, $mesD, $anioD, $patronFinal) {
                    TurnoMedico::where('archivo_id', $archivoDestino->id)
                        ->where('uci_id', $uci->id)->delete();

                    $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mesD, $anioD);

                    foreach ($patronFinal as $medicoId => $diasPattern) {
                        $filas = [];
                        for ($d = 1; $d <= $diasEnMes; $d++) {
                            $fecha  = Carbon::create($anioD, $mesD, $d);
                            $dow    = $fecha->dayOfWeek;
                            $idx    = ($dow === 0) ? 6 : $dow - 1;
                            $codigo = strtoupper($diasPattern[$idx] ?? '');
                            $horas  = self::HORAS_MAP[$codigo] ?? 0;

                            $filas[] = [
                                'archivo_id'      => $archivoDestino->id,
                                'medico_id'       => $medicoId,
                                'uci_id'          => $uci->id,
                                'fecha'           => $fecha->toDateString(),
                                'dia_numero'      => $d,
                                'dia_semana'      => $this->nombreDiaSemana($idx),
                                'codigo_turno'    => $codigo,
                                'horas_diurnas'   => in_array($codigo,['M','T','MT','MTN']) ? min($horas,12) : 0,
                                'horas_nocturnas' => in_array($codigo,['N','MTN','MN'])     ? 12 : 0,
                                'horas_total'     => $horas,
                                'es_fin_semana'   => in_array($dow, [0,6]),
                                'es_domingo'      => ($dow === 0),
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ];
                        }
                        TurnoMedico::insert($filas);
                    }

                    $this->recalcularArchivo($archivoDestino, $mesD, $anioD);
                });

                $generados++;
            } catch (\Throwable $e) {
                $errores[] = "{$mesD}/{$anioD}: " . $e->getMessage();
            }
        }

        $msg = "Secuencia repetida en {$generados} mes(es).";
        if ($errores) $msg .= ' Errores: ' . implode('; ', $errores);

        return back()->with('success', $msg);
    }

    // ── Agregar médico nuevo a un mes/UCI ────────────────────────

    public function agregarMedico(Request $request)
    {
        $request->validate([
            'uci_id'              => 'required|exists:ucis,id',
            'mes'                 => 'required|integer|between:1,12',
            'anio'                => 'required|integer',
            'medico_id'           => 'nullable|exists:medicos,id',
            'nombre_nuevo'        => 'nullable|string|max:100',
            'apellido_nuevo'      => 'nullable|string|max:100',
            'patron'              => 'required|array',
            'reemplaza_medico_id' => 'nullable|exists:medicos,id',
        ]);

        $uci  = Uci::findOrFail($request->uci_id);
        $mes  = (int)$request->mes;
        $anio = (int)$request->anio;

        // Buscar o crear médico — búsqueda global por nombre (mismo médico puede tener turnos en varias UCIs)
        $medicoId = $request->medico_id;
        if (!$medicoId && $request->nombre_nuevo) {
            $nombreBuscar  = trim($request->nombre_nuevo);
            $apellidoBuscar= trim($request->apellido_nuevo ?? '');
            // Buscar coincidencia exacta en cualquier UCI
            $medico = Medico::where('nombre', $nombreBuscar)
                ->when($apellidoBuscar, fn($q) => $q->where('apellido', $apellidoBuscar))
                ->first();
            if (!$medico) {
                $medico = Medico::create([
                    'nombre'   => $nombreBuscar,
                    'apellido' => $apellidoBuscar,
                    'uci_id'   => $uci->id,
                    'activo'   => true,
                ]);
            }
            $medicoId = $medico->id;
        }

        if (!$medicoId) {
            return back()->with('error', 'Seleccione un médico o ingrese el nombre de uno nuevo.');
        }

        $archivo = ArchivoCargado::firstOrCreate(
            ['mes' => $mes, 'anio' => $anio],
            ['nombre_archivo' => "Editor {$uci->codigo} {$mes}/{$anio}", 'ruta' => '', 'procesado' => false]
        );

        $diasEnMes          = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $patron             = $request->patron;
        $reemplazaMedicoId  = $request->reemplaza_medico_id;

        DB::transaction(function () use ($archivo, $medicoId, $uci, $mes, $anio, $diasEnMes, $patron, $reemplazaMedicoId) {
            // Borrar turnos del médico que se reemplaza
            if ($reemplazaMedicoId) {
                TurnoMedico::where('archivo_id', $archivo->id)
                    ->where('medico_id', $reemplazaMedicoId)
                    ->where('uci_id', $uci->id)
                    ->delete();
            }

            // Borrar turnos previos del nuevo médico (por si ya existía)
            TurnoMedico::where('archivo_id', $archivo->id)
                ->where('medico_id', $medicoId)
                ->where('uci_id', $uci->id)->delete();

            $filas = [];
            for ($d = 1; $d <= $diasEnMes; $d++) {
                $fecha  = Carbon::create($anio, $mes, $d);
                $dow    = $fecha->dayOfWeek;
                $idx    = ($dow === 0) ? 6 : $dow - 1;
                $codigo = strtoupper(trim($patron[$idx] ?? ''));
                $horas  = self::HORAS_MAP[$codigo] ?? 0;

                $filas[] = [
                    'archivo_id'     => $archivo->id,
                    'medico_id'      => $medicoId,
                    'uci_id'         => $uci->id,
                    'fecha'          => $fecha->toDateString(),
                    'dia_numero'     => $d,
                    'dia_semana'     => $this->nombreDiaSemana($idx),
                    'codigo_turno'   => $codigo,
                    'horas_diurnas'  => in_array($codigo,['M','T','MT','MTN']) ? min($horas,12):0,
                    'horas_nocturnas'=> in_array($codigo,['N','MTN','MN'])     ? 12:0,
                    'horas_total'    => $horas,
                    'es_fin_semana'  => in_array($dow,[0,6]),
                    'es_domingo'     => ($dow===0),
                    'fue_laborado'   => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
            TurnoMedico::insert($filas);
            $this->recalcularArchivo($archivo, $mes, $anio);
        });

        $msg = 'Médico agregado a la secuencia.';
        if ($reemplazaMedicoId) {
            $reemplazado = Medico::find($reemplazaMedicoId);
            $msg = "Médico agregado. Los turnos de {$reemplazado?->nombre_completo} fueron eliminados.";
        }

        return back()->with('success', $msg);
    }

    // ── Actualizar datos del médico ──────────────────────────────

    public function actualizarMedico(Request $request, Medico $medico)
    {
        $request->validate([
            'nombre'   => 'required|string|max:100',
            'apellido' => 'nullable|string|max:100',
        ]);

        $medico->update([
            'nombre'   => trim($request->nombre),
            'apellido' => trim($request->apellido ?? ''),
        ]);

        return response()->json([
            'ok'             => true,
            'nombre_completo'=> $medico->fresh()->nombre_completo,
        ]);
    }

    // ── Sustitución de turno (médico A → médico B en fecha X) ───

    public function sustituir(Request $request)
    {
        // Modo 1: edición de celda desde la grilla (medico_id + fecha + uci_id + archivo_id)
        if ($request->has('medico_id') && $request->has('fecha')) {
            $request->validate([
                'medico_id'   => 'required|exists:medicos,id',
                'fecha'       => 'required|date',
                'uci_id'      => 'required|exists:ucis,id',
                'archivo_id'  => 'nullable|exists:archivos_cargados,id',
                'codigo_nuevo'=> 'nullable|string|max:10',
            ]);

            $codigo = strtoupper(trim($request->codigo_nuevo ?? ''));
            if (!array_key_exists($codigo, self::HORAS_MAP)) $codigo = '';
            $horas  = self::HORAS_MAP[$codigo] ?? 0;
            $fecha  = \Carbon\Carbon::parse($request->fecha);
            $dow    = $fecha->dayOfWeek;
            $idx    = ($dow === 0) ? 6 : $dow - 1;

            $turno = TurnoMedico::where('medico_id', $request->medico_id)
                ->where('uci_id', $request->uci_id)
                ->where('fecha', $request->fecha)
                ->when($request->archivo_id, fn($q) => $q->where('archivo_id', $request->archivo_id))
                ->first();

            if ($turno) {
                $turno->update([
                    'codigo_turno'    => $codigo,
                    'horas_diurnas'   => in_array($codigo,['M','T','MT','MTN']) ? min($horas,12) : 0,
                    'horas_nocturnas' => in_array($codigo,['N','MTN','MN'])     ? 12 : 0,
                    'horas_total'     => $horas,
                    'es_fin_semana'   => in_array($dow,[0,6]),
                    'es_domingo'      => ($dow===0),
                ]);
                $archivo = $turno->archivo;
            } else {
                // Crear el registro si no existe
                $archivoId = $request->archivo_id
                    ?? ArchivoCargado::where('mes', $fecha->month)->where('anio', $fecha->year)->value('id');
                if (!$archivoId) {
                    return response()->json(['ok'=>false,'mensaje'=>'No existe archivo para este mes.']);
                }
                $archivo = ArchivoCargado::find($archivoId);
                TurnoMedico::create([
                    'archivo_id'      => $archivoId,
                    'medico_id'       => $request->medico_id,
                    'uci_id'          => $request->uci_id,
                    'fecha'           => $request->fecha,
                    'dia_numero'      => $fecha->day,
                    'dia_semana'      => $this->nombreDiaSemana($idx),
                    'codigo_turno'    => $codigo,
                    'horas_diurnas'   => in_array($codigo,['M','T','MT','MTN']) ? min($horas,12) : 0,
                    'horas_nocturnas' => in_array($codigo,['N','MTN','MN'])     ? 12 : 0,
                    'horas_total'     => $horas,
                    'es_fin_semana'   => in_array($dow,[0,6]),
                    'es_domingo'      => ($dow===0),
                ]);
            }

            if ($archivo) {
                $this->recalcularArchivo($archivo, $archivo->mes, $archivo->anio);
            }

            return response()->json(['ok' => true, 'mensaje' => 'Turno actualizado.']);
        }

        // Modo 2: sustitución clásica por turno_id
        $request->validate([
            'turno_id'        => 'required|exists:turno_medicos,id',
            'medico_nuevo_id' => 'nullable|exists:medicos,id',
            'nombre_nuevo'    => 'nullable|string|max:100',
            'codigo_nuevo'    => 'nullable|string|max:10',
        ]);

        $turno = TurnoMedico::findOrFail($request->turno_id);

        $medicoNuevoId = $request->medico_nuevo_id;
        if (!$medicoNuevoId && $request->nombre_nuevo) {
            $mNuevo        = Medico::firstOrCreate(['nombre' => trim($request->nombre_nuevo)], ['uci_id'=>$turno->uci_id,'activo'=>true]);
            $medicoNuevoId = $mNuevo->id;
        }

        $codigoNuevo = strtoupper(trim($request->codigo_nuevo ?: $turno->codigo_turno));
        $horas       = self::HORAS_MAP[$codigoNuevo] ?? 0;

        DB::transaction(function () use ($turno, $medicoNuevoId, $codigoNuevo, $horas) {
            if ($medicoNuevoId) {
                TurnoMedico::updateOrCreate(
                    ['archivo_id'=>$turno->archivo_id,'medico_id'=>$medicoNuevoId,'uci_id'=>$turno->uci_id,'fecha'=>$turno->fecha],
                    [
                        'dia_numero'=>$turno->dia_numero,'dia_semana'=>$turno->dia_semana,
                        'codigo_turno'=>$codigoNuevo,
                        'horas_diurnas'=>in_array($codigoNuevo,['M','T','MT','MTN'])?min($horas,12):0,
                        'horas_nocturnas'=>in_array($codigoNuevo,['N','MTN','MN'])?12:0,
                        'horas_total'=>$horas,
                        'es_fin_semana'=>$turno->es_fin_semana,'es_domingo'=>$turno->es_domingo,
                    ]
                );
            }
            $turno->update(['codigo_turno'=>'','horas_diurnas'=>0,'horas_nocturnas'=>0,'horas_total'=>0]);

            $archivo = $turno->archivo;
            if ($archivo) $this->recalcularArchivo($archivo, $archivo->mes, $archivo->anio);
        });

        return response()->json(['ok' => true, 'mensaje' => 'Sustitución registrada.']);
    }

    // ── Aprobar cambio de turno (coordinador/master) ─────────────

    public function aprobarCambio(Request $request, \App\Models\SolicitudCambioTurno $solicitud)
    {
        $solicitud->update([
            'estado'           => 'aprobado_coordinador',
            'aprobado_por'     => auth()->user()->name,
            'resuelto_at'      => now(),
            'motivo_coordinador'=> $request->motivo,
        ]);

        // Intercambiar los códigos de turno
        $to = $solicitud->turnoOrigen;
        $td = $solicitud->turnoDestino;

        DB::transaction(function () use ($to, $td) {
            $codOrigen  = $to->codigo_turno;
            $medicoOrig = $to->medico_id;
            $codDest    = $td->codigo_turno;
            $medicoDest = $td->medico_id;

            // El turno origen pasa al médico destino con el código destino
            $to->update([
                'medico_id'       => $medicoDest,
                'codigo_turno'    => $codDest,
                'horas_total'     => self::HORAS_MAP[$codDest] ?? 0,
            ]);

            // El turno destino pasa al médico origen con el código origen
            $td->update([
                'medico_id'       => $medicoOrig,
                'codigo_turno'    => $codOrigen,
                'horas_total'     => self::HORAS_MAP[$codOrigen] ?? 0,
            ]);
        });

        return back()->with('success', 'Cambio de turno aprobado y aplicado.');
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function generarDiasInfo(int $mes, int $anio): array
    {
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $info      = [];
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $fecha   = Carbon::create($anio, $mes, $d);
            $dow     = $fecha->dayOfWeek;
            $idx     = ($dow === 0) ? 6 : $dow - 1;
            $info[$d] = [
                'fecha'   => $fecha->toDateString(),
                'letra'   => self::DIAS_SEMANA[$idx],
                'es_hoy'  => $fecha->isToday(),
                'es_finde'=> in_array($dow, [0,6]),
                'idx_dow' => $idx, // 0=Lun..6=Dom
            ];
        }
        return $info;
    }

    private function nombreDiaSemana(int $idx): string
    {
        return ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'][$idx] ?? '';
    }

    private function recalcularArchivo(ArchivoCargado $archivo, int $mes, int $anio): void
    {
        // Recalcular indicadores médicos simplificado
        $medicos = TurnoMedico::where('archivo_id', $archivo->id)
            ->distinct()->pluck('medico_id');

        $totalMedicos = 0;
        $totalTurnos  = 0;

        foreach ($medicos as $medicoId) {
            $turnos = TurnoMedico::where('archivo_id', $archivo->id)
                ->where('medico_id', $medicoId)->get();

            IndicadorMedico::updateOrCreate(
                ['archivo_id' => $archivo->id, 'medico_id' => $medicoId],
                [
                    'uci_id'          => $turnos->first()?->uci_id,
                    'mes'             => $mes,
                    'anio'            => $anio,
                    'total_horas'     => $turnos->sum('horas_total'),
                    'horas_diurnas'   => $turnos->sum('horas_diurnas'),
                    'horas_nocturnas' => $turnos->sum('horas_nocturnas'),
                    'turnos_M'        => $turnos->where('codigo_turno','M')->count(),
                    'turnos_T'        => $turnos->where('codigo_turno','T')->count(),
                    'turnos_MT'       => $turnos->where('codigo_turno','MT')->count(),
                    'turnos_N'        => $turnos->where('codigo_turno','N')->count(),
                ]
            );
            $totalMedicos++;
            $totalTurnos += $turnos->count();
        }

        $archivo->update([
            'procesado'     => true,
            'total_medicos' => $totalMedicos,
            'total_turnos'  => $totalTurnos,
        ]);
    }
}
