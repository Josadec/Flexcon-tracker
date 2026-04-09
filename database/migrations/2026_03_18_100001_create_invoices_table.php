<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla de Invoices (FPL-12).
     * El Invoice es el documento financiero terminal del ciclo de produccion de FlexCon.
     * Se genera directamente desde un Packing Slip en estado 'shipped'.
     *
     * Ciclo de vida del Invoice:
     *   draft -> issued
     *   draft -> cancelled
     *
     * Decision D-12-01: tabla separada de packing_slips; el Invoice tiene ciclo de vida,
     * estados y datos financieros propios.
     *
     * Decision D-12-13 actualizada con D-12-16: los cargos fijos NO son columnas en esta
     * tabla. Viven como InvoiceItems con is_fixed_charge = true (diseno extensible).
     * Los totales (subtotal_items, subtotal_charges, grand_total) son valores calculados
     * y persistidos por Invoice::calculateTotals().
     *
     * El campo invoice_id en packing_slips ahora queda consolidado en su migracion
     * create correspondiente para mantener el historial mas compacto.
     *
     * Orden de dependencias:
     *   Requiere: packing_slips (2026_03_08_100002), invoice_charge_types (2026_03_18_100000)
     *   Precede a: invoice_items (2026_03_18_100002)
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // =========================================================
            // Identificacion del documento
            // =========================================================

            // Numero unico del Invoice. Formato: 5 digitos con padding cero (ej: '00001').
            // Decision D-12-23 / P-12-03: el sistema arranca desde #00001.
            // Los Invoices de Excel (#01006 y anteriores) son documentos externos
            // y no se mezclan con la serie del sistema para evitar confusion de auditoria.
            $table->string('invoice_number', 10)->unique()
                  ->comment('Numero unico del Invoice. Formato: 5 digitos con zero-padding (00001).');

            // Fecha del documento. Puede diferir de packing_slips.shipped_at.
            // El Invoice puede emitirse dias despues del despacho fisico del PS.
            $table->date('invoice_date')
                  ->comment('Fecha del Invoice. Puede ser posterior a la fecha de despacho del PS.');

            // LOT NO. predominante del Invoice (lote compartido por la mayoria de items del PS).
            // Formato: MMDDYY + x + sufijo (ej: '030926x01').
            // Calculado desde el primer lunes anterior a packing_slips.shipped_at (D-12-16).
            // Decision D-12-21: MUTABLE por diseno. El Admin puede editarlo en estado draft e issued.
            // El LOT NO. es un identificador de lote de produccion, no un dato financiero.
            // NULL si el workstation_type no pudo determinarse automaticamente.
            $table->string('lot_no', 20)->nullable()
                  ->comment('LOT NO. predominante del Invoice. Formato MMDDYY+x+sufijo. Mutable (D-12-21).');

            // =========================================================
            // Estado y tipo del ciclo de vida
            // =========================================================

            // Estado del ciclo de vida del Invoice.
            //   draft: en edicion, precios y cargos editables por el Admin.
            //   issued: emitido, PDF generado, datos financieros bloqueados.
            //   cancelled: cancelado por el usuario.
            $table->enum('status', ['draft', 'issued', 'cancelled'])
                  ->default('draft')
                  ->comment('Estado del ciclo de vida: draft|issued|cancelled.');

            // Tipo de Invoice.
            //   product:    generado desde un Packing Slip (relacion 1:1 con PS).
            //   standalone: Invoice sin PS (consumibles, solventes, servicios — fase futura).
            $table->enum('type', ['product', 'standalone'])
                  ->default('product')
                  ->comment('Tipo de Invoice: product (desde PS) | standalone (sin PS).');

            // =========================================================
            // Referencia al Packing Slip
            // =========================================================

            // FK al Packing Slip origen. NULL para Invoices standalone.
            // Un PS solo puede tener un Invoice de tipo product (constraint a nivel de servicio).
            // ON DELETE RESTRICT: no se puede eliminar un PS que tiene un Invoice asociado.
            $table->foreignId('packing_slip_id')
                  ->nullable()
                  ->constrained('packing_slips')
                  ->onDelete('restrict')
                  ->comment('Packing Slip origen. NULL para Invoices standalone. 1:1 con PS de tipo product.');

            // =========================================================
            // Snapshot de datos del cliente (inmutable al emitir)
            // =========================================================

            // Lugar de entrega FOB. Valor por defecto del documento PDF #01006.
            $table->string('fob_location', 100)->default('Tecate, Ca.')
                  ->comment('Lugar de entrega FOB. Snapshot del config al crear el Invoice.');

            // Datos del destinatario de la factura (Sold To).
            $table->string('sold_to_name', 200)->default('S.E.I.P., Inc.')
                  ->comment('Nombre del comprador (Sold To). Snapshot inmutable al emitir.');

            $table->text('sold_to_address')
                  ->comment('Direccion del comprador. Snapshot inmutable al emitir.');

            // Datos del destinatario del envio (Shipped To).
            $table->string('shipped_to_name', 200)->default('S.E.I.P., Inc.')
                  ->comment('Nombre del destinatario del envio. Snapshot inmutable al emitir.');

            $table->text('shipped_to_address')
                  ->comment('Direccion de envio. Snapshot inmutable al emitir.');

            // =========================================================
            // Totales calculados (desnormalizados para rendimiento)
            // Calculados y persistidos por Invoice::calculateTotals()
            // =========================================================

            // Total de piezas sumando todos los InvoiceItems de producto (is_fixed_charge = false).
            $table->unsignedInteger('total_quantity')->nullable()
                  ->comment('Total de piezas. Suma de invoice_items.quantity WHERE is_fixed_charge = false.');

            // Subtotal de los items de producto (sin cargos fijos).
            $table->decimal('subtotal_items', 14, 2)->nullable()
                  ->comment('Suma de invoice_items.line_total WHERE is_fixed_charge = false.');

            // Subtotal de los cargos fijos (Machine Maintenance, Admin Fee, Shipping Cost, etc.).
            // Decision D-12-16: se calcula sumando InvoiceItems con is_fixed_charge = true.
            $table->decimal('subtotal_charges', 10, 2)->nullable()
                  ->comment('Suma de invoice_items.line_total WHERE is_fixed_charge = true.');

            // Grand Total = subtotal_items + subtotal_charges.
            // Decision D-12-06: DECIMAL(14,2) — suficiente para el maximo observado (~$85,000).
            $table->decimal('grand_total', 14, 2)->nullable()
                  ->comment('Grand Total del Invoice: subtotal_items + subtotal_charges.');

            // =========================================================
            // Control y auditoria
            // =========================================================

            $table->text('notes')->nullable()
                  ->comment('Notas adicionales del Invoice (uso interno).');

            // Timestamps de estados del ciclo de vida.
            $table->timestamp('issued_at')->nullable()
                  ->comment('Momento en que el Invoice fue emitido (status = issued).');

            $table->timestamp('paid_at')->nullable()
                  ->comment('Momento en que el Invoice fue marcado como pagado (fase futura).');

            // Usuario que creo el Invoice.
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Usuario que creo el Invoice.');

            // Usuario que emitio el Invoice (cambio a status = issued).
            $table->foreignId('issued_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Usuario que emitio el Invoice (status = issued).');

            $table->timestamps();
            $table->softDeletes();

            // =========================================================
            // Indices de rendimiento
            // =========================================================
            $table->index('packing_slip_id', 'idx_inv_packing_slip');
            $table->index('invoice_date', 'idx_inv_date');
            $table->index('status', 'idx_inv_status');
            $table->index('type', 'idx_inv_type');
            $table->index('created_by', 'idx_inv_created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
