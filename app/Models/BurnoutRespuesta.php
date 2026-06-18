<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BurnoutRespuesta extends Model
{
    protected $table = 'burnout_respuestas';

    protected $fillable = [
        'encuesta_id','medico_id','pregunta_id','respuesta_valor','periodo_evaluado','fecha_respuesta',
    ];

    public function medico(): BelongsTo    { return $this->belongsTo(Medico::class); }
    public function pregunta(): BelongsTo  { return $this->belongsTo(BurnoutPregunta::class, 'pregunta_id'); }
    public function encuesta(): BelongsTo  { return $this->belongsTo(BurnoutEncuesta::class, 'encuesta_id'); }
}
