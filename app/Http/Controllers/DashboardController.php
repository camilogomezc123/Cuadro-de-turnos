<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\AlertaTurno;
use App\Models\TurnoMedico;
use App\Repositories\DashboardRepository;
use App\Repositories\UciRepository;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardRepository $dashRepo,
        private UciRepository $uciRepo,
    ) {}

    public function index(Request $request)
    {
        $archivos   = ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId  = $request->get('archivo_id', $archivos->first()?->id);
        $archivo    = $archivos->find($archivoId);

        $resumen            = $this->dashRepo->getResumenGeneral($archivoId);
        $horasPorUci        = $this->dashRepo->getHorasPorUci($archivoId);
        $distribucionTurnos = $this->dashRepo->getDistribucionTurnos($archivoId);
        $rankingMedicos     = $this->dashRepo->getRankingMedicos($archivoId);
        $coberturaSemanal   = $this->dashRepo->getCoberturaSemanal($archivoId);
        $ucis               = $this->uciRepo->listar();

        // KPIs de alertas
        $alertasAbiertas = AlertaTurno::when($archivoId, fn($q) => $q->where('archivo_id', $archivoId))
            ->where('estado', 'abierta')->count();
        $alertasAltas = AlertaTurno::when($archivoId, fn($q) => $q->where('archivo_id', $archivoId))
            ->where('estado', 'abierta')->where('prioridad', 'alta')->count();

        // KPIs de MTN
        $turnosMtn = $archivoId ? TurnoMedico::where('archivo_id', $archivoId)
            ->where('codigo_turno', 'MTN')->count() : 0;

        // Distribución extendida incluyendo MTN
        if ($archivoId) {
            foreach (['MTN', 'MN', 'VAC', 'PER', 'INC'] as $extra) {
                $cnt = TurnoMedico::where('archivo_id', $archivoId)->where('codigo_turno', $extra)->count();
                if ($cnt > 0) $distribucionTurnos[$extra] = $cnt;
            }
        }

        // Ranking de alertas por tipo
        $alertasPorTipo = AlertaTurno::when($archivoId, fn($q) => $q->where('archivo_id', $archivoId))
            ->selectRaw('tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('dashboard.index', compact(
            'archivos', 'archivo', 'archivoId',
            'resumen', 'horasPorUci', 'distribucionTurnos',
            'rankingMedicos', 'coberturaSemanal', 'ucis',
            'alertasAbiertas', 'alertasAltas', 'turnosMtn', 'alertasPorTipo'
        ));
    }
}
