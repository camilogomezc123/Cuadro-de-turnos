<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivos_cargados', function (Blueprint $table) {
            // Visibilidad para médicos/no-master, independiente de "procesado".
            // Por defecto true para no afectar los meses ya cargados hoy; el flujo
            // de secuencia-molde lo pone explícitamente en false hasta que el
            // master decida activar ese mes.
            $table->boolean('activo')->default(true)->after('procesado');
        });

        DB::table('archivos_cargados')->update(['activo' => true]);
    }

    public function down(): void
    {
        Schema::table('archivos_cargados', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
