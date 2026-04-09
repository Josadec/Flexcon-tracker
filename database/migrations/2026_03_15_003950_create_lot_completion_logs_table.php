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
        Schema::create('lot_completion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('cycle_number');
            $table->unsignedInteger('original_quantity');
            $table->unsignedInteger('packed_pieces')->default(0);
            $table->unsignedInteger('surplus_pieces')->default(0);
            $table->unsignedInteger('missing_pieces')->default(0);
            $table->unsignedInteger('production_good_pieces')->default(0);
            $table->unsignedInteger('quality_good_pieces')->default(0);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lot_completion_logs');
    }
};
