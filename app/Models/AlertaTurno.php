<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaTurno extends Model
{
    protected $table = 'alertas_turno';

    protected $fillable = [
        'archivo_id', 'medico_id', 'uci_id', 'fecha_turno',
        'tipo', 'prioridad', 'mensaje', 'mensaje_medico', 'estado',
        'resuelta_por', 'resuelta_at', 'nota_resolucion',
    ];

    protected $casts = [
        'fecha_turno' => 'date',
        'resuelta_at' => 'datetime',
    ];

    // Tipos de alerta
    const TIPOS = [
        'CODIGO_INVALIDO'        => 'Código de turno inválido',
        'HOJA_VACIA'             => 'Hoja UCI vacía',
        'MEDICO_DUPLICADO'       => 'Médico duplicado en UCI',
        'MEDICO_DOS_UCI'         => 'Médico en dos UCI el mismo día',
        'JORNADA_EXCESIVA'       => 'Jornada mayor a 12h continuas',
        'NOCHE_SEGUIDA_MANANA'   => 'Noche seguida de mañana',
        'SEMANA_EXCESIVA'        => 'Más de 60h semanales',
        'MES_EXCESIVO'           => 'Supera horas máximas mensuales',
        'MES_DEFICIENTE'         => 'Menos de 100h mensuales',
        'SIN_COBERTURA_NOCHE'    => 'UCI sin cobertura nocturna',
        'SIN_COBERTURA_DOMINGO'  => 'UCI sin cobertura el domingo',
        'TURNO_EN_AUSENCIA'      => 'Turno asignado en período de ausencia',
        'MTN_DIA_HABIL'            => 'MTN en día hábil (lunes a viernes)',
        'JORNADA_24H_HABIL'        => 'Jornada mayor a 24h en día hábil',
        'CODIGO_NO_PARAMETRIZADO'  => 'Código de turno no parametrizado (ej: MN)',
        'EXCESO_200H'              => 'Exceso de 200 horas mensuales',
        'EXCESO_12H_HABIL'         => 'Más de 12 horas en día hábil',
    ];

    public function medico()
    {
        return $this->belongsTo(Medico::class);
    }

    public function uci()
    {
        return $this->belongsTo(Uci::class);
    }

    public function archivo()
    {
        return $this->belongsTo(ArchivoCargado::class, 'archivo_id');
    }

    public function scopeAbiertas($q)     { return $q->where('estado', 'abierta'); }
    public function scopeAltas($q)        { return $q->where('prioridad', 'alta'); }
    public function scopeDelArchivo($q, $id) { return $q->where('archivo_id', $id); }

    public function getBadgePrioridadAttribute(): string
    {
        return match($this->prioridad) {
            'alta'  => 'danger',
            'media' => 'warning',
            'baja'  => 'info',
            default => 'secondary',
        };
    }
}
