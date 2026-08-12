<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\ShippingListDisplay;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El reparto de una orden en lotes no puede pasarse de la cantidad de la orden.
 *
 * El tope ya se comprobaba al guardar, pero el rechazo salía por
 * session()->flash, que se pinta en la cabecera de la pantalla — o sea DETRÁS
 * del modal. Quien pulsaba «Guardar cambios» no veía nada y concluía que la
 * regla no existía. Existía; sólo no se decía, y encima se podían seguir
 * añadiendo renglones sin límite.
 */
class TableroRepartoDeLotesTest extends TestCase
{
    use RefreshDatabase;

    private function materialista(): User
    {
        Role::findOrCreate('Materiales');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Materiales');

        return $user;
    }

    private function orden(int $cantidad = 10000): WorkOrder
    {
        StatusWO::firstOrCreate(['name' => StatusWO::COMPLETED], ['color' => '#000']);
        StatusWO::firstOrCreate(['name' => StatusWO::CANCELLED], ['color' => '#000']);
        $abierto = StatusWO::firstOrCreate(['name' => StatusWO::OPEN], ['color' => '#10B981']);

        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => $cantidad]);

        return WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => $abierto->id, 'sent_pieces' => 0,
        ]);
    }

    /** Deja la orden repartida por completo: un solo lote por toda la cantidad. */
    private function repartidaPorCompleto(WorkOrder $wo): Lot
    {
        return Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => '01',
            'quantity' => $wo->original_quantity,
            'status' => Lot::STATUS_PENDING,
        ]);
    }

    public function test_no_deja_agregar_un_lote_si_la_orden_ya_esta_repartida(): void
    {
        $wo = $this->orden(10000);
        $this->repartidaPorCompleto($wo);

        $componente = Livewire::actingAs($this->materialista())
            ->test(ShippingListDisplay::class)
            ->call('openLotModal', $wo->id);

        $this->assertCount(1, $componente->get('lots'));

        $componente->call('addLot');

        $this->assertCount(1, $componente->get('lots'), 'No debería haber añadido el renglón.');
        $componente->assertHasErrors('lots');
    }

    public function test_el_renglon_nuevo_propone_lo_que_falta_por_repartir(): void
    {
        $wo = $this->orden(10000);
        $this->repartidaPorCompleto($wo);

        $componente = Livewire::actingAs($this->materialista())
            ->test(ShippingListDisplay::class)
            ->call('openLotModal', $wo->id)
            ->set('lots.0.quantity', 6000)   // libera 4,000
            ->call('addLot');

        $this->assertCount(2, $componente->get('lots'));
        $this->assertEquals(4000, $componente->get('lots.1.quantity'));
    }

    public function test_guardar_por_encima_de_la_cantidad_de_la_orden_no_crea_nada(): void
    {
        $wo = $this->orden(10000);
        $this->repartidaPorCompleto($wo);

        Livewire::actingAs($this->materialista())
            ->test(ShippingListDisplay::class)
            ->call('openLotModal', $wo->id)
            ->set('lots.1', ['id' => null, 'number' => '02', 'quantity' => 5000])
            ->call('saveLots')
            ->assertHasErrors('lots')   // el motivo se ve DENTRO del modal
            ->assertSet('showLotModal', true);

        $this->assertSame(1, Lot::where('work_order_id', $wo->id)->count());
        $this->assertSame(10000, (int) Lot::where('work_order_id', $wo->id)->sum('quantity'));
    }

    public function test_cuando_la_suma_cuadra_si_guarda(): void
    {
        $wo = $this->orden(10000);
        $this->repartidaPorCompleto($wo);

        Livewire::actingAs($this->materialista())
            ->test(ShippingListDisplay::class)
            ->call('openLotModal', $wo->id)
            ->set('lots.0.quantity', 6000)
            ->set('lots.1', ['id' => null, 'number' => '02', 'quantity' => 4000])
            ->call('saveLots')
            ->assertHasNoErrors()
            ->assertSet('showLotModal', false);

        $this->assertSame(2, Lot::where('work_order_id', $wo->id)->count());
        $this->assertSame(10000, (int) Lot::where('work_order_id', $wo->id)->sum('quantity'));
    }
}
