<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('indicador_medicos', function (Blueprint $table) {
            $table->integer('turnos_mtn')->default(0)->after('turnos_n');
            $table->integer('turnos_vac')->default(0)->after('turnos_mtn');
            $table->integer('turnos_per')->default(0)->after('turnos_vac');
            $table->integer('turnos_inc')->default(0)->after('turnos_per');
            $table->boolean('tiene_alerta')->default(false)->after('turnos_inc');
        });

        Schema::table('turno_medicos', function (Blueprint $table) {
            $table->boolean('editado_manualmente')->default(false)->after('es_domingo');
            $table->string('editado_por', 100)->nullable()->after('editado_manualmente');
            $table->timestamp('editado_at')->nullable()->after('editado_por');
            $table->boolean('tiene_alerta')->default(false)->after('editado_at');
            $table->boolean('bloqueado_ausencia')->default(false)->after('tiene_alerta');
        });
    }

    public function down(): void
    {
        Schema::table('indicador_medicos', function (Blueprint $table) {
            $table->dropColumn(['turnos_mtn','turnos_vac','turnos_per','turnos_inc','tiene_alerta']);
        });
        Schema::table('turno_medicos', function (Blueprint $table) {
            $table->dropColumn(['editado_manualmente','editado_por','editado_at','tiene_alerta','bloqueado_ausencia']);
        });
    }
};
