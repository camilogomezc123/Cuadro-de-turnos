<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turno_medicos', function (Blueprint $table) {
            $table->unsignedSmallInteger('fila_excel')->nullable()->after('es_domingo');
            $table->unsignedSmallInteger('columna_excel')->nullable()->after('fila_excel');
            $table->enum('estado_validacion', ['ok', 'advertencia', 'error'])->default('ok')->after('columna_excel');
            $table->text('observacion')->nullable()->after('estado_validacion');
            $table->boolean('es_codigo_no_oficial')->default(false)->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('turno_medicos', function (Blueprint $table) {
            $table->dropColumn(['fila_excel','columna_excel','estado_validacion','observacion','es_codigo_no_oficial']);
        });
    }
};
