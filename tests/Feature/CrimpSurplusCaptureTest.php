<?php

namespace Tests\Feature;

use App\Livewire\Admin\Shipping\ShippingQueue;
use App\Livewire\Admin\SentLists\SentListPackagingView;
use App\Livewire\Admin\SentLists\ShippingListDisplay;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PackagingRecord;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Captura MANUAL de piezas sobrantes por Lote de CRIMP (Modal Paso 5 · Empaque).
 * Interpretación A (declarativa): se guarda/muestra/audita, pero NO participa en
 * ningún cálculo aguas abajo (RP-01). Ver
 * docs/Mejoras/ModalCincoEmpaque/01_captura_manual_sobrantes_crimp.md.
 */
class CrimpSurplusCaptureTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: SentList, 1: Lot, 2: CrimpLot, 3: CrimpLot} */
    private function makeViajero(): array
    {
        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        $sentList = SentList::create([
            'po_id'                 => $po->id,
            'status'                => SentList::STATUS_PENDING,
            'current_department'    => SentList::DEPT_SHIPPING,
            'shift_ids'             => [],
            'num_persons'           => 1,
            'start_date'            => now()->toDateString(),
            'end_date'              => now()->toDateString(),
            'total_available_hours' => 0,
            'used_hours'            => 0,
            'remaining_hours'       => 0,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
            'sent_list_id'      => $sentList->id,
        ]);

        $viajero = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-5', 'quantity' => 1000, 'status' => Lot::STATUS_PENDING,
        ]);

        $cl1 = CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 600]);
        $cl2 = CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-2', 'quantity' => 400]);

        return [$sentList, $viajero, $cl1, $cl2];
    }

    private function packer(): User
    {
        Role::firstOrCreate(['name' => 'Empaques', 'guard_name' => 'web']);
        $packer = User::factory()->create();
        $packer->assignRole('Empaques');
        $this->actingAs($packer);

        return $packer;
    }

    public function test_it_saves_the_declared_pieces_surplus_with_audit(): void
    {
        $packer = $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 40)
            ->call('saveConfirmPieceSurplus')
            ->assertHasNoErrors();

        $cl1->refresh();
        $this->assertSame(40, $cl1->surplus_pieces);
        $this->assertSame($packer->id, $cl1->surplus_captured_by);
        $this->assertNotNull($cl1->surplus_captured_at);
    }

    public function test_it_saves_the_declared_crimp_surplus(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cCrimpSurplus', 35)
            ->call('saveConfirmCrimpSurplus')
            ->assertHasNoErrors();

        $this->assertSame(35, $cl1->refresh()->surplus_crimps);
    }

    public function test_the_surplus_is_isolated_per_crimp_lot(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1, $cl2] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)   // default: cl1
            ->set('cPieceSurplus', 40)
            ->call('saveConfirmPieceSurplus')
            ->set('confirmCrimpLotId', $cl2->id)         // dispara rehidratación
            ->set('cPieceSurplus', 10)
            ->call('saveConfirmPieceSurplus');

        $this->assertSame(40, $cl1->refresh()->surplus_pieces);
        $this->assertSame(10, $cl2->refresh()->surplus_pieces);
    }

    public function test_changing_the_crimp_lot_rehydrates_the_input(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1, $cl2] = $this->makeViajero();
        $cl1->update(['surplus_pieces' => 40]);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)     // cl1 → 40
            ->assertSet('cPieceSurplus', 40)
            ->set('confirmCrimpLotId', $cl2->id)          // cl2 → null
            ->assertSet('cPieceSurplus', null)
            ->set('confirmCrimpLotId', $cl1->id)          // cl1 → 40 de nuevo
            ->assertSet('cPieceSurplus', 40);
    }

    public function test_capturing_zero_is_distinct_from_not_captured(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 0)
            ->call('saveConfirmPieceSurplus')
            ->assertHasNoErrors();

        $cl1->refresh();
        $this->assertSame(0, $cl1->surplus_pieces);   // 0, no NULL
        $this->assertTrue($cl1->hasSurplusCaptured());
    }

    public function test_negative_surplus_is_rejected(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', -5)
            ->call('saveConfirmPieceSurplus')
            ->assertHasErrors(['cPieceSurplus' => 'min']);

        $this->assertNull($cl1->refresh()->surplus_pieces);
    }

    public function test_surplus_greater_than_packed_is_accepted(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        // Se pesaron 50; sobraron 450 (conjuntos disjuntos). Válido.
        PackagingPieceWeighing::create([
            'lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 50,
            'weight' => null, 'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 450)
            ->call('saveConfirmPieceSurplus')
            ->assertHasNoErrors();

        $this->assertSame(450, $cl1->refresh()->surplus_pieces);
    }

    public function test_saving_surplus_invalidates_the_confirmation(): void
    {
        $this->packer();
        [$sentList, $viajero] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('confirmDone', true)
            ->set('cPieceSurplus', 5)
            ->call('saveConfirmPieceSurplus')
            ->assertSet('confirmDone', false);
    }

    public function test_surplus_is_blocked_once_ready_for_shipping(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();
        $viajero->update(['ready_for_shipping' => true]);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 40)
            ->call('saveConfirmPieceSurplus');

        // El bloqueo impide TODA escritura: ni el valor ni la marca de auditoría.
        $cl1->refresh();
        $this->assertNull($cl1->surplus_pieces);
        $this->assertNull($cl1->surplus_captured_at);
        $this->assertNull($cl1->surplus_captured_by);
    }

    public function test_capturing_surplus_does_not_change_packed_totals_rp01(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        PackagingPieceWeighing::create([
            'lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 300,
            'weight' => null, 'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);
        PackagingCrimpWeighing::create([
            'lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 250,
            'weight' => null, 'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);

        $piecesBefore    = $viajero->fresh()->getPackagedPiecesTotal();
        $crimpBefore     = $viajero->fresh()->getPackagedCrimpTotal();
        $completedBefore = $viajero->fresh()->getTotalCompletedPieces();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 77)
            ->call('saveConfirmPieceSurplus')
            ->set('cCrimpSurplus', 88)
            ->call('saveConfirmCrimpSurplus');

        $fresh = $viajero->fresh();
        $this->assertSame($piecesBefore, $fresh->getPackagedPiecesTotal());
        $this->assertSame($crimpBefore, $fresh->getPackagedCrimpTotal());
        $this->assertSame($completedBefore, $fresh->getTotalCompletedPieces());
        $this->assertSame(300, $fresh->getPackagedPiecesTotal());
        $this->assertSame(250, $fresh->getPackagedCrimpTotal());
    }

    public function test_the_surplus_can_also_be_saved_from_the_shipping_list_display(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        [, $viajero, $cl1] = $this->makeViajero();

        // Sin abrir el modal (evita el gate canBePackaged): se ejercita el guardado directo.
        Livewire::test(ShippingListDisplay::class)
            ->set('confirmLotId', $viajero->id)
            ->set('confirmCrimpLotId', $cl1->id)
            ->set('cPieceSurplus', 60)
            ->call('saveConfirmPieceSurplus')
            ->assertHasNoErrors();

        $this->assertSame(60, $cl1->refresh()->surplus_pieces);
    }

    // =====================================================
    // Huecos detectados en la auditoría de cobertura §13.2
    // =====================================================

    /** T-06 — sobrante no entero rechazado. */
    public function test_non_integer_surplus_is_rejected(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 'abc')
            ->call('saveConfirmPieceSurplus')
            ->assertHasErrors(['cPieceSurplus' => 'integer']);

        $this->assertNull($cl1->refresh()->surplus_pieces);
    }

    /** T-10 — bloqueo cuando el viajero ya tiene un Packing Slip generado. */
    public function test_surplus_is_blocked_when_packing_slip_exists(): void
    {
        $packer = $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        // El viajero ya está en un Packing Slip (Lot::isInPackingSlip() → true).
        $ps = PackingSlip::create(['ps_number' => 'PS-SURPLUS-1', 'created_by' => $packer->id, 'status' => 'draft']);
        PackingSlipItem::create(['packing_slip_id' => $ps->id, 'lot_id' => $viajero->id, 'quantity_packed' => 500]);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 40)
            ->call('saveConfirmPieceSurplus');

        $cl1->refresh();
        $this->assertNull($cl1->surplus_pieces);
        $this->assertNull($cl1->surplus_captured_at);
        $this->assertNull($cl1->surplus_captured_by);
    }

    /** T-11 — bloqueo por permisos: un usuario sin acceso a Empaque no puede capturar. */
    public function test_surplus_is_blocked_without_packaging_permission(): void
    {
        // Usuario con rol de otro departamento (Calidad) → guardDepartment('packaging') falla.
        Role::firstOrCreate(['name' => 'Calidad', 'guard_name' => 'web']);
        $intruder = User::factory()->create();
        $intruder->assignRole('Calidad');
        $this->actingAs($intruder);

        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(ShippingListDisplay::class)
            ->set('confirmLotId', $viajero->id)
            ->set('confirmCrimpLotId', $cl1->id)
            ->set('cPieceSurplus', 40)
            ->call('saveConfirmPieceSurplus');

        // El guard corta antes de escribir: sin valor y sin marca de auditoría.
        $cl1->refresh();
        $this->assertNull($cl1->surplus_pieces);
        $this->assertNull($cl1->surplus_captured_by);
    }

    /** T-13 — el sobrante sobrevive al borrado de todas las pesadas (dato independiente). */
    public function test_surplus_survives_deletion_of_all_weighings(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        $pw = PackagingPieceWeighing::create([
            'lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 120,
            'weight' => null, 'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);
        $cw = PackagingCrimpWeighing::create([
            'lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 90,
            'weight' => null, 'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 40)
            ->call('saveConfirmPieceSurplus');

        // Se borran TODAS las pesadas del lote.
        $pw->delete();
        $cw->delete();

        $cl1->refresh();
        $this->assertSame(40, $cl1->surplus_pieces);
        $this->assertTrue($cl1->hasSurplusCaptured());
    }

    /** T-14 — REGRESIÓN NO-CRIMP: el sobrante del flujo NO-CRIMP sigue en packaging_records. */
    public function test_non_crimp_surplus_flow_is_untouched(): void
    {
        $part = Part::factory()->create(['is_crimp' => false]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 500]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);
        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => '001', 'quantity' => 500, 'status' => Lot::STATUS_PENDING,
        ]);

        // Sobrante NO-CRIMP vive en packaging_records, no en crimp_lots.
        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 475,
            'surplus_pieces' => 25, 'packed_at' => now(), 'packed_by' => User::factory()->create()->id,
        ]);

        $fresh = $lot->fresh();
        $this->assertFalse($fresh->isViajero());                 // no es CRIMP
        $this->assertSame(25, $fresh->getPackagingTotalSurplus()); // flujo intacto
        $this->assertCount(0, $fresh->crimpLots);                 // no hay lotes de CRIMP
    }

    /**
     * T-15 — propagación NULA al Packing Slip: con sobrante capturado,
     * packing_slip_items.quantity_packed y lots.quantity_packed_final quedan
     * idénticos al caso sin sobrante (RP-01). El Invoice FPL-12 lee
     * packing_slip_items.quantity_packed, por lo que queda cubierto por transitividad.
     */
    public function test_surplus_does_not_propagate_to_packing_slip(): void
    {
        // Escenario completo (patrón CrimpDecisionTest): Calidad 850, piezas 500, CRIMP 800.
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Empaques', 'guard_name' => 'web']);
        $packer = User::factory()->create();
        $packer->assignRole('Empaques');
        $this->actingAs($packer);

        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $sentList = SentList::create([
            'po_id' => $po->id, 'status' => SentList::STATUS_PENDING, 'shift_ids' => [],
            'current_department' => SentList::DEPT_SHIPPING, 'num_persons' => 1,
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'total_available_hours' => 0, 'used_hours' => 0, 'remaining_hours' => 0,
        ]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(),
            'sent_pieces' => 0, 'sent_list_id' => $sentList->id, 'external_wo_number' => '2065668',
        ]);
        $viajero = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => '001', 'quantity' => 1000, 'status' => Lot::STATUS_PENDING,
        ]);
        $cl1 = CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 1000]);
        QualityWeighing::create([
            'lot_id' => $viajero->id, 'production_good_pieces' => 850, 'good_pieces' => 850, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => $packer->id,
        ]);
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 500, 'weighed_at' => now(), 'weighed_by' => $packer->id]);
        PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 800, 'weighed_at' => now(), 'weighed_by' => $packer->id]);

        // 1) Captura de sobrante ANTES de la decisión (después queda bloqueada).
        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceSurplus', 77)
            ->call('saveConfirmPieceSurplus')
            ->assertHasNoErrors();
        $this->assertSame(77, $cl1->refresh()->surplus_pieces);

        // 2) Paso 6: D1 close_as_is → el observer marca ready y fija quantity_packed_final.
        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openDecisionModal', $viajero->id)
            ->call('decisionCloseAsIs')
            ->assertHasNoErrors();

        $viajero->refresh();
        // RP-01: el sobrante 77 NO se resta; quantity_packed_final = piezas empacadas (500).
        $this->assertSame(500, $viajero->quantity_packed_final);

        // 3) Se genera el Packing Slip desde la cola de despacho.
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(ShippingQueue::class)
            ->set('selectedLotIds', [$viajero->id])
            ->set('labelSpecs', [$viajero->id => 'LBL-1'])
            ->call('createPackingSlip')
            ->assertHasNoErrors();

        // El snapshot del PS es getTotalCompletedPieces(), intacto por el sobrante.
        $this->assertDatabaseHas('packing_slip_items', [
            'lot_id'          => $viajero->id,
            'quantity_packed' => $viajero->fresh()->getTotalCompletedPieces(),
        ]);
        $psItem = PackingSlipItem::where('lot_id', $viajero->id)->first();
        $this->assertNotNull($psItem);
        $this->assertSame(500, (int) $psItem->quantity_packed); // no 423 (500-77) ni 577
    }
}
