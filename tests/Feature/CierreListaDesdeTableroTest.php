<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\ShippingListDisplay;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PackagingRecord;
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
 * Cierre de la lista de envío desde el tablero de piso.
 *
 * Empaque registra el empaque y toma la decisión en el tablero, pero el botón
 * de cerrar sólo vivía en Listas Preliminares → pestaña Empaque. Ese salto de
 * pantalla era el paso que se quedaba sin hacer.
 *
 * La regla de «lista completa» distingue CRIMP de no-CRIMP y ahora vive en el
 * modelo, para que las dos pantallas decidan igual.
 */
class CierreListaDesdeTableroTest extends TestCase
{
    use RefreshDatabase;

    private function empaque(): User
    {
        Role::findOrCreate('Empaques');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Empaques');

        return $user;
    }

    /** Lista con un viajero; `crimp` decide con qué regla se mide su empaque. */
    private function lista(bool $crimp = false, bool $conEmpaque = true): array
    {
        StatusWO::firstOrCreate(['name' => StatusWO::COMPLETED], ['color' => '#000']);
        StatusWO::firstOrCreate(['name' => StatusWO::CANCELLED], ['color' => '#000']);
        $abierto = StatusWO::firstOrCreate(['name' => StatusWO::OPEN], ['color' => '#10B981']);

        $part = Part::factory()->create(['is_crimp' => $crimp]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        $lista = SentList::create([
            'po_id' => $po->id, 'status' => SentList::STATUS_PENDING,
            'current_department' => SentList::DEPT_SHIPPING, 'shift_ids' => [], 'num_persons' => 1,
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'total_available_hours' => 0, 'used_hours' => 0, 'remaining_hours' => 0,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => $abierto->id,
            'sent_pieces' => 0, 'sent_list_id' => $lista->id,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-1', 'quantity' => 500,
            'status' => Lot::STATUS_IN_PROGRESS,
        ]);

        if ($crimp) {
            CrimpLot::create(['lot_id' => $lot->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 500]);
        }

        if ($conEmpaque) {
            $user = User::factory()->create();

            if ($crimp) {
                // En CRIMP lo empacado se mide con pesadas, no con registros.
                PackagingPieceWeighing::create([
                    'lot_id' => $lot->id, 'quantity' => 300, 'weighed_at' => now(), 'weighed_by' => $user->id,
                ]);
                PackagingCrimpWeighing::create([
                    'lot_id' => $lot->id, 'quantity' => 500, 'weighed_at' => now(), 'weighed_by' => $user->id,
                ]);
            } else {
                PackagingRecord::create([
                    'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 500,
                    'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
                ]);
            }
        }

        return [$lista, $lot];
    }

    public function test_el_tablero_ofrece_cerrar_una_lista_no_crimp_completa(): void
    {
        $user = $this->empaque();
        [$lista] = $this->lista(crimp: false);

        Livewire::actingAs($user)->test(ShippingListDisplay::class, ['sentList' => $lista->id])
            ->assertSee('Cerrar lista #')
            ->call('openCloseListModal', $lista->id)
            ->assertSet('showCloseListModal', true)
            ->call('confirmCloseList');

        $this->assertSame(SentList::STATUS_CONFIRMED, $lista->fresh()->status);
    }

    public function test_el_tablero_ofrece_cerrar_una_lista_crimp_completa(): void
    {
        $user = $this->empaque();
        [$lista] = $this->lista(crimp: true);

        Livewire::actingAs($user)->test(ShippingListDisplay::class, ['sentList' => $lista->id])
            ->call('openCloseListModal', $lista->id)
            ->call('confirmCloseList');

        // En CRIMP el empaque son pesadas: si se midiera con PackagingRecord,
        // una lista CRIMP nunca se podría cerrar.
        $this->assertSame(SentList::STATUS_CONFIRMED, $lista->fresh()->status);
    }

    public function test_no_se_cierra_una_lista_con_viajeros_sin_empacar(): void
    {
        $user = $this->empaque();
        [$lista] = $this->lista(crimp: false, conEmpaque: false);

        Livewire::actingAs($user)->test(ShippingListDisplay::class, ['sentList' => $lista->id])
            // El botón se ve, pero deshabilitado y diciendo por qué.
            ->assertSee('faltan 1 por empacar')
            // Aunque se llame al método directo: el botón deshabilitado no protege nada.
            ->call('openCloseListModal', $lista->id)
            ->call('confirmCloseList');

        $this->assertSame(SentList::STATUS_PENDING, $lista->fresh()->status);
    }

    public function test_un_rol_ajeno_a_empaque_no_ve_ni_puede_cerrar(): void
    {
        Role::findOrCreate('Produccion');
        $produccion = User::factory()->create(['email_verified_at' => now()]);
        $produccion->assignRole('Produccion');

        [$lista] = $this->lista(crimp: false);

        Livewire::actingAs($produccion)->test(ShippingListDisplay::class, ['sentList' => $lista->id])
            ->assertDontSee('Cerrar lista #')
            ->call('openCloseListModal', $lista->id)
            ->call('confirmCloseList');

        $this->assertSame(SentList::STATUS_PENDING, $lista->fresh()->status);
    }

    public function test_una_lista_ya_cerrada_se_marca_como_tal(): void
    {
        $user = $this->empaque();
        [$lista] = $this->lista(crimp: false);
        $lista->update(['status' => SentList::STATUS_CONFIRMED]);

        Livewire::actingAs($user)->test(ShippingListDisplay::class, ['sentList' => $lista->id])
            ->assertSee('cerrada')
            ->assertDontSee('Cerrar lista #');
    }

    public function test_las_dos_pantallas_deciden_con_la_misma_regla(): void
    {
        $this->empaque();
        [$listaCrimp] = $this->lista(crimp: true);
        [$listaNoCrimp] = $this->lista(crimp: false);
        [$listaIncompleta] = $this->lista(crimp: false, conEmpaque: false);

        // La regla vive en el modelo; la vista de Empaque delega en ella.
        $this->assertTrue($listaCrimp->allLotsHavePackaging());
        $this->assertTrue($listaNoCrimp->allLotsHavePackaging());
        $this->assertFalse($listaIncompleta->allLotsHavePackaging());
    }
}
