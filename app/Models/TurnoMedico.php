<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoMedico extends Model
{
    protected $table = 'turno_medicos';

    protected $fillable = [
        'archivo_id', 'medico_id', 'uci_id', 'fecha',
        'dia_numero', 'dia_semana', 'codigo_turno',
        'horas_diurnas', 'horas_nocturnas', 'horas_total',
        'es_fin_semana', 'es_domingo',
        'fila_excel', 'columna_excel', 'estado_validacion', 'observacion', 'es_codigo_no_oficial',
    ];

    protected $casts = [
        'fecha'               => 'date',
        'es_fin_semana'       => 'boolean',
        'es_domingo'          => 'boolean',
        'es_codigo_no_oficial'=> 'boolean',
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
