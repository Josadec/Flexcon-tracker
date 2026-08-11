<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\TvDisplay;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use App\Support\PendingActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M8 — La liberación de material para CRIMP se evalúa por `material_status`
 * (a nivel viajero), ya no por el estado de un Kit.
 *
 * El cálculo vivía en App\Traits\ComputesAreaStats, eliminado en cfcc37d al
 * refactorizar los dashboards. Hoy hay dos consumidores vivos de esa regla y
 * este archivo cubre los dos:
 *
 *  - App\Support\PendingActions, que calcula las acciones pendientes por área.
 *  - TvDisplay, que pinta el semáforo que alimenta /tv y /admin/sent-lists/tv.
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

    // ===================== PendingActions =====================

    public function test_liberacion_de_material_se_evalua_por_material_status(): void
    {
        $crimpLiberado  = $this->makeLot(isCrimp: true,  materialStatus: 'released');
        $crimpPendiente = $this->makeLot(isCrimp: true,  materialStatus: 'pending');
        $noCrimpPend    = $this->makeLot(isCrimp: false, materialStatus: 'pending');

        $items = PendingActions::make()->items();

        $porLote = fn (Lot $lot) => $items
            ->where('lot.id', $lot->id)
            ->pluck('phase')
            ->all();

        // Pendiente → aparece la fase de liberación, con la etiqueta de su tipo.
        $this->assertContains('crimp_release', $porLote($crimpPendiente));
        $this->assertContains('material_release', $porLote($noCrimpPend));

        // Liberado → ya no se pide liberar, sin importar que no tenga kits.
        $this->assertNotContains('crimp_release', $porLote($crimpLiberado));
        $this->assertNotContains('material_release', $porLote($crimpLiberado));
    }

    public function test_viajero_crimp_liberado_sin_lotes_capturados_genera_aviso(): void
    {
        $viajero = $this->makeLot(isCrimp: true, materialStatus: 'released');

        $avisos = PendingActions::make()->advisories();

        $this->assertTrue(
            $avisos->contains(fn ($a) => $a['lot']->id === $viajero->id
                && $a['title'] === 'Viajero CRIMP sin lotes capturados'),
            'Un viajero CRIMP liberado sin lotes de CRIMP debe avisar que Empaque no podrá registrar el Paso 5.'
        );
    }

    // ===================== Semáforo de TvDisplay =====================

    public function test_material_column_uses_material_status_for_crimp(): void
    {
        $this->makeLot(isCrimp: true,  materialStatus: 'released');
        $this->makeLot(isCrimp: true,  materialStatus: 'pending');
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
