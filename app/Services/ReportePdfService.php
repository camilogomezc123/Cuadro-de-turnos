<?php

namespace App\Services;

use App\Models\ArchivoCargado;
use App\Models\IndicadorMedico;
use App\Models\IndicadorUci;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportePdfService
{
    public function generarConsolidadoGeneral(ArchivoCargado $archivo): string
    {
        $indicadoresUci = IndicadorUci::with('uci')
            ->where('archivo_id', $archivo->id)
            ->get();

        $indicadoresMedico = IndicadorMedico::with(['medico', 'uci'])
            ->where('archivo_id', $archivo->id)
            ->orderBy('uci_id')
            ->orderByDesc('total_horas')
            ->get();

        $totalHoras     = $indicadoresMedico->sum('total_horas');
        $totalNocturnas = $indicadoresMedico->sum('horas_nocturnas');
        $totalMedicos   = $indicadoresMedico->count();

        $pdf = Pdf::loadView('pdf.consolidado', compact(
            'archivo', 'indicadoresUci', 'indicadoresMedico',
            'totalHoras', 'totalNocturnas', 'totalMedicos'
        ))->setPaper('a4', 'landscape');

        $ruta = storage_path("app/reportes/pdf_consolidado_{$archivo->mes}_{$archivo->anio}.pdf");
        @mkdir(dirname($ruta), 0777, true);
        $pdf->save($ruta);

        return $ruta;
    }

    public function generarReporteMedico(int $medicoId, int $mes, int $anio): string
    {
        $indicador = IndicadorMedico::with(['medico', 'uci'])
            ->where('medico_id', $medicoId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->firstOrFail();

        $turnos = \App\Models\TurnoMedico::where('medico_id', $medicoId)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->orderBy('fecha')
            ->get();

        $pdf = Pdf::loadView('pdf.medico', compact('indicador', 'turnos'))
            ->setPaper('a4', 'portrait');

        $ruta = storage_path("app/reportes/pdf_medico_{$medicoId}_{$mes}_{$anio}.pdf");
        @mkdir(dirname($ruta), 0777, true);
        $pdf->save($ruta);

        return $ruta;
    }
}
