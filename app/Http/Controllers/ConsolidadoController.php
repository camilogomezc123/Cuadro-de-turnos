<?php

namespace App\Http\Controllers;

use App\Models\TurnoMedico;
use App\Models\Medico;
use App\Models\Uci;
use App\Models\Novedad;
use App\Models\AlertaTurno;
use App\Models\ArchivoCargado;
use App\Models\AuditoriaSistema;
use App\Services\HoraConsolidadoService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ConsolidadoController extends Controller
{
    public function __construct(private HoraConsolidadoService $horaService) {}

    public function index(Request $request)
    {
        $mes    = (int)($request->mes  ?? now()->month);
        $anio   = (int)($request->anio ?? now()->year);
        $meses  = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $consolidado = $this->horaService->consolidadoMensual($mes, $anio);

        // KPIs resumen
        $totalProgramadas   = $consolidado->sum('horas_programadas');
        $totalReconocidas   = $consolidado->sum('horas_reconocidas');
        $totalConExceso     = $consolidado->where('supera_200h', true)->count();
        $totalNovedades     = $consolidado->sum('novedades');
        $totalAlertas       = $consolidado->sum('alertas');

        return view('consolidado.index', compact(
            'consolidado','mes','anio','meses',
            'totalProgramadas','totalReconocidas','totalConExceso','totalNovedades','totalAlertas'
        ));
    }

    // ── Consolidado anual: todos los médicos × 12 meses ─────────

    public function anual(Request $request)
    {
        $anio  = (int)($request->anio ?? now()->year);
        $anios = range(now()->year - 2, now()->year + 2);
        $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        // Una sola consulta: horas reconocidas por médico × mes
        $filas = \Illuminate\Support\Facades\DB::table('turno_medicos')
            ->join('medicos','medicos.id','=','turno_medicos.medico_id')
            ->where('medicos.activo', true)
            ->whereYear('turno_medicos.fecha', $anio)
            ->where('turno_medicos.fue_laborado', true)
            ->selectRaw('
                medicos.id,
                medicos.nombre,
                medicos.apellido,
                MONTH(turno_medicos.fecha) as mes,
                SUM(COALESCE(turno_medicos.horas_reconocidas, turno_medicos.horas_total)) as horas
            ')
            ->groupBy('medicos.id','medicos.nombre','medicos.apellido',
                      \Illuminate\Support\Facades\DB::raw('MONTH(turno_medicos.fecha)'))
            ->orderBy('medicos.nombre')
            ->get();

        // Pivotar: [medico_id => [nombre, meses[1..12]]]
        $matriz  = [];
        $totales = array_fill(1, 12, 0);   // total de horas por mes

        foreach ($filas as $f) {
            if (!isset($matriz[$f->id])) {
                $matriz[$f->id] = [
                    'nombre' => trim($f->nombre . ' ' . $f->apellido),
                    'meses'  => array_fill(1, 12, 0),
                    'total'  => 0,
                ];
            }
            $matriz[$f->id]['meses'][$f->mes]  = (float)$f->horas;
            $matriz[$f->id]['total']           += (float)$f->horas;
            $totales[$f->mes]                  += (float)$f->horas;
        }

        // Médicos activos que no tienen ningún turno en el año (mostrarlos en 0)
        $medicosActivos = \App\Models\Medico::where('activo', true)->orderBy('nombre')->get();
        foreach ($medicosActivos as $m) {
            if (!isset($matriz[$m->id])) {
                $matriz[$m->id] = [
                    'nombre' => $m->nombre_completo,
                    'meses'  => array_fill(1, 12, 0),
                    'total'  => 0,
                ];
            }
        }

        uasort($matriz, fn($a, $b) => $b['total'] <=> $a['total']); // ordenar por total desc

        return view('consolidado.anual', compact('anio','anios','meses','matriz','totales'));
    }

    // ── Excel: Consolidado mensual por médico ────────────────────

    public function descargarConsolidado(Request $request)
    {
        $mes  = (int)($request->mes  ?? now()->month);
        $anio = (int)($request->anio ?? now()->year);

        $spreadsheet = new Spreadsheet();
        $this->hojaConsolidadoMedicos($spreadsheet, $mes, $anio);
        $this->hojaConsolidadoUcis($spreadsheet, $mes, $anio);
        $this->hojaAlertas($spreadsheet, $mes, $anio);
        $this->hojaNovedades($spreadsheet, $mes, $anio);

        AuditoriaSistema::registrar(
            'DESCARGAR_CONSOLIDADO', 'reportes', null, null,
            null, ['mes'=>$mes,'anio'=>$anio],
            "Descarga consolidado {$mes}/{$anio}", Auth::user()->name
        );

        return $this->enviarExcel($spreadsheet, "Consolidado_UCI_{$mes}_{$anio}.xlsx");
    }

    // ── Excel: Cuadro de turnos completo ────────────────────────

    public function descargarCuadro(Request $request)
    {
        $mes  = (int)($request->mes  ?? now()->month);
        $anio = (int)($request->anio ?? now()->year);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $ucis = Uci::where('activa', true)->orderBy('nombre')->get();
        foreach ($ucis as $uci) {
            $this->hojaCuadroUci($spreadsheet, $uci, $mes, $anio);
        }

        $this->hojaConsolidadoMedicos($spreadsheet, $mes, $anio);
        $this->hojaAlertas($spreadsheet, $mes, $anio);
        $this->hojaNovedades($spreadsheet, $mes, $anio);

        AuditoriaSistema::registrar(
            'DESCARGAR_CUADRO_TURNOS', 'reportes', null, null,
            null, ['mes'=>$mes,'anio'=>$anio],
            "Descarga cuadro turnos {$mes}/{$anio}", Auth::user()->name
        );

        return $this->enviarExcel($spreadsheet, "CuadroTurnos_UCI_{$mes}_{$anio}.xlsx");
    }

    // ── Hojas del Spreadsheet ────────────────────────────────────

    private function hojaConsolidadoMedicos(Spreadsheet $ss, int $mes, int $anio): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Consolidado Médicos');

        $nombreMes = Carbon::create($anio, $mes, 1)->locale('es')->monthName;
        $headers   = [
            'Médico','Documento','H.Programadas','H.Reconocidas',
            'M','T','MT','N','MTN','H.Diurnas','H.Nocturnas',
            'Domingos','Fines de Semana','UCIs','Estado Carga','Alertas','Novedades'
        ];

        // Título
        $sheet->setCellValue([1,1], "CONSOLIDADO MENSUAL — {$nombreMes} {$anio}");
        $this->estiloTitulo($sheet, 'A1', count($headers));

        // Cabeceras
        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col+1, 2], $h);
        }
        $this->estiloEncabezado($sheet, 2, count($headers));

        $consolidado = $this->horaService->consolidadoMensual($mes, $anio);
        $fila        = 3;

        foreach ($consolidado as $row) {
            $m       = $row['medico'];
            $estado  = $row['estado_carga'];
            $valores = [
                $m->nombre_completo, $m->documento ?? '—',
                $row['horas_programadas'], $row['horas_reconocidas'],
                $row['turnos_M'], $row['turnos_T'], $row['turnos_MT'],
                $row['turnos_N'], $row['turnos_MTN'],
                $row['horas_diurnas'], $row['horas_nocturnas'],
                $row['total_domingos'], $row['total_fines_semana'],
                $row['ucis_trabajadas'], ucfirst($estado),
                $row['alertas'], $row['novedades'],
            ];

            foreach ($valores as $col => $val) {
                $sheet->setCellValue([$col+1, $fila], $val);
            }

            // Color por estado de carga
            $bgColor = match($estado) {
                'exceso'   => 'FFDDDD',
                'bajo'     => 'FFF9C4',
                default    => 'F0F8F0',
            };
            $sheet->getStyle("A{$fila}:Q{$fila}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($bgColor);

            $fila++;
        }

        // Fila de totales
        $sheet->setCellValue([1, $fila], 'TOTALES');
        $sheet->setCellValue([3, $fila], $consolidado->sum('horas_programadas'));
        $sheet->setCellValue([4, $fila], $consolidado->sum('horas_reconocidas'));
        $this->estiloEncabezado($sheet, $fila, count($headers));

        $sheet->getColumnDimensionByColumn(1)->setWidth(30);
        $sheet->getColumnDimensionByColumn(2)->setWidth(15);
        for ($c = 3; $c <= count($headers); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setWidth(12);
        }
    }

    private function hojaCuadroUci(Spreadsheet $ss, Uci $uci, int $mes, int $anio): void
    {
        $diasEnMes  = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $nombreMes  = Carbon::create($anio, $mes, 1)->locale('es')->monthName;

        $sheet = $ss->createSheet();
        $sheet->setTitle(substr($uci->codigo ?? $uci->nombre, 0, 31));

        // Título
        $sheet->setCellValue([1,1], "{$uci->nombre} — {$nombreMes} {$anio}");
        $this->estiloTitulo($sheet, 'A1', $diasEnMes + 3);

        // Fila 2: cabecera días
        $sheet->setCellValue([1,2], 'Médico');
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $fecha   = Carbon::create($anio, $mes, $d);
            $letraDia= mb_substr(ucfirst($fecha->locale('es')->dayName), 0, 2);
            $sheet->setCellValue([$d+1, 2], $letraDia);
            $sheet->setCellValue([$d+1, 3], $d);
        }
        $sheet->setCellValue([$diasEnMes+2, 2], 'Total H');
        $this->estiloEncabezado($sheet, 2, $diasEnMes + 2);
        $this->estiloEncabezado($sheet, 3, $diasEnMes + 2);

        // Turnos de la UCI
        $archivos = ArchivoCargado::where('procesado', true)
            ->where('anio', $anio)->where('mes', $mes)->pluck('id');

        $turnos = TurnoMedico::whereIn('archivo_id', $archivos)
            ->where('uci_id', $uci->id)
            ->with('medico')->orderBy('medico_id')->orderBy('dia_numero')
            ->get()->groupBy('medico_id');

        $fila = 4;
        foreach ($turnos as $medicoId => $ts) {
            $medico = $ts->first()->medico;
            $sheet->setCellValue([1, $fila], $medico?->nombre_completo ?? '—');

            $totalH = 0;
            foreach ($ts as $t) {
                $col    = $t->dia_numero + 1;
                $codigo = $t->codigo_turno ?: '—';
                $sheet->setCellValue([$col, $fila], $codigo);
                $totalH += $t->horas_reconocidas ?? $t->horas_total ?? 0;

                // Color por tipo de turno
                $rgb = match(strtoupper($codigo)) {
                    'M'   => 'BBDEFB', 'T'  => 'C8E6C9', 'MT' => 'FFE0B2',
                    'N'   => 'D1C4E9', 'MTN'=> 'F48FB1', 'MN' => 'FCE4EC',
                    default => 'FFFFFF',
                };
                $sheet->getStyle([$col, $fila])
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($rgb);
            }

            $sheet->setCellValue([$diasEnMes + 2, $fila], $totalH);
            $fila++;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        for ($c = 2; $c <= $diasEnMes + 2; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setWidth(5);
        }
    }

    private function hojaConsolidadoUcis(Spreadsheet $ss, int $mes, int $anio): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Por UCI');

        $ucis    = Uci::where('activa', true)->orderBy('nombre')->get();
        $archivos = ArchivoCargado::where('procesado',true)->where('mes',$mes)->where('anio',$anio)->pluck('id');

        $headers = ['UCI','Total Médicos','Total H Programadas','Total H Reconocidas','Turnos M','Turnos T','Turnos MT','Turnos N','Turnos MTN'];
        $sheet->setCellValue([1,1], 'Consolidado por UCI');
        $this->estiloTitulo($sheet, 'A1', count($headers));
        foreach ($headers as $i => $h) { $sheet->setCellValue([$i+1,2], $h); }
        $this->estiloEncabezado($sheet, 2, count($headers));

        $fila = 3;
        foreach ($ucis as $uci) {
            $ts = TurnoMedico::whereIn('archivo_id', $archivos)->where('uci_id', $uci->id)->get();
            $sheet->setCellValue([1,$fila], $uci->nombre);
            $sheet->setCellValue([2,$fila], $ts->pluck('medico_id')->unique()->count());
            $sheet->setCellValue([3,$fila], $ts->sum('horas_total'));
            $sheet->setCellValue([4,$fila], $ts->sum(fn($t)=>$t->horas_reconocidas??$t->horas_total??0));
            $sheet->setCellValue([5,$fila], $ts->where('codigo_turno','M')->count());
            $sheet->setCellValue([6,$fila], $ts->where('codigo_turno','T')->count());
            $sheet->setCellValue([7,$fila], $ts->where('codigo_turno','MT')->count());
            $sheet->setCellValue([8,$fila], $ts->where('codigo_turno','N')->count());
            $sheet->setCellValue([9,$fila], $ts->where('codigo_turno','MTN')->count());
            $fila++;
        }
        $sheet->getColumnDimension('A')->setWidth(30);
        for ($c=2;$c<=count($headers);$c++) $sheet->getColumnDimensionByColumn($c)->setWidth(14);
    }

    private function hojaAlertas(Spreadsheet $ss, int $mes, int $anio): void
    {
        $sheet   = $ss->createSheet();
        $sheet->setTitle('Alertas');
        $headers = ['Médico','UCI','Fecha','Tipo','Prioridad','Mensaje','Estado'];
        $sheet->setCellValue([1,1], 'Alertas del Mes');
        $this->estiloTitulo($sheet,'A1', count($headers));
        foreach ($headers as $i=>$h) { $sheet->setCellValue([$i+1,2],$h); }
        $this->estiloEncabezado($sheet,2,count($headers));

        $alertas = AlertaTurno::with(['medico','uci'])
            ->whereYear('created_at',$anio)->whereMonth('created_at',$mes)
            ->orderBy('prioridad')->get();

        $fila = 3;
        foreach ($alertas as $a) {
            $sheet->setCellValue([1,$fila], $a->medico?->nombre_completo ?? '—');
            $sheet->setCellValue([2,$fila], $a->uci?->nombre ?? '—');
            $sheet->setCellValue([3,$fila], $a->fecha_turno?->format('d/m/Y') ?? '—');
            $sheet->setCellValue([4,$fila], AlertaTurno::TIPOS[$a->tipo] ?? $a->tipo);
            $sheet->setCellValue([5,$fila], strtoupper($a->prioridad));
            $sheet->setCellValue([6,$fila], $a->mensaje);
            $sheet->setCellValue([7,$fila], ucfirst($a->estado));
            $fila++;
        }
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(60);
    }

    private function hojaNovedades(Spreadsheet $ss, int $mes, int $anio): void
    {
        $sheet   = $ss->createSheet();
        $sheet->setTitle('Novedades');
        $headers = ['Médico','UCI','Fecha','Tipo','Descripción','Horas Afectadas','Resta Horas','Estado'];
        $sheet->setCellValue([1,1],'Novedades del Mes');
        $this->estiloTitulo($sheet,'A1',count($headers));
        foreach ($headers as $i=>$h) { $sheet->setCellValue([$i+1,2],$h); }
        $this->estiloEncabezado($sheet,2,count($headers));

        $novedades = Novedad::with(['medico','uci'])
            ->whereYear('fecha',$anio)->whereMonth('fecha',$mes)
            ->orderBy('fecha')->get();

        $fila = 3;
        foreach ($novedades as $n) {
            $sheet->setCellValue([1,$fila], $n->medico?->nombre_completo ?? '—');
            $sheet->setCellValue([2,$fila], $n->uci?->nombre ?? '—');
            $sheet->setCellValue([3,$fila], $n->fecha->format('d/m/Y'));
            $sheet->setCellValue([4,$fila], $n->label_tipo);
            $sheet->setCellValue([5,$fila], $n->descripcion ?? '—');
            $sheet->setCellValue([6,$fila], $n->horas_afectadas);
            $sheet->setCellValue([7,$fila], $n->resta_horas ? 'Sí' : 'No');
            $sheet->setCellValue([8,$fila], ucfirst($n->estado));
            $fila++;
        }
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(50);
    }

    // ── Helpers de estilo ────────────────────────────────────────

    private function estiloTitulo($sheet, string $celda, int $cols): void
    {
        $colFin = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);
        $fila   = (int)filter_var($celda, FILTER_SANITIZE_NUMBER_INT);
        $sheet->mergeCells("A{$fila}:{$colFin}{$fila}");
        $sheet->getStyle("A{$fila}")->applyFromArray([
            'font'      => ['bold'=>true,'size'=>14,'color'=>['rgb'=>'1a2340']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'dbeafe']],
        ]);
        $sheet->getRowDimension($fila)->setRowHeight(24);
    }

    private function estiloEncabezado($sheet, int $fila, int $cols): void
    {
        $colFin = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);
        $sheet->getStyle("A{$fila}:{$colFin}{$fila}")->applyFromArray([
            'font'      => ['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1e4080']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($fila)->setRowHeight(18);
    }

    private function enviarExcel(Spreadsheet $ss, string $nombre)
    {
        $writer = new Xlsx($ss);
        $tmp    = tempnam(sys_get_temp_dir(), 'uci_');

        $writer->save($tmp);

        return response()->download($tmp, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
