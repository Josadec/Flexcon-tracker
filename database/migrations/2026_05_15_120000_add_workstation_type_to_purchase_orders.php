<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Tipo de estación elegido para esta PO (table | machine | semi_automatic).
            // Si es NULL, se usa el del Standard de la parte como fallback.
            $table->string('workstation_type', 20)->nullable()->after('part_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('workstation_type');
        });
    }
};
