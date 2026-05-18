<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kits', function (Blueprint $table) {
            $table->dropUnique('kits_kit_number_unique');
            $table->unique(['work_order_id', 'kit_number'], 'kits_wo_kit_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kits', function (Blueprint $table) {
            $table->dropUnique('kits_wo_kit_number_unique');
            $table->unique('kit_number', 'kits_kit_number_unique');
        });
    }
};
