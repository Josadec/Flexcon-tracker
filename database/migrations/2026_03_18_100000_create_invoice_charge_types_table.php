<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea el catalogo administrable de tipos de cargo adicional del Invoice (FPL-12).
     * Esta tabla es la fuente de verdad para los cargos fijos (Machine Maintenance,
     * Administration Fee, SHIPPING COST) y cualquier cargo futuro que se agregue.
     *
     * Decision D-12-22: los cargos fijos NO son columnas en invoices ni constantes
     * en config/invoice.php. Viven en este catalogo con un default_amount editable
     * por el Admin desde la UI, sin necesidad de modificar codigo ni hacer deploys.
     *
     * Decision D-12-16: los cargos se materializan como InvoiceItems con
     * is_fixed_charge = true, tomando un snapshot del default_amount al crear el Invoice.
     *
     * Decision D-12-17: implementa SoftDeletes para preservar el historial de
     * tipos de cargo usados en Invoices anteriores (integridad referencial informativa).
     *
     * Orden de dependencias:
     *   Esta tabla debe crearse ANTES que invoices e invoice_items.
     *   No tiene dependencias hacia tablas del modulo Invoice.
     */
    public function up(): void
    {
        Schema::create('invoice_charge_types', function (Blueprint $table) {
            $table->id();

            // Identificador tecnico interno (slug inmutable post-creacion).
            // Ejemplos: 'machine_maintenance', 'administration_fee', 'shipping_cost'.
            // Decision D-12-18: el campo code es inmutable; el label es el campo editable.
            $table->string('code', 64)->unique()
                  ->comment('Slug tecnico inmutable. Ej: machine_maintenance, shipping_cost');

            // Etiqueta visible en el Invoice PDF y en la UI.
            // El Admin puede cambiar este campo sin tocar codigo.
            // Ejemplos: Machine Maintenance, Administration Fee, SHIPPING COST.
            $table->string('label', 150)
                  ->comment('Etiqueta visible en el PDF del Invoice. Editable por Admin.');

            // Monto por defecto que se pre-carga al crear un nuevo Invoice.
            // El InvoiceFromPackingSlipService lee este valor al generar los InvoiceItems.
            // Historial: Machine Maintenance era $800 en 2025; subio a $1,200 en Invoice #01006 (2026).
            $table->decimal('default_amount', 10, 2)->default(0.00)
                  ->comment('Monto por defecto al crear un Invoice. Editable por Admin sin deploy.');

            // Controla si este tipo aparece activo en el catalogo.
            // FALSE = desactivado; no se usa en nuevos Invoices pero el historial queda intacto.
            $table->boolean('is_active')->default(true)
                  ->comment('Activo = visible para nuevos Invoices. Inactivo = solo historial.');

            // Controla si este cargo se incluye automaticamente en todos los Invoices nuevos.
            // TRUE  = el servicio lo agrega sin pedir confirmacion al usuario.
            // FALSE = cargo opcional; el usuario decide si incluirlo en cada Invoice.
            $table->boolean('always_include')->default(true)
                  ->comment('Si true, el servicio lo agrega automaticamente a cada Invoice nuevo.');

            // Orden de aparicion en la tabla del PDF (menor numero = aparece primero).
            $table->smallInteger('sort_order')->unsigned()->default(0)
                  ->comment('Orden de aparicion en el PDF del Invoice. Menor = primero.');

            // Notas internas para el equipo de administracion (no aparece en el PDF).
            $table->text('notes')->nullable()
                  ->comment('Notas internas de administracion. No aparece en el PDF.');

            // Auditoria de creacion y ultima modificacion.
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Usuario que creo este tipo de cargo.');

            $table->foreignId('updated_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Usuario que realizo la ultima modificacion.');

            $table->timestamps();
            $table->softDeletes();

            // Indices de rendimiento
            $table->index(['is_active', 'sort_order'], 'idx_ict_active_order');
            $table->index('deleted_at', 'idx_ict_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_charge_types');
    }
};
