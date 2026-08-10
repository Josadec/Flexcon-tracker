<?php

namespace Tests\Feature;

use App\Livewire\Admin\SemiAutomatics\SemiAutomaticCreate;
use App\Livewire\Admin\SemiAutomatics\SemiAutomaticEdit;
use App\Livewire\Admin\SemiAutomatics\SemiAutomaticList;
use App\Livewire\Admin\SemiAutomatics\SemiAutomaticShow;
use App\Models\Area;
use App\Models\Department;
use App\Models\Semi_Automatic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guardar una estación semi-automática estaba roto por partida triple:
 * la regla `unique` apuntaba a `semi_automatics` (la tabla real es
 * `semi__automatics`), el redirect usaba `semi-automatics.index` en vez de
 * `admin.semi-automatics.index`, y `employees` era NOT NULL aunque los
 * formularios lo tratan como opcional.
 */
class SemiAutomaticCrudTest extends TestCase
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

    public function test_crear_sin_numero_de_empleados(): void
    {
        $admin = $this->admin();
        $area = $this->area();

        Livewire::actingAs($admin)->test(SemiAutomaticCreate::class)
            ->set('number', 'SA-01')
            ->set('area_id', $area->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('semi__automatics', ['number' => 'SA-01', 'employees' => null]);
    }

    public function test_editar_guarda_y_regresa_al_listado(): void
    {
        $admin = $this->admin();
        $area = $this->area();
        $otra = $this->area('Empaque');

        $semi = Semi_Automatic::create([
            'number' => 'SA-02', 'employees' => 2, 'active' => true, 'area_id' => $area->id,
        ]);

        Livewire::actingAs($admin)->test(SemiAutomaticEdit::class, ['semiAutomatic' => $semi])
            ->set('number', 'SA-02B')
            ->set('area_id', $otra->id)
            ->set('employees', '')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.semi-automatics.index'));

        $this->assertDatabaseHas('semi__automatics', [
            'id' => $semi->id, 'number' => 'SA-02B', 'area_id' => $otra->id, 'employees' => null,
        ]);
    }

    public function test_el_numero_repetido_se_rechaza_sin_reventar(): void
    {
        $admin = $this->admin();
        $area = $this->area();

        Semi_Automatic::create(['number' => 'SA-10', 'employees' => 1, 'active' => true, 'area_id' => $area->id]);
        $otro = Semi_Automatic::create(['number' => 'SA-11', 'employees' => 1, 'active' => true, 'area_id' => $area->id]);

        Livewire::actingAs($admin)->test(SemiAutomaticEdit::class, ['semiAutomatic' => $otro])
            ->set('number', 'SA-10')
            ->call('update')
            ->assertHasErrors(['number' => 'unique']);
    }

    public function test_listado_filtra_y_elimina(): void
    {
        $admin = $this->admin();
        $area = $this->area();
        $otra = $this->area('Empaque');

        $activo = Semi_Automatic::create(['number' => 'SA-20', 'employees' => 3, 'active' => true, 'area_id' => $area->id]);
        Semi_Automatic::create(['number' => 'SA-21', 'employees' => 1, 'active' => false, 'area_id' => $otra->id]);

        Livewire::actingAs($admin)->test(SemiAutomaticList::class)
            ->assertOk()
            ->assertSee('SA-20')
            ->assertSee('SA-21')
            ->set('filterStatus', '0')->assertSee('SA-21')->assertDontSee('SA-20')
            ->call('clearFilters')
            ->set('filterArea', $area->id)->assertSee('SA-20')->assertDontSee('SA-21')
            ->call('clearFilters')
            ->call('sortBy', 'employees')->assertSet('sortField', 'employees')
            ->call('sortBy', 'comments')->assertSet('sortField', 'employees') // columna no permitida
            ->call('deleteSemiAutomatic', $activo->id);

        $this->assertSoftDeleted('semi__automatics', ['id' => $activo->id]);

        Livewire::actingAs($admin)->test(SemiAutomaticShow::class, ['semiAutomatic' => $activo->fresh()])
            ->assertOk()
            ->assertSee('SA-20')
            ->assertSee('Corte');
    }
}
