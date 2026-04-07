<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega el campo invoice_id a la tabla packing_slips para navegacion
     * bidireccional rapida entre el Packing Slip y su Invoice asociado.
     *
     * Decision D-12-04: invoice_id en packing_slips permite $ps->hasInvoice()
     * sin una consulta adicional a la tabla invoices.
     *
     * Nota sobre la referencia circular:
     *   packing_slips.invoice_id -> invoices.id
     *   invoices.packing_slip_id -> packing_slips.id
     * Esta referencia bidireccional es correcta y comun en documentos relacionados.
     * Se resuelve con el orden de ejecucion de migraciones:
     *   1. create_invoices_table (referencia packing_slips que ya existe)
     *   2. create_invoice_items_table (referencia invoices)
     *   3. add_invoice_id_to_packing_slips_table (referencia invoices — esta migracion)
     *
     * ON DELETE SET NULL: si se elimina el Invoice (soft delete o fisico), el campo
     * invoice_id del PS queda en NULL — el PS no se pierde.
     *
     * Orden de dependencias:
     *   Requiere: invoices (2026_03_18_100001)
     */
    public function up(): void
    {
        Schema::table('packing_slips', function (Blueprint $table) {
            // Campo de convenencia para la navegacion PS -> Invoice sin JOIN adicional.
            // NULL significa que este PS aun no tiene un Invoice generado.
            $table->foreignId('invoice_id')
                  ->nullable()
                  ->after('shipped_by')
                  ->constrained('invoices')
                  ->onDelete('set null')
                  ->comment('Invoice generado para este PS. NULL si aun no se genero el Invoice.');

            // Indice para acelerar la busqueda de PS por invoice_id.
            $table->index('invoice_id', 'idx_ps_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_slips', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropIndex('idx_ps_invoice_id');
            $table->dropColumn('invoice_id');
        });
    }
};
