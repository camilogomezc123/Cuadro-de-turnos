<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BurnoutAlerta extends Model
{
    protected $table = 'burnout_alertas';

    protected $fillable = [
        'resultado_id','medico_id','periodo_evaluado',
        'tipo_alerta','descripcion','nivel_riesgo','estado',
    ];

    public function medico(): BelongsTo    { return $this->belongsTo(Medico::class); }
    public function resultado(): BelongsTo { return $this->belongsTo(BurnoutResultado::class, 'resultado_id'); }
}
