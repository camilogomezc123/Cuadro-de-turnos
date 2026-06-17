<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auditoria_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('accion', 80);           // EDITAR_TURNO, IMPORTAR_EXCEL, APROBAR_CAMBIO, etc.
            $table->string('modulo', 60);            // planificacion, archivos, cambios, ausencias
            $table->string('entidad', 60)->nullable(); // TurnoMedico, Ausencia, etc.
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('usuario', 120)->default('sistema');
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['accion', 'modulo']);
            $table->index(['entidad', 'entidad_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_sistema');
    }
};
