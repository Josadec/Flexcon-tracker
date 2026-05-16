<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('over_times', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropIndex('idx_over_times_shift');
            $table->dropIndex('idx_over_times_shift_date');

            $table->unsignedBigInteger('shift_id')->nullable()->change();

            $table->foreign('shift_id')->references('id')->on('shifts')->nullOnDelete();
            $table->index('shift_id', 'idx_over_times_shift');
            $table->index(['shift_id', 'date'], 'idx_over_times_shift_date');
        });
    }

    public function down(): void
    {
        Schema::table('over_times', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropIndex('idx_over_times_shift');
            $table->dropIndex('idx_over_times_shift_date');

            $table->unsignedBigInteger('shift_id')->nullable(false)->change();

            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
            $table->index('shift_id', 'idx_over_times_shift');
            $table->index(['shift_id', 'date'], 'idx_over_times_shift_date');
        });
    }
};
