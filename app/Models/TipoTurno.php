<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoTurno extends Model
{
    protected $table = 'tipos_turno';

    protected $fillable = [
        'codigo', 'nombre', 'hora_inicio', 'hora_fin',
        'horas_diurnas', 'horas_nocturnas', 'horas_total',
        'color_hex', 'color_clase',
        'es_ausencia', 'cubre_manana', 'cubre_tarde', 'cubre_noche',
        'solo_finde', 'activo',
    ];

    protected $casts = [
        'es_ausencia'   => 'boolean',
        'cubre_manana'  => 'boolean',
        'cubre_tarde'   => 'boolean',
        'cubre_noche'   => 'boolean',
        'solo_finde'    => 'boolean',
        'activo'        => 'boolean',
    ];

    public static function buscarPorCodigo(string $codigo): ?self
    {
        return static::where('codigo', strtoupper(trim($codigo)))->first();
    }
}
