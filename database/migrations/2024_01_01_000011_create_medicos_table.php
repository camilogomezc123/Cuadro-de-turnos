<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->foreignId('uci_id')->constrained('ucis')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['nombre', 'uci_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicos');
    }
};
