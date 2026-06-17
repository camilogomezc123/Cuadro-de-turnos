<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('configuracion_cobertura_uci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uci_id')->constrained('ucis')->cascadeOnDelete();
            $table->integer('min_medicos_manana')->default(1);
            $table->integer('min_medicos_tarde')->default(1);
            $table->integer('min_medicos_noche')->default(1);
            $table->integer('min_medicos_finde')->default(1);
            $table->decimal('horas_minimas_mensual', 5, 1)->default(100);
            $table->decimal('horas_maximas_mensual', 5, 1)->default(240);
            $table->decimal('horas_maximas_semanales', 5, 1)->default(60);
            $table->boolean('permite_mtn')->default(true);
            $table->timestamps();

            $table->unique('uci_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_cobertura_uci');
    }
};
