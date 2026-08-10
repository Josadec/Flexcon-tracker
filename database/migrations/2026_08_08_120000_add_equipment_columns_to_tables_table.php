<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La pantalla de editar mesa captura nombre y datos de equipo (marca, modelo,
 * número de serie, número de activo y descripción), y Table::$fillable los
 * declara, pero la migración original de `tables` nunca creó esas columnas: al
 * guardar, el UPDATE reventaba con "Unknown column".
 *
 * Las columnas se agregan sólo si faltan, para que este migrate sea inofensivo
 * en las bases donde ya se hubieran añadido a mano.
 */
return new class extends Migration
{
    /** Columna => cómo crearla. */
    private function columns(): array
    {
        return [
            'name'         => fn (Blueprint $t) => $t->string('name')->nullable()->after('number'),
            'brand'        => fn (Blueprint $t) => $t->string('brand')->nullable(),
            'model'        => fn (Blueprint $t) => $t->string('model')->nullable(),
            's_n'          => fn (Blueprint $t) => $t->string('s_n')->nullable(),
            'asset_number' => fn (Blueprint $t) => $t->string('asset_number')->nullable(),
            'description'  => fn (Blueprint $t) => $t->text('description')->nullable(),
        ];
    }

    public function up(): void
    {
        $missing = array_filter(
            $this->columns(),
            fn ($_, $column) => !Schema::hasColumn('tables', $column),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($missing)) {
            return;
        }

        Schema::table('tables', function (Blueprint $table) use ($missing) {
            foreach ($missing as $definition) {
                $definition($table);
            }
        });
    }

    public function down(): void
    {
        $present = array_keys(array_filter(
            $this->columns(),
            fn ($_, $column) => Schema::hasColumn('tables', $column),
            ARRAY_FILTER_USE_BOTH
        ));

        if (empty($present)) {
            return;
        }

        Schema::table('tables', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }
};
