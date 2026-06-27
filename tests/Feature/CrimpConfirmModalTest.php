<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\SentListPackagingView;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Paso 5 — Modal de Confirmación de Empaque (diagrama 3 / wireframe).
 * Un solo modal: selecciona lote de CRIMP, captura pesadas (piezas + CRIMP),
 * confirma cantidades → genera "Empaque Terminado" y continúa al Paso 6.
 */
class CrimpConfirmModalTest extends TestCase
{
    use RefreshDatabase;

    private function makeViajero(User $packer): array
    {
        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        $sentList = SentList::create([
            'po_id'                 => $po->id,
            'status'                => SentList::STATUS_PENDING,
            'shift_ids'             => [],
            'num_persons'           => 1,
            'start_date'            => now()->toDateString(),
            'end_date'              => now()->toDateString(),
            'total_available_hours' => 0,
            'used_hours'            => 0,
            'remaining_hours'       => 0,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
            'sent_list_id'      => $sentList->id,
        ]);

        $viajero = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-5', 'quantity' => 1000, 'status' => Lot::STATUS_PENDING,
        ]);

        $cl1 = CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 600]);
        CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-2', 'quantity' => 400]);

        return [$sentList, $viajero, $cl1];
    }

    public function test_confirm_modal_captures_weighings_scoped_to_crimp_lot(): void
    {
        $packer = User::factory()->create();
        $this->actingAs($packer);

        [$sentList, $viajero, $cl1] = $this->makeViajero($packer);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->assertSet('confirmCrimpLotId', $cl1->id) // primer lote por defecto
            ->set('cPieceQty', 300)
            ->call('addConfirmPieceWeighing')
            ->assertHasNoErrors()
            ->set('cCrimpQty', 580)
            ->call('addConfirmCrimpWeighing')
            ->assertHasNoErrors()
            ->call('confirmPackaging')
            ->assertSet('confirmDone', true);

        $pw = PackagingPieceWeighing::where('lot_id', $viajero->id)->first();
        $this->assertNotNull($pw);
        $this->assertSame($cl1->id, $pw->crimp_lot_id);
        $this->assertSame(300, $pw->quantity);
        $this->assertNull($pw->weight); // Paso 5 ya no captura kg

        $cw = PackagingCrimpWeighing::where('lot_id', $viajero->id)->first();
        $this->assertNotNull($cw);
        $this->assertSame($cl1->id, $cw->crimp_lot_id);
        $this->assertSame(580, $cw->quantity);
        $this->assertNull($cw->weight); // Paso 5 ya no captura kg
    }

    public function test_can_edit_a_registered_piece_weighing(): void
    {
        $packer = User::factory()->create();
        $this->actingAs($packer);

        [$sentList, $viajero, $cl1] = $this->makeViajero($packer);

        $component = Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceQty', 300)
            ->call('addConfirmPieceWeighing')
            ->assertHasNoErrors();

        $pw = PackagingPieceWeighing::where('lot_id', $viajero->id)->first();

        // Editar: corregir cantidad equivocada (300 → 250) sin borrar
        $component
            ->call('editConfirmPieceWeighing', $pw->id)
            ->assertSet('editPieceWId', $pw->id)
            ->assertSet('editPieceWQty', 300)
            ->set('editPieceWQty', 250)
            ->call('saveConfirmPieceWeighing')
            ->assertHasNoErrors()
            ->assertSet('editPieceWId', null);

        $this->assertSame(250, $pw->fresh()->quantity);

        // No permite guardar 0
        $component
            ->call('editConfirmPieceWeighing', $pw->id)
            ->set('editPieceWQty', 0)
            ->call('saveConfirmPieceWeighing')
            ->assertHasErrors('editPieceWQty');

        $this->assertSame(250, $pw->fresh()->quantity);
    }

    public function test_confirm_modal_requires_quantity(): void
    {
        $packer = User::factory()->create();
        $this->actingAs($packer);

        [$sentList, $viajero] = $this->makeViajero($packer);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceQty', 0)
            ->call('addConfirmPieceWeighing')
            ->assertHasErrors('cPieceQty');
    }

    public function test_continue_to_paso6_opens_decision_modal(): void
    {
        $packer = User::factory()->create();
        $this->actingAs($packer);

        [$sentList, $viajero] = $this->makeViajero($packer);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->call('goToDecisionFromConfirm')
            ->assertSet('showConfirmModal', false)
            ->assertSet('showDecisionModal', true)
            ->assertSet('decIsCrimp', true);
    }
}
