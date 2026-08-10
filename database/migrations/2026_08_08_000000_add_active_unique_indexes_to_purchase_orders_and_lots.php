<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restablece la unicidad a nivel de base de datos para los registros ACTIVOS.
 *
 * Los índices compuestos (po_number, deleted_at) en purchase_orders y
 * (work_order_id, lot_number, deleted_at) en lots NO impiden duplicados entre
 * registros vivos: MySQL admite múltiples filas con NULL en una columna de un
 * índice UNIQUE, y deleted_at es NULL en todo registro activo. La unicidad
 * quedaba solo en la capa de aplicación (Rule::unique()->withoutTrashed()),
 * que no cubre condiciones de carrera ni escrituras fuera del formulario.
 *
 * MySQL no soporta índices únicos parciales, así que se usa una columna
 * generada que vale el número cuando el registro está vivo y NULL cuando está
 * borrado. En SQLite/Postgres se usa directamente un índice único parcial.
 *
 * Los índices (…, deleted_at) previos se conservan: son los que permiten
 * reutilizar un número después de un soft delete, que era el objetivo del
 * cambio original en 2026_05_21_000000.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->guardNoActiveDuplicates('purchase_orders', ['po_number']);
        $this->guardNoActiveDuplicates('lots', ['work_order_id', 'lot_number']);

        $this->addActiveUnique(
            table: 'purchase_orders',
            column: 'po_number',
            generatedColumn: 'po_number_active',
            indexName: 'purchase_orders_po_number_active_unique',
        );

        $this->addActiveUnique(
            table: 'lots',
            column: 'lot_number',
            generatedColumn: 'lot_number_active',
            indexName: 'lots_wo_lot_number_active_unique',
            scope: ['work_order_id'],
        );
    }

    public function down(): void
    {
        $this->dropActiveUnique('lots', 'lot_number_active', 'lots_wo_lot_number_active_unique');
        $this->dropActiveUnique('purchase_orders', 'po_number_active', 'purchase_orders_po_number_active_unique');
    }

    /**
     * Aborta con un mensaje accionable si la tabla ya trae duplicados activos:
     * sin esto el ADD UNIQUE falla con un error críptico de MySQL.
     */
    private function guardNoActiveDuplicates(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $duplicates = DB::table($table)
            ->select($columns)
            ->selectRaw('COUNT(*) as total')
            ->whereNull('deleted_at')
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $detail = $duplicates
            ->map(fn ($row) => collect($columns)
                ->map(fn ($column) => "{$column}={$row->$column}")
                ->implode(', ')." (x{$row->total})")
            ->implode('; ');

        throw new RuntimeException(
            "No se puede aplicar el índice único en `{$table}`: ya existen registros activos duplicados -> {$detail}. ".
            'Resuélvelos (borra o renumera los sobrantes) y vuelve a correr la migración.'
        );
    }

    private function addActiveUnique(
        string $table,
        string $column,
        string $generatedColumn,
        string $indexName,
        array $scope = [],
    ): void {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $indexName)) {
            return;
        }

        if ($this->usesGeneratedColumn()) {
            if (! Schema::hasColumn($table, $generatedColumn)) {
                DB::statement(
                    "ALTER TABLE `{$table}` ADD COLUMN `{$generatedColumn}` VARCHAR(255) ".
                    "GENERATED ALWAYS AS (CASE WHEN `deleted_at` IS NULL THEN `{$column}` END) STORED"
                );
            }

            $indexColumns = collect([...$scope, $generatedColumn])
                ->map(fn ($name) => "`{$name}`")
                ->implode(', ');

            DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$indexName}` ({$indexColumns})");

            return;
        }

        $indexColumns = collect([...$scope, $column])
            ->map(fn ($name) => "\"{$name}\"")
            ->implode(', ');

        DB::statement(
            "CREATE UNIQUE INDEX \"{$indexName}\" ON \"{$table}\" ({$indexColumns}) WHERE \"deleted_at\" IS NULL"
        );
    }

    private function dropActiveUnique(string $table, string $generatedColumn, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if ($this->usesGeneratedColumn()) {
            if (Schema::hasIndex($table, $indexName)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }

            if (Schema::hasColumn($table, $generatedColumn)) {
                DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$generatedColumn}`");
            }

            return;
        }

        DB::statement("DROP INDEX IF EXISTS \"{$indexName}\"");
    }

    /** MySQL/MariaDB no tienen índices parciales; el resto sí. */
    private function usesGeneratedColumn(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
