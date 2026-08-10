<?php

namespace Tests\Feature;

use App\Livewire\Admin\Materials\DynamicSentListView;
use App\Livewire\Admin\Materials\MaterialsAreaDashboard;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Mesa de trabajo de Materiales: liberar material (paso 3) se hace aquí, y la
 * pantalla distingue viajeros con y sin CRIMP porque el procedimiento cambia.
 */
class MaterialsManageTest extends TestCase
{
    use RefreshDatabase;

    private function materialista(): User
    {
        Role::findOrCreate('Materiales');
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $user->assignRole('Materiales');

        return $user;
    }

    private function viajero(bool $crimp, string $numero = '001'): Lot
    {
        $part = Part::factory()->create(['is_crimp' => $crimp]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 500,
            'status' => Lot::STATUS_PENDING, 'material_status' => 'pending',
        ]);

        if ($crimp) {
            CrimpLot::create([
                'lot_id' => $lot->id, 'crimp_lot_number' => 'CL-' . $numero, 'quantity' => 300,
            ]);
        }

        return $lot;
    }

    public function test_liberar_material_se_hace_aqui_tambien_para_crimp(): void
    {
        $user = $this->materialista();
        $lot = $this->viajero(true);

        Livewire::actingAs($user)->test(DynamicSentListView::class)
            ->call('openMaterialModal', $lot->id)
            ->assertSet('materialStatus', 'pending')
            ->call('setMaterialStatus', 'released')
            ->call('saveMaterialStatus')
            ->assertHasNoErrors();

        $this->assertSame('released', $lot->fresh()->material_status);
    }

    public function test_no_se_rechaza_material_de_un_viajero_ya_producido(): void
    {
        $user = $this->materialista();
        $lot = $this->viajero(false);
        $lot->update(['material_status' => 'released']);

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 500, 'good_pieces' => 120,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(DynamicSentListView::class)
            ->call('openMaterialModal', $lot->id)
            ->call('setMaterialStatus', 'rejected')
            ->call('saveMaterialStatus')
            ->assertHasErrors('materialStatus');

        $this->assertSame('released', $lot->fresh()->material_status);
    }

    public function test_el_filtro_separa_viajeros_con_y_sin_crimp(): void
    {
        $user = $this->materialista();
        $conCrimp = $this->viajero(true, 'C-1');
        $sinCrimp = $this->viajero(false, 'S-1');

        Livewire::actingAs($user)->test(DynamicSentListView::class)
            ->assertSee('C-1')->assertSee('S-1')
            ->set('filterType', 'crimp')->assertSee('C-1')->assertDontSee('S-1')
            ->set('filterType', 'standard')->assertSee('S-1')->assertDontSee('C-1')
            ->call('clearFilters')
            ->set('filterMaterial', 'released')->assertDontSee('C-1');
    }

    public function test_no_se_repite_el_numero_de_viajero_en_la_misma_orden(): void
    {
        $user = $this->materialista();
        $lot = $this->viajero(false, '001');

        Livewire::actingAs($user)->test(DynamicSentListView::class)
            ->call('openCreateLotModal', $lot->work_order_id)
            ->set('newLotNumber', '001')
            ->set('newLotQuantity', 100)
            ->call('createLot')
            ->assertHasErrors(['newLotNumber' => 'unique']);
    }

    public function test_los_pendientes_de_materiales_se_listan_arriba(): void
    {
        $user = $this->materialista();
        $this->viajero(true, 'C-9');

        Livewire::actingAs($user)->test(DynamicSentListView::class)
            ->assertOk()
            ->assertSee('Lo que te toca ahora')
            ->assertSee('Liberar material')
            ->assertSee('C-9');
    }

    public function test_el_panel_del_area_cuenta_el_trabajo_pendiente(): void
    {
        $user = $this->materialista();
        $this->viajero(true, 'C-1');
        $this->viajero(false, 'S-1');

        $stats = Livewire::actingAs($user)->test(MaterialsAreaDashboard::class)
            ->assertOk()
            ->assertSee('Gestión de materiales')
            ->viewData('stats');

        $this->assertSame(2, $stats['por_liberar']);
        $this->assertSame(1, $stats['por_liberar_crimp']);
        $this->assertSame(1, $stats['viajeros_crimp']);
        $this->assertSame(0, $stats['crimp_sin_lotes']);
    }
}
