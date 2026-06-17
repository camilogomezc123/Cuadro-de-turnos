<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaSistema extends Model
{
    protected $table = 'auditoria_sistema';
    public    $timestamps = false;

    protected $fillable = [
        'accion', 'modulo', 'entidad', 'entidad_id',
        'usuario', 'datos_anteriores', 'datos_nuevos',
        'ip', 'descripcion', 'created_at',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos'     => 'array',
        'created_at'       => 'datetime',
    ];

    public static function registrar(
        string  $accion,
        string  $modulo,
        ?string $entidad      = null,
        ?int    $entidadId    = null,
        ?array  $anterior     = null,
        ?array  $nuevo        = null,
        string  $descripcion  = '',
        string  $usuario      = 'sistema'
    ): void {
        static::create([
            'accion'            => $accion,
            'modulo'            => $modulo,
            'entidad'           => $entidad,
            'entidad_id'        => $entidadId,
            'usuario'           => $usuario,
            'datos_anteriores'  => $anterior,
            'datos_nuevos'      => $nuevo,
            'descripcion'       => $descripcion,
            'created_at'        => now(),
        ]);
    }
}
