<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates standard shift configurations for production planning.
     * These are initial/default shifts that can be modified by administrators.
     */
    public function run(): void
    {
        // Clear existing shifts if running in fresh environment
        if (app()->environment('local')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Shift::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $shifts = [
            [
                'name' => 'Primer Turno',
                'start_time' => '07:00:00',
                'end_time' => '17:00:00',
                'active' => 1,
                'comments' => 'Primer turno: 7:00 AM - 5:00 PM (10 horas)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Segundo Turno',
                'start_time' => '17:00:00',
                'end_time' => '02:00:00',
                'active' => 1,
                'comments' => 'Segundo turno: 5:00 PM - 2:00 AM del día siguiente (9 horas, cruza medianoche)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($shifts as $shift) {
            Shift::firstOrCreate(
                ['name' => $shift['name']], // Unique identifier
                $shift // All attributes
            );
        }

        $this->command->info('Shifts seeded successfully!');
        $this->command->info('Total shifts created: ' . Shift::count());
    }
}
