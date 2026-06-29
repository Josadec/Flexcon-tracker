<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\WoResume;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackagingPieceWeighing;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WoResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_shows_viajeros_crimp_lots_and_states(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $part = Part::factory()->create(['is_crimp' => true, 'number' => '189-10492']);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo   = WorkOrder::factory()->create(['purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0]);

        // Viajero 01 — completado (empaca todo)
        $v1 = Lot::create(['work_order_id' => $wo->id, 'lot_number' => '01', 'quantity' => 500, 'status' => Lot::STATUS_PENDING]);
        $cl1 = CrimpLot::create(['lot_id' => $v1->id, 'crimp_lot_number' => '001', 'quantity' => 500]);
        PackagingPieceWeighing::create(['lot_id' => $v1->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 500, 'weighed_at' => now(), 'weighed_by' => $user->id]);

        // Viajero 02 — no iniciado
        $v2 = Lot::create(['work_order_id' => $wo->id, 'lot_number' => '02', 'quantity' => 300, 'status' => Lot::STATUS_PENDING]);
        CrimpLot::create(['lot_id' => $v2->id, 'crimp_lot_number' => '002', 'quantity' => 300]);

        Livewire::test(WoResume::class, ['workOrder' => $wo])
            ->assertOk()
            ->assertSee('Viajero 01')
            ->assertSee('Viajero 02')
            ->assertSee('001')
            ->assertSee('002')
            ->assertSee('Completado')
            ->assertSee('No iniciado');
    }
}
