<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plain unique index on purchase_orders.po_number also counted
 * soft-deleted rows, so a po_number could never be reused after a PO was
 * soft-deleted. Switch to a composite (po_number, deleted_at) unique index
 * — same pattern already used by the lots table — so a soft-deleted PO no
 * longer blocks the number. Uniqueness among *active* POs is enforced in
 * the application layer via Rule::unique()->withoutTrashed().
 *
 * NOTE (2026-08-08): the application layer alone turned out to be insufficient
 * — this composite index does not constrain active rows at all, since MySQL
 * allows repeated values when one indexed column is NULL, and deleted_at is
 * NULL on every live PO. Migration 2026_08_08_000000 restores the database
 * guarantee via a generated column. Keep this index: it is what still allows
 * a number to be reused after a soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_po_number_unique');
            $table->unique(['po_number', 'deleted_at'], 'purchase_orders_po_number_deleted_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_po_number_deleted_unique');
            $table->unique('po_number', 'purchase_orders_po_number_unique');
        });
    }
};
