<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiar el enum para incluir 'medico'. SQLite (usado en tests) no
        // soporta ALTER TABLE ... MODIFY; ahí se recrea la columna en su lugar.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('rol', ['master', 'coordinador', 'visualizador', 'medico'])
                    ->default('visualizador')->change();
            });
        } else {
            DB::statement("ALTER TABLE users MODIFY rol ENUM('master','coordinador','visualizador','medico') DEFAULT 'visualizador'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('medico_id')->nullable()->after('uci_asignada');
            $table->foreign('medico_id')->references('id')->on('medicos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['medico_id']);
            $table->dropColumn('medico_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('rol', ['master', 'coordinador', 'visualizador'])
                    ->default('visualizador')->change();
            });
        } else {
            DB::statement("ALTER TABLE users MODIFY rol ENUM('master','coordinador','visualizador') DEFAULT 'visualizador'");
        }
    }
};
