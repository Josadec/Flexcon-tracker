<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date code manual por lote de CRIMP (formato FPL-10 del cliente, ej. "260602B01").
     * En el Packing Slip cada lote de CRIMP es una fila y lleva su propio código de fecha,
     * fiel al PDF oficial (Ensambles Formula FPL-10). Nullable/aditivo: seguro sobre datos existentes.
     */
    public function up(): void
    {
        Schema::table('crimp_lots', function (Blueprint $table) {
            $table->string('date_code', 20)->nullable()->after('lote_fabricante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crimp_lots', function (Blueprint $table) {
            $table->dropColumn('date_code');
        });
    }
};
