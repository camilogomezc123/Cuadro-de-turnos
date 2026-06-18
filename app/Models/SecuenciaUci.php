<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecuenciaUci extends Model
{
    protected $table = 'secuencias_uci';

    protected $fillable = [
        'uci_id', 'nombre', 'anio', 'activa', 'creada_por_usuario_id',
    ];

    protected $casts = ['activa' => 'boolean'];

    public function uci(): BelongsTo
    {
        return $this->belongsTo(Uci::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SecuenciaUciDetalle::class);
    }

    public function detallesDiaSemana(int $dia): HasMany
    {
        return $this->detalles()->where('dia_semana', $dia)->where('es_fin_de_semana', false);
    }

    public function detallesFinSemana(): HasMany
    {
        return $this->detalles()->where('es_fin_de_semana', true)->orderBy('orden_rotacion_fin_semana');
    }
}
