<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\SentListIndex;
use App\Models\AuditTrail;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\PackagingRecord;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ReopeningService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 3 — reabrir lo cerrado: en un solo sitio, con permiso y con motivo.
 *
 * El cliente pidió poder corregir todo, «desde una PO hasta una Invoice»,
 * incluso con la factura emitida, pero sólo Administración. Antes había seis
 * implementaciones distintas de «reabrir», sólo una dejaba rastro, ninguna
 * pedía motivo, y una de ellas dejaba «viajeros fantasma» en la cola de
 * despacho listos para volver a facturarse.
 */
class ReaperturaControladaTest extends TestCase
{
    use RefreshDatabase;

    private function servicio(): ReopeningService
    {
        return app(ReopeningService::class);
    }

    private function conRoles(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function usuario(string $rol): User
    {
        $this->conRoles();
        $user = User::factory()->create(['name' => 'Jonas', 'last_name' => 'Admin']);
        $user->assignRole($rol);

        return $user->fresh();
    }

    /** Un viajero cerrado por Empaque y ya en la cola de despacho. */
    private function viajeroCerrado(User $user, string $numero = 'L-1'): Lot
    {
        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 500,
            'status' => Lot::STATUS_IN_PROGRESS,
        ]);

        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 500,
            'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        // «Cerrar tal como está» (D1): el cierre corriente, y el único que la
        // pantalla deja corregir. `complete_lot` reinicia el viajero y por eso
        // su decisión anterior ya no se reabre.
        $lot->update(['closure_decision' => Lot::CLOSURE_CLOSE_AS_IS]);

