<?php

namespace Tests\Feature;

use App\Livewire\Admin\PurchaseOrders\POCreate;
use App\Models\Lot;
use App\Models\Part;
use App\Models\Price;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use App\Services\POPriceDetectionService;
use App\Services\PurchaseOrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the important PO bugs:
 *   M1 - validatePOPrice() compared against sample_price, ignoring tiers.
 *   M3 - po_number unique rule counted soft-deleted rows.
 *   M4 - approveAndCreateWO() had no entry-status guard.
 *   M5 - Lot model events crashed when the WorkOrder was soft-deleted.
 *   M6 - the (number, deleted_at) unique indexes never constrained active rows,
 *        since a NULL deleted_at makes every live row unique to the database.
 */
class PurchaseOrderImportantFixesTest extends TestCase
{
    use RefreshDatabase;

    // ===================================================================
    // M1 — tier-aware price validation
    // ===================================================================

    public function test_m1_validate_po_price_uses_tier_pricing(): void
    {
        $part = Part::factory()->active()->create();
        $price = Price::factory()->create([
            'part_id' => $part->id,
            'workstation_type' => Price::WORKSTATION_TABLE,
            'sample_price' => 10.0,
            'active' => true,
        ]);
        $price->tiers()->create(['min_quantity' => 1, 'max_quantity' => 999, 'tier_price' => 8.0]);
        $price->tiers()->create(['min_quantity' => 1000, 'max_quantity' => null, 'tier_price' => 5.0]);

        $service = app(POPriceDetectionService::class);

        // Quantity 2000 falls in the 1000+ tier → expected price 5.0
        $valid = PurchaseOrder::factory()->create([
            'part_id' => $part->id,
            'workstation_type' => Price::WORKSTATION_TABLE,
            'quantity' => 2000,
            'unit_price' => 5.0,
        ]);
        $this->assertTrue($service->validatePOPrice($valid)->isValid);

        // sample_price (10.0) must NOT validate for a tiered quantity
        $invalid = PurchaseOrder::factory()->create([
            'part_id' => $part->id,
            'workstation_type' => Price::WORKSTATION_TABLE,
            'quantity' => 2000,
            'unit_price' => 10.0,
        ]);
        $this->assertFalse($service->validatePOPrice($invalid)->isValid);
    }

    // ===================================================================
    // M3 — unique po_number ignores soft-deleted rows
    // ===================================================================

    public function test_m3_po_number_can_be_reused_after_soft_delete(): void
    {
        $part = Part::factory()->active()->create();

        // A previously soft-deleted PO holds the number 'PO-REUSE'.
        PurchaseOrder::factory()->create(['po_number' => 'PO-REUSE'])->delete();

        Livewire::test(POCreate::class)
            ->set('po_number', 'PO-REUSE')
            ->set('part_id', $part->id)
            ->set('quantity', 100)
            ->set('unit_price', '1.50')
            ->call('savePO')
            ->assertHasNoErrors('po_number');

        $this->assertDatabaseHas('purchase_orders', [
            'po_number' => 'PO-REUSE',
            'deleted_at' => null,
        ]);
    }

    // ===================================================================
    // M4 — approval entry-status guard
    // ===================================================================

    public function test_m4_rejected_po_cannot_be_approved(): void
    {
        StatusWO::firstOrCreate(['name' => 'Open'], ['color' => '#10B981']);
        $po = PurchaseOrder::factory()->rejected()->create();

        $result = app(PurchaseOrderService::class)->approveAndCreateWO($po);

        $this->assertFalse($result['success']);
        $this->assertSame(PurchaseOrder::STATUS_REJECTED, $po->fresh()->status);
        $this->assertNull($po->fresh()->workOrder);
    }

    public function test_m4_approved_po_cannot_be_reapproved(): void
    {
        StatusWO::firstOrCreate(['name' => 'Open'], ['color' => '#10B981']);
        $po = PurchaseOrder::factory()->approved()->create();

        $result = app(PurchaseOrderService::class)->approveAndCreateWO($po);

        $this->assertFalse($result['success']);
    }

    // ===================================================================
    // M5 — Lot events tolerate a soft-deleted WorkOrder
    // ===================================================================

    public function test_m5_lot_creation_survives_soft_deleted_work_order(): void
    {
        StatusWO::firstOrCreate(['name' => 'Open'], ['color' => '#10B981']);
        $po = PurchaseOrder::factory()->create();
        $wo = WorkOrder::factory()->create(['purchase_order_id' => $po->id]);
        $wo->delete();

        // The "created" model event calls workOrder?->updateSentPieces();
        // before the fix this threw a null-pointer Error.
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'LOT-M5',
            'quantity' => 50,
            'status' => Lot::STATUS_COMPLETED,
        ]);

        $this->assertModelExists($lot);
    }

    // ===================================================================
    // M6 — the database, not just Rule::unique(), rejects active duplicates
    // ===================================================================

    public function test_m6_database_rejects_duplicate_active_po_number(): void
    {
        PurchaseOrder::factory()->create(['po_number' => 'PO-DUP']);

        // Skips Rule::unique() on purpose: this is the race-condition path,
        // where two requests both clear validation before either one inserts.
        $this->expectException(UniqueConstraintViolationException::class);

        PurchaseOrder::factory()->create(['po_number' => 'PO-DUP']);
    }

    public function test_m6_database_rejects_duplicate_active_lot_number_per_wo(): void
    {
        StatusWO::firstOrCreate(['name' => 'Open'], ['color' => '#10B981']);
        $po = PurchaseOrder::factory()->create();
        $wo = WorkOrder::factory()->create(['purchase_order_id' => $po->id]);

        Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'LOT-DUP',
            'quantity' => 10,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'LOT-DUP',
            'quantity' => 20,
        ]);
    }

    public function test_m6_lot_number_can_be_reused_after_soft_delete(): void
    {
        StatusWO::firstOrCreate(['name' => 'Open'], ['color' => '#10B981']);
        $po = PurchaseOrder::factory()->create();
        $wo = WorkOrder::factory()->create(['purchase_order_id' => $po->id]);

        Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'LOT-REUSE',
            'quantity' => 10,
        ])->delete();

        $reused = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'LOT-REUSE',
            'quantity' => 20,
        ]);

        $this->assertModelExists($reused);
    }
}
