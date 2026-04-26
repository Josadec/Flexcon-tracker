<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_tiers', function (Blueprint $table) {
            $table->decimal('min_quantity', 15, 4)->change();
            $table->decimal('max_quantity', 15, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('price_tiers', function (Blueprint $table) {
            $table->unsignedInteger('min_quantity')->change();
            $table->unsignedInteger('max_quantity')->nullable()->change();
        });
    }
};
