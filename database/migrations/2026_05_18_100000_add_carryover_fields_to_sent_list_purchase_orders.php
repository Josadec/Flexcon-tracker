<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sent_list_purchase_orders', function (Blueprint $table) {
            $table->boolean('is_carryover')->default(false)->after('lot_number');
            $table->foreignId('carryover_from_sent_list_id')
                  ->nullable()
                  ->after('is_carryover')
                  ->constrained('sent_lists')
                  ->nullOnDelete();
            $table->integer('pending_quantity_at_carryover')
                  ->nullable()
                  ->after('carryover_from_sent_list_id');

            $table->index('is_carryover');
            $table->index('carryover_from_sent_list_id');
        });
    }

    public function down(): void
    {
        Schema::table('sent_list_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['carryover_from_sent_list_id']);
            $table->dropIndex(['is_carryover']);
            $table->dropIndex(['carryover_from_sent_list_id']);
            $table->dropColumn([
                'is_carryover',
                'carryover_from_sent_list_id',
                'pending_quantity_at_carryover',
            ]);
        });
    }
};
