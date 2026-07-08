<?php

namespace Tests\Feature;

use App\Livewire\Admin\Shipping\ShippingQueue;
use App\Models\AuditTrail;
use App\Models\Lot;
use App\Models\PackingSlip;
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
 * FPL-10 — ShippingQueue (Cola de Despacho).
 *
 * Cubre la logica operativa que no tenia tests: seleccion con validacion de WO
 * externo (D-06-05), creacion del Packing Slip con snapshots, y devolucion de un
 * lote a Empaque con registro en AuditTrail.
 */
class ShippingQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'shipping-queue-admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    /**
     * Crea un lote listo para shipping. $externalWo controla si el WO tiene numero
     * externo (requisito para poder incluirlo en un PS — decision D-06-05).
     */
    private function makeReadyLot(bool $externalWo = true, string $lotNumber = '001'): Lot
    {
        $part = Part::factory()->create(['is_crimp' => false]);
        $po   = PurchaseOrder::factory()->approved()->create([
            'part_id'  => $part->id,
            'quantity' => 1000,
            'wo'       => null,
        ]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id'  => $po->id,
            'status_id'          => StatusWO::factory(),
            'external_wo_number' => $externalWo ? '2065668' : null,
            'sent_pieces'        => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => $lotNumber,
            'quantity'      => 1000,
            'status'        => Lot::STATUS_PENDING,
        ]);

        $lot->ready_for_shipping = true;
        $lot->ready_for_shipping_at = now();
        $lot->save();

        return $lot->fresh();
    }

    // =========================================================
    // Seleccion de lotes (D-06-05)
    // =========================================================

    public function test_toggle_lot_rechaza_lote_sin_wo_externo(): void
    {
        $lot = $this->makeReadyLot(externalWo: false);

        Livewire::test(ShippingQueue::class)
            ->call('toggleLot', $lot->id)
            ->assertSet('selectedLotIds', [])
            ->assertSet('errorMessage', fn ($msg) => ! empty($msg));
    }

    public function test_toggle_lot_agrega_lote_con_wo_externo(): void
    {
        $lot = $this->makeReadyLot(externalWo: true);

        Livewire::test(ShippingQueue::class)
            ->call('toggleLot', $lot->id)
            ->assertSet('selectedLotIds', [$lot->id]);
    }

    // =========================================================
    // Creacion del Packing Slip
    // =========================================================

    public function test_create_packing_slip_crea_ps_con_item_y_saca_el_lote_de_la_cola(): void
    {
        $lot = $this->makeReadyLot(externalWo: true);

        $this->assertTrue(Lot::readyForShipping()->where('id', $lot->id)->exists());

        Livewire::test(ShippingQueue::class)
            ->set('selectedLotIds', [$lot->id])
            ->set('labelSpecs', [$lot->id => 'LBL-1'])
            ->call('createPackingSlip')
            ->assertHasNoErrors();

        // Se creo exactamente un PS en estado borrador con un item para el lote.
        $ps = PackingSlip::first();
        $this->assertNotNull($ps);
        $this->assertSame(PackingSlip::STATUS_DRAFT, $ps->status);
        $this->assertDatabaseHas('packing_slip_items', [
            'packing_slip_id' => $ps->id,
            'lot_id'          => $lot->id,
            'label_spec'      => 'LBL-1',
        ]);

        // El lote ya no aparece en la cola (tiene packingSlipItem).
        $this->assertFalse(Lot::readyForShipping()->where('id', $lot->id)->exists());
        $this->assertTrue($lot->fresh()->isInPackingSlip());
    }

    public function test_create_packing_slip_falla_sin_lotes_seleccionados(): void
    {
        Livewire::test(ShippingQueue::class)
            ->set('selectedLotIds', [])
            ->call('createPackingSlip')
            ->assertSet('errorMessage', fn ($msg) => ! empty($msg));

        $this->assertSame(0, PackingSlip::count());
    }

    // =========================================================
    // Devolucion a Empaque
    // =========================================================

    public function test_confirm_return_lot_devuelve_a_empaque_y_registra_auditoria(): void
    {
        // Devolver requiere que el WO NO tenga numero externo (lote bloqueado en la cola).
        $lot = $this->makeReadyLot(externalWo: false);

        Livewire::test(ShippingQueue::class)
            ->call('openReturnModal', $lot->id)
            ->assertSet('showReturnModal', true)
            ->set('returnReason', 'Error de conteo, reempacar.')
            ->call('confirmReturnLot')
            ->assertSet('showReturnModal', false);

        // El lote sale de la cola de despacho...
        $fresh = $lot->fresh();
        $this->assertFalse((bool) $fresh->ready_for_shipping);
        $this->assertFalse(Lot::readyForShipping()->where('id', $lot->id)->exists());

        // ...los campos de trazabilidad del retorno se persisten en el lote...
        $this->assertNotNull($fresh->returned_to_packaging_at);
        $this->assertSame($this->admin->id, $fresh->returned_to_packaging_by);
        $this->assertSame('Error de conteo, reempacar.', $fresh->returned_to_packaging_reason);

        // ...y queda registrado en el AuditTrail con el motivo.
        $this->assertDatabaseHas('audit_trails', [
            'auditable_type' => Lot::class,
            'auditable_id'   => $lot->id,
            'action'         => 'returned_to_packaging',
        ]);
    }

    public function test_confirm_return_lot_requiere_motivo(): void
    {
        $lot = $this->makeReadyLot(externalWo: false);

        Livewire::test(ShippingQueue::class)
            ->call('openReturnModal', $lot->id)
            ->set('returnReason', '   ')
            ->call('confirmReturnLot')
            ->assertSet('errorMessage', fn ($msg) => ! empty($msg));

        // No se devolvio: sigue en la cola.
        $this->assertTrue((bool) $lot->fresh()->ready_for_shipping);
        $this->assertSame(0, AuditTrail::where('action', 'returned_to_packaging')->count());
    }
}
