<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivos_cargados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo', 255);
            $table->string('ruta', 500);
            $table->tinyInteger('mes');
            $table->smallInteger('anio');
            $table->boolean('procesado')->default(false);
            $table->integer('total_medicos')->default(0);
            $table->integer('total_turnos')->default(0);
            $table->json('errores')->nullable();
            $table->json('advertencias')->nullable();
            $table->timestamps();

            $table->unique(['mes', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_cargados');
    }
};
