<?php

namespace Tests\Feature;

use App\Livewire\Admin\Tables\TableEdit;
use App\Livewire\Admin\Tables\TableList;
use App\Models\Area;
use App\Models\Department;
use App\Models\ProductionStatus;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La ficha de la mesa captura nombre y datos de equipo, pero la migración
 * original de `tables` no creaba esas columnas: guardar reventaba con
 * "Unknown column". Estas pruebas fijan que el alta de columnas siga presente
 * y que el buscador del listado cubra los campos que promete.
 */
class TableEquipmentFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $user->assignRole('admin');

        return $user;
    }

    private function area(): Area
    {
        $dept = Department::create(['name' => 'Producción']);

        return Area::create(['name' => 'Corte', 'department_id' => $dept->id]);
    }

    public function test_editar_una_mesa_guarda_nombre_y_datos_de_equipo(): void
    {
        $admin = $this->admin();
        $area = $this->area();
        $status = ProductionStatus::create(['name' => 'En proceso', 'color' => '#0ea5e9', 'order' => 1, 'active' => true]);

        $table = Table::create([
            'number' => 'M-02', 'employees' => 2, 'active' => true, 'area_id' => $area->id,
        ]);

        Livewire::actingAs($admin)->test(TableEdit::class, ['table' => $table])
            ->set('production_status_id', $status->id)
            ->set('name', 'Mesa grande')
            ->set('brand', 'Bosch')
            ->set('model', 'X-200')
            ->set('s_n', 'SN-0001')
            ->set('asset_number', 'AC-123')
            ->set('description', 'Mesa de doble estación')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'name' => 'Mesa grande',
            'brand' => 'Bosch',
            'model' => 'X-200',
            's_n' => 'SN-0001',
            'asset_number' => 'AC-123',
            'description' => 'Mesa de doble estación',
        ]);
    }

    public function test_el_buscador_cubre_numero_nombre_y_numero_de_activo(): void
    {
        $admin = $this->admin();
        $area = $this->area();

        Table::create([
            'number' => 'M-01', 'name' => 'Mesa grande', 'asset_number' => 'AC-999',
            'employees' => 4, 'active' => true, 'area_id' => $area->id,
        ]);
        Table::create([
            'number' => 'M-02', 'employees' => 2, 'active' => true, 'area_id' => $area->id,
        ]);

        Livewire::actingAs($admin)->test(TableList::class)
            ->set('search', 'M-02')->assertSee('M-02')->assertDontSee('M-01')
            ->set('search', 'grande')->assertSee('M-01')->assertDontSee('M-02')
            ->set('search', 'AC-999')->assertSee('M-01')->assertDontSee('M-02');
    }
}
