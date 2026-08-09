<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\TvDisplay;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M8 — Semáforos: la columna de Materiales/Viajero para CRIMP se evalúa por
 * material_status (liberación a nivel viajero), ya no por estado de Kit.
 *
 * El cálculo vivía en App\Traits\ComputesAreaStats, eliminado en cfcc37d al
 * refactorizar los dashboards a App\Support\PendingActions. Hoy la única
 * implementación viva del semáforo por área es TvDisplay, que alimenta tanto
 * /tv (público) como /admin/sent-lists/tv.
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

    public function test_material_column_uses_material_status_for_crimp(): void
    {
        // CRIMP liberado → verde; CRIMP pendiente → gris; sin kits de por medio.
        $this->makeLot(isCrimp: true, materialStatus: 'released');
        $this->makeLot(isCrimp: true, materialStatus: 'pending');
        $this->makeLot(isCrimp: false, materialStatus: 'released');

        $stats = Livewire::test(TvDisplay::class)->viewData('areaStats');

        $this->assertSame(3, $stats['material']['total']);
        $this->assertSame(2, $stats['material']['green']); // 1 crimp + 1 no-crimp liberados
        $this->assertSame(1, $stats['material']['gray']);  // crimp pendiente
        $this->assertSame(0, $stats['material']['yellow']); // ya no hay amarillo por materiales
    }

    public function test_crimp_and_non_crimp_share_the_same_material_rule(): void
    {
        // Mismo material_status → mismo color, tenga o no CRIMP la parte.
        $this->makeLot(isCrimp: true, materialStatus: 'pending');
        $this->makeLot(isCrimp: false, materialStatus: 'pending');

        $stats = Livewire::test(TvDisplay::class)->viewData('areaStats');

        $this->assertSame(2, $stats['material']['total']);
        $this->assertSame(0, $stats['material']['green']);
        $this->assertSame(2, $stats['material']['gray']);
    }

    public function test_crimp_lots_report_release_from_the_viajero_material_status(): void
    {
        // La tarjeta de la WO marca los lotes de CRIMP como liberados según el
        // material_status del viajero, no según un estado propio del kit.
        $this->makeLot(isCrimp: true, materialStatus: 'released');

        $cards = Livewire::test(TvDisplay::class)->viewData('woCards');

        $this->assertCount(1, $cards);
        $this->assertTrue($cards[0]['is_crimp']);
        $this->assertSame(1, $cards[0]['material']['green']);
        $this->assertSame(0, $cards[0]['material']['gray']);
    }
}
