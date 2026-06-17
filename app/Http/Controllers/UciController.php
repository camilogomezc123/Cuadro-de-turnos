<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\Uci;
use App\Repositories\UciRepository;
use Illuminate\Http\Request;

class UciController extends Controller
{
    public function __construct(private UciRepository $uciRepo) {}

    public function index(Request $request)
    {
        $archivos  = ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = $request->get('archivo_id', $archivos->first()?->id);

        $ucis        = $this->uciRepo->listar();
        $indicadores = $this->uciRepo->getIndicadoresTodas($archivoId);

        return view('ucis.index', compact('ucis', 'indicadores', 'archivos', 'archivoId'));
    }

    public function show(Request $request, Uci $uci)
    {
        $archivos  = ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = $request->get('archivo_id', $archivos->first()?->id);

        $indicador = $this->uciRepo->getIndicadoresUci($uci->id, $archivoId);
        $medicos   = $this->uciRepo->getMedicosUci($uci->id, $archivoId);
        $historial = $this->uciRepo->getHistorialUci($uci->id);

        return view('ucis.show', compact('uci', 'indicador', 'medicos', 'archivos', 'archivoId', 'historial'));
    }
}
