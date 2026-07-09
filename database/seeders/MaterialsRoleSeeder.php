<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class MaterialsRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions for Materials area
        $permissions = [
            'view_materials_area',
            'manage_lots',
            'manage_kits',
            'submit_to_quality',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Materiales role (rol canónico en español)
        $materialsRole = Role::firstOrCreate(['name' => 'Materiales']);

        // Assign all permissions to Materiales role. Usamos givePermissionTo (no sync)
        // para no borrar los permisos 'materiales.*' que asigna AreaUsersSeeder.
        $materialsRole->givePermissionTo($permissions);

        $this->command->info('Materiales role and permissions created successfully.');

        // Assign materials permissions to admin role (el rol admin es minúsculas)
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
            $this->command->info('Materials permissions assigned to admin role.');
        } else {
            $this->command->warn('admin role not found. Materials permissions not assigned to admin.');
        }

        // Create Quality role permissions if they don't exist
        $qualityPermissions = [
            'view_quality_area',
            'approve_kits',
            'reject_kits',
            'create_kit_incidents',
        ];

        foreach ($qualityPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Calidad role (rol canónico en español)
        $qualityRole = Role::firstOrCreate(['name' => 'Calidad']);

        // Assign permissions to Calidad role sin borrar los 'calidad.*' de AreaUsersSeeder.
        $qualityRole->givePermissionTo($qualityPermissions);

        $this->command->info('Calidad role and permissions created successfully.');
    }
}
