<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Novedad extends Model
{
    protected $table = 'novedades';

    protected $fillable = [
        'medico_id', 'turno_id', 'uci_id', 'fecha',
        'tipo_novedad', 'descripcion', 'horas_afectadas', 'resta_horas',
        'usuario_maestro_id', 'visible_para_medico', 'estado',
    ];

    protected $casts = [
        'fecha'              => 'date',
        'resta_horas'        => 'boolean',
        'visible_para_medico'=> 'boolean',
    ];

    const TIPOS = [
        'no_asistencia'     => 'No asistencia a turno',
        'reemplazo_turno'   => 'Reemplazo de turno',
        'cambio_aprobado'   => 'Cambio aprobado',
        'donacion_turno'    => 'Donación de turno',
        'exceso_horas'      => 'Exceso de horas',
        'correccion_manual' => 'Corrección manual',
        'error_programacion'=> 'Error de programación',
        'alerta_12h_habil'  => 'Más de 12h en día hábil',
        'alerta_200h'       => 'Exceso 200 horas mensuales',
        'otro'              => 'Otro',
    ];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoMedico::class, 'turno_id');
    }

    public function uci(): BelongsTo
    {
        return $this->belongsTo(Uci::class);
    }

    public function usuarioMaestro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_maestro_id');
    }

    public function getLabelTipoAttribute(): string
    {
        return self::TIPOS[$this->tipo_novedad] ?? $this->tipo_novedad;
    }

    public function getBadgeEstadoAttribute(): string
    {
        return match($this->estado) {
            'activa'  => 'warning',
            'resuelta'=> 'success',
            'anulada' => 'secondary',
            default   => 'secondary',
        };
    }

    public function scopeActivas($q) { return $q->where('estado', 'activa'); }
    public function scopeVisibles($q) { return $q->where('visible_para_medico', true); }
}
