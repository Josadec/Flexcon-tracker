<?php

namespace Tests\Feature;

use App\Livewire\Admin\Lots\LotList;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `lot_id` está declarado onDelete('cascade') en pesadas de producción, de
 * calidad y de empaque: borrar un viajero con historial lo arrastraba todo.
 * Antes `canBeDeleted()` devolvía true para los cuatro estados posibles.
 */
class LotDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $user->assignRole('admin');

        return $user;
    }

    private function makeLot(string $status = Lot::STATUS_PENDING): Lot
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
            'status' => $status,
        ]);
    }

    public function test_un_viajero_sin_movimiento_si_se_puede_borrar(): void
    {
        $admin = $this->admin();
        $lot = $this->makeLot();

        $this->assertTrue($lot->canBeDeleted());

        Livewire::actingAs($admin)->test(LotList::class)
            ->call('confirmDeletion', $lot->id)
            ->call('delete');

        $this->assertSoftDeleted('lots', ['id' => $lot->id]);
    }

    public function test_un_viajero_con_pesadas_no_se_puede_borrar(): void
    {
        $admin = $this->admin();
        $lot = $this->makeLot();

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 500, 'good_pieces' => 100,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $admin->id,
        ]);

        $lot->refresh();
        $this->assertFalse($lot->canBeDeleted());
        $this->assertStringContainsString('pesadas de producción', $lot->getDeleteBlockReason());

        Livewire::actingAs($admin)->test(LotList::class)
            ->call('confirmDeletion', $lot->id)
            ->call('delete');

        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'deleted_at' => null]);
    }

    /** El estado «completado» no puede ser un atajo para saltarse el guard. */
    public function test_estar_completado_no_habilita_el_borrado(): void
    {
        $admin = $this->admin();
        $lot = $this->makeLot(Lot::STATUS_COMPLETED);

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 500, 'good_pieces' => 100,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)->test(LotList::class)
            ->call('confirmDeletion', $lot->id)
            ->call('delete');

        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'deleted_at' => null]);
    }

    public function test_el_cambio_de_estado_respeta_las_transiciones_validas(): void
    {
        $admin = $this->admin();
        $lot = $this->makeLot(Lot::STATUS_PENDING);

        // Pendiente → Completado no es una transición válida (falta iniciarlo).
        Livewire::actingAs($admin)->test(LotList::class)
            ->call('openStatusModal', $lot->id)
            ->call('setNewStatus', Lot::STATUS_COMPLETED)
            ->call('updateLotStatus');

        $this->assertSame(Lot::STATUS_PENDING, $lot->fresh()->status);

        // Pendiente → En progreso sí lo es.
        Livewire::actingAs($admin)->test(LotList::class)
            ->call('openStatusModal', $lot->id)
            ->call('setNewStatus', Lot::STATUS_IN_PROGRESS)
            ->call('updateLotStatus');

        $this->assertSame(Lot::STATUS_IN_PROGRESS, $lot->fresh()->status);
    }

    public function test_no_se_puede_escribir_un_estado_inventado(): void
    {
        $admin = $this->admin();
        $lot = $this->makeLot();

        Livewire::actingAs($admin)->test(LotList::class)
            ->call('openStatusModal', $lot->id)
            ->call('setNewStatus', 'lo-que-sea')
            ->call('updateLotStatus');

        $this->assertSame(Lot::STATUS_PENDING, $lot->fresh()->status);
    }
}
