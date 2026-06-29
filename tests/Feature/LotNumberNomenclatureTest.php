<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El viajero/lote (Lot) usa SIEMPRE 2 dígitos (01, 02, …), tanto CRIMP como no-CRIMP.
 * (El "lote de CRIMP" —CrimpLot— usa 3 dígitos, aparte y capturado a mano.)
 */
class LotNumberNomenclatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkOrder(bool $isCrimp): WorkOrder
    {
        $part = Part::factory()->create(['is_crimp' => $isCrimp]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        return WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);
    }

    public function test_crimp_viajero_uses_two_digits(): void
    {
        $wo = $this->makeWorkOrder(isCrimp: true);

        $this->assertSame('01', Lot::generateNextLotNumber($wo->id));

        foreach (['01', '02', '03', '04'] as $n) {
            Lot::create(['work_order_id' => $wo->id, 'lot_number' => $n, 'quantity' => 100, 'status' => Lot::STATUS_PENDING]);
        }
        $this->assertSame('05', Lot::generateNextLotNumber($wo->id));
    }

    public function test_non_crimp_lote_uses_two_digits(): void
    {
        $wo = $this->makeWorkOrder(isCrimp: false);

        $this->assertSame('01', Lot::generateNextLotNumber($wo->id));

        foreach (['01', '02'] as $n) {
            Lot::create(['work_order_id' => $wo->id, 'lot_number' => $n, 'quantity' => 100, 'status' => Lot::STATUS_PENDING]);
        }
        $this->assertSame('03', Lot::generateNextLotNumber($wo->id));
    }
}
