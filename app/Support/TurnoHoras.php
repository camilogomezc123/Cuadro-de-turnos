<?php

namespace App\Support;

/**
 * Fuente única de verdad de horas por código de turno.
 *
 * Antes esta tabla estaba duplicada en TurnoService, ExcelParserService,
 * PlantillaService, TurnoEditorController y SecuenciaUciController — cada
 * vez que se agregaba un código (como TN) había que recordar actualizarla
 * en los 5 lugares. Se centraliza aquí para que eso no vuelva a pasar.
 */
final class TurnoHoras
{
    public const TABLA = [
        'M'    => ['diurnas' => 6.0,  'nocturnas' => 0.0,  'total' => 6.0,  'ausencia' => false],
        'T'    => ['diurnas' => 6.0,  'nocturnas' => 0.0,  'total' => 6.0,  'ausencia' => false],
        'MT'   => ['diurnas' => 12.0, 'nocturnas' => 0.0,  'total' => 12.0, 'ausencia' => false],
        'N'    => ['diurnas' => 0.0,  'nocturnas' => 12.0, 'total' => 12.0, 'ausencia' => false],
        'TN'   => ['diurnas' => 6.0,  'nocturnas' => 12.0, 'total' => 18.0, 'ausencia' => false],
        'MTN'  => ['diurnas' => 12.0, 'nocturnas' => 12.0, 'total' => 24.0, 'ausencia' => false],
        'MN'   => ['diurnas' => 6.0,  'nocturnas' => 12.0, 'total' => 18.0, 'ausencia' => false],
        'VAC'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => true],
        'PER'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => true],
        'INC'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => true],
        'LIBRE'=> ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => false],
        ''     => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => false],
    ];

    public const CODIGOS_NOCTURNOS = ['N', 'TN', 'MTN', 'MN'];

    public static function total(string $codigo): float
    {
        return self::TABLA[$codigo]['total'] ?? 0.0;
    }

    public static function diurnas(string $codigo): float
    {
        return self::TABLA[$codigo]['diurnas'] ?? 0.0;
    }

    public static function nocturnas(string $codigo): float
    {
        return self::TABLA[$codigo]['nocturnas'] ?? 0.0;
    }

    public static function esNocturno(string $codigo): bool
    {
        return in_array($codigo, self::CODIGOS_NOCTURNOS, true);
    }

    public static function esAusencia(string $codigo): bool
    {
        return self::TABLA[$codigo]['ausencia'] ?? false;
    }
}
