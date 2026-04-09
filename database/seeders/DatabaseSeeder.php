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
<<<<<<< HEAD

            MaterialsRoleSeeder::class,
            AreaUsersSeeder::class,

            // 2. Catálogos base
            StatusWOSeeder::class,
            ProductionStatusSeeder::class,   // Estados para mesas/máquinas
            DepartmentSeeder::class,         // Departamentos
            AreaSeeder::class,               // Áreas (depende de Departamentos)

            // 3. Turnos y descansos
            ShiftSeeder::class,              // Turnos de producción
            BreakTimeSeeder::class,          // Descansos por turno

            // 4. Estaciones de trabajo (dependen de Áreas y ProductionStatus)
            TableSeeder::class,              // Mesas de trabajo
            Semi_AutomaticSeeder::class,     // Semi-automáticos
            MachineSeeder::class,            // Máquinas

            // 5. Precios y partes
            PriceSeeder::class,              // Precios con tiers

            // 6. Personal
            EmployeeSeeder::class,           // Empleados por turno

            // 7. Estándares (depende de Parts, Tables, Machines)
            StandardSeeder::class,           // Estándares para cálculo de capacidad

            // 8. Datos de prueba
            WorkOrderTestSeeder::class,
=======
>>>>>>> 7e91c1f2f1237c79138a724b3793ab76c5091623

            // 2. Días festivos
            HolidaySeeder::class,
        ]);

<<<<<<< HEAD
        // Create admin user AFTER roles are created.
        // firstOrCreate guarantees that if the user already exists its password
        // and other fields are NEVER overwritten by the seeder or any MCP operation.
        $adminUser = User::firstOrCreate(
            ['email' => 'test@test.com'],           // search key — only used to find the record
            [                                        // these values are only applied on INSERT (new record)
                'name'     => 'Test User',
                'account'  => 'test',
                'password' => Hash::make('password'),
            ]
        );

        // Assign admin role only if not already assigned (idempotent)
        if (! $adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }

        if ($adminUser->wasRecentlyCreated) {
            $this->command->info('Admin user created: test@test.com / password');
        } else {
            $this->command->info('Admin user already exists — password was NOT modified: test@test.com');
        }
=======
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
>>>>>>> 7e91c1f2f1237c79138a724b3793ab76c5091623
    }
}
