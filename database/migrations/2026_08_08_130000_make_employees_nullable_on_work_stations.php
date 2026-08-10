<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `employees` se creó NOT NULL en mesas y semi-automáticos, pero los formularios
 * lo tratan como opcional (`nullable` en las reglas, y guardan null cuando se
 * deja vacío). El INSERT/UPDATE fallaba con "employees cannot be null".
 *
 * Se relaja la columna para que coincida con lo que la aplicación ya hace; las
 * filas existentes conservan su valor.
 */
return new class extends Migration
{
    private const TABLES = ['tables', 'semi__automatics'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (!Schema::hasColumn($name, 'employees')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->integer('employees')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (!Schema::hasColumn($name, 'employees')) {
                continue;
            }

            // Volver a NOT NULL exige un valor para las filas que quedaron en null.
            \DB::table($name)->whereNull('employees')->update(['employees' => 1]);

            Schema::table($name, function (Blueprint $table) {
                $table->integer('employees')->nullable(false)->change();
            });
        }
    }
};
