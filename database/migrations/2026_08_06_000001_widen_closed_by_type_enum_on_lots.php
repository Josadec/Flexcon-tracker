<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `lots.closed_by_type` se creó como enum de 3 valores (los cierres NO-CRIMP).
 * Después se agregaron las 3 decisiones de CRIMP (complete_crimp,
 * complete_pieces, complete_both) y el código empezó a escribirlas en esa
 * misma columna, pero nadie amplió el enum.
 *
 * Con MySQL en strict mode eso lanza «1265 Data truncated», así que el Paso 7
 * (entrega de viajero) de cualquier viajero CRIMP con decisión de completar
 * truena. No lo detectaban las pruebas porque phpunit corre en SQLite, que no
 * valida enums.
 *
 * Los seis valores son los de Lot::CLOSURE_* .
 */
return new class extends Migration
{
    /** Los 3 originales + los 3 de CRIMP. */
    private const ALL = "'complete_lot','new_lot','close_as_is','complete_crimp','complete_pieces','complete_both'";

    private const ORIGINAL = "'complete_lot','new_lot','close_as_is'";

    public function up(): void
    {
        if (! $this->isMySql()) {
            return; // SQLite no aplica enums; nada que ampliar.
        }

        DB::statement('ALTER TABLE `lots` MODIFY `closed_by_type` ENUM(' . self::ALL . ') NULL');
    }

    public function down(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        // Los valores de CRIMP no caben en el enum viejo: se limpian antes de
        // encoger la columna, si no el ALTER falla.
        DB::table('lots')
            ->whereIn('closed_by_type', ['complete_crimp', 'complete_pieces', 'complete_both'])
            ->update(['closed_by_type' => null]);

        DB::statement('ALTER TABLE `lots` MODIFY `closed_by_type` ENUM(' . self::ORIGINAL . ') NULL');
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
