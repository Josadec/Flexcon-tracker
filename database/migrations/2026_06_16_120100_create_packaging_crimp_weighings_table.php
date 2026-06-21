<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesadas de CRIMP en Empaque para partes con CRIMP.
     * Se registran a nivel viajero (lots). Cantidad capturada manualmente;
     * el peso es opcional. Separadas de las pesadas de piezas.
     * Ref: 01_modulos_afectados.md → M6 · Empaque (Paso 4-5).
     */
    public function up(): void
    {
        Schema::create('packaging_crimp_weighings', function (Blueprint $table) {
            $table->id();
            // El "viajero" es el Lot.
            $table->foreignId('lot_id')->constrained('lots')->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->decimal('weight', 10, 3)->nullable();
            $table->foreignId('weighed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('weighed_at');
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('lot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_crimp_weighings');
    }
};
