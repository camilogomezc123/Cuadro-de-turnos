<?php

namespace App\Services;

use App\Models\ArchivoCargado;
use App\Models\IndicadorMedico;
use App\Models\IndicadorUci;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReporteExcelService
{
    private array $coloresUci = ['FF1F4E79','FF2E75B6','FF2E75B6','FF4472C4','FF4472C4','FF70AD47','FF70AD47','FFED7D31','FFA9D18E'];

    public function generarConsolidadoGeneral(ArchivoCargado $archivo): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setTitle("Consolidado UCI - {$archivo->nombre_mes} {$archivo->anio}");

        // Hoja resumen general
        $this->crearHojaResumenGeneral($spreadsheet->getActiveSheet(), $archivo);

        // Hoja por médico
        $hojasMedicos = $spreadsheet->createSheet();
        $hojasMedicos->setTitle('Por Médico');
        $this->crearHojaMedicos($hojasMedicos, $archivo);

        // Hoja por UCI
        $hojaUci = $spreadsheet->createSheet();
        $hojaUci->setTitle('Por UCI');
        $this->crearHojaUcis($hojaUci, $archivo);

        $spreadsheet->setActiveSheetIndex(0);

        $ruta = storage_path("app/reportes/consolidado_{$archivo->mes}_{$archivo->anio}.xlsx");
        @mkdir(dirname($ruta), 0777, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($ruta);

        return $ruta;
    }

    private function crearHojaResumenGeneral($hoja, ArchivoCargado $archivo): void
    {
        $hoja->setTitle('Resumen General');
        $hoja->getColumnDimension('A')->setWidth(35);
        $hoja->getColumnDimension('B')->setWidth(20);
        $hoja->getColumnDimension('C')->setWidth(20);
        $hoja->getColumnDimension('D')->setWidth(20);
        $hoja->getColumnDimension('E')->setWidth(20);
        $hoja->getColumnDimension('F')->setWidth(20);

        // Título
        $hoja->mergeCells('A1:F1');
        $hoja->setCellValue('A1', "CUADRO DE TURNOS UCI — {$archivo->nombre_mes} {$archivo->anio}");
        $hoja->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getRowDimension(1)->setRowHeight(30);

        // Encabezados
        $encabezados = ['UCI', 'Especialistas', 'Horas Totales', 'Horas Prom./Médico', 'Cobertura %', 'Cobertura Nocturna %'];
        $hoja->fromArray([$encabezados], null, 'A3');
        $hoja->getStyle('A3:F3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $indicadores = IndicadorUci::with('uci')
            ->where('archivo_id', $archivo->id)
            ->get();

        $fila = 4;
        foreach ($indicadores as $ind) {
            $hoja->setCellValue("A{$fila}", $ind->uci->nombre);
            $hoja->setCellValue("B{$fila}", $ind->num_especialistas);
            $hoja->setCellValue("C{$fila}", number_format($ind->horas_totales, 1));
            $hoja->setCellValue("D{$fila}", number_format($ind->horas_promedio_medico, 1));
            $hoja->setCellValue("E{$fila}", number_format($ind->cobertura_mensual, 1) . '%');
            $hoja->setCellValue("F{$fila}", number_format($ind->cobertura_nocturna, 1) . '%');

            $hoja->getStyle("A{$fila}:F{$fila}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'fill' => $fila % 2 === 0 ? ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FE']] : [],
            ]);
            $fila++;
        }
    }

    private function crearHojaMedicos($hoja, ArchivoCargado $archivo): void
    {
        $hoja->getColumnDimension('A')->setWidth(35);
        foreach (['B','C','D','E','F','G','H','I','J','K'] as $col) {
            $hoja->getColumnDimension($col)->setWidth(16);
        }

        $encabezados = ['Médico','UCI','Total Horas','H. Diurnas','H. Nocturnas','T. Mañana','T. Tarde','T. M-T','T. Noche','T. F/S','% Ocupación'];
        $hoja->fromArray([$encabezados], null, 'A1');
        $hoja->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $indicadores = IndicadorMedico::with(['medico', 'uci'])
            ->where('archivo_id', $archivo->id)
            ->orderBy('uci_id')
            ->orderByDesc('total_horas')
            ->get();

        $fila = 2;
        foreach ($indicadores as $ind) {
            $hoja->fromArray([[
                $ind->medico->nombre,
                $ind->uci->nombre,
                $ind->total_horas,
                $ind->horas_diurnas,
                $ind->horas_nocturnas,
                $ind->turnos_m,
                $ind->turnos_t,
                $ind->turnos_mt,
                $ind->turnos_n,
                $ind->turnos_fin_semana,
                number_format($ind->porcentaje_ocupacion, 1) . '%',
            ]], null, "A{$fila}");
            $hoja->getStyle("A{$fila}:K{$fila}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'fill' => $fila % 2 === 0 ? ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FE']] : [],
            ]);
            $fila++;
        }
    }

    private function crearHojaUcis($hoja, ArchivoCargado $archivo): void
    {
        $this->crearHojaResumenGeneral($hoja, $archivo);
    }

    public function generarReporteMedico(int $medicoId, int $mes, int $anio): string
    {
        $indicador = IndicadorMedico::with(['medico', 'uci'])
            ->where('medico_id', $medicoId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->firstOrFail();

        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Indicadores Médico');

        $hoja->getColumnDimension('A')->setWidth(30);
        $hoja->getColumnDimension('B')->setWidth(20);

        $hoja->setCellValue('A1', "INDICADORES: {$indicador->medico->nombre}");
        $hoja->setCellValue('A2', "UCI: {$indicador->uci->nombre}");
        $hoja->setCellValue('A3', "Período: {$indicador->mes}/{$indicador->anio}");

        $datos = [
            ['Total Horas Trabajadas', $indicador->total_horas],
            ['Horas Diurnas', $indicador->horas_diurnas],
            ['Horas Nocturnas', $indicador->horas_nocturnas],
            ['Turnos Mañana (M)', $indicador->turnos_m],
            ['Turnos Tarde (T)', $indicador->turnos_t],
            ['Turnos Mañana-Tarde (MT)', $indicador->turnos_mt],
            ['Turnos Noche (N)', $indicador->turnos_n],
            ['Turnos Fin de Semana', $indicador->turnos_fin_semana],
            ['Turnos Domingo', $indicador->turnos_domingo],
            ['Promedio Semanal (hrs)', $indicador->promedio_semanal],
            ['Promedio Diario (hrs)', $indicador->promedio_diario],
            ['% Ocupación', $indicador->porcentaje_ocupacion . '%'],
        ];

        $fila = 5;
        foreach ($datos as [$label, $valor]) {
            $hoja->setCellValue("A{$fila}", $label);
            $hoja->setCellValue("B{$fila}", $valor);
            $fila++;
        }

        $ruta = storage_path("app/reportes/medico_{$medicoId}_{$mes}_{$anio}.xlsx");
        @mkdir(dirname($ruta), 0777, true);

        (new Xlsx($spreadsheet))->save($ruta);
        return $ruta;
    }
}
