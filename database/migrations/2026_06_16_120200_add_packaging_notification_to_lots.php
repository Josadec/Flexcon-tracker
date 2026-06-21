<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de notificación de Empaque terminado (CRIMP) a nivel viajero (lots):
     * No. de etiquetas capturado y quién/cuándo notificó. Ref: M9 (correo Empaque Viajero).
     */
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedInteger('packaging_label_count')->nullable()->after('completion_count');
            $table->dateTime('packaging_notified_at')->nullable()->after('packaging_label_count');
            $table->foreignId('packaging_notified_by')->nullable()->after('packaging_notified_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('packaging_notified_by');
            $table->dropColumn(['packaging_label_count', 'packaging_notified_at']);
        });
    }
};
