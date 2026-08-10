<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use App\Support\PendingActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M8 — La liberación de material para CRIMP se evalúa por `material_status`
 * (a nivel viajero), ya no por el estado de un Kit.
 *
 * La cobertura vive contra PendingActions, que es quien calcula hoy las
 * acciones pendientes por área (antes era el trait ComputesAreaStats).
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
}
