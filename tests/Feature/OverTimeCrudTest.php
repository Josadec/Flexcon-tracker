<?php

namespace Tests\Feature;

use App\Livewire\Admin\OverTimes\OverTimeCreate;
use App\Livewire\Admin\OverTimes\OverTimeEdit;
use App\Livewire\Admin\OverTimes\OverTimeList;
use App\Livewire\Admin\OverTimes\OverTimeShow;
use App\Models\OverTime;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OverTimeCrudTest extends TestCase
{
    // Cubre el cálculo de horas (netas y hombre), que depende de que la hora
    // cruda se lea igual venga como "08:00" o como "08:00:00", y que la
    // búsqueda no se escape del filtro de turno.

    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $u = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $u->assignRole('admin');

        return $u;
    }

    private function employee(string $name): User
    {
        $e = User::factory()->create(['name' => $name, 'last_name' => 'Pérez', 'active' => true]);
        $e->assignRole('employee');

        return $e;
    }

    public function test_listado_filtra_y_calcula_horas(): void
    {
        $admin = $this->admin();
        $manana = Shift::create(['name' => 'Matutino', 'start_time' => '06:00', 'end_time' => '14:00', 'active' => true]);
        $noche = Shift::create(['name' => 'Nocturno', 'start_time' => '22:00', 'end_time' => '06:00', 'active' => true]);

        $futuro = OverTime::create([
            'name' => 'Sábado extra', 'date' => now()->addDays(5)->toDateString(),
            'start_time' => '08:00', 'end_time' => '12:00', 'break_minutes' => 30, 'shift_id' => $manana->id,
        ]);
        $futuro->users()->sync([$this->employee('Beto')->id, $this->employee('Ciro')->id]);

        $pasado = OverTime::create([
            'name' => 'Domingo viejo', 'date' => now()->subDays(5)->toDateString(),
            'start_time' => '08:00', 'end_time' => '10:00', 'break_minutes' => 0, 'shift_id' => $noche->id,
        ]);

        Livewire::actingAs($admin)->test(OverTimeList::class)
            ->assertOk()
            ->assertSee('Sábado extra')
            ->assertSee('Domingo viejo')
            ->assertSee('3.5 h')  // horas netas: 4 h menos 30 min
            ->assertSee('7 h')    // horas-hombre: 3.5 × 2 empleados
            ->assertSee('2 empleados')
            ->set('filterWhen', 'upcoming')->assertSee('Sábado extra')->assertDontSee('Domingo viejo')
            ->call('clearFilters')
            // La búsqueda agrupada no debe escaparse del filtro de turno.
            ->set('filterShift', (string) $manana->id)
            ->set('search', 'Domingo')->assertDontSee('Domingo viejo')
            ->call('clearFilters')
            ->set('search', 'Nocturno')->assertSee('Domingo viejo')->assertDontSee('Sábado extra')
            ->call('clearFilters')
            ->call('sortBy', 'name')->assertSet('sortField', 'name')
            ->call('sortBy', 'comments')->assertSet('sortField', 'name')
            ->call('deleteOverTime', $pasado->id);

        $this->assertDatabaseMissing('over_times', ['id' => $pasado->id]);
    }

    public function test_alta_con_empleados_y_quitar_uno(): void
    {
        $admin = $this->admin();
        $shift = Shift::create(['name' => 'Matutino', 'start_time' => '06:00', 'end_time' => '14:00', 'active' => true]);
        $beto = $this->employee('Beto');
        $ciro = $this->employee('Ciro');

        Livewire::actingAs($admin)->test(OverTimeCreate::class)
            ->assertOk()
            ->assertSee('Beto Pérez')
            ->set('name', 'Extra lunes')
            ->set('date', now()->addDay()->toDateString())
            ->set('shift_id', (string) $shift->id)
            ->set('start_time', '15:00')
            ->set('end_time', '18:00')
            ->set('break_minutes', '0')
            ->set('selectedEmployeeIds', [(string) $beto->id, (string) $ciro->id])
            ->assertSee('Ciro Pérez')
            ->call('removeEmployee', (string) $ciro->id)
            ->assertSet('selectedEmployeeIds', [(string) $beto->id])
            ->call('save')
            ->assertHasNoErrors();

        $ot = OverTime::where('name', 'Extra lunes')->firstOrFail();
        $this->assertSame(1, $ot->users()->count());
        $this->assertEquals(3.0, $ot->net_hours);
        $this->assertEquals(3.0, $ot->total_hours);
    }

    public function test_edicion_y_detalle(): void
    {
        $admin = $this->admin();
        $beto = $this->employee('Beto');

        $ot = OverTime::create([
            'name' => 'Extra martes', 'date' => now()->addDays(2)->toDateString(),
            'start_time' => '15:00', 'end_time' => '19:00', 'break_minutes' => 0,
        ]);
        $ot->users()->sync([$beto->id]);

        Livewire::actingAs($admin)->test(OverTimeEdit::class, ['overTime' => $ot])
            ->assertOk()
            ->assertSet('start_time', '15:00')
            ->set('break_minutes', '60')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.over-times.show', $ot));

        $this->assertEquals(3.0, $ot->fresh()->net_hours);

        Livewire::actingAs($admin)->test(OverTimeShow::class, ['overTime' => $ot->fresh()])
            ->assertOk()
            ->assertSee('Extra martes')
            ->assertSee('Beto Pérez')
            ->assertSee('Empleados convocados');
    }
}
