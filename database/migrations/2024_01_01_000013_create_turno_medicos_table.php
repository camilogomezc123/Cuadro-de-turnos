<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turno_medicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archivo_id')->constrained('archivos_cargados')->cascadeOnDelete();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->foreignId('uci_id')->constrained('ucis')->cascadeOnDelete();
            $table->date('fecha');
            $table->tinyInteger('dia_numero');
            $table->string('dia_semana', 20);
            $table->string('codigo_turno', 10)->default('');
            $table->decimal('horas_diurnas', 4, 1)->default(0);
            $table->decimal('horas_nocturnas', 4, 1)->default(0);
            $table->decimal('horas_total', 4, 1)->default(0);
            $table->boolean('es_fin_semana')->default(false);
            $table->boolean('es_domingo')->default(false);
            $table->timestamps();

            $table->index(['medico_id', 'fecha']);
            $table->index(['uci_id', 'fecha']);
            $table->index(['archivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turno_medicos');
    }
};
