<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FPL-12 — InvoicePdfService + InvoiceController (rutas de PDF).
 *
 * El PDF solo esta disponible en estado issued: en draft el servicio lanza
 * excepcion y el controller redirige con error. En issued se sirve un PDF valido.
 */
class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'invoice-pdf-admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function makeInvoice(string $status, string $number = '03001'): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number'     => $number,
            'invoice_date'       => '2026-06-10',
            'status'             => $status,
            'type'               => Invoice::TYPE_PRODUCT,
            'sold_to_address'    => 'S.E.I.P., Inc. / 915 Armorlite Dr.',
            'shipped_to_address' => 'S.E.I.P., Inc. / 915 Armorlite Dr.',
            'subtotal_items'     => '9580.00',
            'subtotal_charges'   => '1200.00',
            'grand_total'        => '10780.00',
            'total_quantity'     => 100000,
            'created_by'         => $this->admin->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'STS H-M-3', 'quantity' => 100000,
            'unit_cost' => '0.0958', 'line_total' => '9580.00', 'sort_order' => 1, 'is_fixed_charge' => false,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Machine Maintenance', 'quantity' => 1,
            'unit_cost' => '1200.00', 'line_total' => '1200.00', 'sort_order' => 10, 'is_fixed_charge' => true,
        ]);

        return $invoice->fresh();
    }

    // =========================================================
    // Servicio
    // =========================================================

    public function test_service_lanza_excepcion_si_el_invoice_esta_en_draft(): void
    {
        $invoice = $this->makeInvoice(Invoice::STATUS_DRAFT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no tiene PDF disponible');

        app(InvoicePdfService::class)->generate($invoice);
    }

    public function test_service_genera_pdf_para_invoice_issued(): void
    {
        $invoice = $this->makeInvoice(Invoice::STATUS_ISSUED);

        $pdf = app(InvoicePdfService::class)->generate($invoice);
        $output = $pdf->output();

        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_service_filename_usa_el_formato_invoice_nnnnn_pdf(): void
    {
        $invoice = $this->makeInvoice(Invoice::STATUS_ISSUED, '03042');

        $this->assertSame('invoice-03042.pdf', app(InvoicePdfService::class)->getFilename($invoice));
    }

    // =========================================================
    // Controller (HTTP)
    // =========================================================

    public function test_download_pdf_de_issued_devuelve_pdf_valido(): void
    {
        $invoice = $this->makeInvoice(Invoice::STATUS_ISSUED);

        $response = $this->get(route('admin.invoices.pdf', $invoice->invoice_number));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_stream_pdf_de_issued_devuelve_pdf_valido(): void
    {
        $invoice = $this->makeInvoice(Invoice::STATUS_ISSUED, '03002');

        $response = $this->get(route('admin.invoices.pdf.stream', $invoice->invoice_number));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_download_pdf_de_draft_redirige_con_error(): void
    {
        $invoice = $this->makeInvoice(Invoice::STATUS_DRAFT, '03003');

        $this->get(route('admin.invoices.pdf', $invoice->invoice_number))
            ->assertRedirect(route('admin.invoices.show', $invoice->invoice_number))
            ->assertSessionHas('error');
    }
}