        return $lot->fresh();
    }

    // ===============================================
    // PERMISO Y MOTIVO
    // ===============================================

    public function test_un_rol_operativo_no_puede_reabrir_un_viajero(): void
    {
        $empaques = $this->usuario('Empaques');
        $lot = $this->viajeroCerrado($empaques);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sólo Administración');

        $this->servicio()->reopenLot($lot, 'El cliente pidió corregir la cantidad.', $empaques);
    }

    public function test_administracion_si_puede_reabrir(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);

        $this->servicio()->reopenLot($lot, 'El cliente reportó 20 piezas de menos.', $jonas);

        $lot->refresh();
        $this->assertFalse($lot->isFinished());
        $this->assertNull($lot->closure_decision);
        $this->assertFalse((bool) $lot->ready_for_shipping);
        $this->assertSame(Lot::STATUS_IN_PROGRESS, $lot->status);
    }

    public function test_reabrir_exige_un_motivo_escrito(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);

        try {
            $this->servicio()->reopenLot($lot, 'error', $jonas);
            $this->fail('Se reabrió sin motivo suficiente.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Escribe por qué se reabre', $e->getMessage());
        }

        $this->assertTrue($lot->fresh()->isFinished());
    }

    public function test_toda_reapertura_deja_rastro_con_su_motivo(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);

        $this->servicio()->reopenLot($lot, 'El cliente reportó 20 piezas de menos.', $jonas);

        $entrada = AuditTrail::where('action', 'reopen')->firstOrFail();
        $this->assertSame(Lot::class, $entrada->auditable_type);
        $this->assertSame($lot->id, $entrada->auditable_id);
        $this->assertSame('El cliente reportó 20 piezas de menos.', $entrada->new_values['motivo']);
        $this->assertSame('Jonas Admin', $entrada->user_name);
    }

    // ===============================================
    // EL VIAJERO FANTASMA
    // ===============================================

    public function test_reabrir_saca_el_viajero_de_la_cola_de_despacho(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);

        $this->assertTrue((bool) $lot->ready_for_shipping);

        $this->servicio()->reopenLot($lot, 'Corrección de cantidades con el cliente.', $jonas);

        // El «viajero fantasma»: una de las seis implementaciones no limpiaba
        // esto y el viajero reabierto se quedaba listo para volver a facturar.
        $this->assertFalse((bool) $lot->fresh()->ready_for_shipping);
        $this->assertNull($lot->fresh()->quantity_packed_final);
    }

    // ===============================================
    // CASCADA HASTA LA FACTURA
    // ===============================================

    private function facturar(Lot $lot, User $user): array
    {
        $ps = PackingSlip::create([
            'ps_number' => 'PS-001', 'created_by' => $user->id,
            'status' => PackingSlip::STATUS_SHIPPED, 'document_date' => now()->toDateString(),
            'shipped_at' => now(), 'shipped_by' => $user->id,
        ]);
        PackingSlipItem::create([
            'packing_slip_id' => $ps->id, 'lot_id' => $lot->id, 'quantity_packed' => 500,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-0123', 'status' => Invoice::STATUS_ISSUED,
            'invoice_date' => now()->toDateString(), 'issued_at' => now(), 'issued_by' => $user->id,
            'sold_to_address' => 'Tecate, Ca.', 'shipped_to_address' => 'Tecate, Ca.',
            'packing_slip_id' => $ps->id,
        ]);
        $ps->update(['invoice_id' => $invoice->id]);

        return [$ps->fresh(), $invoice->fresh()];
    }

    public function test_la_cascada_se_anuncia_antes_de_tocar_nada(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);
        [$ps, $invoice] = $this->facturar($lot, $jonas);

        $cascada = $this->servicio()->cascadeFor($lot->fresh());

        $this->assertNotEmpty($cascada);
        $this->assertStringContainsString('INV-0123', implode(' ', $cascada));
        $this->assertStringContainsString('PS-001', implode(' ', $cascada));
    }

    public function test_reabrir_con_factura_emitida_reabre_factura_y_packing_slip(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);
        [$ps, $invoice] = $this->facturar($lot, $jonas);

        // El cliente lo pidió expresamente: se tiene que poder corregir aunque
        // la factura ya esté emitida. No se bloquea, se reabre en cascada.
        $this->servicio()->reopenLot($lot->fresh(), 'El cliente reclamó la cantidad facturada.', $jonas);

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->issued_at);
        $this->assertSame(PackingSlip::STATUS_DRAFT, $ps->fresh()->status);
        $this->assertNull($ps->fresh()->shipped_at);
        $this->assertFalse($lot->fresh()->isFinished());
        $this->assertSame(0, PackingSlipItem::where('lot_id', $lot->id)->count());
    }

    public function test_la_cascada_queda_registrada_en_los_tres_documentos(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);
        $this->facturar($lot, $jonas);

        $this->servicio()->reopenLot($lot->fresh(), 'El cliente reclamó la cantidad facturada.', $jonas);

        $this->assertSame(3, AuditTrail::where('action', 'reopen')->count());
    }

    // ===============================================
    // FUGAS QUE SE CERRARON
    // ===============================================

    public function test_un_rol_operativo_no_puede_reabrir_una_lista_confirmada(): void
    {
        $empaques = $this->usuario('Empaques');

        $po = PurchaseOrder::factory()->approved()->create();
        $lista = SentList::create([
            'po_id' => $po->id, 'status' => SentList::STATUS_CONFIRMED,
            'current_department' => SentList::DEPT_SHIPPING, 'shift_ids' => [], 'num_persons' => 1,
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'total_available_hours' => 0, 'used_hours' => 0, 'remaining_hours' => 0,
        ]);

        Livewire::actingAs($empaques)->test(SentListIndex::class)
            ->call('openStatusModal', $lista->id)
            ->set('newStatus', SentList::STATUS_PENDING)
            ->call('saveStatus');

        // Antes cualquiera de los cinco roles operativos podía devolver a
        // edición cualquier lista ya cerrada, desde un <select> y sin rastro.
        $this->assertSame(SentList::STATUS_CONFIRMED, $lista->fresh()->status);
    }

    public function test_administracion_si_puede_reabrir_una_lista_confirmada(): void
    {
        $jonas = $this->usuario('admin');

        $po = PurchaseOrder::factory()->approved()->create();
        $lista = SentList::create([
            'po_id' => $po->id, 'status' => SentList::STATUS_CONFIRMED,
            'current_department' => SentList::DEPT_SHIPPING, 'shift_ids' => [], 'num_persons' => 1,
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'total_available_hours' => 0, 'used_hours' => 0, 'remaining_hours' => 0,
        ]);

        Livewire::actingAs($jonas)->test(SentListIndex::class)
            ->call('openStatusModal', $lista->id)
            ->set('newStatus', SentList::STATUS_PENDING)
            ->call('saveStatus');

        $this->assertSame(SentList::STATUS_PENDING, $lista->fresh()->status);
    }

    public function test_el_modal_anuncia_la_cascada_antes_de_reabrir(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);
        $this->facturar($lot, $jonas);

        Livewire::actingAs($jonas)->test(\App\Livewire\Admin\SentLists\ShippingListDisplay::class)
            ->call('openDecisionModal', $lot->id)
            ->call('openReopenModal')
            ->assertSet('showReopenModal', true)
            ->assertSee('INV-0123')
            ->assertSee('PS-001')
            ->assertSee('¿Por qué se reabre?');
    }

    public function test_quien_toma_la_decision_ya_no_puede_reabrirla(): void
    {
        // Materiales es quien decide el cierre y quien abría el modal de
        // decisión: ahora ve la decisión tomada, pero sin el botón de reabrir.
        $materiales = $this->usuario('Materiales');
        $lot = $this->viajeroCerrado($materiales);

        Livewire::actingAs($materiales)->test(\App\Livewire\Admin\SentLists\ShippingListDisplay::class)
            ->call('openDecisionModal', $lot->id)
            ->assertSet('showDecisionModal', true)
            ->assertDontSee('Reabrir lote y anular la decisión')
            ->assertSee('Sólo Administración puede reabrirla');
    }

    public function test_no_se_reabre_un_viajero_que_no_esta_cerrado(): void
    {
        $jonas = $this->usuario('admin');
        $lot = $this->viajeroCerrado($jonas);
        $this->servicio()->reopenLot($lot, 'Primera corrección del cliente.', $jonas);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no está cerrado');

        $this->servicio()->reopenLot($lot->fresh(), 'Segunda corrección del cliente.', $jonas);
    }
}
