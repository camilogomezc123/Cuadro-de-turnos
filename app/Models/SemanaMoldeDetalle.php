<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemanaMoldeDetalle extends Model
{
    protected $table = 'semanas_molde_detalle';

    protected $fillable = ['semana_molde_id', 'dia_semana', 'codigo_turno'];

    public function semanaMolde()
    {
        return $this->belongsTo(SemanaMolde::class);
    }
}
