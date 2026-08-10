<?php

namespace Tests\Feature;

use App\Livewire\Admin\Machines\MachineCreate;
use App\Livewire\Admin\Machines\MachineEdit;
use App\Livewire\Admin\Machines\MachineList;
use App\Livewire\Admin\Machines\MachineShow;
use App\Models\Area;
use App\Models\Department;
use App\Models\Machine;
use App\Models\ProductionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guardar una máquina estaba roto por tres lados: el redirect de edición usaba
 * `machines.index` en vez de `admin.machines.index`; employees, setup_time y
 * maintenance_time eran NOT NULL aunque los formularios los declaran
 * opcionales; y `asset_number` es único en la base pero no había regla que lo
 * validara, así que un activo repetido salía como error de SQL.
 */
class MachineCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $user->assignRole('admin');

        return $user;
    }

    private function area(string $name = 'Corte'): Area
    {
        $dept = Department::firstOrCreate(['name' => 'Producción']);

        return Area::create(['name' => $name, 'department_id' => $dept->id]);
    }

    public function test_crear_sin_empleados_ni_tiempos(): void
    {
        $admin = $this->admin();
        $area = $this->area();

        Livewire::actingAs($admin)->test(MachineCreate::class)
            ->set('name', 'Prensa 1')
            ->set('area_id', $area->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('machines', [
            'name' => 'Prensa 1', 'employees' => null, 'setup_time' => null, 'maintenance_time' => null,
        ]);
    }

    public function test_editar_guarda_y_regresa_al_listado(): void
    {
        $admin = $this->admin();
        $area = $this->area();
        $otra = $this->area('Empaque');

        $machine = Machine::create([
            'name' => 'Prensa 2', 'employees' => 2, 'setup_time' => 10, 'maintenance_time' => 5,
            'active' => true, 'area_id' => $area->id,
        ]);

        Livewire::actingAs($admin)->test(MachineEdit::class, ['machine' => $machine])
            ->set('name', 'Prensa 2B')
            ->set('area_id', $otra->id)
            ->set('setup_time', '12.5')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.machines.index'));

        $this->assertDatabaseHas('machines', ['id' => $machine->id, 'name' => 'Prensa 2B', 'area_id' => $otra->id]);
    }

    public function test_numero_de_activo_repetido_se_rechaza_con_validacion(): void
    {
        $admin = $this->admin();
        $area = $this->area();

        Machine::create([
            'name' => 'Prensa 3', 'asset_number' => 'AC-500', 'employees' => 1,
            'setup_time' => 1, 'maintenance_time' => 1, 'active' => true, 'area_id' => $area->id,
        ]);

        Livewire::actingAs($admin)->test(MachineCreate::class)
            ->set('name', 'Prensa 4')
            ->set('area_id', $area->id)
            ->set('asset_number', 'AC-500')
            ->call('save')
            ->assertHasErrors(['asset_number' => 'unique']);
    }

    public function test_estado_de_produccion_se_guarda_y_se_muestra(): void
    {
        $admin = $this->admin();
        $area = $this->area();
        $status = ProductionStatus::create([
            'name' => 'En proceso', 'color' => '#0ea5e9', 'order' => 1, 'active' => true,
        ]);

        Livewire::actingAs($admin)->test(MachineCreate::class)
            ->assertSee('En proceso')
            ->set('name', 'Prensa 5')
            ->set('area_id', $area->id)
            ->set('production_status_id', $status->id)
            ->call('save')
            ->assertHasNoErrors();

        $machine = Machine::where('name', 'Prensa 5')->firstOrFail();
        $this->assertSame($status->id, $machine->production_status_id);

        Livewire::actingAs($admin)->test(MachineShow::class, ['machine' => $machine])
            ->assertOk()
            ->assertSee('En proceso');
    }

    public function test_listado_filtra_ordena_y_elimina(): void
    {
        $admin = $this->admin();
        $area = $this->area();
        $otra = $this->area('Empaque');

        $activa = Machine::create([
            'name' => 'Prensa A', 'brand' => 'Bosch', 'employees' => 3,
            'setup_time' => 8, 'maintenance_time' => 2, 'active' => true, 'area_id' => $area->id,
        ]);
        Machine::create([
            'name' => 'Prensa B', 'employees' => 1, 'setup_time' => 1, 'maintenance_time' => 1,
            'active' => false, 'area_id' => $otra->id,
        ]);

        Livewire::actingAs($admin)->test(MachineList::class)
            ->assertOk()
            ->assertSee('Prensa A')
            ->assertSee('Prensa B')
            ->set('search', 'Bosch')->assertSee('Prensa A')->assertDontSee('Prensa B')
            ->call('clearFilters')
            ->set('filterStatus', '0')->assertSee('Prensa B')->assertDontSee('Prensa A')
            ->call('clearFilters')
            ->set('filterArea', $area->id)->assertSee('Prensa A')->assertDontSee('Prensa B')
            ->call('clearFilters')
            ->call('sortBy', 'setup_time')->assertSet('sortField', 'setup_time')
            ->call('sortBy', 'comments')->assertSet('sortField', 'setup_time') // columna no permitida
            ->call('deleteMachine', $activa->id);

        $this->assertSoftDeleted('machines', ['id' => $activa->id]);
    }
}
