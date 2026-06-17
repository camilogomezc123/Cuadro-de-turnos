<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tipos_turno', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();       // M, T, MT, N, MTN, VAC, PER, INC, LIBRE
            $table->string('nombre', 80);
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->decimal('horas_diurnas', 4, 1)->default(0);
            $table->decimal('horas_nocturnas', 4, 1)->default(0);
            $table->decimal('horas_total', 4, 1)->default(0);
            $table->string('color_hex', 10)->default('#6c757d');
            $table->string('color_clase', 40)->default('bg-secondary'); // clase Bootstrap
            $table->boolean('es_ausencia')->default(false);   // VAC, PER, INC
            $table->boolean('cubre_manana')->default(false);
            $table->boolean('cubre_tarde')->default(false);
            $table->boolean('cubre_noche')->default(false);
            $table->boolean('solo_finde')->default(false);    // MTN solo sáb/dom
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_turno');
    }
};
