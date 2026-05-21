<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PackingSlip;
use App\Services\InvoiceFromPackingSlipService;
use App\Services\InvoicePdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceFromPackingSlipService $invoiceService,
        private readonly InvoicePdfService             $pdfService,
    ) {}

    /**
     * Crea un Invoice FPL-12 a partir de un Packing Slip en estado 'shipped'.
     *
     * POST /admin/packing-slips/{packingSlip}/invoice
     * Nombre: admin.shipping-list.create-invoice
     *
     * En caso de exito redirige al detalle del Invoice creado.
     * En caso de error redirige de vuelta al PS con el mensaje de error.
     *
     * @param  PackingSlip $packingSlip  (resuelto por Route Model Binding via ps_number)
     * @return RedirectResponse
     */
    public function createFromPackingSlip(PackingSlip $packingSlip): RedirectResponse
    {
        try {
            $invoice = $this->invoiceService->createFromPackingSlip($packingSlip);

            return redirect()
                ->route('admin.invoices.show', $invoice->invoice_number)
                ->with('success', "Invoice #{$invoice->invoice_number} creado correctamente desde el Packing Slip {$packingSlip->ps_number}.");

        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.shipping-list.show', $packingSlip->ps_number)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Fuerza la descarga del PDF del Invoice FPL-12.
     *
     * GET /admin/invoices/{invoice}/pdf
     * Nombre: admin.invoices.pdf
     *
     * El Invoice debe estar en estado 'issued' o 'paid' para que el PDF
     * este disponible. Si el estado es 'draft', redirige con error.
     *
     * @param  Invoice $invoice  (resuelto por Route Model Binding via invoice_number)
     * @return Response|RedirectResponse
     */
    public function downloadPdf(Invoice $invoice): Response|RedirectResponse
    {
        try {
            $pdf      = $this->pdfService->generate($invoice);
            $filename = $this->pdfService->getFilename($invoice);

            return $pdf->download($filename);

        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.invoices.show', $invoice->invoice_number)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Visualiza el PDF del Invoice en el navegador (stream, sin descarga forzada).
     *
     * GET /admin/invoices/{invoice}/pdf/stream
     * Nombre: admin.invoices.pdf.stream
     *
     * Util para previsualizar el PDF antes de emitir el Invoice formalmente.
     *
     * @param  Invoice $invoice
     * @return Response|RedirectResponse
     */
    public function streamPdf(Invoice $invoice): Response|RedirectResponse
    {
        try {
            $pdf      = $this->pdfService->generate($invoice);
            $filename = $this->pdfService->getFilename($invoice);

            return $pdf->stream($filename);

        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.invoices.show', $invoice->invoice_number)
                ->with('error', $e->getMessage());
        }
    }
}
