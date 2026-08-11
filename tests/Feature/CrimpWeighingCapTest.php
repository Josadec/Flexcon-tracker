<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\SentListPackagingView;
use App\Livewire\Admin\SentLists\ShippingListDisplay;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tope de pesadas por Lote de CRIMP (Modal Paso 5 · Empaque).
 * Regla: SUM(pesadas de un crimp_lot) NO puede superar crimp_lots.quantity,
 * validada por separado para manguitas y para CRIMP, en alta y en edición.
 */
class CrimpWeighingCapTest extends TestCase
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
            'work_order_id' => $wo->id, 'lot_number' => 'V-1', 'quantity' => 1000, 'status' => Lot::STATUS_PENDING,
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

    public function test_adding_a_weighing_over_the_target_is_rejected(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)   // default cl1 (target 600)
            ->set('cPieceQty', 700)
            ->call('addConfirmPieceWeighing')
            ->assertHasErrors(['cPieceQty']);

        $this->assertSame(0, PackagingPieceWeighing::where('crimp_lot_id', $cl1->id)->count());
    }

    public function test_adding_a_weighing_exactly_at_the_target_is_accepted(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceQty', 600)                     // exacto = target
            ->call('addConfirmPieceWeighing')
            ->assertHasNoErrors();

        $this->assertSame(600, (int) PackagingPieceWeighing::where('crimp_lot_id', $cl1->id)->sum('quantity'));
    }

    public function test_the_crossing_weighing_is_rejected_even_if_each_is_individually_valid(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        $component = Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceQty', 400)                     // 0 + 400 = 400 <= 600 OK
            ->call('addConfirmPieceWeighing')
            ->assertHasNoErrors()
            ->set('cPieceQty', 300)                     // 400 + 300 = 700 > 600 → rechazado
            ->call('addConfirmPieceWeighing')
            ->assertHasErrors(['cPieceQty']);

        // Solo quedó la primera pesada.
        $this->assertSame(400, (int) PackagingPieceWeighing::where('crimp_lot_id', $cl1->id)->sum('quantity'));
    }

    public function test_the_cap_is_per_crimp_lot(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1, $cl2] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceQty', 600)                     // llena CL-1 (target 600)
            ->call('addConfirmPieceWeighing')
            ->assertHasNoErrors()
            ->set('confirmCrimpLotId', $cl2->id)         // cambia a CL-2 (target 400)
            ->set('cPieceQty', 400)                      // llenar CL-2 al tope → permitido
            ->call('addConfirmPieceWeighing')
            ->assertHasNoErrors();

        $this->assertSame(600, (int) PackagingPieceWeighing::where('crimp_lot_id', $cl1->id)->sum('quantity'));
        $this->assertSame(400, (int) PackagingPieceWeighing::where('crimp_lot_id', $cl2->id)->sum('quantity'));
    }

    public function test_manguitas_and_crimp_are_capped_independently(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->set('cPieceQty', 600)                     // manguitas al tope
            ->call('addConfirmPieceWeighing')
            ->assertHasNoErrors()
            ->set('cCrimpQty', 600)                      // CRIMP al tope, independiente
            ->call('addConfirmCrimpWeighing')
            ->assertHasNoErrors();

        $this->assertSame(600, (int) PackagingPieceWeighing::where('crimp_lot_id', $cl1->id)->sum('quantity'));
        $this->assertSame(600, (int) PackagingCrimpWeighing::where('crimp_lot_id', $cl1->id)->sum('quantity'));
    }

    public function test_editing_a_weighing_over_the_target_is_rejected(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        $w = PackagingPieceWeighing::create([
            'lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 500,
            'weight' => null, 'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->call('editConfirmPieceWeighing', $w->id)
            ->set('editPieceWQty', 650)                 // 650 > 600 → rechazado
            ->call('saveConfirmPieceWeighing')
            ->assertHasErrors(['editPieceWQty']);

        $this->assertSame(500, $w->refresh()->quantity);  // sin cambio
    }

    public function test_editing_a_weighing_within_the_target_is_accepted(): void
    {
        $this->packer();
        [$sentList, $viajero, $cl1] = $this->makeViajero();

        // Dos pesadas: 400 + 100 = 500 (target 600).
        $w1 = PackagingPieceWeighing::create([
            'lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 400,
            'weight' => null, 'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);
        $w2 = PackagingPieceWeighing::create([
            'lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 100,
            'weight' => null, 'weighed_at' => now(), 'weighed_by' => auth()->id(),
        ]);

        // Editar w2 a 250 → 400 + 250 = 650 > 600 → rechazado (excluye w2, no lo cuenta doble).
        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->call('editConfirmPieceWeighing', $w2->id)
            ->set('editPieceWQty', 250)
            ->call('saveConfirmPieceWeighing')
            ->assertHasErrors(['editPieceWQty']);
        $this->assertSame(100, $w2->refresh()->quantity);

        // Editar w2 a 200 → 400 + 200 = 600 exacto → aceptado.
        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openConfirmModal', $viajero->id)
            ->call('editConfirmPieceWeighing', $w2->id)
            ->set('editPieceWQty', 200)
            ->call('saveConfirmPieceWeighing')
            ->assertHasNoErrors();
        $this->assertSame(200, $w2->refresh()->quantity);
        $this->assertSame(400, $w1->refresh()->quantity);
    }

    public function test_the_cap_also_applies_from_the_shipping_list_display(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        [, $viajero, $cl1] = $this->makeViajero();

        // Sin abrir el modal (evita el gate canBePackaged): guardado directo.
        Livewire::test(ShippingListDisplay::class)
            ->set('confirmLotId', $viajero->id)
            ->set('confirmCrimpLotId', $cl1->id)
            ->set('cCrimpQty', 700)                     // 700 > 600 → rechazado
            ->call('addConfirmCrimpWeighing')
            ->assertHasErrors(['cCrimpQty']);

        $this->assertSame(0, PackagingCrimpWeighing::where('crimp_lot_id', $cl1->id)->count());

        // Dentro del tope → aceptado.
        Livewire::test(ShippingListDisplay::class)
            ->set('confirmLotId', $viajero->id)
            ->set('confirmCrimpLotId', $cl1->id)
            ->set('cCrimpQty', 600)
            ->call('addConfirmCrimpWeighing')
            ->assertHasNoErrors();

        $this->assertSame(600, (int) PackagingCrimpWeighing::where('crimp_lot_id', $cl1->id)->sum('quantity'));
    }
}
