<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BurnoutEncuesta extends Model
{
    protected $table = 'burnout_encuestas';

    protected $fillable = [
        'nombre','descripcion','periodo','activa','permite_posponer',
        'fecha_inicio','fecha_fin','creada_por',
    ];

    protected $casts = [
        'activa'           => 'boolean',
        'permite_posponer' => 'boolean',
        'fecha_inicio'     => 'date',
        'fecha_fin'        => 'date',
    ];

    public function preguntas(): HasMany
    {
        return $this->hasMany(BurnoutPregunta::class, 'encuesta_id')->orderBy('orden');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(BurnoutResultado::class, 'encuesta_id');
    }

    // Calcula el periodo actual en formato 'YYYY-MM' segun el tipo de periodo
    public function periodoActual(): string
    {
        $now = now();
        return match ($this->periodo) {
            'bimestral'   => $now->year . '-' . str_pad((int)ceil($now->month / 2), 2, '0', STR_PAD_LEFT) . 'B',
            'trimestral'  => $now->year . '-T' . (int)ceil($now->month / 3),
            default       => $now->format('Y-m'),
        };
    }

    public static function activa(): ?self
    {
        return static::where('activa', true)->latest()->first();
    }
}
