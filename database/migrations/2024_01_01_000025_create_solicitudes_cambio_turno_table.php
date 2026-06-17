<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitudes_cambio_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_origen_id')->constrained('turno_medicos')->cascadeOnDelete();
            $table->foreignId('turno_destino_id')->nullable()->constrained('turno_medicos')->nullOnDelete();
            $table->foreignId('medico_solicitante_id')->constrained('medicos')->cascadeOnDelete();
            $table->foreignId('medico_receptor_id')->constrained('medicos')->cascadeOnDelete();
            $table->text('motivo');
            $table->enum('estado', [
                'pendiente',
                'aceptado_colega',
                'rechazado_colega',
                'aprobado_coordinador',
                'rechazado_coordinador',
                'cancelado',
            ])->default('pendiente');
            // Respuesta del colega
            $table->string('respuesta_colega', 255)->nullable();
            $table->timestamp('respondido_colega_at')->nullable();
            // Respuesta del coordinador
            $table->string('aprobado_por', 100)->nullable();
            $table->string('motivo_coordinador', 255)->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();

            $table->index(['medico_solicitante_id', 'estado']);
            $table->index(['medico_receptor_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cambio_turno');
    }
};
