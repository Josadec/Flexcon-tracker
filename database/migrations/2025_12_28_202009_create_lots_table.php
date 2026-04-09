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
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->onDelete('cascade');
            $table->string('lot_number');
            $table->text('description')->nullable();
            $table->integer('quantity');
            $table->integer('quantity_packed_final')->nullable()->comment('Snapshot of packed pieces at close');
            $table->boolean('ready_for_shipping')->default(false);
            $table->timestamp('ready_for_shipping_at')->nullable();
            $table->enum('closed_by_type', ['complete_lot', 'new_lot', 'close_as_is'])->nullable();
            $table->timestamp('returned_to_packaging_at')->nullable();
            $table->foreignId('returned_to_packaging_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('returned_to_packaging_reason', 255)->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed, cancelled
            $table->text('comments')->nullable();
            $table->json('raw_material_batch_numbers')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->date('receipt_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('inspection_status')->default('pending')->comment('pending, approved, rejected');
            $table->text('inspection_comments')->nullable();
            $table->timestamp('inspection_completed_at')->nullable();
            $table->foreignId('inspection_completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('material_status')->default('pending');
            $table->string('packaging_status')->default('pending');
            $table->string('packaging_comments')->nullable();
            $table->unsignedBigInteger('packaging_inspected_by')->nullable();
            $table->timestamp('packaging_inspected_at')->nullable();
            $table->unsignedInteger('completion_count')->default(0);
            $table->string('final_inspection_status')->default('pending');
            $table->string('final_inspection_comments')->nullable();
            $table->unsignedBigInteger('final_inspection_completed_by')->nullable();
            $table->timestamp('final_inspection_completed_at')->nullable();
            $table->boolean('surplus_delivered')->default(false);
            $table->timestamp('surplus_delivered_at')->nullable();
            $table->unsignedBigInteger('surplus_delivered_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['work_order_id', 'status']);
            $table->index('status');
            $table->index('supplier_id', 'lots_supplier_id_index');
            $table->index('receipt_date', 'lots_receipt_date_index');
            $table->index('expiration_date', 'lots_expiration_date_index');
            $table->index('inspection_status');
            $table->index(['work_order_id', 'inspection_status']);
            $table->index(['ready_for_shipping', 'ready_for_shipping_at'], 'idx_lots_shipping_queue');
            $table->unique(['work_order_id', 'lot_number', 'deleted_at'], 'lots_wo_lot_number_deleted_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
