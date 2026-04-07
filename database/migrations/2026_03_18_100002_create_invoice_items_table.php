<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla de items del Invoice (FPL-12).
     * Cada item es un snapshot financiero inmutable de un lote del Packing Slip.
     * Los cargos fijos (Machine Maintenance, Administration Fee, SHIPPING COST)
     * tambien se almacenan como items con is_fixed_charge = true.
     *
     * Decision D-12-02: tabla separada de packing_slip_items.
     * Los items del Invoice son snapshot financiero independiente.
     *
     * Decision D-12-05: calculos monetarios con bcmul() para line_total.
     * Evita errores de punto flotante con precios de 4 decimales x 100,000+ piezas.
     *
     * Decision D-12-06:
     *   unit_cost:  DECIMAL(10,4) — precision para precios como 0.1796, 0.0935
     *   line_total: DECIMAL(12,2) — suficiente para 999,999 piezas x max unit_cost
     *
     * Decision D-12-16 (diseno extensible):
     *   El campo invoice_charge_type_id vincula los items de cargo con el catalogo
     *   invoice_charge_types. Si is_fixed_charge = true, este campo debe ser NOT NULL
     *   a nivel de logica de negocio (enforceado en el servicio, no en la DB).
     *
     * Orden de dependencias:
     *   Requiere: invoices (2026_03_18_100001), invoice_charge_types (2026_03_18_100000)
     *   Requiere: packing_slip_items, lots, work_orders, purchase_orders, parts (existentes)
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            // FK al Invoice padre. CASCADE: si se elimina el Invoice, se eliminan sus items.
            $table->foreignId('invoice_id')
                  ->constrained('invoices')
                  ->onDelete('cascade')
                  ->comment('Invoice al que pertenece este item.');

            // Referencia al PackingSlipItem origen.
            // NULL para items de cargos fijos (no tienen PSItem).
            $table->foreignId('packing_slip_item_id')
                  ->nullable()
                  ->constrained('packing_slip_items')
                  ->onDelete('set null')
                  ->comment('PackingSlipItem origen. NULL para cargos fijos.');

            // Referencia al tipo de cargo del catalogo (para items de cargo fijo).
            // NULL para items de producto.
            // Vincula el snapshot de este InvoiceItem con el tipo del catalogo que lo origino.
            $table->foreignId('invoice_charge_type_id')
                  ->nullable()
                  ->constrained('invoice_charge_types')
                  ->onDelete('set null')
                  ->comment('Tipo de cargo del catalogo. Solo para is_fixed_charge = true. NULL para items de producto.');

            // =========================================================
            // Referencias de navegacion al origen del item
            // (nullable; SET NULL si el registro padre se elimina)
            // Permiten navegar al contexto de produccion sin re-consultar el PS.
            // =========================================================

            $table->foreignId('lot_id')
                  ->nullable()
                  ->constrained('lots')
                  ->onDelete('set null')
                  ->comment('Lote origen. NULL para cargos fijos.');

            $table->foreignId('work_order_id')
                  ->nullable()
                  ->constrained('work_orders')
                  ->onDelete('set null')
                  ->comment('Work Order origen. NULL para cargos fijos.');

            $table->foreignId('purchase_order_id')
                  ->nullable()
                  ->constrained('purchase_orders')
                  ->onDelete('set null')
                  ->comment('Purchase Order origen. NULL para cargos fijos.');

            $table->foreignId('part_id')
                  ->nullable()
                  ->constrained('parts')
                  ->onDelete('set null')
                  ->comment('Parte origen. NULL para cargos fijos.');

            // =========================================================
            // Snapshot inmutable del item al momento de facturacion
            // =========================================================

            // Descripcion del item. Para cargos fijos: 'Machine Maintenance', etc.
            $table->string('description', 255)
                  ->comment('Descripcion del item o del cargo fijo. Snapshot al momento de crear el Invoice.');

            // Numero de item de la parte (Item No. en el PDF).
            // NULL para cargos fijos.
            $table->string('item_number', 100)->nullable()
                  ->comment('Numero de item de la parte (Item No.). NULL para cargos fijos.');

            // LOT NO. del item. Formato: MMDDYY + x + sufijo ('01' o '20').
            // Ejemplo: '030926x01' = 9 de marzo de 2026, workstation tipo table.
            // Decision D-12-09: formato MMDDYY confirmado por PDF #01006.
            // Decision D-12-10: sufijo x01 para table, x20 para machine/semi_automatic.
            // NULL para cargos fijos.
            $table->string('lot_number', 30)->nullable()
                  ->comment('LOT NO. del item. Formato MMDDYY+x+sufijo (030926x01). NULL para cargos fijos.');

            // Numero de PO. NULL para cargos fijos.
            $table->string('po_number', 50)->nullable()
                  ->comment('Numero de la Purchase Order. NULL para cargos fijos.');

            // Numero de WO para el Invoice.
            // Decision D-12-11: solo los 7 digitos de external_wo_number (sin prefijo W0 ni sufijo 001).
            // Diferente al formato del PS (W0 + numero + 001).
            // NULL para cargos fijos.
            $table->string('wo_number', 50)->nullable()
                  ->comment('Numero de WO (7 digitos de external_wo_number). NULL para cargos fijos.');

            // =========================================================
            // Cantidades y precios (snapshot inmutable)
            // =========================================================

            // Cantidad de piezas. Para cargos fijos: siempre 1.
            $table->unsignedInteger('quantity')->default(1)
                  ->comment('Cantidad de piezas. Para cargos fijos es 1.');

            // Precio unitario con 4 decimales (precision para precios como 0.1796, 0.0935, 0.2712).
            // Para cargos fijos: el importe del cargo (ej: 1200.0000).
            // Decision D-12-05: calcular line_total con bcmul() para evitar errores de float.
            $table->decimal('unit_cost', 10, 4)
                  ->comment('Precio unitario con 4 decimales. Snapshot inmutable.');

            // Total de la linea. Calculado: round(bcmul(quantity, unit_cost, 6), 2).
            // Para cargos fijos: igual al unit_cost.
            $table->decimal('line_total', 12, 2)
                  ->comment('Total de la linea = bcmul(quantity, unit_cost) redondeado a 2 decimales.');

            // =========================================================
            // Clasificacion y orden
            // =========================================================

            // Orden de aparicion en el PDF. Los cargos fijos tienen sort_order > todos los items.
            $table->smallInteger('sort_order')->unsigned()->default(0)
                  ->comment('Orden de aparicion en el PDF. Los cargos fijos van al final (sort_order > items).');

            // TRUE para Machine Maintenance, Administration Fee, SHIPPING COST y cualquier cargo futuro.
            // FALSE para items de producto provenientes del PS.
            $table->boolean('is_fixed_charge')->default(false)
                  ->comment('TRUE para cargos adicionales (Machine Maintenance, etc.). FALSE para items de producto.');

            $table->timestamps();

            // =========================================================
            // Indices de rendimiento
            // =========================================================
            $table->index('invoice_id', 'idx_ii_invoice');
            $table->index('packing_slip_item_id', 'idx_ii_psi');
            $table->index('invoice_charge_type_id', 'idx_ii_charge_type');
            $table->index(['invoice_id', 'sort_order'], 'idx_ii_sort');
            $table->index('is_fixed_charge', 'idx_ii_fixed_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
