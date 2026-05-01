<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->integer('employees');
            $table->boolean('active');
            $table->text('comments')->nullable();

            // NO puedes eliminar un área si tiene mesas (RESTRICT)
            $table->foreignId('area_id')->constrained('areas');
            $table->foreignId('production_status_id')->nullable()->constrained('production_statuses')->nullOnDelete();
            $table->unsignedBigInteger('standard_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('standard_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
