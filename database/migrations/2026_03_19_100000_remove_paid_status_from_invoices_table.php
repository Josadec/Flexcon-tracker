<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Elimina el valor 'paid' del ENUM status de la tabla invoices.
     *
     * Contexto de la decision:
     *   En esta fase del proyecto FlexCon-Tracker NO se gestiona el pago de Invoices.
     *   El ciclo de vida queda reducido a:
     *     draft  -> issued  (emision normal)
     *     draft  -> cancelled (salida lateral)
     *
     *   Mantener 'paid' en el ENUM generaba confusion en la UI, filtros y estadisticas.
     *   Se elimina para alinear el esquema con el flujo real del negocio.
     *
     * ENUM anterior : ['draft', 'issued', 'paid', 'cancelled']
     * ENUM resultante: ['draft', 'issued', 'cancelled']
     *
     * ADVERTENCIA up(): si existen registros con status = 'paid' al ejecutar esta
     * migracion, MySQL emitira un warning y truncara esos valores al default 'draft'.
     * Verifique que no existan registros en estado 'paid' antes de migrar.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `invoices`
             MODIFY COLUMN `status` ENUM('draft', 'issued', 'cancelled')
             NOT NULL DEFAULT 'draft'
             COMMENT 'Estado del ciclo de vida: draft|issued|cancelled. paid eliminado (fase actual no gestiona pagos).'"
        );
    }

    /**
     * Revierte el ENUM agregando 'paid' de nuevo.
     *
     * ENUM revertido: ['draft', 'issued', 'paid', 'cancelled']
     */
    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `invoices`
             MODIFY COLUMN `status` ENUM('draft', 'issued', 'paid', 'cancelled')
             NOT NULL DEFAULT 'draft'
             COMMENT 'Estado del ciclo de vida: draft|issued|paid|cancelled.'"
        );
    }
};
