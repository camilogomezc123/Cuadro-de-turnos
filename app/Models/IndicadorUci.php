<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicadorUci extends Model
{
    protected $table = 'indicador_ucis';

    protected $fillable = [
        'archivo_id', 'uci_id', 'mes', 'anio',
        'num_especialistas', 'horas_totales', 'horas_promedio_medico',
        'cobertura_mensual', 'cobertura_fin_semana', 'cobertura_nocturna',
        'carga_diurna_pct', 'carga_nocturna_pct',
    ];

    public function uci(): BelongsTo
    {
        return $this->belongsTo(Uci::class);
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(ArchivoCargado::class, 'archivo_id');
    }
}
