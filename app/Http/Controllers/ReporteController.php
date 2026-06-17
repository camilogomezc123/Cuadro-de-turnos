<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Repositories\UciRepository;
use App\Services\ReporteExcelService;
use App\Services\ReportePdfService;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function __construct(
        private ReporteExcelService $excelSvc,
        private ReportePdfService $pdfSvc,
        private UciRepository $uciRepo,
    ) {}

    public function index(Request $request)
    {
        $archivos  = ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = $request->get('archivo_id', $archivos->first()?->id);
        $ucis      = $this->uciRepo->listar();

        return view('reportes.index', compact('archivos', 'archivoId', 'ucis'));
    }

    public function exportarExcel(Request $request)
    {
        $request->validate(['archivo_id' => 'required|exists:archivos_cargados,id']);

        $archivo = ArchivoCargado::findOrFail($request->archivo_id);
        $ruta    = $this->excelSvc->generarConsolidadoGeneral($archivo);

        return response()->download($ruta, "Consolidado_UCI_{$archivo->mes}_{$archivo->anio}.xlsx")
            ->deleteFileAfterSend();
    }

    public function exportarPdf(Request $request)
    {
        $request->validate(['archivo_id' => 'required|exists:archivos_cargados,id']);

        $archivo = ArchivoCargado::findOrFail($request->archivo_id);
        $ruta    = $this->pdfSvc->generarConsolidadoGeneral($archivo);

        return response()->download($ruta, "Consolidado_UCI_{$archivo->mes}_{$archivo->anio}.pdf")
            ->deleteFileAfterSend();
    }

    public function exportarMedicoExcel(Request $request)
    {
        $request->validate([
            'medico_id'  => 'required|exists:medicos,id',
            'archivo_id' => 'required|exists:archivos_cargados,id',
        ]);

        $archivo = ArchivoCargado::findOrFail($request->archivo_id);
        $ruta    = $this->excelSvc->generarReporteMedico($request->medico_id, $archivo->mes, $archivo->anio);

        $medico = Medico::findOrFail($request->medico_id);
        return response()->download($ruta, "Reporte_{$medico->nombre}_{$archivo->mes}_{$archivo->anio}.xlsx")
            ->deleteFileAfterSend();
    }

    public function exportarMedicoPdf(Request $request)
    {
        $request->validate([
            'medico_id'  => 'required|exists:medicos,id',
            'archivo_id' => 'required|exists:archivos_cargados,id',
        ]);

        $archivo = ArchivoCargado::findOrFail($request->archivo_id);
        $ruta    = $this->pdfSvc->generarReporteMedico($request->medico_id, $archivo->mes, $archivo->anio);

        $medico = Medico::findOrFail($request->medico_id);
        return response()->download($ruta, "Reporte_{$medico->nombre}_{$archivo->mes}_{$archivo->anio}.pdf")
            ->deleteFileAfterSend();
    }
}
