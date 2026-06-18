<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('burnout_encuestas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->enum('periodo', ['mensual','bimestral','trimestral'])->default('mensual');
            $table->boolean('activa')->default(false);
            $table->boolean('permite_posponer')->default(true);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('burnout_preguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuesta_id')->constrained('burnout_encuestas')->cascadeOnDelete();
            $table->text('texto_pregunta');
            $table->enum('dimension', ['agotamiento_emocional','despersonalizacion','realizacion_personal']);
            $table->tinyInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->boolean('obligatoria')->default(true);
            $table->timestamps();
        });

        Schema::create('burnout_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuesta_id')->constrained('burnout_encuestas')->cascadeOnDelete();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->foreignId('pregunta_id')->constrained('burnout_preguntas')->cascadeOnDelete();
            $table->tinyInteger('respuesta_valor'); // 0-6
            $table->string('periodo_evaluado', 10); // 'YYYY-MM'
            $table->timestamp('fecha_respuesta')->useCurrent();
            $table->timestamps();

            $table->index(['medico_id','periodo_evaluado','encuesta_id']);
        });

        Schema::create('burnout_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuesta_id')->constrained('burnout_encuestas')->cascadeOnDelete();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->string('periodo_evaluado', 10); // 'YYYY-MM'
            $table->tinyInteger('puntaje_agotamiento_emocional')->default(0);
            $table->enum('clasificacion_agotamiento_emocional', ['bajo','moderado','alto'])->default('bajo');
            $table->tinyInteger('puntaje_despersonalizacion')->default(0);
            $table->enum('clasificacion_despersonalizacion', ['bajo','moderado','alto'])->default('bajo');
            $table->tinyInteger('puntaje_realizacion_personal')->default(0);
            $table->enum('clasificacion_realizacion_personal', ['alta','moderada','baja'])->default('alta');
            $table->boolean('burnout_positivo')->default(false);
            $table->boolean('burnout_severo')->default(false);
            $table->timestamp('fecha_calculo')->useCurrent();
            // Cruce con cuadro de turnos
            $table->decimal('horas_programadas_mes', 6, 1)->default(0);
            $table->integer('turnos_nocturnos')->default(0);
            $table->integer('fines_semana_trabajados')->default(0);
            $table->boolean('supera_200h')->default(false);
            $table->timestamps();

            $table->unique(['medico_id','periodo_evaluado','encuesta_id']);
        });

        Schema::create('burnout_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resultado_id')->constrained('burnout_resultados')->cascadeOnDelete();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->string('periodo_evaluado', 10);
            $table->enum('tipo_alerta', [
                'burnout_positivo_exceso_horas',
                'burnout_severo',
                'burnout_positivo_nocturnos',
                'uci_alto_riesgo',
            ]);
            $table->string('descripcion', 500);
            $table->enum('nivel_riesgo', ['medio','alto','critico'])->default('medio');
            $table->enum('estado', ['activa','atendida'])->default('activa');
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamps();

            $table->index(['medico_id','estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('burnout_alertas');
        Schema::dropIfExists('burnout_resultados');
        Schema::dropIfExists('burnout_respuestas');
        Schema::dropIfExists('burnout_preguntas');
        Schema::dropIfExists('burnout_encuestas');
    }
};
