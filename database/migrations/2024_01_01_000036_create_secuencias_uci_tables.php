<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('secuencias_uci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uci_id')->constrained('ucis')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->year('anio');
            $table->boolean('activa')->default(true);
            $table->unsignedBigInteger('creada_por_usuario_id')->nullable();
            $table->timestamps();

            $table->index(['uci_id', 'anio', 'activa']);
        });

        Schema::create('secuencias_uci_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secuencia_uci_id')->constrained('secuencias_uci')->cascadeOnDelete();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            // 0=lunes, 1=martes ... 6=domingo
            $table->tinyInteger('dia_semana');
            $table->string('codigo_turno', 10)->default('');
            $table->boolean('es_fin_de_semana')->default(false);
            // Para rotación de fines de semana: semana 1,2,3,4
            $table->tinyInteger('orden_rotacion_fin_semana')->nullable();
            $table->date('fecha_inicio_vigencia')->nullable();
            $table->date('fecha_fin_vigencia')->nullable();
            $table->timestamps();

            $table->index(['secuencia_uci_id', 'medico_id']);
            $table->index(['secuencia_uci_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secuencias_uci_detalle');
        Schema::dropIfExists('secuencias_uci');
    }
};
