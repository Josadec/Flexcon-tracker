<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\SentListPackagingView;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $packer = User::factory()->create();
        $this->actingAs($packer);

        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        $sentList = SentList::create([
            'po_id' => $po->id, 'status' => SentList::STATUS_PENDING, 'shift_ids' => [],
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
}
