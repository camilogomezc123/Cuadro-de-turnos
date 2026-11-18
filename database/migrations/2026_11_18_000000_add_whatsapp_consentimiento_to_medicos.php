<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicos', function (Blueprint $table) {
            $table->boolean('whatsapp_notificaciones')->default(false)->after('telefono');
            $table->timestamp('whatsapp_consentimiento_en')->nullable()->after('whatsapp_notificaciones');
        });
    }

    public function down(): void
    {
        Schema::table('medicos', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_notificaciones', 'whatsapp_consentimiento_en']);
        });
    }
};
