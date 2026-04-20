<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Validar que no existan filas exactamente idénticas (price_id + min + max + tier_price)
        $duplicates = DB::select("
            SELECT price_id, min_quantity, max_quantity, tier_price, COUNT(*) AS total
            FROM price_tiers
            GROUP BY price_id, min_quantity, max_quantity, tier_price
            HAVING COUNT(*) > 1
        ");

        if (!empty($duplicates)) {
            $detail = collect($duplicates)
                ->map(fn ($r) => "price_id={$r->price_id}, {$r->min_quantity}-{$r->max_quantity}, tier_price={$r->tier_price} ({$r->total} filas)")
                ->implode(' | ');

            throw new \RuntimeException(
                "No se puede aplicar el nuevo constraint: existen filas exactamente duplicadas. Resuélvalas manualmente antes de migrar. Detalle: {$detail}"
            );
        }

        Schema::table('price_tiers', function (Blueprint $table) {
            // Elimina el constraint viejo UNIQUE(price_id, min_quantity, max_quantity)
            $table->dropUnique('price_tier_unique');
            // Nuevo constraint: previene filas idénticas en las 4 columnas
            // Permite: mismo rango con distinto precio ✓ | distinto rango con mismo precio ✓
            $table->unique(['price_id', 'min_quantity', 'max_quantity', 'tier_price'], 'price_tier_unique');
        });
    }

    public function down(): void
    {
        Schema::table('price_tiers', function (Blueprint $table) {
            $table->dropUnique('price_tier_unique');
            $table->unique(['price_id', 'min_quantity', 'max_quantity'], 'price_tier_unique');
        });
    }
};
