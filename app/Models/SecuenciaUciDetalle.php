<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecuenciaUciDetalle extends Model
{
    protected $table = 'secuencias_uci_detalle';

    protected $fillable = [
        'secuencia_uci_id', 'medico_id', 'dia_semana', 'codigo_turno',
        'es_fin_de_semana', 'orden_rotacion_fin_semana',
        'fecha_inicio_vigencia', 'fecha_fin_vigencia',
    ];

    protected $casts = [
        'es_fin_de_semana'       => 'boolean',
        'fecha_inicio_vigencia'  => 'date',
        'fecha_fin_vigencia'     => 'date',
    ];

    public function secuencia(): BelongsTo
    {
        return $this->belongsTo(SecuenciaUci::class, 'secuencia_uci_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function getNombreDiaAttribute(): string
    {
        return ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'][$this->dia_semana] ?? '';
    }
}
