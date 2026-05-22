<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Part;
use App\Models\Price;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the three critical PO bugs:
 *   C1 - deleting a PO destroys in-progress production with no guard.
 *   C2 - FK violation when force-deleting a PO with a soft-deleted WorkOrder.
 *   C3 - approve + create WO not atomic (PO left "approved" without a WO).
 */
class PurchaseOrderCriticalFixesTest extends TestCase
{
    use RefreshDatabase;

    private function openStatus(): StatusWO
    {
        return StatusWO::firstOrCreate(['name' => 'Open'], ['color' => '#10B981']);
    }

    // ===================================================================
    // C1 — deletion guard
    // ===================================================================

    public function test_c1_pending_po_without_work_order_can_be_deleted(): void
    {
        $po = PurchaseOrder::factory()->create();

        $this->assertNull($po->getDeletionBlockReason());
        $this->assertTrue($po->canBeDeleted());
    }

    public function test_c1_approved_po_with_empty_open_wo_can_be_deleted(): void
    {
        $this->openStatus();
        $po = PurchaseOrder::factory()->approved()->create();
        WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'sent_pieces' => 0,
        ]);

        $this->assertTrue($po->fresh()->canBeDeleted());
    }

    public function test_c1_po_with_shipped_pieces_cannot_be_deleted(): void
    {
        $this->openStatus();
        $po = PurchaseOrder::factory()->approved()->create();
        WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'sent_pieces' => 5,
        ]);

        $reason = $po->fresh()->getDeletionBlockReason();
        $this->assertNotNull($reason);
        $this->assertStringContainsString('pieza', $reason);
        $this->assertFalse($po->fresh()->canBeDeleted());
    }

    public function test_c1_po_with_lots_cannot_be_deleted(): void
    {
        $this->openStatus();
        $po = PurchaseOrder::factory()->approved()->create();
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'sent_pieces' => 0,
        ]);
        Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'LOT-1',
            'quantity' => 100,
        ]);

        $reason = $po->fresh()->getDeletionBlockReason();
        $this->assertNotNull($reason);
        $this->assertStringContainsString('lote', $reason);
    }

    // ===================================================================
    // C2 — force delete with a soft-deleted WorkOrder
    // ===================================================================

    public function test_c2_force_delete_po_with_soft_deleted_work_order(): void
    {
        $this->openStatus();
        $po = PurchaseOrder::factory()->create();
        $wo = WorkOrder::factory()->create(['purchase_order_id' => $po->id]);

        // Soft-delete the WO — the restrict FK would block the PO otherwise.
        $wo->delete();
        $this->assertSoftDeleted('work_orders', ['id' => $wo->id]);

        $po->forceDeleteWithRelations();

        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
        $this->assertDatabaseMissing('work_orders', ['id' => $wo->id]);
    }

    // ===================================================================
    // C3 — atomic approve + create WorkOrder
    // ===================================================================

    private function makePricedPO(): PurchaseOrder
    {
        $part = Part::factory()->active()->create();
        Price::factory()->create([
            'part_id' => $part->id,
            'workstation_type' => Price::WORKSTATION_TABLE,
            'sample_price' => 1.2345,
            'active' => true,
        ]);

        return PurchaseOrder::factory()->create([
            'part_id' => $part->id,
            'workstation_type' => Price::WORKSTATION_TABLE,
            'unit_price' => 1.2345,
            'quantity' => 500,
            'status' => PurchaseOrder::STATUS_PENDING,
        ]);
    }

    public function test_c3_approve_creates_work_order_atomically(): void
    {
        $this->openStatus();
        $po = $this->makePricedPO();

        $result = app(PurchaseOrderService::class)->approveAndCreateWO($po);

        $this->assertTrue($result['success']);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->fresh()->status);
        $this->assertNotNull($po->fresh()->workOrder);
    }

    public function test_c3_po_not_left_approved_when_wo_creation_fails(): void
    {
        // No "Open" status exists → WorkOrder cannot be created.
        $po = $this->makePricedPO();

        $result = app(PurchaseOrderService::class)->approveAndCreateWO($po);

        $this->assertFalse($result['success']);
        // The PO must keep its original status — no "approved without WO" limbo.
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $po->fresh()->status);
        $this->assertNull($po->fresh()->workOrder);
    }
}
