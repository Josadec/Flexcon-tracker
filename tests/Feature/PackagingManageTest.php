<?php

namespace Tests\Feature;

use App\Livewire\Admin\Packaging\PackagingManagement;
use App\Models\Lot;
use App\Models\PackagingRecord;
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
 * Mesa de trabajo de Empaque: lo que se registra aquí alimenta la decisión de
 * cierre del viajero, así que las cantidades no pueden pasar de lo que Calidad
 * aprobó ni quedarse sin trazabilidad.
 */
class PackagingManageTest extends TestCase
{
    use RefreshDatabase;

    private function empacador(): User
    {
        Role::findOrCreate('Empaques');
        $user = User::factory()->create(['name' => 'Beto Ruiz']);
        $user->assignRole('Empaques');

        return $user;
    }

    /**
     * Viajero listo para empacar: material liberado, inspección aprobada,
     * producción pesada y Calidad con piezas buenas.
     */
    private function viajeroListo(User $user, int $aprobadas = 500, bool $crimp = false, string $numero = 'L-1'): Lot
    {
        $part = Part::factory()->create(['is_crimp' => $crimp]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 1000,
            'status' => Lot::STATUS_IN_PROGRESS, 'material_status' => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
        ]);

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => $aprobadas, 'good_pieces' => $aprobadas,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        QualityWeighing::create([
            'lot_id' => $lot->id, 'production_good_pieces' => $aprobadas, 'good_pieces' => $aprobadas,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        return $lot;
    }

    public function test_la_pantalla_lista_los_viajeros_que_esperan_empaque(): void
    {
        $user = $this->empacador();
        $this->viajeroListo($user, 500, false, 'L-9');

        // La ruta completa, con layout y middleware de rol.
        $this->actingAs($user)->get(route('admin.packaging.manage'))->assertOk();

        $stats = Livewire::actingAs($user)->test(PackagingManagement::class)
            ->assertOk()
            ->assertSee('Gestión de empaque')
            ->assertSee('Lo que te toca ahora')
            ->assertSee('L-9')
            // El alta sin viajero preseleccionado también tiene que dibujarse.
            ->call('openCreateModal')
            ->assertSee('Elige el viajero')
            ->viewData('stats');

        $this->assertSame(1, $stats['listos_sin_empacar']);
    }

    public function test_los_viajeros_crimp_no_cuentan_como_pendientes_de_esta_pantalla(): void
    {
        $user = $this->empacador();
        $this->viajeroListo($user, 500, true, 'C-1');

        $stats = Livewire::actingAs($user)->test(PackagingManagement::class)
            ->assertOk()
            ->viewData('stats');

        // En CRIMP lo empacado se mide con pesadas; aquí saldría pendiente siempre.
        $this->assertSame(0, $stats['listos_sin_empacar']);
    }

    public function test_el_alta_precarga_lo_que_queda_por_empacar(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 300,
            'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('openCreateForLot', $lot->id)
            ->assertSet('formLotId', $lot->id)
            ->assertSet('formPackedPieces', 200);
    }

    public function test_no_se_puede_empacar_mas_de_lo_que_queda_del_viajero(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 400,
            'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        // Quedan 100: pedir 300 tiene que fallar. Antes se comparaba contra las
        // 500 aprobadas sin descontar el registro anterior.
        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('openCreateForLot', $lot->id)
            ->set('formPackedPieces', 300)
            ->set('formPackedAt', now()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('formPackedPieces');

        $this->assertSame(1, $lot->packagingRecords()->count());
    }

    public function test_empacadas_mas_sobrante_no_pasan_de_lo_disponible(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('openCreateForLot', $lot->id)
            ->set('formPackedPieces', 450)
            ->set('formSurplusPieces', 100)
            ->set('formPackedAt', now()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('formSurplusPieces');

        $this->assertSame(0, $lot->packagingRecords()->count());
    }

    public function test_al_editar_no_se_cuenta_dos_veces_el_propio_registro(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        $record = PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 400,
            'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('openEditModal', $record->id)
            ->set('formPackedPieces', 500)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(500, $record->fresh()->packed_pieces);
    }

    public function test_cero_piezas_empacadas_no_es_un_registro_valido(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('openCreateForLot', $lot->id)
            ->set('formPackedPieces', 0)
            ->set('formPackedAt', now()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('formPackedPieces');
    }

    public function test_la_fecha_de_empaque_no_puede_ser_futura(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('openCreateForLot', $lot->id)
            ->set('formPackedPieces', 100)
            ->set('formPackedAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('formPackedAt');
    }

    public function test_corregir_el_sobrante_exige_motivo(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('openCreateForLot', $lot->id)
            ->set('formPackedPieces', 400)
            ->set('formSurplusPieces', 100)
            ->set('formAdjustedSurplus', 80)
            ->set('formPackedAt', now()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('formAdjustmentReason')
            ->set('formAdjustmentReason', 'Al recontar, 20 piezas venían dañadas.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(80, $lot->packagingRecords()->first()->adjusted_surplus);
    }

    public function test_no_se_registra_empaque_de_un_viajero_que_calidad_no_ha_aprobado(): void
    {
        $user = $this->empacador();

        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);
        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'SIN-QC', 'quantity' => 500,
            'status' => Lot::STATUS_PENDING, 'material_status' => 'pending',
        ]);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('openCreateForLot', $lot->id)
            ->assertSet('showModal', false)
            ->assertSet('formLotId', null);

        $this->assertSame(0, $lot->packagingRecords()->count());
    }

    public function test_no_se_borra_el_empaque_de_un_viajero_ya_recibido(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        $record = PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 400,
            'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        $lot->update(['viajero_received' => true]);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('confirmDelete', $record->id)
            ->call('deleteRecord');

        // El registro sobrevive: el viajero ya fue recibido con estas cantidades.
        $this->assertNotSoftDeleted($record);
    }

    public function test_el_borrado_normal_si_elimina_el_registro(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        $record = PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 400,
            'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->call('confirmDelete', $record->id)
            ->call('deleteRecord')
            ->assertSet('showDeleteModal', false);

        $this->assertSoftDeleted($record);
    }

    public function test_cambiar_de_orden_limpia_el_filtro_de_viajero(): void
    {
        $user = $this->empacador();
        $lot = $this->viajeroListo($user, 500);

        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 400,
            'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(PackagingManagement::class)
            ->set('filterLotId', (string) $lot->id)
            ->set('filterWorkOrderId', (string) $lot->work_order_id)
            ->assertSet('filterLotId', '');
    }
}
