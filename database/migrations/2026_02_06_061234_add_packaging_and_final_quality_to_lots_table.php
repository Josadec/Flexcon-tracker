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
        Schema::table('lots', function (Blueprint $table) {
            $table->string('packaging_status')->default('pending')->after('inspection_completed_by');
            $table->string('packaging_comments')->nullable()->after('packaging_status');
            $table->unsignedBigInteger('packaging_inspected_by')->nullable()->after('packaging_comments');
            $table->timestamp('packaging_inspected_at')->nullable()->after('packaging_inspected_by');
            $table->string('final_inspection_status')->default('pending')->after('packaging_inspected_at');
            $table->string('final_inspection_comments')->nullable()->after('final_inspection_status');
            $table->unsignedBigInteger('final_inspection_completed_by')->nullable()->after('final_inspection_comments');
            $table->timestamp('final_inspection_completed_at')->nullable()->after('final_inspection_completed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn([
                'packaging_status',
                'packaging_comments',
                'packaging_inspected_by',
                'packaging_inspected_at',
                'final_inspection_status',
                'final_inspection_comments',
                'final_inspection_completed_by',
                'final_inspection_completed_at',
            ]);
        });
    }
};
