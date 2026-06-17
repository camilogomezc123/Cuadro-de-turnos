<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Repositories\MedicoRepository;
use App\Repositories\UciRepository;
use Illuminate\Http\Request;

class MedicoController extends Controller
{
    public function __construct(
        private MedicoRepository $medicoRepo,
        private UciRepository $uciRepo,
    ) {}

    public function index(Request $request)
    {
        $archivos  = ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = $request->get('archivo_id', $archivos->first()?->id);
        $uciId     = $request->get('uci_id');
        $ucis      = $this->uciRepo->listar();

        $medicos = $this->medicoRepo->listar($uciId ?: null, $archivoId);

        return view('medicos.index', compact('medicos', 'ucis', 'archivos', 'archivoId', 'uciId'));
    }

    public function show(Request $request, Medico $medico)
    {
        $archivos  = ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = $request->get('archivo_id', $archivos->first()?->id);
        $archivo   = $archivos->find($archivoId);

        $indicador = null;
        $turnos    = collect();

        if ($archivo) {
            $indicador = $this->medicoRepo->getIndicadoresMedico($medico->id, $archivo->mes, $archivo->anio);
            $turnos    = $this->medicoRepo->getTurnosMedico($medico->id, $archivo->mes, $archivo->anio);
        }

        $historial = $this->medicoRepo->getHistorialMedico($medico->id);

        return view('medicos.show', compact('medico', 'indicador', 'turnos', 'archivos', 'archivo', 'historial'));
    }
}
