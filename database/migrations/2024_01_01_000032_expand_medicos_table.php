<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medicos', function (Blueprint $table) {
            $table->string('apellido', 100)->nullable()->after('nombre');
            $table->string('documento', 30)->nullable()->after('apellido');
            $table->string('email', 150)->nullable()->after('documento');
            $table->string('telefono', 30)->nullable()->after('email');
            $table->boolean('puede_ingresar_sistema')->default(false)->after('activo');
            $table->timestamp('fecha_creacion_usuario')->nullable()->after('puede_ingresar_sistema');
            // uci_id puede ser null si el médico trabaja en múltiples UCIs
            $table->unsignedBigInteger('uci_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medicos', function (Blueprint $table) {
            $table->dropColumn(['apellido','documento','email','telefono','puede_ingresar_sistema','fecha_creacion_usuario']);
        });
    }
};
