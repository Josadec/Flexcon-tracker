<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lotes de CRIMP — sustituyen al concepto de "Kit" en el flujo de partes con CRIMP.
     * Cuelgan del "viajero" (tabla lots): 1 viajero -> N lotes de CRIMP, cada uno con su
     * lote de fabricante (campo manual).
     * Ref: Diagramas_flujo/diagram_crimp/Reporte_areas_afectadas/02_tablas_modelos_rutas.md (A.1).
     */
    public function up(): void
    {
        Schema::create('crimp_lots', function (Blueprint $table) {
            $table->id();
            // El "viajero" es el Lot padre.
            $table->foreignId('lot_id')->constrained('lots')->onDelete('cascade');
            $table->string('crimp_lot_number');
            // Lote de fabricante: campo manual. La obligatoriedad se valida en UI (decisión B.1 abierta).
            $table->string('lote_fabricante')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('crimp_lot_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crimp_lots');
    }
};
