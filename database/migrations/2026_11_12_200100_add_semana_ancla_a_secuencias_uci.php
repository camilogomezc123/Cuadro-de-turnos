<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secuencias_uci', function (Blueprint $table) {
            // Lunes de referencia (semana de ancla) para que la rotación de fin de
            // semana (sábado sí/no, domingo sí/no) sea continua sin importar en qué
            // orden o combinación de meses/años se aplique la secuencia.
            $table->date('semana_ancla')->nullable()->after('activa');
        });
    }

    public function down(): void
    {
        Schema::table('secuencias_uci', function (Blueprint $table) {
            $table->dropColumn('semana_ancla');
        });
    }
};
