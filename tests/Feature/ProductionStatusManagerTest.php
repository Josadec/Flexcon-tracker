<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProductionStatuses\ProductionStatusManager;
use App\Livewire\Admin\Tables\TableEdit;
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
 * El catálogo de estados de producción se administra desde un modal
 * reutilizable, montado hoy en Mesas. Lo comparten mesas, semi-automáticos y
 * máquinas, así que un estado en uso no se puede eliminar.
 */
class ProductionStatusManagerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $user->assignRole('admin');

        return $user;
    }

    private function makeStatus(string $name, int $order = 1): ProductionStatus
    {
        return ProductionStatus::create([
            'name' => $name, 'color' => '#10b981', 'order' => $order, 'active' => true,
        ]);
    }

    public function test_crea_un_estado_y_avisa_a_la_pantalla_anfitriona(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(ProductionStatusManager::class)
            ->call('open')
            ->assertSet('show', true)
            ->call('startCreate')
            ->assertSet('order', '1') // sugiere el siguiente lugar
            ->set('name', 'En proceso')
            ->set('color', '#0EA5E9')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('production-statuses-updated')
            ->assertDispatched('production-status-created')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('production_statuses', ['name' => 'En proceso', 'color' => '#0EA5E9', 'order' => 1]);
    }

    public function test_edita_un_estado_existente(): void
    {
        $admin = $this->admin();
        $status = $this->makeStatus('Pendiente');

        Livewire::actingAs($admin)->test(ProductionStatusManager::class)
            ->call('open')
            ->call('startEdit', $status->id)
            ->assertSet('name', 'Pendiente')
            ->set('name', 'Pendiente de material')
            ->call('save')
            ->assertHasNoErrors()
            // Editar no debe reasignar selecciones en la pantalla anfitriona.
            ->assertNotDispatched('production-status-created');

        $this->assertDatabaseHas('production_statuses', ['id' => $status->id, 'name' => 'Pendiente de material']);
    }

    public function test_nombre_repetido_se_rechaza(): void
    {
        $admin = $this->admin();
        $this->makeStatus('En proceso');

        Livewire::actingAs($admin)->test(ProductionStatusManager::class)
            ->call('open')
            ->call('startCreate')
            ->set('name', 'En proceso')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);
    }

    public function test_no_elimina_un_estado_en_uso(): void
    {
        $admin = $this->admin();
        $status = $this->makeStatus('En proceso');

        $dept = Department::create(['name' => 'Producción']);
        $area = Area::create(['name' => 'Corte', 'department_id' => $dept->id]);
        Table::create([
            'number' => 'M-01', 'employees' => 2, 'active' => true,
            'area_id' => $area->id, 'production_status_id' => $status->id,
        ]);

        Livewire::actingAs($admin)->test(ProductionStatusManager::class)
            ->call('open')
            ->call('delete', $status->id)
            ->assertSet('feedbackTone', 'danger');

        $this->assertDatabaseHas('production_statuses', ['id' => $status->id, 'deleted_at' => null]);
    }

    public function test_elimina_un_estado_sin_usar(): void
    {
        $admin = $this->admin();
        $status = $this->makeStatus('Sin usar');

        Livewire::actingAs($admin)->test(ProductionStatusManager::class)
            ->call('open')
            ->call('delete', $status->id)
            ->assertSet('feedbackTone', 'success')
            ->assertDispatched('production-statuses-updated');

        $this->assertSoftDeleted('production_statuses', ['id' => $status->id]);
    }

    public function test_la_mesa_en_edicion_toma_el_estado_recien_creado(): void
    {
        $admin = $this->admin();
        $dept = Department::create(['name' => 'Producción']);
        $area = Area::create(['name' => 'Corte', 'department_id' => $dept->id]);
        $table = Table::create([
            'number' => 'M-05', 'employees' => 1, 'active' => true, 'area_id' => $area->id,
        ]);
        $nuevo = $this->makeStatus('Recién creado');

        Livewire::actingAs($admin)->test(TableEdit::class, ['table' => $table])
            ->assertSet('production_status_id', '')
            ->call('useNewProductionStatus', $nuevo->id)
            ->assertSet('production_status_id', (string) $nuevo->id);
    }
}
