<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requiere redefinir el ENUM completo para ampliar valores
        DB::statement("ALTER TABLE burnout_preguntas MODIFY COLUMN dimension ENUM(
            'agotamiento_emocional',
            'despersonalizacion',
            'realizacion_personal',
            'carga_laboral_recuperacion',
            'impacto_turnos'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE burnout_preguntas MODIFY COLUMN dimension ENUM(
            'agotamiento_emocional',
            'despersonalizacion',
            'realizacion_personal'
        ) NOT NULL");
    }
};
