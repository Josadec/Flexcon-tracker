<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\SentListPackagingView;
use App\Models\Lot;
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
 * Bug: el modal de empaque NO-CRIMP permitía registrar 0 piezas empacadas.
 * Ahora exige al menos 1 (validación min:1).
 */
class PackagingZeroValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_packaging_rejects_zero_packed_pieces(): void
    {
        Role::firstOrCreate(['name' => 'Empaques', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Empaques');
        $this->actingAs($user);

        $part = Part::factory()->create(['is_crimp' => false]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 500]);

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

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'L-1', 'quantity' => 500, 'status' => Lot::STATUS_PENDING,
        ]);

        QualityWeighing::create([
            'lot_id' => $lot->id, 'production_good_pieces' => 500, 'good_pieces' => 500, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        // 0 empacadas → error
        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openPackagingModal', $lot->id)
            ->set('packedPieces', 0)
            ->set('packedAt', now()->format('Y-m-d\TH:i'))
            ->call('savePackaging')
            ->assertHasErrors('packedPieces');

        $this->assertSame(0, $lot->packagingRecords()->count());

        // >= 1 empacada → guarda
        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openPackagingModal', $lot->id)
            ->set('packedPieces', 480)
            ->set('packedAt', now()->format('Y-m-d\TH:i'))
            ->call('savePackaging')
            ->assertHasNoErrors();

        $this->assertSame(1, $lot->packagingRecords()->count());
    }
}
