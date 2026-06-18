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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

    // ── Carga de Excel por UCI ───────────────────────────────────

    public function cargarExcelForm(Request $request)
    {
        $ucis  = Uci::where('activa', true)->orderBy('nombre')->get();
        $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $anios = range(now()->year - 1, now()->year + 3);

        // Si viene de un parse exitoso, mostrar preview
        $preview = session('secuencia_preview');

        return view('secuencias.cargar-excel', compact('ucis','meses','anios','preview'));
    }

    public function parsearExcel(Request $request)
    {
        $request->validate([
            'uci_id' => 'required|exists:ucis,id',
            'nombre' => 'required|string|max:120',
            'anio'   => 'required|integer|min:2020|max:2035',
            'excel'  => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $uci = Uci::findOrFail($request->uci_id);

        try {
            $parsed = $this->parseExcelSecuencia($request->file('excel'));
        } catch (\Throwable $e) {
            return back()->withErrors(['excel' => 'Error al leer el archivo: ' . $e->getMessage()])->withInput();
        }

        if (empty($parsed['doctores'])) {
            return back()->withErrors(['excel' => 'No se encontraron médicos en el archivo. Verifique que la columna A tenga los nombres.'])->withInput();
        }

        // Guardar secuencia en BD
        $secuencia = DB::transaction(function () use ($request, $uci, $parsed) {
            $seq = SecuenciaUci::create([
                'uci_id'               => $uci->id,
                'nombre'               => $request->nombre,
                'anio'                 => $request->anio,
                'activa'               => true,
                'creada_por_usuario_id'=> Auth::id(),
            ]);

            // Determinar slots de rotación de fin de semana
            $slotsUsados = [];
            foreach ($parsed['doctores'] as $doc) {
                if ($doc['weekend_slot'] !== null && !in_array($doc['weekend_slot'], $slotsUsados)) {
                    $slotsUsados[] = $doc['weekend_slot'];
                }
            }
            sort($slotsUsados);
            $slotMap = array_flip($slotsUsados); // original_week → slot_orden (0,1,2,...)

            foreach ($parsed['doctores'] as $doc) {
                // Buscar o crear médico — case-insensitive, tolera nombre completo en columna 'nombre'
                $partes   = explode(' ', $doc['nombre'], 2);
                $nombre   = $partes[0];
                $apellido = $partes[1] ?? '';

                $medico = Medico::buscarPorNombreCompleto($doc['nombre'])
                    ?? Medico::create([
                        'nombre'   => mb_convert_case($nombre,   MB_CASE_TITLE, 'UTF-8'),
                        'apellido' => mb_convert_case($apellido, MB_CASE_TITLE, 'UTF-8'),
                        'uci_id'   => $seq->uci_id,
                        'activo'   => true,
                    ]);

                // Días hábiles (Lun=0 … Vie=4)
                foreach ($doc['patron_fijo'] as $dia => $codigo) {
                    SecuenciaUciDetalle::create([
                        'secuencia_uci_id' => $seq->id,
                        'medico_id'        => $medico->id,
                        'dia_semana'       => (int)$dia,
                        'codigo_turno'     => $codigo,
                        'es_fin_de_semana' => false,
                    ]);
                }

                // Fin de semana (Sáb=5, Dom=6)
                if ($doc['weekend_slot'] !== null) {
                    $orden   = $slotMap[$doc['weekend_slot']] ?? 0;
                    $weekRot = $doc['weekend_rot'][$doc['weekend_slot']] ?? [];

                    foreach ([5 => ($weekRot['sab'] ?? ''), 6 => ($weekRot['dom'] ?? '')] as $dia => $codigo) {
                        if ($codigo !== '') {
                            SecuenciaUciDetalle::create([
                                'secuencia_uci_id'            => $seq->id,
                                'medico_id'                   => $medico->id,
                                'dia_semana'                  => $dia,
                                'codigo_turno'                => $codigo,
                                'es_fin_de_semana'            => true,
                                'orden_rotacion_fin_semana'   => $orden,
                            ]);
                        }
                    }
                }
            }

            return $seq;
        });

        // Preview para la vista de aplicación de meses
        session(['secuencia_preview' => [
            'id'       => $secuencia->id,
            'nombre'   => $secuencia->nombre,
            'uci'      => $uci->nombre,
            'anio'     => $request->anio,
            'doctores' => array_map(fn($d) => $d['nombre'], $parsed['doctores']),
            'semanas'  => $parsed['num_semanas'],
        ]]);

        return redirect()->route('secuencias.cargar-excel')
            ->with('success', "Secuencia \"{$secuencia->nombre}\" creada con " . count($parsed['doctores']) . ' médicos. Ahora selecciona los meses a programar.');
    }

    // Aplicar secuencia a varios meses a la vez
    public function aplicarMeses(Request $request, SecuenciaUci $secuencia)
    {
        $request->validate([
            'meses_anio' => 'required|array|min:1',
        ]);

        $total   = 0;
        $errores = [];

        foreach ($request->meses_anio as $mesAnio) {
            [$mes, $anio] = explode('-', $mesAnio);
            try {
                $r = $this->generarTurnosDesdeSecuencia($secuencia, (int)$mes, (int)$anio);
                $total += $r['turnos_creados'];
            } catch (\Throwable $e) {
                $errores[] = "{$mes}/{$anio}: " . $e->getMessage();
            }
        }

        $msg = "{$total} turnos programados en " . count($request->meses_anio) . ' mes(es).';
        if ($errores) $msg .= ' Errores: ' . implode('; ', $errores);

        return back()->with('success', $msg);
    }

    // ── Importar calendario mensual directo ─────────────────────

    public function importarCalendario(Request $request)
    {
        $request->validate([
            'uci_id' => 'required|exists:ucis,id',
            'mes'    => 'required|integer|between:1,12',
            'anio'   => 'required|integer|min:2020|max:2035',
            'excel'  => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $uci       = Uci::findOrFail($request->uci_id);
        $mes       = (int)$request->mes;
        $anio      = (int)$request->anio;
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

        try {
            $parsed = $this->parseCalendarioMensual($request->file('excel'), $mes, $anio, $diasEnMes);
        } catch (\Throwable $e) {
            return back()->withErrors(['excel_cal' => 'Error al leer el archivo: ' . $e->getMessage()])->withInput();
        }

        if (empty($parsed['doctores'])) {
            return back()->withErrors(['excel_cal' => 'No se encontraron médicos. Verifique que la columna A tenga los nombres.'])->withInput();
        }

        $totalTurnos = 0;

        DB::transaction(function () use ($uci, $mes, $anio, $diasEnMes, $parsed, &$totalTurnos) {
            // Buscar o crear archivo del mes
            $archivo = ArchivoCargado::firstOrCreate(
                ['mes' => $mes, 'anio' => $anio],
                [
                    'nombre_archivo' => "Importado {$uci->codigo} {$mes}/{$anio}",
                    'ruta'           => '',
                    'procesado'      => true,
                    'total_medicos'  => 0,
                    'total_turnos'   => 0,
                ]
            );

            // Borrar turnos previos de esta UCI en este mes
            TurnoMedico::where('archivo_id', $archivo->id)
                ->where('uci_id', $uci->id)
                ->delete();

            $horasMap = ['M'=>6,'T'=>6,'MT'=>12,'N'=>12,'MTN'=>24,'MN'=>18,
                         'VAC'=>0,'PER'=>0,'INC'=>0,'LIBRE'=>0,''=>0];

            foreach ($parsed['doctores'] as $doc) {
                // Buscar o crear médico — case-insensitive, tolera nombre completo en columna 'nombre'
                $partes   = explode(' ', $doc['nombre'], 2);
                $nombre   = $partes[0];
                $apellido = $partes[1] ?? '';

                $medico = Medico::buscarPorNombreCompleto($doc['nombre'])
                    ?? Medico::create([
                        'nombre'   => mb_convert_case($nombre,   MB_CASE_TITLE, 'UTF-8'),
                        'apellido' => mb_convert_case($apellido, MB_CASE_TITLE, 'UTF-8'),
                        'uci_id'   => $uci->id,
                        'activo'   => true,
                    ]);

                $filas = [];
                foreach ($doc['turnos'] as $dia => $codigo) {
                    $fecha   = Carbon::create($anio, $mes, $dia);
                    $dow     = $fecha->dayOfWeek;       // 0=Dom..6=Sáb
                    $idx     = ($dow === 0) ? 6 : $dow - 1; // 0=Lun..6=Dom
                    $horas   = $horasMap[$codigo] ?? 0;
                    $esFinde = in_array($dow, [0, 6]);
                    $diaNom  = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'][$idx];

                    $filas[] = [
                        'archivo_id'      => $archivo->id,
                        'medico_id'       => $medico->id,
                        'uci_id'          => $uci->id,
                        'fecha'           => $fecha->toDateString(),
                        'dia_numero'      => $dia,
                        'dia_semana'      => $diaNom,
                        'codigo_turno'    => $codigo,
                        'horas_diurnas'   => in_array($codigo,['M','T','MT','MTN']) ? min($horas,12) : 0,
                        'horas_nocturnas' => in_array($codigo,['N','MTN','MN'])     ? 12 : 0,
                        'horas_total'     => $horas,
                        'es_fin_semana'   => $esFinde,
                        'es_domingo'      => ($dow === 0),
                        'estado_turno'    => 'programado',
                        'fue_laborado'    => true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];
                }

                if ($filas) {
                    TurnoMedico::insert($filas);
                    $totalTurnos += count($filas);
                }
            }

            $archivo->update([
                'procesado'     => true,
                'total_medicos' => count($parsed['doctores']),
                'total_turnos'  => $totalTurnos,
            ]);
        });

        $mesesNombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                         'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return back()
            ->with('tab_activo', 'calendario')
            ->with('success_cal', "Importados {$totalTurnos} turnos para {$uci->nombre} — {$mesesNombres[$mes]} {$anio} (" . count($parsed['doctores']) . " médicos).");
    }

    // ── Parser: calendario mensual directo ───────────────────────

    private function parseCalendarioMensual(
        \Illuminate\Http\UploadedFile $file,
        int $mes,
        int $anio,
        int $diasEnMes
    ): array {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet       = $spreadsheet->getActiveSheet();

        $maxRow = $sheet->getHighestDataRow();
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $codigosValidos = ['M','T','MT','N','MTN','MN','VAC','PER','INC','LIBRE'];

        // ── Detectar fila de encabezado de días y columna de nombres ──
        // Buscamos una fila que tenga números 1…N secuenciales
        $headerRow  = null;
        $nombreCol  = 1;
        $colDiaMap  = []; // col_index => dia_numero (1..31)

        for ($row = 1; $row <= min($maxRow, 10); $row++) {
            $numerosEncontrados = [];
            for ($col = 1; $col <= min($maxCol, 40); $col++) {
                $v = $sheet->getCell([$col, $row])->getValue();
                if (is_numeric($v) && (int)$v >= 1 && (int)$v <= 31) {
                    $numerosEncontrados[$col] = (int)$v;
                }
            }
            // Si hay ≥5 números que van del 1 al diasEnMes, es la fila de encabezado
            if (count($numerosEncontrados) >= 5) {
                $vals = array_values($numerosEncontrados);
                if (min($vals) <= 5 && max($vals) >= min(20, $diasEnMes)) {
                    $headerRow = $row;
                    $colDiaMap = $numerosEncontrados;
                    // Columna de nombres = la columna anterior al primer número
                    $primeraCol = array_key_first($numerosEncontrados);
                    $nombreCol  = max(1, $primeraCol - 1);
                    break;
                }
            }
        }

        // Sin encabezado numérico: asumir col A = nombres, col B en adelante = días 1…N
        if (!$colDiaMap) {
            $nombreCol = 1;
            for ($d = 1; $d <= $diasEnMes; $d++) {
                $colDiaMap[$d + 1] = $d; // col 2 = día 1, col 3 = día 2, …
            }
        }

        // ── Parsear médicos ──
        $startRow = $headerRow ? $headerRow + 1 : 1;
        $doctores = [];

        for ($row = $startRow; $row <= $maxRow; $row++) {
            $nombre = trim((string)$sheet->getCell([$nombreCol, $row])->getValue());
            if (empty($nombre)) continue;
            if (is_numeric($nombre)) continue; // fila de totales
            if (in_array(strtoupper($nombre), ['MÉDICO','MEDICO','NOMBRE','TOTAL','SUMA'])) continue;

            $turnos = []; // dia_numero => codigo
            foreach ($colDiaMap as $col => $dia) {
                if ($dia < 1 || $dia > $diasEnMes) continue;
                $raw    = strtoupper(trim((string)$sheet->getCell([$col, $row])->getValue()));
                $codigo = in_array($raw, $codigosValidos) ? $raw : '';
                $turnos[$dia] = $codigo;
            }

            // Solo guardar si el médico tiene al menos un turno
            $conTurno = array_filter($turnos, fn($c) => $c !== '');
            if (empty($conTurno)) continue;

            $doctores[] = [
                'nombre' => $nombre,
                'turnos' => $turnos,
            ];
        }

        return ['doctores' => $doctores];
    }

    // ── Parser Excel ─────────────────────────────────────────────

    private function parseExcelSecuencia(\Illuminate\Http\UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet       = $spreadsheet->getActiveSheet();

        $maxRow = $sheet->getHighestDataRow();
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $letrasValidas = ['L','M','J','V','S','D'];

        // Buscar fila de encabezado (contiene L, M, M, J, V, S, D repeating)
        $headerRow  = null;
        $diasStartCol = null;

        for ($row = 1; $row <= min($maxRow, 15); $row++) {
            $encontradas = 0;
            $primeraLetra = null;
            for ($col = 1; $col <= min($maxCol, 60); $col++) {
                $v = strtoupper(trim((string)$sheet->getCell([$col, $row])->getValue()));
                if (in_array($v, $letrasValidas)) {
                    $encontradas++;
                    if ($primeraLetra === null) {
                        $primeraLetra = $col;
                    }
                }
            }
            if ($encontradas >= 5) {
                $headerRow    = $row;
                $diasStartCol = $primeraLetra;
                break;
            }
        }

        if (!$headerRow) {
            throw new \Exception('No se encontró la fila con letras de días (L/M/J/V/S/D). Verifique el formato del Excel.');
        }

        // Columna de nombres = la columna inmediatamente antes de los días, o col 1
        $nombreCol = max(1, $diasStartCol - 1);

        // Mapear columnas: cada columna day → [dia_idx 0-6, semana_num]
        $colMap = [];
        $daySeq = [0, 1, 2, 3, 4, 5, 6]; // Lun, Mar, Mié, Jue, Vie, Sáb, Dom
        $pos    = 0;
        for ($col = $diasStartCol; $col <= $maxCol; $col++) {
            $v = strtoupper(trim((string)$sheet->getCell([$col, $headerRow])->getValue()));
            if (in_array($v, $letrasValidas)) {
                $colMap[$col] = [
                    'dia'    => $daySeq[$pos % 7],
                    'semana' => intdiv($pos, 7),
                ];
                $pos++;
            }
        }

        $numSemanas = (int)ceil($pos / 7);

        // Detectar nombre de la UCI (texto antes del encabezado)
        $uciNombreDetectado = null;
        for ($row = 1; $row < $headerRow; $row++) {
            for ($col = 1; $col <= $maxCol; $col++) {
                $v = trim((string)$sheet->getCell([$col, $row])->getValue());
                if ($v && !in_array(strtoupper($v), $letrasValidas)) {
                    $uciNombreDetectado = $v;
                    break 2;
                }
            }
        }

        // Parsear médicos
        $codigosValidos = ['M','T','MT','N','MTN','MN','VAC','PER','INC','LIBRE'];
        $doctores = [];

        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $nombre = trim((string)$sheet->getCell([$nombreCol, $row])->getValue());
            if (empty($nombre)) continue;
            // Ignorar filas de totales o encabezados repetidos
            if (in_array(strtoupper($nombre), ['MÉDICO','MEDICO','NOMBRE','TOTAL'])) continue;

            // Recopilar códigos por [dia][semana]
            $cdsPorDiaSemana = [];
            foreach ($colMap as $col => $info) {
                $raw = strtoupper(trim((string)$sheet->getCell([$col, $row])->getValue()));
                // Solo guardar si es un código válido
                $codigo = in_array($raw, $codigosValidos) ? $raw : '';
                $cdsPorDiaSemana[$info['dia']][$info['semana']] = $codigo;
            }

            // Patrón fijo (Lun–Vie): código más frecuente por día
            $patronFijo = [];
            for ($d = 0; $d <= 4; $d++) {
                $semanas = $cdsPorDiaSemana[$d] ?? [];
                $noVacios = array_filter($semanas, fn($c) => $c !== '');
                if (empty($noVacios)) {
                    $patronFijo[$d] = '';
                } else {
                    $counts = array_count_values($noVacios);
                    arsort($counts);
                    $patronFijo[$d] = array_key_first($counts);
                }
            }

            // Rotación fines de semana: detectar primera semana con turno
            $weekendRot   = [];
            $weekendSlot  = null;
            for ($semana = 0; $semana < $numSemanas; $semana++) {
                $sab = $cdsPorDiaSemana[5][$semana] ?? '';
                $dom = $cdsPorDiaSemana[6][$semana] ?? '';
                if ($sab !== '' || $dom !== '') {
                    $weekendRot[$semana] = ['sab' => $sab, 'dom' => $dom];
                    if ($weekendSlot === null) {
                        $weekendSlot = $semana; // primera semana donde trabaja fin de semana
                    }
                }
            }

            $doctores[] = [
                'nombre'         => $nombre,
                'patron_fijo'    => $patronFijo,
                'weekend_rot'    => $weekendRot,
                'weekend_slot'   => $weekendSlot,
            ];
        }

        return [
            'uci_nombre'  => $uciNombreDetectado,
            'num_semanas' => $numSemanas,
            'doctores'    => $doctores,
        ];
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
