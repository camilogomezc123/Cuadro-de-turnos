<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_autorizacion_extra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medico_id');
            $table->tinyInteger('mes')->unsigned();
            $table->smallInteger('anio')->unsigned();
            $table->unsignedBigInteger('autorizado_por_user_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['medico_id', 'mes', 'anio']);
            $table->foreign('medico_id')->references('id')->on('medicos')->onDelete('cascade');
            $table->foreign('autorizado_por_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_autorizacion_extra');
    }
};
