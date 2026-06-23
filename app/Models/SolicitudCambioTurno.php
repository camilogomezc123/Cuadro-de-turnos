<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCambioTurno extends Model
{
    protected $table = 'solicitudes_cambio_turno';

    protected $fillable = [
        'tipo_movimiento',
        'turno_origen_id', 'componente_turno', 'turno_destino_id',
        'medico_solicitante_id', 'medico_receptor_id',
        'motivo', 'estado',
        'respuesta_colega', 'respondido_colega_at',
        'aprobado_por', 'motivo_coordinador', 'resuelto_at',
        'fecha_respuesta_receptor', 'fecha_aprobacion_maestro',
        'usuario_maestro_aprueba_id', 'observacion_maestro',
    ];

    protected $casts = [
        'respondido_colega_at'    => 'datetime',
        'resuelto_at'             => 'datetime',
        'fecha_respuesta_receptor'=> 'datetime',
        'fecha_aprobacion_maestro'=> 'datetime',
    ];

    const TIPOS = [
        'oferta_abierta'  => 'Oferta abierta',
        'cambio_directo'  => 'Cambio directo',
        'donacion_directa'=> 'Donación directa',
    ];

    const ESTADOS = [
        'solicitado'                 => ['label'=>'Solicitado',              'badge'=>'secondary'],
        'pendiente'                  => ['label'=>'Pendiente',               'badge'=>'warning'],
        'enviado_a_receptor'         => ['label'=>'Enviado al receptor',     'badge'=>'info'],
        'aceptado_por_receptor'      => ['label'=>'Aceptado por receptor',   'badge'=>'primary'],
        'rechazado_por_receptor'     => ['label'=>'Rechazado por receptor',  'badge'=>'danger'],
        'pendiente_aprobacion_maestro'=> ['label'=>'Pendiente maestro',      'badge'=>'warning'],
        'aprobado_por_maestro'       => ['label'=>'Aprobado',               'badge'=>'success'],
        'rechazado_por_maestro'      => ['label'=>'Rechazado por maestro',   'badge'=>'danger'],
        'aceptado_colega'            => ['label'=>'Aceptado por colega',     'badge'=>'info'],
        'rechazado_colega'           => ['label'=>'Rechazado por colega',    'badge'=>'danger'],
        'aprobado_coordinador'       => ['label'=>'Aprobado',               'badge'=>'success'],
        'rechazado_coordinador'      => ['label'=>'Rechazado',              'badge'=>'danger'],
        'cancelado'                  => ['label'=>'Cancelado',              'badge'=>'secondary'],
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

    public function usuarioMaestro()
    {
        return $this->belongsTo(User::class, 'usuario_maestro_aprueba_id');
    }

    public function getEstaAbiertaAttribute(): bool
    {
        return in_array($this->estado, [
            'pendiente','solicitado','enviado_a_receptor',
            'aceptado_por_receptor','pendiente_aprobacion_maestro','aceptado_colega',
        ]);
    }

    public function getBadgeEstadoAttribute(): string
    {
        return self::ESTADOS[$this->estado]['badge'] ?? 'secondary';
    }

    public function getLabelEstadoAttribute(): string
    {
        return self::ESTADOS[$this->estado]['label'] ?? $this->estado;
    }

    public function getLabelTipoAttribute(): string
    {
        return self::TIPOS[$this->tipo_movimiento] ?? $this->tipo_movimiento;
    }

    public function scopePendientesParaMaestro($q)
    {
        return $q->whereIn('estado', ['aceptado_colega','aceptado_por_receptor','pendiente_aprobacion_maestro']);
    }

    public function scopeOfertasAbiertas($q)
    {
        return $q->where('tipo_movimiento','oferta_abierta')
                 ->where('estado','pendiente');
    }
}
