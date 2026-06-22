<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En el modal de confirmación (Paso 5) las pesadas de piezas ("manguitas") y de CRIMP
     * se capturan para el lote de CRIMP seleccionado del viajero. Asociamos también la pesada
     * de piezas al lote de CRIMP. Nullable para no romper pesadas existentes.
     */
    public function up(): void
    {
        Schema::table('packaging_piece_weighings', function (Blueprint $table) {
            $table->foreignId('crimp_lot_id')->nullable()->after('lot_id')
                ->constrained('crimp_lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packaging_piece_weighings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crimp_lot_id');
        });
    }
};
