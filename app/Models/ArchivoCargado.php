<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchivoCargado extends Model
{
    protected $table = 'archivos_cargados';

    protected $fillable = [
        'nombre_archivo', 'ruta', 'mes', 'anio',
        'procesado', 'total_medicos', 'total_turnos',
        'errores', 'advertencias',
    ];

    protected $casts = [
        'procesado' => 'boolean',
        'errores'   => 'array',
        'advertencias' => 'array',
    ];

    public function turnoMedicos(): HasMany
    {
        return $this->hasMany(TurnoMedico::class, 'archivo_id');
    }

    public function indicadorMedicos(): HasMany
    {
        return $this->hasMany(IndicadorMedico::class, 'archivo_id');
    }

    public function indicadorUcis(): HasMany
    {
        return $this->hasMany(IndicadorUci::class, 'archivo_id');
    }

    public function getNombreMesAttribute(): string
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        return $meses[$this->mes] ?? '';
    }
}
