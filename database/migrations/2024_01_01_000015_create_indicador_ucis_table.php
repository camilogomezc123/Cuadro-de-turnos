<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicador_ucis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archivo_id')->constrained('archivos_cargados')->cascadeOnDelete();
            $table->foreignId('uci_id')->constrained('ucis')->cascadeOnDelete();
            $table->tinyInteger('mes');
            $table->smallInteger('anio');
            $table->integer('num_especialistas')->default(0);
            $table->decimal('horas_totales', 8, 1)->default(0);
            $table->decimal('horas_promedio_medico', 6, 1)->default(0);
            $table->decimal('cobertura_mensual', 5, 2)->default(0);
            $table->decimal('cobertura_fin_semana', 5, 2)->default(0);
            $table->decimal('cobertura_nocturna', 5, 2)->default(0);
            $table->decimal('carga_diurna_pct', 5, 2)->default(0);
            $table->decimal('carga_nocturna_pct', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['uci_id', 'mes', 'anio']);
            $table->index(['archivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_ucis');
    }
};
