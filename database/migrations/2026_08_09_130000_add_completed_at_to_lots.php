<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una sola definición de "viajero terminado".
 *
 * Hasta ahora convivían tres, y un lote podía estar en cualquier combinación
 * de las tres:
 *   - `status = 'completed'`  (administrativa, se ponía a mano en 8 sitios)
 *   - `closure_decision`      (la decisión de cierre de Empaque, 6 valores)
 *   - `ready_for_shipping`    (la cola de despacho, encendida en 3 sitios)
 *
 * El cliente lo dijo claro: «un lote se termina cuando se termina de empacar,
 * ya está listo para el shipping list». Eso es `ready_for_shipping`. Se guarda
 * como fecha propia e indexada porque el tablero tiene que poder preguntar
 * «dame lo que sigue abierto» en la consulta, no filtrando en PHP.
 *
 * El campo no sustituye a los otros tres: los resume. Se mantiene solo, en
 * `Lot::booted()`, para que no haya forma de escribir uno y olvidar el otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('ready_for_shipping_at');

            // Sin clave foránea, igual que el resto de los `_by` de esta tabla
            // (`viajero_received_by`, `closure_decided_by`, `surplus_received_by`).
            //
            // No es capricho: en SQLite, añadir una FK obliga a recrear la
            // tabla, y en ese rebuild se pierde la cláusula `WHERE deleted_at
            // IS NULL` del índice único parcial `lots_wo_lot_number_active_unique`
            // — con lo que dejaría de poderse reutilizar un número de viajero
            // después de un borrado lógico.
            $table->unsignedBigInteger('completed_by')->nullable()->after('completed_at');

            // El índice que hace barato el filtro del tablero: los viajeros
            // abiertos de una orden.
            $table->index(['work_order_id', 'completed_at'], 'lots_wo_completed_index');
        });

        // Backfill: lo que ya estaba terminado bajo cualquiera de las tres
        // nociones anteriores. Sin esto, el tablero escondería lo que no debe
        // y mostraría lo que ya se cerró.
        DB::table('lots')
            ->whereNull('completed_at')
            ->where(fn ($q) => $q->where('ready_for_shipping', true)->orWhere('status', 'completed'))
            ->update([
                'completed_at' => DB::raw('COALESCE(ready_for_shipping_at, closure_decided_at, updated_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex('lots_wo_completed_index');
            $table->dropColumn(['completed_at', 'completed_by']);
        });
    }
};
