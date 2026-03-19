<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use RuntimeException;

class InvoicePdfService
{
    /**
     * Genera el objeto PDF del Invoice FPL-12.
     *
     * El metodo realiza el eager loading de las relaciones necesarias,
     * agrupa los items (productos vs cargos fijos) y retorna el objeto PDF
     * configurado en papel carta (letter) en orientacion portrait.
     *
     * El controlador decide si hacer stream (visualizar en navegador) o
     * download (forzar descarga), segun la ruta que llame al servicio.
     *
     * @param  Invoice $invoice
     * @return DomPdfInstance
     *
     * @throws RuntimeException si el Invoice no esta en estado issued o paid
     */
    public function generate(Invoice $invoice): DomPdfInstance
    {
        // Validar que el Invoice este en un estado que permita generar el PDF
        if (! $invoice->isPdfAvailable()) {
            throw new RuntimeException(
                "El Invoice #{$invoice->invoice_number} no tiene PDF disponible. "
                . "El PDF solo se genera para Invoices en estado 'issued' o 'paid'. "
                . "Estado actual: '{$invoice->status}'."
            );
        }

        // Eager load de todas las relaciones necesarias para el PDF
        $invoice->load([
            'items.part',
            'packingSlip',
        ]);

        // Separar items de producto de los cargos fijos
        // Ambas colecciones ya vienen ordenadas por sort_order (relacion definida en Invoice::items())
        $productItems = $invoice->items
            ->filter(fn ($item) => ! $item->is_fixed_charge)
            ->values();

        $fixedChargeItems = $invoice->items
            ->filter(fn ($item) => $item->is_fixed_charge)
            ->values();

        // Preparar datos para la vista Blade del PDF
        $data = [
            'invoice'          => $invoice,
            'productItems'     => $productItems,
            'fixedChargeItems' => $fixedChargeItems,
            'logoPath'         => public_path('flexcon.png'),
            'issuer'           => config('invoice.issuer'),
        ];

        return Pdf::loadView('pdf.invoice', $data)
            ->setPaper('letter', 'portrait');
    }

    /**
     * Retorna el nombre de archivo sugerido para el PDF del Invoice.
     *
     * Formato: 'invoice-NNNNN.pdf'
     * Ejemplo: 'invoice-01006.pdf'
     *
     * @param  Invoice $invoice
     * @return string
     */
    public function getFilename(Invoice $invoice): string
    {
        return 'invoice-' . $invoice->invoice_number . '.pdf';
    }
}
