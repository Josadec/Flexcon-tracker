<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use App\Traits\ComputesAreaStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M8 — Semáforos: la columna "Kit" para CRIMP se evalúa por material_status
 * (liberación a nivel viajero), ya no por estado de Kit.
 */
class CrimpAreaStatsTest extends TestCase
{
    use RefreshDatabase;

    private function makeLot(bool $isCrimp, string $materialStatus): Lot
    {
        $part = Part::factory()->create(['is_crimp' => $isCrimp]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 100]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        return Lot::create([
            'work_order_id'   => $wo->id, 'lot_number' => 'L-'.fake()->unique()->numerify('####'),
            'quantity'        => 100, 'status' => Lot::STATUS_PENDING,
            'material_status' => $materialStatus,
        ]);
    }

    public function test_kit_column_uses_material_status_for_crimp(): void
    {
        // CRIMP liberado → verde; CRIMP pendiente → gris; sin kits de por medio.
        $this->makeLot(isCrimp: true, materialStatus: 'released');
        $this->makeLot(isCrimp: true, materialStatus: 'pending');
        $this->makeLot(isCrimp: false, materialStatus: 'released');

        $stats = (new class {
            use ComputesAreaStats;

            public function run(): array
            {
                return $this->computeAreaStats();
            }
        })->run();

        $this->assertSame(3, $stats['kit']['total']);
        $this->assertSame(2, $stats['kit']['green']); // 1 crimp + 1 no-crimp liberados
        $this->assertSame(1, $stats['kit']['gray']);  // crimp pendiente
        $this->assertSame(0, $stats['kit']['yellow']); // ya no hay amarillo por kit
    }
}
