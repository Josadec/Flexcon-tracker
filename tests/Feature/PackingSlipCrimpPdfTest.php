<?php

namespace Tests\Feature;

use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * FPL-10 — Desglose CRIMP en el PDF del Packing Slip.
 *
 * Vista:       resources/views/pdf/packing-slip.blade.php
 * Controlador: App\Http\Controllers\PackingSlipPdfController (eager-load items.lot.crimpLots).
 * Patrón:      replica el desglose viajero -> lotes de CRIMP ya existente en FPL-02.
 *
 * Regla de oro: el desglose aplica SOLO a partes is_crimp; NO-CRIMP queda idéntico.
 * Se renderiza la vista Blade directamente con datos controlados para assertear el HTML
 * sin parsear el binario del PDF (mismo enfoque que SentListPdfExportTest).
 */
class PackingSlipCrimpPdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un Packing Slip con un item ligado a un lote (viajero) de una parte
     * crimp/no-crimp. Devuelve [PackingSlip, Lot].
     */
    private function makePackingSlipWithItem(bool $isCrimp): array
    {
        $user = User::factory()->create();
        $part = Part::factory()->create(['is_crimp' => $isCrimp, 'item_number' => 'IT-100']);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'V-PS-001',
            'quantity'      => 1000,
            'status'        => Lot::STATUS_PENDING,
        ]);

        $ps = PackingSlip::create([
            'ps_number'     => 'PS-CRIMP-1',
            'created_by'    => $user->id,
            'status'        => PackingSlip::STATUS_PENDING,
            'document_date' => now(),
        ]);

        PackingSlipItem::create([
            'packing_slip_id' => $ps->id,
            'lot_id'          => $lot->id,
            'quantity_packed' => 1000,
            'wo_number_ps'    => 'WO-PS-1',
        ]);

        return [$ps, $lot];
    }

    private function renderPdf(PackingSlip $ps): string
    {
        $ps->load(['items.lot.workOrder.purchaseOrder.part', 'items.lot.crimpLots']);

        $itemsGroupedByPo = $ps->items
            ->groupBy(fn ($item) => $item->lot?->workOrder?->purchaseOrder?->po_number ?? 'Sin PO')
            ->map(fn ($poItems) => $poItems->sortByDesc('quantity_packed')->values());

        return View::make('pdf.packing-slip', [
            'packingSlip'      => $ps,
            'itemsGroupedByPo' => $itemsGroupedByPo,
            'logoPath'         => public_path('flexcon.png'),
        ])->render();
    }

    /**
     * CRIMP: un viajero con 2 lotes de CRIMP produce una sub-fila por cada uno,
     * mostrando crimp_lot_number, lote_fabricante y cantidad; y la etiqueta "Viajero".
     */
    public function test_crimp_packing_slip_muestra_desglose_de_lotes_de_crimp(): void
    {
        [$ps, $lot] = $this->makePackingSlipWithItem(isCrimp: true);

        CrimpLot::create([
            'lot_id' => $lot->id, 'crimp_lot_number' => 'CL-001',
            'lote_fabricante' => 'PROV-AAA', 'quantity' => 600,
        ]);
        CrimpLot::create([
            'lot_id' => $lot->id, 'crimp_lot_number' => 'CL-002',
            'lote_fabricante' => 'PROV-BBB', 'quantity' => 400,
        ]);

        $html = $this->renderPdf($ps);

        // Etiqueta "Viajero" + el lot_number en la sub-fila.
        $this->assertStringContainsString('Viajero V-PS-001', $html);
        // crimp_lot_number de cada lote de CRIMP.
        $this->assertStringContainsString('CL-001', $html);
        $this->assertStringContainsString('CL-002', $html);
        // lote_fabricante de cada lote de CRIMP.
        $this->assertStringContainsString('PROV-AAA', $html);
        $this->assertStringContainsString('PROV-BBB', $html);
        // Cantidad capturada de cada lote de CRIMP.
        $this->assertStringContainsString(number_format(600), $html);
        $this->assertStringContainsString(number_format(400), $html);
    }

    /**
     * Regresión NO-CRIMP: aunque el lote tuviera lotes de CRIMP colgados, una parte
     * NO-crimp NUNCA imprime el desglose ni la etiqueta "Viajero" en el packing slip.
     */
    public function test_no_crimp_packing_slip_no_muestra_desglose(): void
    {
        [$ps, $lot] = $this->makePackingSlipWithItem(isCrimp: false);

        // Datos CRIMP presentes en BD, pero la parte NO es crimp: no deben aparecer.
        CrimpLot::create([
            'lot_id' => $lot->id, 'crimp_lot_number' => 'CL-NO',
            'lote_fabricante' => 'PROV-OCULTO', 'quantity' => 600,
        ]);

        $html = $this->renderPdf($ps);

        $this->assertStringNotContainsString('Viajero V-PS-001', $html);
        $this->assertStringNotContainsString('CL-NO', $html);
        $this->assertStringNotContainsString('PROV-OCULTO', $html);
    }

    /**
     * CRIMP sin lotes de CRIMP capturados: el packing slip muestra solo la fila del
     * item (sin sub-filas) y no rompe.
     */
    public function test_crimp_sin_lotes_no_genera_subfilas(): void
    {
        [$ps] = $this->makePackingSlipWithItem(isCrimp: true);

        $html = $this->renderPdf($ps);

        // No hay filas de desglose (la cadena 'crimp-sub' del CSS no cuenta; buscamos la fila real).
        $this->assertStringNotContainsString('<tr class="crimp-sub">', $html);
        // La fila principal del item sigue presente.
        $this->assertStringContainsString('WO-PS-1', $html);
    }
}
