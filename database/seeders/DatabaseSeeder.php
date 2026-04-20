<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the seeders in order (dependencies first!)
        $this->call([
            // 1. Base: Permisos y Roles
            PermissionSeeder::class,
            RoleSeeder::class,

            // 2. Días festivos
            HolidaySeeder::class,
        ]);

        // Create admin user AFTER roles are created
        $adminUser = User::firstOrCreate(
            ['email' => 'JJimenez@ensamblesformula.com'],
            [
                'name'     => 'Jonathan',
                'account'  => 'test',
                'password' => Hash::make('Flexcon2026'),
            ]
        );
        $adminUser->assignRole('admin');

        $mauricio = User::firstOrCreate(
            ['email' => 'maubr170295@gmail.com'],
            [
                'name'     => 'Mauricio Belmonte',
                'account'  => 'maubr170295',
                'password' => Hash::make('admin2026'),
            ]
        );
        $mauricio->assignRole('admin');

        $josadec = User::firstOrCreate(
            ['email' => 'josadec@gmail.com'],
            [
                'name'     => 'Josadec Pedraza',
                'account'  => 'josadec',
                'password' => Hash::make('admin2026'),
            ]
        );
        $josadec->assignRole('admin');

        $this->command->info('Usuarios admin creados: Jonathan, Mauricio, Josadec');
    }
}
