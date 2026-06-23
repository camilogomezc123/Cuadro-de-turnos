<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_cambio_turno', function (Blueprint $table) {
            // Componente del turno que se ofrece: NULL = turno completo, 'M'/'T'/'N'/'MT'/'MN' = parte
            $table->string('componente_turno', 10)->nullable()->after('turno_origen_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_cambio_turno', function (Blueprint $table) {
            $table->dropColumn('componente_turno');
        });
    }
};