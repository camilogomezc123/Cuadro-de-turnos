<?php

namespace App\Repositories;

use App\Models\IndicadorMedico;
use App\Models\Medico;
use App\Models\TurnoMedico;
use Illuminate\Database\Eloquent\Collection;

class MedicoRepository
{
    public function listar(?int $uciId = null, ?int $archivoId = null): Collection
    {
        $query = Medico::with(['uci', 'indicadores' => function ($q) use ($archivoId) {
            if ($archivoId) $q->where('archivo_id', $archivoId);
        }])->where('activo', true);

        if ($uciId) $query->where('uci_id', $uciId);

        return $query->orderBy('nombre')->get();
    }

    public function getIndicadoresMedico(int $medicoId, ?int $mes = null, ?int $anio = null): ?IndicadorMedico
    {
        $query = IndicadorMedico::with(['medico', 'uci'])
            ->where('medico_id', $medicoId);

        if ($mes)  $query->where('mes', $mes);
        if ($anio) $query->where('anio', $anio);

        return $query->latest()->first();
    }

    public function getTurnosMedico(int $medicoId, int $mes, int $anio): Collection
    {
        return TurnoMedico::where('medico_id', $medicoId)
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get();
    }

    public function getHistorialMedico(int $medicoId): Collection
    {
        return IndicadorMedico::with(['uci'])
            ->where('medico_id', $medicoId)
            ->orderBy('anio', 'desc')
            ->orderBy('mes', 'desc')
            ->get();
    }
}
