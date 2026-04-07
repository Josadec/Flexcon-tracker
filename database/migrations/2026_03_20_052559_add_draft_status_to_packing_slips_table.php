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
        DB::statement("ALTER TABLE packing_slips MODIFY COLUMN status ENUM('draft','pending','shipped','cancelled') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE packing_slips MODIFY COLUMN status ENUM('pending','shipped','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
