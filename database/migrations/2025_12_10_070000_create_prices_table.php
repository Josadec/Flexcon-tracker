<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->onDelete('cascade');
            
            // Precio de muestra (antes unit_price)
            $table->decimal('sample_price', 10, 4);
            
            // Tipo de estación de trabajo
            $table->enum('workstation_type', ['table', 'machine', 'semi_automatic'])->default('table');
            
            $table->date('effective_date');
            $table->boolean('active')->default(true);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['part_id', 'active', 'effective_date']);
            $table->index(['workstation_type']);
        });

        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('min_quantity');
            $table->unsignedInteger('max_quantity')->nullable(); // null = sin límite (ej: 100000+)
            $table->decimal('tier_price', 10, 4);
            $table->timestamps();

            $table->index(['price_id', 'min_quantity']);
            $table->unique(['price_id', 'min_quantity', 'max_quantity'], 'price_tier_unique');
        });

        DB::unprepared("
            CREATE TRIGGER check_unique_active_price_before_insert
            BEFORE INSERT ON prices
            FOR EACH ROW
            BEGIN
                IF NEW.active = 1 THEN
                    IF EXISTS (
                        SELECT 1 FROM prices
                        WHERE part_id = NEW.part_id
                          AND workstation_type = NEW.workstation_type
                          AND active = 1
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'An active price already exists for this workstation type';
                    END IF;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER check_unique_active_price_before_update
            BEFORE UPDATE ON prices
            FOR EACH ROW
            BEGIN
                IF NEW.active = 1 THEN
                    IF EXISTS (
                        SELECT 1 FROM prices
                        WHERE part_id = NEW.part_id
                          AND workstation_type = NEW.workstation_type
                          AND active = 1
                          AND id != NEW.id
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'An active price already exists for this workstation type';
                    END IF;
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS check_unique_active_price_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS check_unique_active_price_before_update');
        Schema::dropIfExists('price_tiers');
        Schema::dropIfExists('prices');
    }
};
