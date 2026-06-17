<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Ausencia extends Model
{
    protected $fillable = [
        'medico_id', 'tipo', 'fecha_inicio', 'fecha_fin',
        'descripcion', 'documento_referencia', 'estado',
        'aprobada_por', 'aprobada_at', 'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'aprobada_at'  => 'datetime',
    ];

    public function medico()
    {
        return $this->belongsTo(Medico::class);
    }

    /** Verifica si una fecha cae dentro de esta ausencia */
    public function cubre(Carbon|string $fecha): bool
    {
        $f = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
        return $this->estado === 'aprobada'
            && $f->between($this->fecha_inicio, $this->fecha_fin);
    }

    public function getNombreTipoAttribute(): string
    {
        return match($this->tipo) {
            'vacaciones'   => 'Vacaciones',
            'permiso'      => 'Permiso',
            'incapacidad'  => 'Incapacidad',
            'licencia'     => 'Licencia',
            default        => 'Otro',
        };
    }

    public function getDiasAttribute(): int
    {
        return $this->fecha_inicio->diffInDays($this->fecha_fin) + 1;
    }

    public function scopeAprobadas($q) { return $q->where('estado', 'aprobada'); }
    public function scopePendientes($q) { return $q->where('estado', 'pendiente'); }
}
