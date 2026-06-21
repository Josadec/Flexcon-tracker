<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La pesada de CRIMP en Empaque se asocia al lote de CRIMP específico del viajero
     * (diagrama Paso 5: "modal que selecciona el lote de CRIMP"). Nullable para no
     * romper pesadas existentes ni el caso sin selección.
     */
    public function up(): void
    {
        Schema::table('packaging_crimp_weighings', function (Blueprint $table) {
            $table->foreignId('crimp_lot_id')->nullable()->after('lot_id')
                ->constrained('crimp_lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packaging_crimp_weighings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crimp_lot_id');
        });
    }
};
