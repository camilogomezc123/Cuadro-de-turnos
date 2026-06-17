<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicador_medicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archivo_id')->constrained('archivos_cargados')->cascadeOnDelete();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->foreignId('uci_id')->constrained('ucis')->cascadeOnDelete();
            $table->tinyInteger('mes');
            $table->smallInteger('anio');
            $table->decimal('total_horas', 6, 1)->default(0);
            $table->decimal('horas_diurnas', 6, 1)->default(0);
            $table->decimal('horas_nocturnas', 6, 1)->default(0);
            $table->integer('turnos_m')->default(0);
            $table->integer('turnos_t')->default(0);
            $table->integer('turnos_mt')->default(0);
            $table->integer('turnos_n')->default(0);
            $table->integer('turnos_fin_semana')->default(0);
            $table->integer('turnos_domingo')->default(0);
            $table->decimal('promedio_semanal', 5, 2)->default(0);
            $table->decimal('promedio_diario', 5, 2)->default(0);
            $table->decimal('porcentaje_ocupacion', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['medico_id', 'uci_id', 'mes', 'anio']);
            $table->index(['archivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_medicos');
    }
};
