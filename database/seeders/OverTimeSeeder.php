<?php

namespace Database\Seeders;

use App\Models\OverTime;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class OverTimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar que existan turnos
        if (Shift::count() === 0) {
            $this->command->warn('No hay turnos creados. Ejecute ShiftSeeder primero.');
            return;
        }

        $this->command->info('Creando overtimes de ejemplo...');

        $shift1 = Shift::where('name', 'like', '%Turno 1%')->first()
                      ?? Shift::first();
        $shift2 = Shift::where('name', 'like', '%Turno 2%')->first()
                      ?? Shift::skip(1)->first()
                      ?? $shift1;

        // Obtener empleados activos para asignar
        $employees = User::active()->employees()->get();

        // Helper para asignar empleados aleatorios
        $assignEmployees = function (OverTime $overTime, int $count) use ($employees) {
            if ($employees->isNotEmpty()) {
                $selected = $employees->random(min($count, $employees->count()));
                $overTime->users()->sync($selected->pluck('id')->toArray());
            }
        };

        // Overtime 1: Producción urgente - Turno 1
        $ot1 = OverTime::create([
            'name'          => 'Overtime Producción Urgente - Cliente ABC',
            'shift_id'      => $shift1->id,
            'date'          => Carbon::today()->addDays(2),
            'start_time'    => '17:00',
            'end_time'      => '21:00',
            'break_minutes' => 15,
            'comments'      => 'Pedido urgente cliente ABC - Deadline viernes',
        ]);
        $assignEmployees($ot1, 12);

        // Overtime 2: Fin de semana
        $ot2 = OverTime::create([
            'name'          => 'Overtime Fin de Semana',
            'shift_id'      => $shift1->id,
            'date'          => Carbon::today()->next('Saturday'),
            'start_time'    => '08:00',
            'end_time'      => '17:00',
            'break_minutes' => 60,
            'comments'      => 'Producción extra para cumplir cuota mensual',
        ]);
        $assignEmployees($ot2, 20);

        // Overtime 3: Nocturno (cruza medianoche)
        $ot3 = OverTime::create([
            'name'          => 'Overtime Nocturno - Turno 2',
            'shift_id'      => $shift2->id,
            'date'          => Carbon::today()->addDays(5),
            'start_time'    => '22:00',
            'end_time'      => '02:00',
            'break_minutes' => 30,
            'comments'      => 'Producción nocturna - máquina X disponible',
        ]);
        $assignEmployees($ot3, 8);

        // Overtime 4: Corto - 2 horas
        $ot4 = OverTime::create([
            'name'          => 'Overtime Corto - Completar Lote',
            'shift_id'      => $shift1->id,
            'date'          => Carbon::today()->addDays(3),
            'start_time'    => '17:00',
            'end_time'      => '19:00',
            'break_minutes' => 0,
            'comments'      => 'Completar lote L-12345',
        ]);
        $assignEmployees($ot4, 6);

        // Overtime 5: Extendido - 6 horas
        $ot5 = OverTime::create([
            'name'          => 'Overtime Extendido - Pedido Especial',
            'shift_id'      => $shift1->id,
            'date'          => Carbon::today()->addWeeks(1),
            'start_time'    => '17:00',
            'end_time'      => '23:00',
            'break_minutes' => 45,
            'comments'      => 'Cliente prioritario - pago de overtime 2x',
        ]);
        $assignEmployees($ot5, 15);

        // Generar 10 overtimes aleatorios adicionales
        // La factory ya asigna empleados via afterCreating
        OverTime::factory()->count(10)->create();

        $this->command->info('Overtimes creados exitosamente');
        $this->command->info('   - 5 overtimes específicos');
        $this->command->info('   - 10 overtimes aleatorios');
        $this->command->table(
            ['Total de Overtimes'],
            [[OverTime::count()]]
        );
    }
}
