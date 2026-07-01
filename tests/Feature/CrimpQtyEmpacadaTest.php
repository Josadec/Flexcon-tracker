<?php

namespace Tests\Feature;

use App\Livewire\Admin\PackingSlips\PackingSlipCreate;
use App\Livewire\Admin\Shipping\ShippingQueue;
use App\Models\CrimpLot;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lot;
use App\Models\LotCompletionLog;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PackagingRecord;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\InvoiceFromPackingSlipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Qty Empacada para partes CRIMP.
 *
 * Regla de negocio (confirmada 2026-06-29): 1 pieza de una parte CRIMP = 1 manguita + 1 CRIMP
 * (relación 1:1). "Qty Empacada" de un viajero CRIMP = manguitas empacadas =
 * Lot::getPackagedPiecesTotal() (tabla packaging_piece_weighings), NO packaging_records
 * (que el flujo CRIMP nunca escribe).
 *
 * Bugs que cubre:
 *   BUG-B: Lot::getCompletionCycles()/getTotalCompletedPieces() leía packaging_records → 0 en CRIMP.
 *   BUG-A: LotPackagingObserver persistía quantity_packed_final desde packaging_records → 0 en CRIMP.
 *
 * Propagación: BUG-B alimenta la ruta ShippingQueue::createPackingSlip (usa el método);
 * BUG-A alimenta la ruta PackingSlipCreate::save / PackingSlipShow (usa quantity_packed_final).
 * Ambos snapshots fluyen al Invoice y a los PDFs.
 */
class CrimpQtyEmpacadaTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // Helpers de setup (patrón tomado de CrimpDecisionTest::makeScenario)
    // =====================================================================

    private function makeSentListForPo(PurchaseOrder $po): SentList
    {
        return SentList::create([
            'po_id' => $po->id,
            'status' => SentList::STATUS_PENDING,
            'shift_ids' => [],
            'num_persons' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_available_hours' => 0,
            'used_hours' => 0,
            'remaining_hours' => 0,
        ]);
    }

    /**
     * Construye un viajero CRIMP con $manguitas piezas empacadas (packaging_piece_weighings)
     * y $crimps CRIMP empacados (packaging_crimp_weighings). Sin packaging_records (como el
     * flujo CRIMP real). El WO tiene external_wo_number para pasar la validación del PS.
     *
     * @return array{0: SentList, 1: Lot, 2: User}
     */
    private function makeCrimpViajero(int $manguitas, ?int $crimps = null): array
    {
        $crimps = $crimps ?? $manguitas;
        $user = User::factory()->create();
        $this->actingAs($user);

        $part = Part::factory()->create(['is_crimp' => true]);
        $po = PurchaseOrder::factory()->approved()->create([
            'part_id' => $part->id,
            'quantity' => max(1000, $manguitas),
        ]);

        $sentList = $this->makeSentListForPo($po);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id' => StatusWO::factory(),
            'sent_pieces' => 0,
            'sent_list_id' => $sentList->id,
            'external_wo_number' => '2040090',
        ]);

        $viajero = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => '01',
            'quantity' => $manguitas,
            'status' => Lot::STATUS_IN_PROGRESS,
        ]);

        CrimpLot::create([
            'lot_id' => $viajero->id,
            'crimp_lot_number' => '001',
            'quantity' => $manguitas,
        ]);

        // Empaque CRIMP: manguitas (piezas) + CRIMP. NO se tocan packaging_records.
        PackagingPieceWeighing::create([
            'lot_id' => $viajero->id,
            'quantity' => $manguitas,
            'weighed_at' => now(),
            'weighed_by' => $user->id,
        ]);
        PackagingCrimpWeighing::create([
            'lot_id' => $viajero->id,
            'quantity' => $crimps,
            'weighed_at' => now(),
            'weighed_by' => $user->id,
        ]);

        return [$sentList, $viajero, $user];
    }

    /**
     * Dispara la decisión de cierre "close_as_is" con un update() real para que
     * corra LotPackagingObserver (ruta por la que un viajero CRIMP entra a la cola).
     */
    private function closeAsIs(Lot $lot, User $user): void
    {
        $lot->update([
            'closure_decision' => Lot::CLOSURE_CLOSE_AS_IS,
            'closure_decided_by' => $user->id,
            'closure_decided_at' => now(),
            'status' => Lot::STATUS_COMPLETED,
            'packaging_status' => 'approved',
        ]);
    }

    private function shipAndInvoice(PackingSlip $ps, User $user): Invoice
    {
        $ps->update([
            'status' => PackingSlip::STATUS_SHIPPED,
            'shipped_at' => now(),
            'shipped_by' => $user->id,
        ]);

        return app(InvoiceFromPackingSlipService::class)->createFromPackingSlip($ps->fresh());
    }

    // =====================================================================
    // BUG-B — getCompletionCycles()/getTotalCompletedPieces() CRIMP-aware
    // =====================================================================

    public function test_crimp_total_completed_pieces_counts_manguitas_not_packaging_records(): void
    {
        [, $viajero, $user] = $this->makeCrimpViajero(500);

        $this->closeAsIs($viajero, $user);
        $viajero->refresh();

        // No hay packaging_records (el bug leía esa tabla y devolvía 0).
        $this->assertSame(0, (int) $viajero->packagingRecords()->sum('packed_pieces'));
        $this->assertSame(500, $viajero->getPackagedPiecesTotal());

        // Fuente única: getTotalCompletedPieces() debe contar manguitas empacadas.
        $this->assertSame(500, $viajero->getTotalCompletedPieces());
    }

    // =====================================================================
    // BUG-A — quantity_packed_final / ready_for_shipping vía observer
    // =====================================================================

    public function test_crimp_observer_persists_manguitas_as_quantity_packed_final(): void
    {
        [, $viajero, $user] = $this->makeCrimpViajero(500);

        $this->closeAsIs($viajero, $user);
        $viajero->refresh();

        $this->assertTrue((bool) $viajero->ready_for_shipping);
        $this->assertSame(500, (int) $viajero->quantity_packed_final);
    }

    // =====================================================================
    // Propagación ruta 1: ShippingQueue::createPackingSlip (usa el método)
    // =====================================================================

    public function test_propagation_via_shipping_queue_route(): void
    {
        [, $viajero, $user] = $this->makeCrimpViajero(500);
        $this->closeAsIs($viajero, $user);

        Livewire::test(ShippingQueue::class)
            ->call('toggleLot', $viajero->id)
            ->call('createPackingSlip')
            ->assertHasNoErrors();

        $item = PackingSlipItem::where('lot_id', $viajero->id)->firstOrFail();
        $this->assertSame(500, (int) $item->quantity_packed);

        // Invoice hereda del snapshot
        $invoice = $this->shipAndInvoice($item->packingSlip, $user);
        $invItem = InvoiceItem::where('invoice_id', $invoice->id)
            ->where('lot_id', $viajero->id)
            ->firstOrFail();
        $this->assertSame(500, (int) $invItem->quantity);
    }

    // =====================================================================
    // Propagación ruta 2: PackingSlipCreate::save (usa quantity_packed_final)
    // =====================================================================

    public function test_propagation_via_packing_slip_create_route(): void
    {
        [, $viajero, $user] = $this->makeCrimpViajero(700);
        $this->closeAsIs($viajero, $user);

        Livewire::test(PackingSlipCreate::class)
            ->set('selectedLotIds', [$viajero->id])
            ->call('save')
            ->assertHasNoErrors();

        $item = PackingSlipItem::where('lot_id', $viajero->id)->firstOrFail();
        $this->assertSame(700, (int) $item->quantity_packed);

        $invoice = $this->shipAndInvoice($item->packingSlip, $user);
        $invItem = InvoiceItem::where('invoice_id', $invoice->id)
            ->where('lot_id', $viajero->id)
            ->firstOrFail();
        $this->assertSame(700, (int) $invItem->quantity);
    }

    // =====================================================================
    // Regresión NO-CRIMP — un solo ciclo (sin cambios respecto a hoy)
    // =====================================================================

    public function test_regression_non_crimp_single_cycle_unchanged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $sentList = $this->makeSentListForPo($po);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id' => StatusWO::factory(),
            'sent_pieces' => 0,
            'sent_list_id' => $sentList->id,
            'external_wo_number' => '2040091',
        ]);
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => '01',
            'quantity' => 1000,
            'status' => Lot::STATUS_IN_PROGRESS,
        ]);

        // Flujo NO-CRIMP: el empaque vive en packaging_records.
        PackagingRecord::create([
            'lot_id' => $lot->id,
            'available_pieces' => 300,
            'packed_pieces' => 300,
            'surplus_pieces' => 0,
            'packed_at' => now(),
            'packed_by' => $user->id,
        ]);

        $this->closeAsIs($lot, $user);
        $lot->refresh();

        // NO-CRIMP se mantiene idéntico: lee packaging_records.
        $this->assertSame(300, $lot->getTotalCompletedPieces());
        $this->assertSame(300, (int) $lot->quantity_packed_final);
        $this->assertSame(0, $lot->getPackagedPiecesTotal());
    }

    // =====================================================================
    // Regresión NO-CRIMP — multi-ciclo (blinda la variante gated del observer)
    //
    // Con completion logs previos, getTotalCompletedPieces() acumula todos los
    // ciclos (200 + 150 = 350), pero quantity_packed_final debe seguir siendo el
    // empaque del ciclo actual (150), NO getTotalCompletedPieces(). Esto prueba que
    // el observer usa la variante gated (packaging_records) y no el método.
    // =====================================================================

    public function test_regression_non_crimp_multi_cycle_observer_uses_current_cycle(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $sentList = $this->makeSentListForPo($po);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id' => StatusWO::factory(),
            'sent_pieces' => 0,
            'sent_list_id' => $sentList->id,
            'external_wo_number' => '2040092',
        ]);
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => '01',
            'quantity' => 500,
            'status' => Lot::STATUS_IN_PROGRESS,
            'completion_count' => 1,
        ]);

        // Ciclo previo ya registrado en el log (200 piezas).
        LotCompletionLog::create([
            'lot_id' => $lot->id,
            'cycle_number' => 1,
            'original_quantity' => 1000,
            'packed_pieces' => 200,
            'surplus_pieces' => 0,
            'missing_pieces' => 800,
            'production_good_pieces' => 200,
            'quality_good_pieces' => 200,
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);

        // Ciclo actual: empaque en packaging_records (150).
        PackagingRecord::create([
            'lot_id' => $lot->id,
            'available_pieces' => 150,
            'packed_pieces' => 150,
            'surplus_pieces' => 0,
            'packed_at' => now(),
            'packed_by' => $user->id,
        ]);

        $this->closeAsIs($lot, $user);
        $lot->refresh();

        // Acumulado de ciclos: 200 (log) + 150 (final) = 350 — sin cambios respecto a hoy.
        $this->assertSame(350, $lot->getTotalCompletedPieces());

        // quantity_packed_final = empaque del ciclo actual (150), NO 350.
        $this->assertSame(150, (int) $lot->quantity_packed_final);
    }
}
