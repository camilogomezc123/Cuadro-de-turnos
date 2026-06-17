<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('semanas_molde', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('uci_id')->nullable()->constrained('ucis')->nullOnDelete();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('semanas_molde_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semana_molde_id')->constrained('semanas_molde')->cascadeOnDelete();
            $table->enum('dia_semana', ['lunes','martes','miercoles','jueves','viernes','sabado','domingo']);
            $table->string('codigo_turno', 10); // M, T, MT, N, MTN, LIBRE, VAC, PER, INC
            $table->timestamps();

            $table->unique(['semana_molde_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semanas_molde_detalle');
        Schema::dropIfExists('semanas_molde');
    }
};
