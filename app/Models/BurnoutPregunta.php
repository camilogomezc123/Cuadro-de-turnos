<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BurnoutPregunta extends Model
{
    protected $table = 'burnout_preguntas';

    protected $fillable = [
        'encuesta_id','texto_pregunta','dimension','orden','activa','obligatoria',
    ];

    protected $casts = [
        'activa'     => 'boolean',
        'obligatoria'=> 'boolean',
    ];

    public function encuesta(): BelongsTo
    {
        return $this->belongsTo(BurnoutEncuesta::class, 'encuesta_id');
    }
}
