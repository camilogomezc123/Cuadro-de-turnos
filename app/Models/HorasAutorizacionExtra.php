<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorasAutorizacionExtra extends Model
{
    protected $table = 'horas_autorizacion_extra';

    const UPDATED_AT = null; // solo created_at

    protected $fillable = ['medico_id', 'mes', 'anio', 'autorizado_por_user_id'];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_user_id');
    }
}
