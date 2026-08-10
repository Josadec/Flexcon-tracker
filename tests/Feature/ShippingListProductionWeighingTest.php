<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\ShippingListDisplay;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tablero de piso (/admin/sent-lists/display): el modal de Producción debe
 * dejar corregir y borrar pesadas, no sólo agregarlas. Sin esto, una captura
 * de más (p.ej. 200 pz en un lote de 100) quedaba imposible de arreglar.
 */
class ShippingListProductionWeighingTest extends TestCase
{
    use RefreshDatabase;

    private function makeLot(bool $isCrimp = false): Lot
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $part = Part::factory()->create(['is_crimp' => $isCrimp]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 100]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        return Lot::create([
            'work_order_id'     => $wo->id,
            'lot_number'        => '003',
            'quantity'          => 100,
            'status'            => Lot::STATUS_IN_PROGRESS,
            'material_status'   => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
        ]);
    }

    private function weighing(Lot $lot, int $good): Weighing
    {
        return Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => $lot->quantity,
            'good_pieces' => $good, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);
    }

    public function test_el_modal_lista_las_pesadas_ya_registradas(): void
    {
        $lot = $this->makeLot();
        $this->weighing($lot, 100);
        $this->weighing($lot, 100);

        Livewire::test(ShippingListDisplay::class)
            ->call('openProductionModal', $lot->id)
            ->assertSet('showProductionModal', true)
            ->assertSet('prodAlreadyWeighed', 200)
            ->assertCount('prodWeighingsList', 2);
    }

    public function test_editar_una_pesada_corrige_la_cantidad(): void
    {
        $lot = $this->makeLot();
        $this->weighing($lot, 100);
        $w2 = $this->weighing($lot, 100);   // captura de más: 200 en un lote de 100

        Livewire::test(ShippingListDisplay::class)
            ->call('openProductionModal', $lot->id)
            ->call('editProductionWeighing', $w2->id)
            ->assertSet('prodEditingId', $w2->id)
            ->assertSet('prodWeighedPieces', 100)
            ->set('prodWeighedPieces', 20)
            ->call('saveProduction')
            ->assertHasNoErrors()
            ->assertSet('prodAlreadyWeighed', 120);

        $this->assertSame(20, $w2->fresh()->good_pieces);
    }

    public function test_eliminar_una_pesada(): void
    {
        $lot = $this->makeLot();
        $this->weighing($lot, 100);
        $w2 = $this->weighing($lot, 100);

        Livewire::test(ShippingListDisplay::class)
            ->call('openProductionModal', $lot->id)
            ->call('deleteProductionWeighing', $w2->id)
            ->assertSet('prodAlreadyWeighed', 100);

        $this->assertSoftDeleted('weighings', ['id' => $w2->id]);
    }

    public function test_no_se_puede_borrar_por_debajo_de_lo_que_calidad_ya_verifico(): void
    {
        $lot = $this->makeLot();
        $w1  = $this->weighing($lot, 100);

        QualityWeighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'production_good_pieces' => 100,
            'good_pieces' => 80, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);

        Livewire::test(ShippingListDisplay::class)
            ->call('openProductionModal', $lot->id)
            ->call('deleteProductionWeighing', $w1->id)
            ->assertSee('Calidad ya verific');

        $this->assertNotSoftDeleted('weighings', ['id' => $w1->id]);
    }

    public function test_no_se_puede_editar_por_debajo_de_lo_que_calidad_ya_verifico(): void
    {
        $lot = $this->makeLot();
        $w1  = $this->weighing($lot, 100);

        QualityWeighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'production_good_pieces' => 100,
            'good_pieces' => 80, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);

        Livewire::test(ShippingListDisplay::class)
            ->call('openProductionModal', $lot->id)
            ->call('editProductionWeighing', $w1->id)
            ->set('prodWeighedPieces', 50)
            ->call('saveProduction')
            ->assertHasErrors('prodWeighedPieces');

        $this->assertSame(100, $w1->fresh()->good_pieces);
    }

    public function test_no_se_puede_tocar_una_pesada_de_otro_lote(): void
    {
        $lotA = $this->makeLot();
        $lotB = $this->makeLot();
        $wB   = $this->weighing($lotB, 50);

        Livewire::test(ShippingListDisplay::class)
            ->call('openProductionModal', $lotA->id)
            ->call('deleteProductionWeighing', $wB->id);

        $this->assertNotSoftDeleted('weighings', ['id' => $wB->id]);
    }
}
