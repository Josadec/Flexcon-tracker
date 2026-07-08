<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PackingSlip;
use App\Models\User;
use App\Services\InvoiceDeleteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FPL-12 — InvoiceDeleteService.
 *
 * Reglas: bloquea el borrado de Invoices emitidos; para draft/cancelled libera
 * el PS (nullea invoice_id), borra los items fisicamente, soft-deletea el Invoice
 * y preserva el invoice_number en la secuencia (withTrashed).
 */
class InvoiceDeleteServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'invoice-delete-admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function makeInvoiceLinkedToPs(string $status = Invoice::STATUS_DRAFT): array
    {
        $ps = PackingSlip::create([
            'ps_number'     => 'PS-DEL-1',
            'created_by'    => $this->admin->id,
            'status'        => PackingSlip::STATUS_SHIPPED,
            'document_date' => now(),
            'shipped_at'    => now(),
        ]);

        $invoice = Invoice::create([
            'invoice_date'       => '2026-06-10',
            'status'             => $status,
            'type'               => Invoice::TYPE_PRODUCT,
            'packing_slip_id'    => $ps->id,
            'sold_to_address'    => 'S.E.I.P., Inc. / 915 Armorlite Dr.',
            'shipped_to_address' => 'S.E.I.P., Inc. / 915 Armorlite Dr.',
            'created_by'         => $this->admin->id,
        ]);

        $ps->update(['invoice_id' => $invoice->id]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'STS H-M-3', 'quantity' => 100000,
            'unit_cost' => '0.0958', 'line_total' => '9580.00', 'sort_order' => 1, 'is_fixed_charge' => false,
        ]);

        return [$ps, $invoice];
    }

    public function test_borra_draft_libera_ps_elimina_items_y_soft_deletea(): void
    {
        [$ps, $invoice] = $this->makeInvoiceLinkedToPs();
        $invoiceId = $invoice->id;

        app(InvoiceDeleteService::class)->delete($invoice);

        // PS liberado
        $this->assertNull($ps->fresh()->invoice_id);
        // Items borrados fisicamente
        $this->assertDatabaseMissing('invoice_items', ['invoice_id' => $invoiceId]);
        // Invoice soft-deleted (no borrado fisico)
        $this->assertSoftDeleted('invoices', ['id' => $invoiceId]);
    }

    public function test_no_borra_invoice_emitido(): void
    {
        [, $invoice] = $this->makeInvoiceLinkedToPs(Invoice::STATUS_ISSUED);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("no puede ser eliminado");

        app(InvoiceDeleteService::class)->delete($invoice);
    }

    public function test_preserva_el_numero_en_la_secuencia_tras_soft_delete(): void
    {
        [, $invoice] = $this->makeInvoiceLinkedToPs();
        $deletedNumber = (int) $invoice->invoice_number;

        app(InvoiceDeleteService::class)->delete($invoice);

        // El generador incluye trashed: el siguiente numero es deletedNumber + 1,
        // nunca reutiliza el numero del Invoice soft-deleted.
        $next = Invoice::generateInvoiceNumber();

        $this->assertSame($deletedNumber + 1, (int) $next);
    }
}
