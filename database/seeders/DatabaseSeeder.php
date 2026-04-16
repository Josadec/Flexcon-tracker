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
        $adminUser = User::factory()->create([
            'name' => 'Jonathan',
            'email' => 'JJimenez@ensamblesformula.com',
            'account' => 'test',
            'password' => Hash::make('Flexcon2026'),
        ]);

        // Assign admin role
        $adminUser->assignRole('admin');

        $this->command->info('Admin user created: test@test.com / password');
    }
}
