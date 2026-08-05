<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tipos_turno')->updateOrInsert(
            ['codigo' => 'TN'],
            [
                'nombre'          => 'Tarde-Noche',
                'hora_inicio'     => '13:00:00',
                'hora_fin'        => '07:00:00',
                'horas_diurnas'   => 6.0,
                'horas_nocturnas' => 12.0,
                'horas_total'     => 18.0,
                'color_hex'       => '#1E1B4B',
                'color_clase'     => 'badge-TN',
                'es_ausencia'     => false,
                'cubre_manana'    => false,
                'cubre_tarde'     => true,
                'cubre_noche'     => true,
                'solo_finde'      => false,
                'activo'          => true,
                'updated_at'      => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('tipos_turno')->where('codigo', 'TN')->delete();
    }
};
