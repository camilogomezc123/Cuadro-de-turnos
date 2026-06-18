<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('turno_medicos', function (Blueprint $table) {
            $table->enum('estado_turno', [
                'programado',
                'laborado',
                'no_asistido',
                'reemplazado',
                'ofrecido',
                'aceptado_por_otro',
                'pendiente_aprobacion',
                'cancelado',
                'descubierto',
            ])->default('programado')->after('es_domingo');

            $table->boolean('fue_laborado')->default(true)->after('estado_turno');
            $table->decimal('horas_reconocidas', 4, 1)->nullable()->after('fue_laborado');

            $table->unsignedBigInteger('medico_original_id')->nullable()->after('horas_reconocidas');
            $table->unsignedBigInteger('medico_reemplazo_id')->nullable()->after('medico_original_id');

            $table->text('motivo_modificacion')->nullable()->after('medico_reemplazo_id');
            $table->unsignedBigInteger('modificado_por_usuario_id')->nullable()->after('motivo_modificacion');
            $table->timestamp('fecha_modificacion')->nullable()->after('modificado_por_usuario_id');

            $table->foreign('medico_original_id')->references('id')->on('medicos')->nullOnDelete();
            $table->foreign('medico_reemplazo_id')->references('id')->on('medicos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('turno_medicos', function (Blueprint $table) {
            $table->dropForeign(['medico_original_id']);
            $table->dropForeign(['medico_reemplazo_id']);
            $table->dropColumn([
                'estado_turno','fue_laborado','horas_reconocidas',
                'medico_original_id','medico_reemplazo_id',
                'motivo_modificacion','modificado_por_usuario_id','fecha_modificacion',
            ]);
        });
    }
};
