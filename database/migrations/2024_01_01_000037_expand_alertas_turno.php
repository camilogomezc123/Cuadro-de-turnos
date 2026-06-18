<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('alertas_turno', function (Blueprint $table) {
            // fecha ya existe como 'fecha', alias para claridad
            $table->renameColumn('fecha', 'fecha_turno');
            $table->text('mensaje_medico')->nullable()->after('mensaje');
        });
    }

    public function down(): void
    {
        Schema::table('alertas_turno', function (Blueprint $table) {
            $table->renameColumn('fecha_turno', 'fecha');
            $table->dropColumn('mensaje_medico');
        });
    }
};
