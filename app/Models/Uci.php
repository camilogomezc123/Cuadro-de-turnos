<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uci extends Model
{
    protected $fillable = ['nombre', 'codigo', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    public function medicos(): HasMany
    {
        return $this->hasMany(Medico::class);
    }

    public function turnoMedicos(): HasMany
    {
        return $this->hasMany(TurnoMedico::class);
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(IndicadorUci::class);
    }
}
