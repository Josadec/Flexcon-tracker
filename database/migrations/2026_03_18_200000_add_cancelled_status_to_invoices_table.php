<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega el valor 'cancelled' al ENUM status de la tabla invoices.
     *
     * Contexto del error (corregido por esta migracion):
     *   SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status' at row 1
     *   SQL: update `invoices` set `status` = cancelled where `id` = 1
     *
     * El ENUM original en create_invoices_table era ['draft', 'issued', 'paid'].
     * InvoiceShow::cancelInvoice() usaba el literal 'cancelled' que no existia en el ENUM.
     *
     * Decision: se agrega 'cancelled' al ENUM en lugar de usar 'voided' para mantener
     * semantica clara (cancelled = accion del usuario; voided = anulacion contable futura).
     *
     * ENUM resultante: ['draft', 'issued', 'paid', 'cancelled']
     */
    public function up(): void
    {
        // MySQL/MariaDB no permiten ALTER COLUMN en ENUM via Blueprint de forma directa.
        // Se usa DB::statement para redefinir el ENUM con el valor adicional.
        DB::statement(
            "ALTER TABLE `invoices`
             MODIFY COLUMN `status` ENUM('draft', 'issued', 'paid', 'cancelled')
             NOT NULL DEFAULT 'draft'
             COMMENT 'Estado del ciclo de vida: draft|issued|paid|cancelled.'"
        );
    }

    /**
     * Revierte el ENUM a su definicion original ['draft', 'issued', 'paid'].
     *
     * ADVERTENCIA: si existen registros con status = 'cancelled' al momento
     * de ejecutar down(), MySQL emitira un warning y truncara esos valores.
     * Asegurese de que no existan registros cancelados antes de revertir.
     */
    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `invoices`
             MODIFY COLUMN `status` ENUM('draft', 'issued', 'paid')
             NOT NULL DEFAULT 'draft'
             COMMENT 'Estado del ciclo de vida: draft|issued|paid.'"
        );
    }
};
