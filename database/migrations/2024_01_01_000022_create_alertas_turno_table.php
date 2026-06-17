<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alertas_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archivo_id')->nullable()->constrained('archivos_cargados')->nullOnDelete();
            $table->foreignId('medico_id')->nullable()->constrained('medicos')->nullOnDelete();
            $table->foreignId('uci_id')->nullable()->constrained('ucis')->nullOnDelete();
            $table->date('fecha')->nullable();
            $table->string('tipo', 60);           // CODIGO_INVALIDO, MEDICO_DUPLICADO, HORA_MAXIMA, etc.
            $table->enum('prioridad', ['alta','media','baja'])->default('media');
            $table->text('mensaje');
            $table->enum('estado', ['abierta','en_revision','cerrada'])->default('abierta');
            $table->string('resuelta_por', 100)->nullable();
            $table->timestamp('resuelta_at')->nullable();
            $table->text('nota_resolucion')->nullable();
            $table->timestamps();

            $table->index(['estado', 'prioridad']);
            $table->index(['medico_id', 'fecha']);
            $table->index(['archivo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_turno');
    }
};
