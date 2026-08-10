<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captura MANUAL de piezas sobrantes por Lote de CRIMP (Modal Paso 5 · Empaque).
 *
 * Interpretación A (declarativa): el sobrante es material bueno que sobró y se
 * devuelve a Materiales. Se guarda, se muestra y se audita, pero NO participa en
 * ningún cálculo aguas abajo (Paso 6, Packing Slip, Invoice). Ver RP-01 en
 * docs/Mejoras/ModalCincoEmpaque/01_captura_manual_sobrantes_crimp.md.
 *
 * NULL = todavía no se capturó.  0 = se capturó y no hubo sobrante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crimp_lots', function (Blueprint $table) {
            $table->unsignedInteger('surplus_pieces')->nullable()->after('quantity')
                ->comment('Manguitas sobrantes declaradas por Empaque (Paso 5). NULL = sin capturar.');
            $table->unsignedInteger('surplus_crimps')->nullable()->after('surplus_pieces')
                ->comment('CRIMP sobrantes declarados por Empaque (Paso 5). NULL = sin capturar.');
            $table->dateTime('surplus_captured_at')->nullable()->after('surplus_crimps');
            $table->foreignId('surplus_captured_by')->nullable()->after('surplus_captured_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crimp_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('surplus_captured_by');
            $table->dropColumn(['surplus_pieces', 'surplus_crimps', 'surplus_captured_at']);
        });
    }
};
