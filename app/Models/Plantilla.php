<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plantilla extends Model
{
    protected $fillable = [
        'nombre', 'descripcion', 'uci_id', 'aplica_todas_ucis',
        'archivo_base_id', 'mes_base', 'anio_base', 'activa', 'anios_generados',
    ];

    protected $casts = [
        'anios_generados'    => 'array',
        'activa'             => 'boolean',
        'aplica_todas_ucis'  => 'boolean',
    ];

    const NOMBRES_MESES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                           'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    public function uci()          { return $this->belongsTo(Uci::class); }
    public function archivoBase()  { return $this->belongsTo(ArchivoCargado::class, 'archivo_base_id'); }

    public function getNombreMesBaseAttribute(): string
    {
        return self::NOMBRES_MESES[$this->mes_base ?? 0] ?? '';
    }
}
