<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega los campos necesarios para el mecanismo de retorno de lote a Empaque.
     * Cuando Shipping devuelve un lote a Empaque, se registra el timestamp, el usuario
     * que ejecuto la accion y el motivo del retorno.
     *
     * El retorno limpia ready_for_shipping y closure_decision para permitir que
     * LotPackagingObserver funcione correctamente en el segundo ciclo de empaque.
     */
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            // Timestamp de cuando el lote fue devuelto a Empaque desde Shipping.
            $table->timestamp('returned_to_packaging_at')
                  ->nullable()
                  ->after('closed_by_type')
                  ->comment('Momento en que el lote fue devuelto a Empaque desde la cola de Shipping');

            // Usuario que ejecuto el retorno (Admin o Shipping).
            $table->unsignedBigInteger('returned_to_packaging_by')
                  ->nullable()
                  ->after('returned_to_packaging_at')
                  ->comment('ID del usuario que devolvio el lote a Empaque');

            // Motivo obligatorio del retorno (ingresado por el usuario).
            $table->string('returned_to_packaging_reason', 255)
                  ->nullable()
                  ->after('returned_to_packaging_by')
                  ->comment('Motivo por el que el lote fue devuelto a Empaque');

            // Foreign key hacia users
            $table->foreign('returned_to_packaging_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropForeign(['returned_to_packaging_by']);
            $table->dropColumn([
                'returned_to_packaging_at',
                'returned_to_packaging_by',
                'returned_to_packaging_reason',
            ]);
        });
    }
};
