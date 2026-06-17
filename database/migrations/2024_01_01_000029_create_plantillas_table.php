<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->foreignId('uci_id')->nullable()->constrained('ucis')->nullOnDelete();
            $table->boolean('aplica_todas_ucis')->default(false);
            $table->foreignId('archivo_base_id')->nullable()->constrained('archivos_cargados')->nullOnDelete();
            $table->integer('mes_base')->nullable();
            $table->integer('anio_base')->nullable();
            $table->boolean('activa')->default(true);
            $table->json('anios_generados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas');
    }
};
