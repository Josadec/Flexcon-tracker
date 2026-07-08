<?php

namespace Tests\Feature;

use App\Livewire\Admin\Invoices\InvoiceShow;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FPL-12 — Componente InvoiceShow (edicion inline + transiciones de estado).
 *
 * Cubre la logica de UI que NO cubria InvoiceGenerationTest (que prueba el
 * servicio y el modelo): edicion de LOT NO., cargos, numero de invoice, y las
 * transiciones emitir / cancelar / eliminar con sus guards.
 */
class InvoiceShowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'invoice-show-admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function makeInvoice(array $overrides = []): Invoice
    {
        return Invoice::create($overrides + [
            'invoice_date'       => '2026-06-10',
            'status'             => Invoice::STATUS_DRAFT,
            'type'               => Invoice::TYPE_PRODUCT,
            'sold_to_address'    => 'S.E.I.P., Inc. / 915 Armorlite Dr.',
            'shipped_to_address' => 'S.E.I.P., Inc. / 915 Armorlite Dr.',
            'created_by'         => $this->admin->id,
        ]);
    }

    private function addProductItem(Invoice $invoice, array $overrides = []): InvoiceItem
    {
        return InvoiceItem::create($overrides + [
            'invoice_id'     => $invoice->id,
            'description'    => 'STS H-M-3',
            'quantity'       => 100000,
            'unit_cost'      => '0.0958',
            'line_total'     => '9580.00',
            'sort_order'     => 1,
            'is_fixed_charge' => false,
        ]);
    }

    private function addChargeItem(Invoice $invoice, array $overrides = []): InvoiceItem
    {
        return InvoiceItem::create($overrides + [
            'invoice_id'     => $invoice->id,
            'description'    => 'Machine Maintenance',
            'quantity'       => 1,
            'unit_cost'      => '1200.00',
            'line_total'     => '1200.00',
            'sort_order'     => 10,
            'is_fixed_charge' => true,
        ]);
    }

    // =========================================================
    // LOT NO.
    // =========================================================

    public function test_update_lot_no_valido_propaga_a_todos_los_items_de_producto(): void
    {
        $invoice = $this->makeInvoice();
        $a = $this->addProductItem($invoice, ['sort_order' => 1]);
        $b = $this->addProductItem($invoice, ['description' => 'STS H-M-2', 'sort_order' => 2]);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->set('lotNoValue', '030926x01')
            ->call('updateLotNo')
            ->assertHasNoErrors();

        $this->assertSame('030926x01', $invoice->fresh()->lot_no);
        $this->assertSame('030926x01', $a->fresh()->lot_number);
        $this->assertSame('030926x01', $b->fresh()->lot_number);
    }

    public function test_update_lot_no_rechaza_formato_invalido(): void
    {
        $invoice = $this->makeInvoice();

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->set('lotNoValue', 'FORMATO-MALO')
            ->call('updateLotNo')
            ->assertHasErrors(['lotNoValue']);

        $this->assertNull($invoice->fresh()->lot_no);
    }

    // =========================================================
    // Cargos fijos
    // =========================================================

    public function test_update_charge_amount_recalcula_totales_en_draft(): void
    {
        $invoice = $this->makeInvoice();
        $this->addProductItem($invoice); // 9580.00
        $charge = $this->addChargeItem($invoice); // 1200.00

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->set('editingChargeId', $charge->id)
            ->set('editingChargeAmount', '500.00')
            ->call('updateChargeAmount')
            ->assertHasNoErrors();

        // unit_cost tiene cast decimal:4
        $this->assertSame('500.0000', $charge->fresh()->unit_cost);

        $invoice->refresh();
        $this->assertSame('9580.00', $invoice->subtotal_items);
        $this->assertSame('500.00', $invoice->subtotal_charges);
        $this->assertSame('10080.00', $invoice->grand_total);
    }

    public function test_update_charge_amount_bloqueado_si_no_es_draft(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED]);
        $charge = $this->addChargeItem($invoice);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->set('editingChargeId', $charge->id)
            ->set('editingChargeAmount', '9999.00')
            ->call('updateChargeAmount')
            ->assertDispatched('notify');

        // El monto no cambio: el guard canBeModified() lo impidio (cast decimal:4).
        $this->assertSame('1200.0000', $charge->fresh()->unit_cost);
    }

    // =========================================================
    // lot_number por item
    // =========================================================

    public function test_save_lot_item_actualiza_un_item_individual(): void
    {
        $invoice = $this->makeInvoice();
        $a = $this->addProductItem($invoice, ['sort_order' => 1]);
        $b = $this->addProductItem($invoice, ['description' => 'STS H-M-2', 'sort_order' => 2]);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->set('editingLotItemId', $a->id)
            ->set('editingLotItemValue', 'LOTE-A')
            ->call('saveLotItem')
            ->assertHasNoErrors();

        $this->assertSame('LOTE-A', $a->fresh()->lot_number);
        $this->assertNull($b->fresh()->lot_number); // el otro item no se toco
    }

    public function test_save_lot_item_bloqueado_si_no_es_draft(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED]);
        $a = $this->addProductItem($invoice);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->set('editingLotItemId', $a->id)
            ->set('editingLotItemValue', 'NO-DEBE')
            ->call('saveLotItem')
            ->assertDispatched('notify');

        $this->assertNull($a->fresh()->lot_number);
    }

    // =========================================================
    // Numero de Invoice
    // =========================================================

    public function test_update_invoice_number_valido_redirige_a_la_nueva_url(): void
    {
        $invoice = $this->makeInvoice(['invoice_number' => '02001']);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('updateInvoiceNumber', '02050')
            ->assertRedirect(route('admin.invoices.show', '02050'));

        $this->assertSame('02050', $invoice->fresh()->invoice_number);
    }

    public function test_update_invoice_number_rechaza_duplicado_incluyendo_soft_deleted(): void
    {
        $trashed = $this->makeInvoice(['invoice_number' => '02100']);
        $trashed->delete(); // soft-delete: el numero sigue "ocupado"

        $invoice = $this->makeInvoice(['invoice_number' => '02101']);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('updateInvoiceNumber', '02100')
            ->assertNoRedirect()
            ->assertDispatched('notify');

        // El numero no cambio: colision con un registro trashed.
        $this->assertSame('02101', $invoice->fresh()->invoice_number);
    }

    public function test_update_invoice_number_bloqueado_si_no_es_draft(): void
    {
        $invoice = $this->makeInvoice(['invoice_number' => '02200', 'status' => Invoice::STATUS_ISSUED]);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('updateInvoiceNumber', '02201')
            ->assertNoRedirect()
            ->assertDispatched('notify');

        $this->assertSame('02200', $invoice->fresh()->invoice_number);
    }

    // =========================================================
    // Emitir / Cancelar
    // =========================================================

    public function test_issue_invoice_transiciona_draft_a_issued(): void
    {
        $invoice = $this->makeInvoice();
        $this->addProductItem($invoice); // con precio > 0

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('issueInvoice')
            ->assertDispatched('notify');

        $invoice->refresh();
        $this->assertTrue($invoice->isIssued());
        $this->assertNotNull($invoice->issued_at);
        $this->assertSame($this->admin->id, $invoice->issued_by);
    }

    public function test_issue_invoice_emite_pero_advierte_si_hay_items_sin_precio(): void
    {
        $invoice = $this->makeInvoice();
        $this->addProductItem($invoice, ['unit_cost' => '0.0000', 'line_total' => '0.00']);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('issueInvoice')
            ->assertDispatched('notify');

        // Se emite de todas formas (la advertencia no bloquea).
        $this->assertTrue($invoice->fresh()->isIssued());
    }

    public function test_cancel_invoice_desde_issued(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED]);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('cancelInvoice');

        $this->assertTrue($invoice->fresh()->isCancelled());
    }

    // =========================================================
    // Eliminar
    // =========================================================

    public function test_delete_invoice_en_draft_redirige_al_index(): void
    {
        $invoice = $this->makeInvoice();
        $this->addProductItem($invoice);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('deleteInvoice')
            ->assertRedirect(route('admin.invoices.index'));

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_delete_invoice_bloqueado_si_esta_issued(): void
    {
        $invoice = $this->makeInvoice(['status' => Invoice::STATUS_ISSUED]);

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('deleteInvoice')
            ->assertNoRedirect()
            ->assertDispatched('notify');

        // Sigue vivo: un Invoice emitido no se puede eliminar.
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
    }
}
