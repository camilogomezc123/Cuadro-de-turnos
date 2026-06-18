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
        // nuevos campos de estado
        'estado_turno', 'fue_laborado', 'horas_reconocidas',
        'medico_original_id', 'medico_reemplazo_id',
        'motivo_modificacion', 'modificado_por_usuario_id', 'fecha_modificacion',
    ];

    protected $casts = [
        'fecha'                => 'date',
        'es_fin_semana'        => 'boolean',
        'es_domingo'           => 'boolean',
        'es_codigo_no_oficial' => 'boolean',
        'fue_laborado'         => 'boolean',
        'fecha_modificacion'   => 'datetime',
    ];

    // Horas efectivamente reconocidas (usa horas_reconocidas si fue modificado, sino horas_total)
    public function getHorasEfectivasAttribute(): float
    {
        if (!$this->fue_laborado) return 0;
        return $this->horas_reconocidas ?? $this->horas_total ?? 0;
    }

    // Código de turno "seleccionable" (excluye VAC de nuevas asignaciones)
    public static function codigosActivos(): array
    {
        return ['M', 'T', 'MT', 'N', 'MTN', 'MN', 'PER', 'INC', 'LIBRE'];
    }

    public static function horasPorCodigo(string $codigo): float
    {
        return match(strtoupper($codigo)) {
            'M'    => 6,
            'T'    => 6,
            'MT'   => 12,
            'N'    => 12,
            'MTN'  => 24,
            'MN'   => 18,
            default => 0,
        };
    }

    public function esTurnoActivo(): bool
    {
        return in_array($this->codigo_turno, ['M','T','MT','N','MTN','MN'])
            && $this->fue_laborado
            && !in_array($this->estado_turno, ['no_asistido','cancelado','descubierto']);
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function medicoOriginal(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_original_id');
    }

    public function medicoReemplazo(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_reemplazo_id');
    }

    public function uci(): BelongsTo
    {
        return $this->belongsTo(Uci::class);
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(ArchivoCargado::class, 'archivo_id');
    }

    // Badge CSS para la vista
    public function getBadgeClassAttribute(): string
    {
        return 'badge-' . strtoupper($this->codigo_turno ?: 'libre');
    }

    public function getLabelEstadoAttribute(): string
    {
        return match($this->estado_turno) {
            'programado'          => 'Programado',
            'laborado'            => 'Laborado',
            'no_asistido'         => 'No asistido',
            'reemplazado'         => 'Reemplazado',
            'ofrecido'            => 'Ofrecido',
            'aceptado_por_otro'   => 'Aceptado por otro',
            'pendiente_aprobacion'=> 'Pendiente aprobación',
            'cancelado'           => 'Cancelado',
            'descubierto'         => 'Descubierto',
            default               => 'Programado',
        };
    }
}
