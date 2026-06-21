<?php

namespace Tests\Feature;

use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M1 / M7 — Reajuste CRIMP en Materiales.
 *
 * Cubre el cambio de mayor riesgo: el gate de inspección (Lot::canBeInspected) dejó de
 * depender del Kit y ahora se evalúa a nivel viajero/lote (material_status), tanto para
 * CRIMP como para NO-CRIMP. También verifica el wiring viajero -> lotes de CRIMP que usa
 * el alta de Materiales (decisión B.1 Opción A).
 */
class CrimpMaterialsInspectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeLot(bool $isCrimp, string $materialStatus): Lot
    {
        $part = Part::factory()->create(['is_crimp' => $isCrimp]);

        $po = PurchaseOrder::factory()->approved()->create([
            'part_id'  => $part->id,
            'quantity' => 1000,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);

        return Lot::create([
            'work_order_id'   => $wo->id,
            'lot_number'      => 'L-'.fake()->unique()->numerify('#####'),
            'quantity'        => 1000,
            'status'          => Lot::STATUS_PENDING,
            'material_status' => $materialStatus,
        ]);
    }

    /**
     * CRIMP: el gate de inspección se abre con material liberado a nivel viajero,
     * SIN necesidad de un Kit liberado (antes lo exigía).
     */
    public function test_crimp_lot_can_be_inspected_when_material_released(): void
    {
        $lot = $this->makeLot(isCrimp: true, materialStatus: 'released');

        $this->assertTrue($lot->canBeInspected());
        $this->assertNull($lot->getInspectionBlockedReason());
    }

    /**
     * CRIMP: con material pendiente el gate permanece cerrado y el motivo habla de
     * material (ya no de kit).
     */
    public function test_crimp_lot_blocked_when_material_pending(): void
    {
        $lot = $this->makeLot(isCrimp: true, materialStatus: 'pending');

        $this->assertFalse($lot->canBeInspected());
        $this->assertStringContainsString('material', strtolower((string) $lot->getInspectionBlockedReason()));
    }

    /**
     * Regresión NO-CRIMP: el gate sigue funcionando igual que antes (material_status).
     */
    public function test_non_crimp_lot_gate_unchanged(): void
    {
        $released = $this->makeLot(isCrimp: false, materialStatus: 'released');
        $rejected = $this->makeLot(isCrimp: false, materialStatus: 'rejected');

        $this->assertTrue($released->canBeInspected());
        $this->assertFalse($rejected->canBeInspected());
    }

    /**
     * Wiring B.1 Opción A: los lotes de CRIMP cuelgan del viajero (lot_id) y el lote
     * es reconocido como viajero por tener parte CRIMP. lote_fabricante es opcional.
     */
    public function test_crimp_lots_hang_off_viajero(): void
    {
        $viajero = $this->makeLot(isCrimp: true, materialStatus: 'pending');

        $conFab = CrimpLot::create([
            'lot_id'           => $viajero->id,
            'crimp_lot_number' => 'CL-1',
            'lote_fabricante'  => 'FAB-9',
            'quantity'         => 400,
        ]);

        $sinFab = CrimpLot::create([
            'lot_id'           => $viajero->id,
            'crimp_lot_number' => 'CL-2',
            'quantity'         => 600,
        ]);

        $this->assertTrue($viajero->isViajero());
        $this->assertSame(2, $viajero->crimpLots()->count());
        $this->assertSame($viajero->id, $conFab->lot->id);
        $this->assertSame('FAB-9', $conFab->lote_fabricante);
        $this->assertNull($sinFab->lote_fabricante, 'lote_fabricante debe ser opcional');
    }
}
