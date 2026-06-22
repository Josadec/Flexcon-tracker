<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ────────────────────────────────────────────────────────────
        // 1. Base — permisos, roles y días festivos
        // ────────────────────────────────────────────────────────────
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            HolidaySeeder::class,
        ]);

        // ────────────────────────────────────────────────────────────
        // 2. Usuarios admin
        // ────────────────────────────────────────────────────────────
        $this->createAdminUsers();

        // ────────────────────────────────────────────────────────────
        // 3. Estructura organizacional — departamentos y áreas
        // ────────────────────────────────────────────────────────────
        $this->call([
            DepartmentSeeder::class,
            AreaSeeder::class,
            DepartmentUsersSeeder::class,
            AreaUsersSeeder::class,
        ]);

        // ────────────────────────────────────────────────────────────
        // 4. Catálogos base — turnos, descansos, tiempo extra
        // ────────────────────────────────────────────────────────────
        $this->call([
            ShiftSeeder::class,
            BreakTimeSeeder::class,
            OverTimeSeeder::class,
        ]);

        // ────────────────────────────────────────────────────────────
        // 5. Estados de producción y workstations
        // ────────────────────────────────────────────────────────────
        $this->call([
            ProductionStatusSeeder::class,
            TableSeeder::class,
            MachineSeeder::class,
            Semi_AutomaticSeeder::class,
        ]);

        // ────────────────────────────────────────────────────────────
        // 6. Empleados (necesitan turnos y áreas)
        // ────────────────────────────────────────────────────────────
        $this->call([
            EmployeeRoleSeeder::class,
            MaterialsRoleSeeder::class,
            EmployeeSeeder::class,
        ]);

        // ────────────────────────────────────────────────────────────
        // 7. Catálogo REAL del cliente — partes + precios + tiers desde los
        // CSV canónicos (Diagramas_flujo/DB/plantillas_importacion). Restaura
        // las 431 partes (27 CRIMP) tras un migrate:fresh.
        // ────────────────────────────────────────────────────────────
        $this->call([
            ClientCatalogImportSeeder::class,
        ]);

        // ────────────────────────────────────────────────────────────
        // 8. Estados de Work Orders y tipos de cargo de Invoice
        // ────────────────────────────────────────────────────────────
        $this->call([
            StatusWOSeeder::class,
            InvoiceChargeTypeSeeder::class,
        ]);

        // ────────────────────────────────────────────────────────────
        // 9. Flujo operacional de prueba (POs → WOs) — DESHABILITADO
        // Depende de partes con estándares activos. Reactivar después de
        // importar el CSV y correr StandardSeeder manualmente si se quiere
        // generar POs/WOs de demostración.
        // ────────────────────────────────────────────────────────────
        // $this->call([
        //     WorkOrderTestSeeder::class,
        // ]);

        // ────────────────────────────────────────────────────────────
        // 10. Demo CRIMP de práctica (Sent List en Empaque con viajeros en
        // distintos estados). Necesita partes (paso 7) y StatusWO (paso 8).
        // ────────────────────────────────────────────────────────────
        $this->call([
            CrimpFlowSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('════════════════════════════════════════════════════════');
        $this->command->info('  Seed completo. Resumen:');
        $this->command->info('════════════════════════════════════════════════════════');
        $this->command->info('  • Admins:     Jonathan, Mauricio, Josadec');
        $this->command->info('  • Estructura: Departamentos, áreas, turnos');
        $this->command->info('  • Recursos:   Mesas, máquinas, semi-automáticos');
        $this->command->info('  • Workflow:   Estados WO, tipos de cargo Invoice');
        $this->command->info('  • Catálogo:   Partes + precios + tiers (CSV del cliente)');
        $this->command->info('  • Demo CRIMP: Lista de envío en Empaque (Paso 5/Paso 6)');
        $this->command->info('');
        $this->command->info('  Empaque CRIMP:  /admin/sent-lists/1  (tab "Empaque")');
        $this->command->info('  Tablero (Mesa): /admin/sent-lists/display/sl/1');
        $this->command->info('════════════════════════════════════════════════════════');
    }

    private function createAdminUsers(): void
    {
        $admins = [
            [
                'email'    => 'JJimenez@ensamblesformula.com',
                'name'     => 'Jonathan',
                'account'  => 'test',
                'password' => 'Flexcon2026',
            ],
            [
                'email'    => 'maubr170295@gmail.com',
                'name'     => 'Mauricio Belmonte',
                'account'  => 'maubr170295',
                'password' => 'admin2026',
            ],
            [
                'email'    => 'josadec@gmail.com',
                'name'     => 'Josadec Pedraza',
                'account'  => 'josadec',
                'password' => 'admin2026',
            ],
        ];

        foreach ($admins as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'account'  => $data['account'],
                    'password' => Hash::make($data['password']),
                ]
            );
            $user->assignRole('admin');
        }
    }
}
