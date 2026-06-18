<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\Uci;
use App\Models\TurnoMedico;
use App\Models\Medico;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function index(Request $request)
    {
        $ucis     = Uci::orderBy('nombre')->get();
        $archivos = ArchivoCargado::where('procesado', true)
                        ->orderByDesc('anio')->orderByDesc('mes')->get();

        $uciId     = $request->get('uci_id', $ucis->first()?->id);
        $archivoId = $request->get('archivo_id', $archivos->first()?->id);
        $uci       = $ucis->find($uciId);
        $archivo   = $archivos->find($archivoId);

        $grilla        = [];
        $medicos       = collect();
        $diasDelMes    = 0;
        $primerDiaDow  = 0;

        if ($archivo && $uciId) {
            $diasDelMes   = cal_days_in_month(CAL_GREGORIAN, $archivo->mes, $archivo->anio);
            $primerDia    = Carbon::create($archivo->anio, $archivo->mes, 1);
            $primerDiaDow = ($primerDia->dayOfWeek === 0) ? 6 : $primerDia->dayOfWeek - 1; // 0=Lun

            // Obtener todos los turnos del mes/UCI
            $turnos = TurnoMedico::where('archivo_id', $archivoId)
                ->where('uci_id', $uciId)
                ->with('medico')
                ->orderBy('fecha')
                ->get();

            // Agrupar: medicoId → dia → turno
            $medicoIds = $turnos->pluck('medico_id')->unique();
            $medicos   = Medico::whereIn('id', $medicoIds)->orderBy('nombre')->get();

            foreach ($medicos as $medico) {
                $grilla[$medico->id] = [];
                for ($d = 1; $d <= $diasDelMes; $d++) {
                    $grilla[$medico->id][$d] = '';
                }
            }
            foreach ($turnos as $t) {
                $dia = (int) Carbon::parse($t->fecha)->format('d');
                if (isset($grilla[$t->medico_id])) {
                    $grilla[$t->medico_id][$dia] = $t->codigo_turno;
                }
            }
        }

        // Navegación mes anterior / siguiente
        $mesAnteriorArchivo = null;
        $mesSiguienteArchivo = null;
        if ($archivo) {
            $current = $archivo->anio * 12 + $archivo->mes;
            $mesAnteriorArchivo = $archivos
                ->filter(fn($a) => ($a->anio * 12 + $a->mes) < $current)
                ->sortByDesc(fn($a) => $a->anio * 12 + $a->mes)
                ->first();
            $mesSiguienteArchivo = $archivos
                ->filter(fn($a) => ($a->anio * 12 + $a->mes) > $current)
                ->sortBy(fn($a) => $a->anio * 12 + $a->mes)
                ->first();
        }

        return view('calendario.index', compact(
            'ucis', 'archivos', 'uci', 'uciId', 'archivo', 'archivoId',
            'grilla', 'medicos', 'diasDelMes', 'primerDiaDow',
            'mesAnteriorArchivo', 'mesSiguienteArchivo'
        ));
    }

    public function descargarExcel(Request $request)
    {
        $archivoId = $request->archivo_id;
        $uciId     = $request->uci_id;
        $archivo   = ArchivoCargado::findOrFail($archivoId);
        $uci       = Uci::findOrFail($uciId);

        $diasDelMes = cal_days_in_month(CAL_GREGORIAN, $archivo->mes, $archivo->anio);
        $turnos     = TurnoMedico::where('archivo_id', $archivoId)
            ->where('uci_id', $uciId)
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
        $sheet->setCellValue([1, 1], $uci->nombre . ' — ' . $nombresMeses[$archivo->mes] . ' ' . $archivo->anio);
        $sheet->mergeCells([1,1, $diasDelMes+1, 1]);
        $sheet->getStyle([1,1,$diasDelMes+1,1])->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => 'center'],
        ]);

        // Fila 2: Iniciales días semana
        $diasSemana = ['L','M','M','J','V','S','D'];
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $fecha = Carbon::create($archivo->anio, $archivo->mes, $d);
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
        $filename = "Cuadro_Turnos_{$uci->codigo}_{$nombresMeses[$archivo->mes]}_{$archivo->anio}.xlsx";

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
