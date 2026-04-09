<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $roles = [
            'admin',     // Cambiado a minúsculas para consistencia
            'HR',
            'Maintenance',
            'Production',
            'Shipping',
            'Warehouse',
            'Materials',
            'Quality',
            'employee',  // Rol para empleados (panel de empleado)
        ];

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            
            // Asignar permisos según el rol
            switch ($roleName) {
                case 'admin':
                    // Admin tiene todos los permisos
                    $role->syncPermissions(Permission::all());
                    break;
                    
                case 'HR':
                    // HR puede gestionar usuarios y ver reportes
                    $role->syncPermissions([
                        'admin.view-dashboard',
                        'admin.view-reports',
                        'admin.create-reports',
                        'admin.export-reports',
                        'usuarios.view-users',
                        'usuarios.create-users',
                        'usuarios.edit-users',
                        'usuarios.delete-users',
                        'usuarios.view-roles',
                        'catalogos.view-departments',
                        'catalogos.view-areas',
                    ]);
                    break;

                case 'Maintenance':
                case 'Production':
                case 'Shipping':
                case 'Warehouse':
                case 'Materials':
                case 'Quality':
                    // Roles operativos tienen permisos básicos
                    $role->syncPermissions([
                        'admin.view-dashboard',
                        'usuarios.view-users',
                        'admin.view-reports',
                    ]);
                    break;

                case 'employee':
                    // Empleados solo pueden ver su dashboard
                    $role->syncPermissions([
                        'admin.view-dashboard',
                    ]);
                    break;
            }
        }

        // Log the created roles
        $this->command->info('Roles y permisos asignados correctamente!');
    }
}
