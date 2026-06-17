<?php

namespace App\Repositories;

use App\Models\ArchivoCargado;
use App\Models\IndicadorMedico;
use App\Models\IndicadorUci;
use App\Models\Medico;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    public function getResumenGeneral(?int $archivoId = null): array
    {
        $query = IndicadorMedico::query();
        if ($archivoId) $query->where('archivo_id', $archivoId);

        $totalMedicos   = $query->distinct('medico_id')->count('medico_id');
        $totalHoras     = $query->sum('total_horas');
        $horasNocturnas = $query->sum('horas_nocturnas');
        $promedioHoras  = $totalMedicos > 0 ? round($totalHoras / $totalMedicos, 1) : 0;

        $queryUci = IndicadorUci::query();
        if ($archivoId) $queryUci->where('archivo_id', $archivoId);
        $coberturaMensualProm = round($queryUci->avg('cobertura_mensual') ?? 0, 1);
        $coberturaFindeProm   = round($queryUci->avg('cobertura_fin_semana') ?? 0, 1);

        return compact('totalMedicos','totalHoras','horasNocturnas','promedioHoras','coberturaMensualProm','coberturaFindeProm');
    }

    public function getHorasPorUci(?int $archivoId = null): array
    {
        $query = IndicadorUci::with('uci')->orderByDesc('horas_totales');
        if ($archivoId) $query->where('archivo_id', $archivoId);

        return $query->get()->map(fn($i) => [
            'uci'   => $i->uci->nombre,
            'horas' => $i->horas_totales,
            'medicos' => $i->num_especialistas,
        ])->toArray();
    }

    public function getDistribucionTurnos(?int $archivoId = null): array
    {
        $query = IndicadorMedico::query();
        if ($archivoId) $query->where('archivo_id', $archivoId);

        return [
            'M'  => $query->sum('turnos_m'),
            'T'  => $query->sum('turnos_t'),
            'MT' => $query->sum('turnos_mt'),
            'N'  => $query->sum('turnos_n'),
        ];
    }

    public function getRankingMedicos(?int $archivoId = null, int $limit = 15): array
    {
        $query = IndicadorMedico::with(['medico', 'uci'])
            ->orderByDesc('total_horas')
            ->limit($limit);
        if ($archivoId) $query->where('archivo_id', $archivoId);

        return $query->get()->map(fn($i) => [
            'nombre' => $i->medico->nombre,
            'uci'    => $i->uci->nombre,
            'horas'  => $i->total_horas,
            'pct'    => $i->porcentaje_ocupacion,
        ])->toArray();
    }

    public function getCoberturaSemanal(?int $archivoId = null): array
    {
        $query = DB::table('turno_medicos')
            ->selectRaw('dia_semana, SUM(horas_total) as total_horas, COUNT(*) as num_turnos')
            ->where('horas_total', '>', 0)
            ->groupBy('dia_semana');

        if ($archivoId) $query->where('archivo_id', $archivoId);

        $orden = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
        $resultado = $query->get()->keyBy('dia_semana');

        return collect($orden)->map(fn($dia) => [
            'dia'        => ucfirst($dia),
            'horas'      => $resultado[$dia]->total_horas ?? 0,
            'num_turnos' => $resultado[$dia]->num_turnos ?? 0,
        ])->toArray();
    }

    public function getUltimosArchivos(int $limit = 5): array
    {
        return ArchivoCargado::orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
