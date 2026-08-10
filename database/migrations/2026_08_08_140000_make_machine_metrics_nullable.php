<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * En `machines`, employees / setup_time / maintenance_time se crearon NOT NULL,
 * pero los formularios de alta y edición los declaran `nullable` y guardan null
 * cuando se dejan vacíos: crear una máquina sin esos datos reventaba.
 *
 * Es el mismo desajuste que ya se corrigió en mesas y semi-automáticos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->integer('employees')->nullable()->change();
            $table->decimal('setup_time', 8, 2)->nullable()->change();
            $table->decimal('maintenance_time', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Volver a NOT NULL exige un valor para las filas que quedaron en null.
        \DB::table('machines')->whereNull('employees')->update(['employees' => 1]);
        \DB::table('machines')->whereNull('setup_time')->update(['setup_time' => 0]);
        \DB::table('machines')->whereNull('maintenance_time')->update(['maintenance_time' => 0]);

        Schema::table('machines', function (Blueprint $table) {
            $table->integer('employees')->nullable(false)->change();
            $table->decimal('setup_time', 8, 2)->nullable(false)->change();
            $table->decimal('maintenance_time', 8, 2)->nullable(false)->change();
        });
    }
};
