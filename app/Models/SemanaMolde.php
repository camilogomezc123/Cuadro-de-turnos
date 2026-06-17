<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemanaMolde extends Model
{
    protected $table = 'semanas_molde';

    protected $fillable = ['nombre', 'descripcion', 'uci_id', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    public function uci()
    {
        return $this->belongsTo(Uci::class);
    }

    public function detalles()
    {
        return $this->hasMany(SemanaMoldeDetalle::class);
    }

    /** Retorna el código de turno para un día de semana dado ('lunes', 'martes'...) */
    public function turnoParaDia(string $diaSemana): string
    {
        $detalle = $this->detalles->firstWhere('dia_semana', $diaSemana);
        return $detalle?->codigo_turno ?? 'LIBRE';
    }
}
