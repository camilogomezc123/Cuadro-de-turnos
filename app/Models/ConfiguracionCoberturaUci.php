<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionCoberturaUci extends Model
{
    protected $table = 'configuracion_cobertura_uci';

    protected $fillable = [
        'uci_id',
        'min_medicos_manana', 'min_medicos_tarde',
        'min_medicos_noche', 'min_medicos_finde',
        'horas_minimas_mensual', 'horas_maximas_mensual',
        'horas_maximas_semanales', 'permite_mtn',
    ];

    protected $casts = [
        'permite_mtn' => 'boolean',
    ];

    public function uci()
    {
        return $this->belongsTo(Uci::class);
    }

    /** Obtiene o crea config por defecto para una UCI */
    public static function paraUci(int $uciId): self
    {
        return static::firstOrCreate(
            ['uci_id' => $uciId],
            [
                'min_medicos_manana'      => 1,
                'min_medicos_tarde'       => 1,
                'min_medicos_noche'       => 1,
                'min_medicos_finde'       => 1,
                'horas_minimas_mensual'   => 100,
                'horas_maximas_mensual'   => 240,
                'horas_maximas_semanales' => 60,
                'permite_mtn'             => true,
            ]
        );
    }
}
