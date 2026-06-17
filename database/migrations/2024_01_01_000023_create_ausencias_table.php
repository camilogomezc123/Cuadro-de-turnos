<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->enum('tipo', ['vacaciones','permiso','incapacidad','licencia','otro'])->default('vacaciones');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('descripcion', 255)->nullable();
            $table->string('documento_referencia', 120)->nullable(); // nro de resolución, incapacidad, etc.
            $table->enum('estado', ['pendiente','aprobada','rechazada'])->default('pendiente');
            $table->string('aprobada_por', 100)->nullable();
            $table->timestamp('aprobada_at')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['medico_id', 'fecha_inicio', 'fecha_fin']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias');
    }
};
