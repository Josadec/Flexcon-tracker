<?php

namespace App\Http\Controllers;

use App\Models\PackingSlip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PackingSlipPdfController extends Controller
{
    /**
     * Carga todas las relaciones necesarias para el PDF y agrupa los items
     * por PO # ordenados de mayor a menor quantity_packed, tal como exige el FPL-10.
     */
    private function buildData(PackingSlip $packingSlip): array
    {
        $packingSlip->load([
            'items.lot.workOrder.purchaseOrder.part',
        ]);

        // Agrupar por PO # y ordenar dentro de cada grupo de mayor a menor cantidad
        $itemsGroupedByPo = $packingSlip->items
            ->groupBy(fn ($item) => $item->lot?->workOrder?->purchaseOrder?->po_number ?? 'Sin PO')
            ->map(fn ($poItems) => $poItems->sortByDesc('quantity_packed')->values());

        return [
            'packingSlip'      => $packingSlip,
            'itemsGroupedByPo' => $itemsGroupedByPo,
            'logoPath'         => public_path('flexcon.png'),
        ];
    }

    /**
     * Muestra el PDF del Packing Slip directamente en el navegador (stream).
     * Ruta: admin.shipping-list.pdf
     */
    public function show(PackingSlip $packingSlip): Response
    {
        $data = $this->buildData($packingSlip);

        $pdf = Pdf::loadView('pdf.packing-slip', $data)
            ->setPaper('letter', 'portrait');

        return $pdf->stream('packing-slip-' . strtolower($packingSlip->ps_number) . '.pdf');
    }

    /**
     * Fuerza la descarga del PDF del Packing Slip.
     * Ruta: admin.shipping-list.pdf.download
     */
    public function download(PackingSlip $packingSlip): Response
    {
        $data = $this->buildData($packingSlip);

        $pdf = Pdf::loadView('pdf.packing-slip', $data)
            ->setPaper('letter', 'portrait');

        return $pdf->download('packing-slip-' . strtolower($packingSlip->ps_number) . '.pdf');
    }
}
