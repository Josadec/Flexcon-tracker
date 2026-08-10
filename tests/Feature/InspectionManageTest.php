<?php

namespace Tests\Feature;

use App\Livewire\Admin\Inspection\InspectionList;
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
 * Mesa de trabajo de Inspección (paso 4): la decisión abre o cierra el paso
 * siguiente, así que un rechazo tiene que quedar explicado y no puede caer
 * sobre un viajero que Producción ya empezó a pesar.
 */
class InspectionManageTest extends TestCase
{
    use RefreshDatabase;

    private function inspector(): User
    {
        Role::findOrCreate('Calidad');
        $user = User::factory()->create(['name' => 'Carla Díaz']);
        $user->assignRole('Calidad');

        return $user;
    }

    private function viajero(string $materialStatus = 'released', string $numero = 'V-1', bool $crimp = false): Lot
    {
        $part = Part::factory()->create(['is_crimp' => $crimp]);
        $po = PurchaseOrder::factory()->approved()->create([
            'part_id' => $part->id,
            'quantity' => 1000,
            // El WO visible vive en la orden de compra, no en la de trabajo: es
            // el que la gente teclea en el buscador.
            'wo' => 'WO-'.fake()->unique()->numerify('#####'),
        ]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        return Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 500,
            'status' => Lot::STATUS_PENDING, 'material_status' => $materialStatus,
            'inspection_status' => Lot::INSPECTION_PENDING,
        ]);
    }

    public function test_la_pantalla_lista_lo_que_espera_decision(): void
    {
        $user = $this->inspector();
        $this->viajero('released', 'V-9');

        $this->actingAs($user)->get(route('admin.quality.inspection'))->assertOk();

        $stats = Livewire::actingAs($user)->test(InspectionList::class)
            ->assertOk()
            ->assertSee('Inspección de viajeros')
            ->assertSee('Lo que te toca ahora')
            ->assertSee('V-9')
            ->viewData('stats');

        $this->assertSame(1, $stats['por_inspeccionar']);
    }

    public function test_aprobar_deja_el_viajero_listo_para_produccion(): void
    {
        $user = $this->inspector();
        $lot = $this->viajero();

        Livewire::actingAs($user)->test(InspectionList::class)
            ->call('openInspectionModal', $lot->id)
            ->call('setInspectionAction', 'approved')
            ->call('submitInspectionDecision')
            ->assertHasNoErrors();

        $lot->refresh();
        $this->assertSame(Lot::INSPECTION_APPROVED, $lot->inspection_status);
        $this->assertTrue($lot->canBeProduced());
        $this->assertSame($user->id, $lot->inspection_completed_by);
    }

    public function test_rechazar_exige_un_motivo_escrito(): void
    {
        $user = $this->inspector();
        $lot = $this->viajero();

        Livewire::actingAs($user)->test(InspectionList::class)
            ->call('openInspectionModal', $lot->id)
            ->call('setInspectionAction', 'rejected')
            ->call('submitInspectionDecision')
            ->assertHasErrors('inspectionComments')
            // Un motivo de dos letras tampoco sirve para corregir nada.
            ->set('inspectionComments', 'malo')
            ->call('submitInspectionDecision')
            ->assertHasErrors('inspectionComments')
            ->set('inspectionComments', 'El lote llegó con 40 piezas menos de las declaradas.')
            ->call('submitInspectionDecision')
            ->assertHasNoErrors();

        $this->assertSame(Lot::INSPECTION_REJECTED, $lot->fresh()->inspection_status);
    }

    public function test_no_se_rechaza_un_viajero_que_produccion_ya_peso(): void
    {
        $user = $this->inspector();
        $lot = $this->viajero();
        $lot->update(['inspection_status' => Lot::INSPECTION_APPROVED]);

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 200, 'good_pieces' => 200,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(InspectionList::class)
            ->call('openInspectionModal', $lot->id)
            ->call('setInspectionAction', 'rejected')
            ->set('inspectionComments', 'Se detectó un problema en la muestra final.')
            ->call('submitInspectionDecision')
            ->assertHasErrors('inspectionAction');

        $this->assertSame(Lot::INSPECTION_APPROVED, $lot->fresh()->inspection_status);
    }

    public function test_una_decision_ya_tomada_se_puede_corregir(): void
    {
        $user = $this->inspector();
        $lot = $this->viajero();

        // Rechazado por error: antes esto dejaba el viajero muerto, sin ninguna
        // pantalla para volver a decidir.
        $lot->update([
            'inspection_status' => Lot::INSPECTION_REJECTED,
            'inspection_comments' => 'Rechazado por error de captura.',
            'inspection_completed_at' => now(),
        ]);

        Livewire::actingAs($user)->test(InspectionList::class)
            ->call('openInspectionModal', $lot->id)
            // El modal llega con la decisión puesta, no en blanco.
            ->assertSet('inspectionAction', 'rejected')
            ->call('setInspectionAction', 'approved')
            ->call('submitInspectionDecision')
            ->assertHasNoErrors();

        $this->assertSame(Lot::INSPECTION_APPROVED, $lot->fresh()->inspection_status);
    }

    public function test_no_se_inspecciona_un_viajero_sin_material_liberado(): void
    {
        $user = $this->inspector();
        $lot = $this->viajero('pending');

        Livewire::actingAs($user)->test(InspectionList::class)
            ->call('openInspectionModal', $lot->id)
            ->call('setInspectionAction', 'approved')
            ->call('submitInspectionDecision')
            ->assertSet('showInspectionModal', false);

        $this->assertSame(Lot::INSPECTION_PENDING, $lot->fresh()->inspection_status);
    }

    public function test_la_busqueda_encuentra_por_el_wo_que_se_ve_en_pantalla(): void
    {
        $user = $this->inspector();
        $lot = $this->viajero('released', 'V-BUSCA');
        $wo = $lot->workOrder->purchaseOrder->wo;
        $parte = $lot->workOrder->purchaseOrder->part->number;

        // El buscador del modelo sólo miraba wo_number interno: teclear el WO o
        // la parte que se ve en la tabla no devolvía nada.
        Livewire::actingAs($user)->test(InspectionList::class)
            ->set('search', $wo)
            ->assertSee('V-BUSCA')
            ->set('search', $parte)
            ->assertSee('V-BUSCA');
    }

    public function test_el_orden_solo_acepta_columnas_conocidas(): void
    {
        $user = $this->inspector();
        $this->viajero();

        Livewire::actingAs($user)->test(InspectionList::class)
            ->call('sortBy', 'lot_number')
            ->assertSet('sortField', 'lot_number')
            // Un campo inventado desde el navegador no debe llegar al orderBy.
            ->call('sortBy', '(SELECT 1)')
            ->assertSet('sortField', 'lot_number')
            ->assertOk();
    }

    public function test_el_tamano_de_pagina_se_limita_a_las_opciones_ofrecidas(): void
    {
        $user = $this->inspector();
        $this->viajero();

        Livewire::actingAs($user)->test(InspectionList::class)
            ->set('perPage', 100000)
            ->assertSet('perPage', 10)
            ->set('perPage', 25)
            ->assertSet('perPage', 25);
    }
}
