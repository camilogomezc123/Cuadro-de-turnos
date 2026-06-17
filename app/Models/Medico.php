<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medico extends Model
{
    protected $fillable = ['nombre', 'uci_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function uci(): BelongsTo
    {
        return $this->belongsTo(Uci::class);
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoMedico::class);
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(IndicadorMedico::class);
    }
}
