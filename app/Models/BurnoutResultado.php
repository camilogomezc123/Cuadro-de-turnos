<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BurnoutResultado extends Model
{
    protected $table = 'burnout_resultados';

    protected $fillable = [
        'encuesta_id','medico_id','periodo_evaluado',
        'puntaje_agotamiento_emocional','clasificacion_agotamiento_emocional',
        'puntaje_despersonalizacion','clasificacion_despersonalizacion',
        'puntaje_realizacion_personal','clasificacion_realizacion_personal',
        'burnout_positivo','burnout_severo',
        'horas_programadas_mes','turnos_nocturnos','fines_semana_trabajados','supera_200h',
    ];

    protected $casts = [
        'burnout_positivo' => 'boolean',
        'burnout_severo'   => 'boolean',
        'supera_200h'      => 'boolean',
    ];

    public function medico(): BelongsTo   { return $this->belongsTo(Medico::class); }
    public function encuesta(): BelongsTo { return $this->belongsTo(BurnoutEncuesta::class, 'encuesta_id'); }
    public function alertas()             { return $this->hasMany(BurnoutAlerta::class, 'resultado_id'); }

    public function getBadgeAeAttribute(): string
    {
        return match($this->clasificacion_agotamiento_emocional) {
            'alto'     => 'danger',
            'moderado' => 'warning',
            default    => 'success',
        };
    }

    public function getBadgeDpAttribute(): string
    {
        return match($this->clasificacion_despersonalizacion) {
            'alto'     => 'danger',
            'moderado' => 'warning',
            default    => 'success',
        };
    }

    public function getBadgeRpAttribute(): string
    {
        return match($this->clasificacion_realizacion_personal) {
            'baja'     => 'danger',
            'moderada' => 'warning',
            default    => 'success',
        };
    }
}
