<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Medico extends Model
{
    protected $fillable = [
        'nombre', 'apellido', 'documento', 'email', 'telefono',
        'uci_id', 'activo', 'puede_ingresar_sistema', 'fecha_creacion_usuario',
    ];

    protected $casts = [
        'activo'                 => 'boolean',
        'puede_ingresar_sistema' => 'boolean',
        'fecha_creacion_usuario' => 'datetime',
    ];

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    public function uci(): BelongsTo
    {
        return $this->belongsTo(Uci::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoMedico::class);
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(IndicadorMedico::class);
    }

    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class);
    }

    public function solicitudesEnviadas(): HasMany
    {
        return $this->hasMany(SolicitudCambioTurno::class, 'medico_solicitante_id');
    }

    public function solicitudesRecibidas(): HasMany
    {
        return $this->hasMany(SolicitudCambioTurno::class, 'medico_receptor_id');
    }

    // Total horas del mes sumando TODAS las UCIs donde trabajó
    public function totalHorasMes(int $mes, int $anio): float
    {
        return TurnoMedico::where('medico_id', $this->id)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->where('fue_laborado', true)
            ->sum(\DB::raw('COALESCE(horas_reconocidas, horas_total)'));
    }

    public function superaLimite200(int $mes, int $anio): bool
    {
        return $this->totalHorasMes($mes, $anio) > 200;
    }
}
