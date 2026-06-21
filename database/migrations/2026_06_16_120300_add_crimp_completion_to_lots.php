<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decisiones del Paso 6 (M6) para CRIMP: cantidades de "completar" registradas
     * al tomar la decisión D2a/D2b/D2c (a nivel viajero).
     * - complete_crimp_qty: CRIMP a completar = piezas sobrantes − CRIMP sobrante.
     * - complete_pieces_qty: piezas (manguitas) a completar = piezas sobrantes.
     * Ref: 4-diagrama_paso_6_toma_de_Decisiones.mkd
     */
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedInteger('complete_crimp_qty')->nullable()->after('completion_count');
            $table->unsignedInteger('complete_pieces_qty')->nullable()->after('complete_crimp_qty');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn(['complete_crimp_qty', 'complete_pieces_qty']);
        });
    }
};
