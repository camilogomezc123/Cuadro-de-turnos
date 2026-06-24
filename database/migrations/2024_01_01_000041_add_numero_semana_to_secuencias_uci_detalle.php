<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secuencias_uci_detalle', function (Blueprint $table) {
            $table->tinyInteger('numero_semana')->unsigned()->default(1)->after('dia_semana')
                  ->comment('Semana del patrón: 1, 2, 3 o 4 — cada una independiente');
        });
    }

    public function down(): void
    {
        Schema::table('secuencias_uci_detalle', function (Blueprint $table) {
            $table->dropColumn('numero_semana');
        });
    }
};
