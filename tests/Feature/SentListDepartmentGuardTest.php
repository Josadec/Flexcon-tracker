<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\SentListProductionView;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Verifica la restricción de edición cruzada del tablero SentList:
 * cada área solo puede escribir en la etapa que le corresponde y sobre
 * los registros de SU propia lista. (GuardsSentListDepartment)
 */
class SentListDepartmentGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea una lista con un lote LISTO PARA PRODUCIR. El flujo es secuencial
     * (Materiales libera → Calidad inspecciona → Producción pesa), así que el
     * lote nace con material liberado e inspección aprobada: sin eso el
     * componente corta antes de llegar a la guarda de departamento y los tests
     * de bloqueo pasarían por la razón equivocada.
     */
    private function makeList(string $department): array
    {
        $part = Part::factory()->create(['is_crimp' => false]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        $sentList = SentList::create([
            'po_id'                 => $po->id,
            'status'                => SentList::STATUS_PENDING,
            'current_department'    => $department,
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

        // El flujo es secuencial: Producción sólo pesa lotes con inspección
        // aprobada. Esta prueba mide la guarda de departamento, no la compuerta
        // del flujo, así que el lote entra ya inspeccionado.
        $lot = Lot::create([
            'work_order_id'     => $wo->id,
            'lot_number'        => 'V-1',
            'quantity'          => 1000,
            'status'            => Lot::STATUS_PENDING,
<<<<<<< HEAD
            // Precondiciones reales del flujo para que Producción pueda pesar:
            'material_status'   => 'released',                 // Materiales liberó
            'inspection_status' => Lot::INSPECTION_APPROVED,   // Calidad aprobó
=======
            'material_status'   => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
>>>>>>> dba4729e63772ad9744b5ced48b25a0fdb214628
        ]);

        return [$sentList, $lot];
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->assignRole($role);
        return $u;
    }

    /**
     * Ejecuta la acción esperando que la guarda la rechace. Livewire captura el
     * abort(403) internamente, por lo que la garantía verificable es el efecto:
     * la acción no debe completarse. Si aborta con HttpException, confirmamos 403.
     */
    private function runGuarded(callable $action): void
    {
        try {
            $action();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_production_user_can_weigh_on_production_stage(): void
    {
        [$sentList, $lot] = $this->makeList(SentList::DEPT_PRODUCTION);
        $this->actingAs($this->userWithRole('Produccion'));

        Livewire::test(SentListProductionView::class, ['sentList' => $sentList])
            ->call('openWeighingModal', $lot->id)
            ->set('weighingQuantity', 10)
            ->call('saveWeighing')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('weighings', 1);
    }

    public function test_production_user_blocked_when_list_in_another_stage(): void
    {
        // Lista ya en Calidad: Producción no debe poder pesar.
        [$sentList, $lot] = $this->makeList(SentList::DEPT_QUALITY);
        $this->actingAs($this->userWithRole('Produccion'));

        $this->runGuarded(function () use ($sentList, $lot) {
            Livewire::test(SentListProductionView::class, ['sentList' => $sentList])
                ->call('openWeighingModal', $lot->id)
                ->set('weighingQuantity', 10)
                ->call('saveWeighing');
        });

        // La garantía de seguridad: no se registró ninguna pesada.
        $this->assertDatabaseCount('weighings', 0);
    }

    public function test_wrong_department_user_blocked_on_production_view(): void
    {
        // Usuario de Calidad intentando pesar en la vista de Producción.
        [$sentList, $lot] = $this->makeList(SentList::DEPT_PRODUCTION);
        $this->actingAs($this->userWithRole('Calidad'));

        $this->runGuarded(function () use ($sentList, $lot) {
            Livewire::test(SentListProductionView::class, ['sentList' => $sentList])
                ->call('openWeighingModal', $lot->id)
                ->set('weighingQuantity', 10)
                ->call('saveWeighing');
        });

        $this->assertDatabaseCount('weighings', 0);
    }

    public function test_cannot_delete_weighing_from_another_list_idor(): void
    {
        // Lista A (donde el usuario opera) y Lista B (ajena) con una pesada.
        [$listA] = $this->makeList(SentList::DEPT_PRODUCTION);
        [, $lotB] = $this->makeList(SentList::DEPT_PRODUCTION);
        $this->actingAs($this->userWithRole('Produccion'));

        $foreign = Weighing::create([
            'lot_id'      => $lotB->id,
            'quantity'    => 1000,
            'good_pieces' => 50,
            'bad_pieces'  => 0,
            'weighed_at'  => now(),
            'weighed_by'  => auth()->id(),
        ]);

        // Desde la vista de la Lista A no puede borrar una pesada de la Lista B.
        $this->expectException(ModelNotFoundException::class);
        try {
            Livewire::test(SentListProductionView::class, ['sentList' => $listA])
                ->call('deleteWeighing', $foreign->id);
        } finally {
            $this->assertDatabaseHas('weighings', ['id' => $foreign->id, 'deleted_at' => null]);
        }
    }
}
