<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    const DIMENSIONES_NUEVAS = [
        'agotamiento_emocional',
        'despersonalizacion',
        'realizacion_personal',
        'carga_laboral_recuperacion',
        'impacto_turnos',
    ];

    const DIMENSIONES_ORIGINALES = [
        'agotamiento_emocional',
        'despersonalizacion',
        'realizacion_personal',
    ];

    public function up(): void
    {
        // MySQL requiere redefinir el ENUM completo para ampliar valores.
        // SQLite (usado en tests) no soporta ALTER TABLE ... MODIFY COLUMN;
        // ahí se recrea la columna en su lugar.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('burnout_preguntas', function (Blueprint $table) {
                $table->enum('dimension', self::DIMENSIONES_NUEVAS)->change();
            });
        } else {
            DB::statement("ALTER TABLE burnout_preguntas MODIFY COLUMN dimension ENUM('"
                . implode("','", self::DIMENSIONES_NUEVAS) . "') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('burnout_preguntas', function (Blueprint $table) {
                $table->enum('dimension', self::DIMENSIONES_ORIGINALES)->change();
            });
        } else {
            DB::statement("ALTER TABLE burnout_preguntas MODIFY COLUMN dimension ENUM('"
                . implode("','", self::DIMENSIONES_ORIGINALES) . "') NOT NULL");
        }
    }
};
