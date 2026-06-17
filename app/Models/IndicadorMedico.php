<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicadorMedico extends Model
{
    protected $table = 'indicador_medicos';

    protected $fillable = [
        'archivo_id', 'medico_id', 'uci_id', 'mes', 'anio',
        'total_horas', 'horas_diurnas', 'horas_nocturnas',
        'turnos_m', 'turnos_t', 'turnos_mt', 'turnos_n',
        'turnos_fin_semana', 'turnos_domingo',
        'promedio_semanal', 'promedio_diario', 'porcentaje_ocupacion',
    ];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function uci(): BelongsTo
    {
        return $this->belongsTo(Uci::class);
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(ArchivoCargado::class, 'archivo_id');
    }
}
