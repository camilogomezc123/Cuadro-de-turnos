<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCambioTurno extends Model
{
    protected $table = 'solicitudes_cambio_turno';

    protected $fillable = [
        'turno_origen_id', 'turno_destino_id',
        'medico_solicitante_id', 'medico_receptor_id',
        'motivo', 'estado',
        'respuesta_colega', 'respondido_colega_at',
        'aprobado_por', 'motivo_coordinador', 'resuelto_at',
    ];

    protected $casts = [
        'respondido_colega_at' => 'datetime',
        'resuelto_at'          => 'datetime',
    ];

    public function turnoOrigen()
    {
        return $this->belongsTo(TurnoMedico::class, 'turno_origen_id');
    }

    public function turnoDestino()
    {
        return $this->belongsTo(TurnoMedico::class, 'turno_destino_id');
    }

    public function medicoSolicitante()
    {
        return $this->belongsTo(Medico::class, 'medico_solicitante_id');
    }

    public function medicoReceptor()
    {
        return $this->belongsTo(Medico::class, 'medico_receptor_id');
    }

    public function getEstaAbiertaAttribute(): bool
    {
        return in_array($this->estado, ['pendiente', 'aceptado_colega']);
    }

    public function getBadgeEstadoAttribute(): string
    {
        return match($this->estado) {
            'pendiente'               => 'warning',
            'aceptado_colega'         => 'info',
            'rechazado_colega'        => 'danger',
            'aprobado_coordinador'    => 'success',
            'rechazado_coordinador'   => 'danger',
            'cancelado'               => 'secondary',
            default                   => 'secondary',
        };
    }

    public function getLabelEstadoAttribute(): string
    {
        return match($this->estado) {
            'pendiente'               => 'Pendiente',
            'aceptado_colega'         => 'Aceptado por colega',
            'rechazado_colega'        => 'Rechazado por colega',
            'aprobado_coordinador'    => 'Aprobado',
            'rechazado_coordinador'   => 'Rechazado',
            'cancelado'               => 'Cancelado',
            default                   => $this->estado,
        };
    }

    public function scopePendientes($q) { return $q->where('estado', 'pendiente'); }
    public function scopeParaCoordinador($q) { return $q->where('estado', 'aceptado_colega'); }
}
