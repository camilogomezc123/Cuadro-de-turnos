<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ampliar ENUM de estado y agregar tipo_movimiento
        DB::statement("ALTER TABLE solicitudes_cambio_turno
            MODIFY estado ENUM(
                'solicitado',
                'pendiente',
                'enviado_a_receptor',
                'aceptado_por_receptor',
                'rechazado_por_receptor',
                'pendiente_aprobacion_maestro',
                'aprobado_por_maestro',
                'rechazado_por_maestro',
                'aceptado_colega',
                'rechazado_colega',
                'aprobado_coordinador',
                'rechazado_coordinador',
                'cancelado'
            ) DEFAULT 'pendiente'");

        Schema::table('solicitudes_cambio_turno', function (Blueprint $table) {
            $table->enum('tipo_movimiento', [
                'oferta_abierta',
                'cambio_directo',
                'donacion_directa',
            ])->default('cambio_directo')->after('id');

            $table->timestamp('fecha_respuesta_receptor')->nullable()->after('respondido_colega_at');
            $table->timestamp('fecha_aprobacion_maestro')->nullable()->after('fecha_respuesta_receptor');
            $table->unsignedBigInteger('usuario_maestro_aprueba_id')->nullable()->after('fecha_aprobacion_maestro');
            $table->text('observacion_maestro')->nullable()->after('usuario_maestro_aprueba_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_cambio_turno', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_movimiento','fecha_respuesta_receptor',
                'fecha_aprobacion_maestro','usuario_maestro_aprueba_id','observacion_maestro',
            ]);
        });
    }
};
