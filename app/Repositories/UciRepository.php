<?php

namespace App\Repositories;

use App\Models\IndicadorUci;
use App\Models\Uci;
use Illuminate\Database\Eloquent\Collection;

class UciRepository
{
    public function listar(): Collection
    {
        return Uci::where('activa', true)->orderBy('nombre')->get();
    }

    public function getIndicadoresUci(int $uciId, ?int $archivoId = null): ?IndicadorUci
    {
        $query = IndicadorUci::where('uci_id', $uciId);
        if ($archivoId) $query->where('archivo_id', $archivoId);
        return $query->latest()->first();
    }

    public function getIndicadoresTodas(?int $archivoId = null): Collection
    {
        $query = IndicadorUci::with('uci');
        if ($archivoId) $query->where('archivo_id', $archivoId);
        return $query->get();
    }

    public function getMedicosUci(int $uciId, ?int $archivoId = null): Collection
    {
        $query = \App\Models\IndicadorMedico::with('medico')
            ->where('uci_id', $uciId);
        if ($archivoId) $query->where('archivo_id', $archivoId);
        return $query->orderByDesc('total_horas')->get();
    }

    public function getHistorialUci(int $uciId): Collection
    {
        return IndicadorUci::where('uci_id', $uciId)
            ->orderBy('anio', 'desc')
            ->orderBy('mes', 'desc')
            ->get();
    }
}
