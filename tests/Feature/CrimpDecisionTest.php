<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\SentListPackagingView;
use App\Livewire\Admin\SentLists\ShippingListDisplay;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * M6 — Paso 6 (diagrama 4): decisiones CRIMP D2a/D2c (fórmula) y D3 (redondeo a 100).
 *
 * Escenario: disponibles (Calidad) 850, piezas empacadas 500 → sobrante piezas 350.
 * Objetivo CRIMP 1000, CRIMP empacados 800 → sobrante CRIMP 200.
 * Completar CRIMP = 350 − 200 = 150. Nuevo lote D3 = floor(350/100)*100 = 300.
 */
class CrimpDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function makeScenario(): array
    {
        // El actor debe tener rol Empaques: la vista de Empaque ahora exige que el
        // usuario pertenezca al departamento y que la lista esté en la etapa 'envios'.
        Role::firstOrCreate(['name' => 'Empaques', 'guard_name' => 'web']);
        $packer = User::factory()->create();
        $packer->assignRole('Empaques');
        $this->actingAs($packer);

        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        $sentList = SentList::create([
            'po_id' => $po->id, 'status' => SentList::STATUS_PENDING, 'shift_ids' => [],
            'current_department' => SentList::DEPT_SHIPPING,
            'num_persons' => 1, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'total_available_hours' => 0, 'used_hours' => 0, 'remaining_hours' => 0,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(),
            'sent_pieces' => 0, 'sent_list_id' => $sentList->id,
        ]);

        $viajero = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-D', 'quantity' => 1000, 'status' => Lot::STATUS_PENDING,
        ]);

        CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 1000]);
        QualityWeighing::create([
            'lot_id' => $viajero->id, 'production_good_pieces' => 850, 'good_pieces' => 850, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => $packer->id,
        ]);
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'quantity' => 500, 'weighed_at' => now(), 'weighed_by' => $packer->id]);
        PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'quantity' => 800, 'weighed_at' => now(), 'weighed_by' => $packer->id]);

        return [$sentList, $viajero];
    }

    public function test_d2a_completar_crimp_uses_formula(): void
    {
        [$sentList, $viajero] = $this->makeScenario();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openDecisionModal', $viajero->id)
            ->assertSet('decCompletarCrimp', 150)   // 350 − 200
            ->call('decisionCompleteCrimp')
            ->assertHasNoErrors();

        $viajero->refresh();
        $this->assertSame(Lot::CLOSURE_COMPLETE_CRIMP, $viajero->closure_decision);
        $this->assertSame(150, $viajero->complete_crimp_qty);
        $this->assertNull($viajero->complete_pieces_qty);
    }

    public function test_d2c_completar_ambos_records_both(): void
    {
        [$sentList, $viajero] = $this->makeScenario();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openDecisionModal', $viajero->id)
            ->call('decisionCompleteBoth')
            ->assertHasNoErrors();

        $viajero->refresh();
        $this->assertSame(Lot::CLOSURE_COMPLETE_BOTH, $viajero->closure_decision);
        $this->assertSame(150, $viajero->complete_crimp_qty);
        $this->assertSame(350, $viajero->complete_pieces_qty); // sobrante piezas
    }

    public function test_d3_new_lot_rounds_down_to_hundreds(): void
    {
        [$sentList, $viajero] = $this->makeScenario();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openDecisionModal', $viajero->id)
            ->call('decisionNewLot')
            ->assertSet('createLotQuantity', 300) // floor(350/100)*100
            ->assertSet('showCreateLotFormModal', true);
    }

    // ---------------------------------------------------------------------
    // Hueco D2 → cola de shipping (marca diferida al Paso 7).
    // ---------------------------------------------------------------------

    private function packagingUser(): User
    {
        Role::firstOrCreate(['name' => 'Empaques', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->assignRole('Empaques');
        return $u;
    }

    private function materialsUser(): User
    {
        Role::firstOrCreate(['name' => 'Materiales', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->assignRole('Materiales');
        return $u;
    }

    /** Toma una decisión D2 y devuelve el viajero refrescado (aún NO recibido). */
    private function decideCompletion(SentList $sentList, Lot $viajero, string $method): Lot
    {
        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openDecisionModal', $viajero->id)
            ->call($method)
            ->assertHasNoErrors();

        return $viajero->refresh();
    }

    #[DataProvider('completionDecisions')]
    public function test_d2_not_ready_until_viajero_received(string $method, string $decision): void
    {
        [$sentList, $viajero] = $this->makeScenario();

        // Paso 6: la decisión dispara el observer, que NO marca los complete_*.
        $this->decideCompletion($sentList, $viajero, $method);
        $this->assertSame($decision, $viajero->closure_decision);
        $this->assertFalse((bool) $viajero->ready_for_shipping);
        $this->assertFalse(Lot::readyForShipping()->whereKey($viajero->id)->exists());

        // Paso 7: Empaque recibe el viajero → ahora entra a la cola.
        $this->actingAs($this->packagingUser());
        Livewire::test(ShippingListDisplay::class)
            ->call('markViajeroReceived', $viajero->id)
            ->assertHasNoErrors();

        $viajero->refresh();
        $this->assertTrue((bool) $viajero->ready_for_shipping);
        $this->assertSame(500, $viajero->quantity_packed_final); // getPackagedPiecesTotal()
        $this->assertSame($decision, $viajero->closed_by_type);
        $this->assertTrue(Lot::readyForShipping()->whereKey($viajero->id)->exists());
    }

    public static function completionDecisions(): array
    {
        return [
            'D2a completar CRIMP'  => ['decisionCompleteCrimp', Lot::CLOSURE_COMPLETE_CRIMP],
            'D2b completar piezas' => ['decisionCompletePieces', Lot::CLOSURE_COMPLETE_PIECES],
            'D2c completar ambos'  => ['decisionCompleteBoth', Lot::CLOSURE_COMPLETE_BOTH],
        ];
    }

    public function test_revert_viajero_received_removes_from_queue(): void
    {
        [$sentList, $viajero] = $this->makeScenario();
        $this->decideCompletion($sentList, $viajero, 'decisionCompleteCrimp');

        $this->actingAs($this->packagingUser());
        Livewire::test(ShippingListDisplay::class)
            ->call('markViajeroReceived', $viajero->id)
            ->call('revertViajeroReceived', $viajero->id)
            ->assertHasNoErrors();

        $viajero->refresh();
        $this->assertFalse((bool) $viajero->ready_for_shipping);
        $this->assertNull($viajero->quantity_packed_final);
        $this->assertNull($viajero->closed_by_type);
        $this->assertFalse(Lot::readyForShipping()->whereKey($viajero->id)->exists());
    }

    public function test_revert_blocked_when_packing_slip_exists(): void
    {
        [$sentList, $viajero] = $this->makeScenario();
        $this->decideCompletion($sentList, $viajero, 'decisionCompleteCrimp');

        $packer = $this->packagingUser();
        $this->actingAs($packer);
        Livewire::test(ShippingListDisplay::class)
            ->call('markViajeroReceived', $viajero->id)
            ->assertHasNoErrors();

        // Ya facturado en un Packing Slip (D-12).
        $ps = PackingSlip::create([
            'ps_number'  => 'PS-TEST-1', 'created_by' => $packer->id, 'status' => 'draft',
        ]);
        PackingSlipItem::create([
            'packing_slip_id' => $ps->id, 'lot_id' => $viajero->id, 'quantity_packed' => 500,
        ]);

        Livewire::test(ShippingListDisplay::class)
            ->call('revertViajeroReceived', $viajero->id);

        $viajero->refresh();
        // El guard D-12 bloquea: el viajero sigue recibido y marcado.
        $this->assertTrue((bool) $viajero->viajero_received);
        $this->assertTrue((bool) $viajero->ready_for_shipping);
    }

    public function test_d1_close_as_is_still_marked_ready_by_observer(): void
    {
        [$sentList, $viajero] = $this->makeScenario();
        $this->decideCompletion($sentList, $viajero, 'decisionCloseAsIs');

        // Regresión: D1 lo marca el observer en el Paso 6 (no toca markViajeroReceived).
        $this->assertSame(Lot::CLOSURE_CLOSE_AS_IS, $viajero->closure_decision);
        $this->assertTrue((bool) $viajero->ready_for_shipping);
        $this->assertTrue(Lot::readyForShipping()->whereKey($viajero->id)->exists());
    }

    public function test_reopen_removes_received_d2_from_queue(): void
    {
        [$sentList, $viajero] = $this->makeScenario();
        $this->decideCompletion($sentList, $viajero, 'decisionCompleteCrimp');

        $this->actingAs($this->packagingUser());
        Livewire::test(ShippingListDisplay::class)
            ->call('markViajeroReceived', $viajero->id)
            ->assertHasNoErrors();
        $this->assertTrue((bool) $viajero->refresh()->ready_for_shipping);

        // Materiales reabre el lote → debe salir de la cola (no queda fantasma).
        $this->actingAs($this->materialsUser());
        Livewire::test(ShippingListDisplay::class)
            ->call('openDecisionModal', $viajero->id)
            ->call('reopenLot')
            ->assertHasNoErrors();

        $viajero->refresh();
        $this->assertNull($viajero->closure_decision);
        $this->assertFalse((bool) $viajero->ready_for_shipping);
        $this->assertFalse((bool) $viajero->viajero_received);
        $this->assertFalse(Lot::readyForShipping()->whereKey($viajero->id)->exists());
    }
}
