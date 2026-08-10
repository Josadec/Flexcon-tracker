<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto en la auditoría, para poder buscarla.
 *
 * `audit_trails` es polimórfica: guarda `auditable_type` + `auditable_id` y nada
 * más. Con eso se puede sacar el historial de UN registro concreto, pero no
 * responder a lo que el cliente pidió: «buscar por WO, número de parte y
 * descripción». Una pesada no sabe de qué orden es sin recorrer tres tablas, y
 * hacerlo con un JOIN polimórfico por cada tipo no escala.
 *
 * Se desnormaliza el contexto al escribir: cada entrada guarda a qué orden,
 * viajero y parte pertenece lo que se tocó. Es redundante a propósito — es lo
 * que hace que la pantalla de historial (Fase 5) sea una consulta con índice y
 * no un recorrido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->unsignedBigInteger('work_order_id')->nullable()->after('auditable_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->after('work_order_id');
            $table->unsignedBigInteger('lot_id')->nullable()->after('purchase_order_id');
            $table->unsignedBigInteger('part_id')->nullable()->after('lot_id');

            $table->index('work_order_id', 'audit_wo_index');
            $table->index('purchase_order_id', 'audit_po_index');
            $table->index('lot_id', 'audit_lot_index');
            $table->index('part_id', 'audit_part_index');

            // El índice del historial de una entidad, en orden cronológico.
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_entity_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropIndex('audit_wo_index');
            $table->dropIndex('audit_po_index');
            $table->dropIndex('audit_lot_index');
            $table->dropIndex('audit_part_index');
            $table->dropIndex('audit_entity_timeline_index');
            $table->dropColumn(['work_order_id', 'purchase_order_id', 'lot_id', 'part_id']);
        });
    }
};
