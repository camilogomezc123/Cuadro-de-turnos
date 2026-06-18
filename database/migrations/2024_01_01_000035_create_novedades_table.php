<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('novedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->unsignedBigInteger('turno_id')->nullable();
            $table->unsignedBigInteger('uci_id')->nullable();
            $table->date('fecha');
            $table->enum('tipo_novedad', [
                'no_asistencia',
                'reemplazo_turno',
                'cambio_aprobado',
                'donacion_turno',
                'exceso_horas',
                'correccion_manual',
                'error_programacion',
                'alerta_12h_habil',
                'alerta_200h',
                'otro',
            ]);
            $table->text('descripcion')->nullable();
            $table->decimal('horas_afectadas', 4, 1)->default(0);
            $table->boolean('resta_horas')->default(false);
            $table->unsignedBigInteger('usuario_maestro_id')->nullable();
            $table->boolean('visible_para_medico')->default(false);
            $table->enum('estado', ['activa','resuelta','anulada'])->default('activa');
            $table->timestamps();

            $table->index(['medico_id', 'fecha']);
            $table->index(['fecha', 'tipo_novedad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novedades');
    }
};
