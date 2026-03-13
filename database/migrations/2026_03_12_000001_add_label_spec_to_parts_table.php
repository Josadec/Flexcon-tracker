<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->string('label_spec', 150)
                  ->nullable()
                  ->after('active')
                  ->comment('Especificacion de etiqueta militar/aeronautica (ej: M83519/2-8, SAE AS81824/1-2)');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('label_spec');
        });
    }
};
