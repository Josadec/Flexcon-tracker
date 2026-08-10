<?php

namespace Tests\Feature;

use App\Livewire\Admin\Production\WeighingManagement;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El CRUD de pesadas de producción no tenía los dos guards que sí aplica el
 * tablero: no se puede pesar un viajero que Calidad no ha aprobado, y no se
 * puede bajar una pesada por debajo de lo que Calidad ya verificó.
 */
class ProductionWeighingGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $user->assignRole('admin');

        return $user;
    }

    private function makeLot(string $inspection): Lot
    {
        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 500]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        return Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'L-' . fake()->unique()->numerify('####'),
            'quantity' => 500,
            'status' => Lot::STATUS_PENDING,
            'material_status' => 'released',
            'inspection_status' => $inspection,
        ]);
    }

    public function test_no_permite_pesar_un_viajero_sin_inspeccion_aprobada(): void
    {
        $admin = $this->admin();
        $lot = $this->makeLot(Lot::INSPECTION_PENDING);

        Livewire::actingAs($admin)->test(WeighingManagement::class)
            ->call('openCreateModal')
            ->set('selectedLotId', $lot->id)
            ->set('formWeighedPieces', 100)
            ->call('save')
            ->assertHasErrors('selectedLotId');

        $this->assertSame(0, Weighing::where('lot_id', $lot->id)->count());
    }

    public function test_permite_pesar_un_viajero_aprobado(): void
    {
        $admin = $this->admin();
        $lot = $this->makeLot(Lot::INSPECTION_APPROVED);

        Livewire::actingAs($admin)->test(WeighingManagement::class)
            ->call('openCreateModal')
            ->set('selectedLotId', $lot->id)
            ->set('formWeighedPieces', 100)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('weighings', ['lot_id' => $lot->id, 'good_pieces' => 100, 'kit_id' => null]);
    }

    public function test_no_deja_editar_por_debajo_de_lo_que_calidad_verifico(): void
    {
        $admin = $this->admin();
        $lot = $this->makeLot(Lot::INSPECTION_APPROVED);

        $weighing = Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 500, 'good_pieces' => 200,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $admin->id,
        ]);

        QualityWeighing::create([
            'lot_id' => $lot->id, 'kit_id' => null,
            'production_good_pieces' => 200,
            'good_pieces' => 150, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)->test(WeighingManagement::class)
            ->call('openEditModal', $weighing->id)
            ->set('formWeighedPieces', 50)
            ->call('save')
            ->assertHasErrors('formWeighedPieces');

        $this->assertSame(200, (int) $weighing->fresh()->good_pieces);
    }

    public function test_el_selector_solo_ofrece_viajeros_aprobados(): void
    {
        $admin = $this->admin();
        $aprobado = $this->makeLot(Lot::INSPECTION_APPROVED);
        $pendiente = $this->makeLot(Lot::INSPECTION_PENDING);

        $ids = Livewire::actingAs($admin)->test(WeighingManagement::class)
            ->call('openCreateModal')
            ->instance()
            ->selectableLots()
            ->pluck('id');

        $this->assertTrue($ids->contains($aprobado->id));
        $this->assertFalse($ids->contains($pendiente->id));
    }
}
