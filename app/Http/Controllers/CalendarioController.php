<?php

namespace App\Http\Controllers;

use App\Models\Uci;
use App\Models\TurnoMedico;
use App\Models\Medico;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function index(Request $request)
    {
        $ucis  = Uci::orderBy('nombre')->get();
        $uciId = $request->get('uci_id', $ucis->first()?->id);
        $uci   = $ucis->find($uciId);

        $mes  = (int) $request->get('mes',  now()->month);
        $anio = (int) $request->get('anio', now()->year);

        // Clamp to valid ranges
        $mes  = max(1, min(12, $mes));
        $anio = max(2020, min(2035, $anio));

        $anios        = range(now()->year - 2, now()->year + 2);
        $nombresMeses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                         'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $grilla       = [];
        $medicos      = collect();
        $diasDelMes   = 0;
        $primerDiaDow = 0;

        if ($uciId) {
            $diasDelMes   = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            $primerDia    = Carbon::create($anio, $mes, 1);
            $primerDiaDow = ($primerDia->dayOfWeek === 0) ? 6 : $primerDia->dayOfWeek - 1;

            $fechaInicio = Carbon::create($anio, $mes, 1)->startOfMonth()->toDateString();
            $fechaFin    = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

            $turnos = TurnoMedico::where('uci_id', $uciId)
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->with('medico')
                ->orderBy('fecha')
                ->get();

            $medicoIds = $turnos->pluck('medico_id')->unique();
            $medicos   = Medico::whereIn('id', $medicoIds)->orderBy('nombre')->get();

            foreach ($medicos as $medico) {
                $grilla[$medico->id] = array_fill(1, $diasDelMes, '');
            }
            foreach ($turnos as $t) {
                $dia = (int) Carbon::parse($t->fecha)->format('d');
                if (isset($grilla[$t->medico_id])) {
                    $grilla[$t->medico_id][$dia] = $t->codigo_turno;
                }
            }
        }

        // Navegación mes anterior / siguiente
        $prevMes  = $mes === 1  ? 12 : $mes - 1;
        $prevAnio = $mes === 1  ? $anio - 1 : $anio;
        $nextMes  = $mes === 12 ? 1  : $mes + 1;
        $nextAnio = $mes === 12 ? $anio + 1 : $anio;

        return view('calendario.index', compact(
            'ucis', 'uci', 'uciId',
            'mes', 'anio', 'anios', 'nombresMeses',
            'grilla', 'medicos', 'diasDelMes', 'primerDiaDow',
            'prevMes', 'prevAnio', 'nextMes', 'nextAnio'
        ));
    }

    public function descargarExcel(Request $request)
    {
        $uciId = $request->uci_id;
        $mes   = (int) $request->get('mes',  now()->month);
        $anio  = (int) $request->get('anio', now()->year);
        $uci   = Uci::findOrFail($uciId);

        $diasDelMes  = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $fechaInicio = Carbon::create($anio, $mes, 1)->startOfMonth()->toDateString();
        $fechaFin    = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

        $turnos = TurnoMedico::where('uci_id', $uciId)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->with('medico')
            ->orderBy('fecha')
            ->get();

        $medicoIds = $turnos->pluck('medico_id')->unique();
        $medicos   = Medico::whereIn('id', $medicoIds)->orderBy('nombre')->get();

        $grilla = [];
        foreach ($medicos as $m) {
            $grilla[$m->id] = array_fill(1, $diasDelMes, '');
        }
        foreach ($turnos as $t) {
            $dia = (int) Carbon::parse($t->fecha)->format('d');
            if (isset($grilla[$t->medico_id])) {
                $grilla[$t->medico_id][$dia] = $t->codigo_turno;
            }
        }

        // Generar Excel con PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($uci->codigo, 0, 31));

        $nombresMeses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                         'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        // Fila 1: Título UCI
        $sheet->setCellValue([1, 1], $uci->nombre . ' — ' . $nombresMeses[$mes] . ' ' . $anio);
        $sheet->mergeCells([1,1, $diasDelMes+1, 1]);
        $sheet->getStyle([1,1,$diasDelMes+1,1])->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => 'center'],
        ]);

        // Fila 2: Iniciales días semana
        $diasSemana = ['L','M','M','J','V','S','D'];
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $fecha = Carbon::create($anio, $mes, $d);
            $dow   = ($fecha->dayOfWeek === 0) ? 6 : $fecha->dayOfWeek - 1;
            $sheet->setCellValue([$d+1, 2], $diasSemana[$dow]);
        }
        // Fila 3: Números de día
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $sheet->setCellValue([$d+1, 3], $d);
        }

        // Filas de médicos
        $fila = 4;
        foreach ($medicos as $m) {
            $sheet->setCellValue([1, $fila], $m->nombre);
            for ($d = 1; $d <= $diasDelMes; $d++) {
                $sheet->setCellValue([$d+1, $fila], $grilla[$m->id][$d] ?? '');
            }
            $fila++;
        }

        // Ajustar ancho
        $sheet->getColumnDimensionByColumn(1)->setWidth(28);
        for ($c = 2; $c <= $diasDelMes+1; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setWidth(4);
        }

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "Cuadro_Turnos_{$uci->codigo}_{$nombresMeses[$mes]}_{$anio}.xlsx";

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
