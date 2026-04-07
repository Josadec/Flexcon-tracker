<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceChargeType;
use App\Models\InvoiceItem;
use App\Models\PackingSlip;
use App\Models\Price;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InvoiceFromPackingSlipService
{
    /**
     * Crea un Invoice de tipo 'product' a partir de un Packing Slip en estado 'shipped'.
     *
     * Logica completa:
     *   1. Valida que el PS este en status='shipped' y no tenga Invoice asignado.
     *   2. Dentro de DB::transaction() con lockForUpdate():
     *      a. Crea el registro Invoice (status=draft, invoice_date=hoy).
     *      b. Por cada PackingSlipItem: crea InvoiceItem con snapshot completo.
     *      c. Por cada InvoiceChargeType con always_include=true: crea InvoiceItem de cargo.
     *      d. Recalcula y persiste los totales del Invoice.
     *      e. Actualiza packing_slips.invoice_id con el ID del Invoice creado.
     *   3. Retorna el Invoice creado (con items cargados).
     *
     * @param  PackingSlip $ps
     * @return Invoice
     *
     * @throws RuntimeException si el PS no esta en estado shipped
     * @throws RuntimeException si el PS ya tiene un Invoice asociado
     * @throws RuntimeException si no hay items en el PS
     */
    public function createFromPackingSlip(PackingSlip $ps): Invoice
    {
        // ----------------------------------------------------------------
        // Validaciones previas a la transaccion
        // ----------------------------------------------------------------
        if (! $ps->isShipped()) {
            throw new RuntimeException(
                "El Packing Slip {$ps->ps_number} debe estar en estado 'shipped' para generar un Invoice. Estado actual: '{$ps->status}'."
            );
        }

        if ($ps->hasInvoice()) {
            throw new RuntimeException(
                "El Packing Slip {$ps->ps_number} ya tiene un Invoice asociado (ID: {$ps->invoice_id}). No se puede generar un segundo Invoice."
            );
        }

        // Cargar las relaciones necesarias antes de entrar a la transaccion
        $ps->load([
            'items.lot.workOrder.purchaseOrder.part.prices',
        ]);

        if ($ps->items->isEmpty()) {
            throw new RuntimeException(
                "El Packing Slip {$ps->ps_number} no tiene items. No se puede generar un Invoice sin items."
            );
        }

        // ----------------------------------------------------------------
        // Calcular el LOT NO. predominante del envio
        // ----------------------------------------------------------------
        // Se determina el workstation_type predominante del PS para el sufijo global.
        // Cada item tambien calcula su propio lot_number en base a su precio activo.
        $lotNoForInvoice = $this->calculateLotNo($ps);

        // ----------------------------------------------------------------
        // Transaccion con bloqueo de la fila del PS para evitar condiciones
        // de carrera si dos procesos intentan crear el Invoice al mismo tiempo.
        // ----------------------------------------------------------------
        return DB::transaction(function () use ($ps, $lotNoForInvoice) {

            // Re-leer el PS con lockForUpdate dentro de la transaccion
            $psLocked = PackingSlip::lockForUpdate()->findOrFail($ps->id);

            // Verificar de nuevo despues del lock (double-check pattern)
            if ($psLocked->invoice_id !== null) {
                throw new RuntimeException(
                    "El Packing Slip {$ps->ps_number} ya tiene un Invoice asociado (generado concurrentemente). Recargue la pagina."
                );
            }

            // --------------------------------------------------------
            // Paso a: Crear el registro Invoice
            // --------------------------------------------------------
            $invoice = Invoice::create([
                'invoice_date'       => now()->toDateString(),
                'lot_no'             => $lotNoForInvoice,
                'status'             => Invoice::STATUS_DRAFT,
                'type'               => Invoice::TYPE_PRODUCT,
                'packing_slip_id'    => $ps->id,
                // Snapshot de datos del cliente desde config
                'fob_location'       => config('invoice.fob_location', 'Tecate, Ca.'),
                'sold_to_name'       => config('invoice.sold_to.name', 'S.E.I.P., Inc.'),
                'sold_to_address'    => config('invoice.sold_to.address', '915 Armorlite Dr.') . "\n" . config('invoice.sold_to.city', 'San Marcos, Ca. 92069'),
                'shipped_to_name'    => config('invoice.shipped_to.name', 'S.E.I.P., Inc.'),
                'shipped_to_address' => config('invoice.shipped_to.address', '915 Armorlite Dr.') . "\n" . config('invoice.shipped_to.city', 'San Marcos, Ca. 92069'),
                'created_by'         => Auth::id(),
            ]);

            // --------------------------------------------------------
            // Paso b: Crear InvoiceItems por cada PackingSlipItem
            // --------------------------------------------------------
            $sortOrder      = 1;
            $itemsWithoutPrice = [];

            foreach ($ps->items as $psItem) {
                $lot  = $psItem->lot;
                $wo   = $lot?->workOrder;
                $po   = $wo?->purchaseOrder;
                $part = $po?->part;

                // Obtener el precio activo de la parte
                $price      = $part ? Price::getActivePriceForPart($part->id) : null;
                $unitCost   = '0.0000';
                $priceSource = 'manual';
                $priceTierId = null;

                if ($price !== null) {
                    // Usar la cantidad de la PO para determinar el tier correcto
                    $qty          = $po?->quantity ?? $psItem->quantity_packed;
                    $unitCostFloat = $price->getPriceForQuantity((int) $qty);

                    if ($unitCostFloat !== null) {
                        $unitCost = (string) $unitCostFloat;

                        // Determinar la fuente del precio y el tier aplicado
                        $tier = $price->tiers()
                            ->where('min_quantity', '<=', $qty)
                            ->where(function ($q) use ($qty) {
                                $q->whereNull('max_quantity')
                                  ->orWhere('max_quantity', '>=', $qty);
                            })
                            ->first();

                        if ($tier) {
                            $priceSource = 'tier';
                            $priceTierId = $tier->id;
                        } else {
                            // Fallback al sample_price
                            $priceSource = 'sample';
                        }
                    }
                }

                if ($unitCost === '0.0000' || $unitCost === '0') {
                    $itemsWithoutPrice[] = $part?->number ?? "Item #{$psItem->id}";
                }

                // Calcular line_total con precision exacta (Decision D-12-05)
                $lineTotal = round(
                    (float) bcmul((string) $psItem->quantity_packed, $unitCost, 6),
                    2
                );

                // Derivar el lot_number del item a partir del workstation_type de este precio
                $itemLotNumber = $this->deriveLotNumber($ps, $price?->workstation_type);

                // Derivar el wo_number para el Invoice (solo los 7 digitos del external_wo_number)
                $woNumber = $wo?->external_wo_number ?? $wo?->purchaseOrder?->wo ?? null;

                InvoiceItem::create([
                    'invoice_id'           => $invoice->id,
                    'packing_slip_item_id' => $psItem->id,
                    'lot_id'               => $lot?->id,
                    'work_order_id'        => $wo?->id,
                    'purchase_order_id'    => $po?->id,
                    'part_id'              => $part?->id,
                    // Snapshot inmutable
                    'description'          => $part?->number ?? '-',
                    'item_number'          => $part?->item_number ?? null,
                    'lot_number'           => $itemLotNumber,
                    'po_number'            => $po?->po_number ?? null,
                    'wo_number'            => $woNumber,
                    // Cantidades y precios
                    'quantity'             => $psItem->quantity_packed,
                    'unit_cost'            => $unitCost,
                    'line_total'           => $lineTotal,
                    // Clasificacion y orden
                    'sort_order'           => $sortOrder,
                    'is_fixed_charge'      => false,
                ]);

                // Actualizar los campos de precio en el PackingSlipItem (auditoria)
                $psItem->update([
                    'unit_price'     => $unitCost,
                    'price_tier_id'  => $priceTierId,
                    'price_source'   => $priceSource,
                ]);

                $sortOrder++;
            }

            // --------------------------------------------------------
            // Paso c: Crear InvoiceItems de cargos fijos (always_include)
            // --------------------------------------------------------
            $chargeTypes = InvoiceChargeType::getActiveTypesForNewInvoice();

            foreach ($chargeTypes as $chargeType) {
                $chargeAmount = (string) $chargeType->default_amount;

                // Los cargos fijos tienen quantity=1 y unit_cost=importe total del cargo
                $lineTotal = round(
                    (float) bcmul('1', $chargeAmount, 6),
                    2
                );

                InvoiceItem::create([
                    'invoice_id'              => $invoice->id,
                    'packing_slip_item_id'    => null,
                    'invoice_charge_type_id'  => $chargeType->id,
                    'lot_id'                  => null,
                    'work_order_id'           => null,
                    'purchase_order_id'       => null,
                    'part_id'                 => null,
                    // Snapshot del cargo
                    'description'             => $chargeType->label,
                    'item_number'             => null,
                    'lot_number'              => null,
                    'po_number'               => null,
                    'wo_number'               => null,
                    // Cantidades y precios
                    'quantity'                => 1,
                    'unit_cost'               => $chargeAmount,
                    'line_total'              => $lineTotal,
                    // Clasificacion y orden
                    'sort_order'              => $sortOrder,
                    'is_fixed_charge'         => true,
                ]);

                $sortOrder++;
            }

            // --------------------------------------------------------
            // Paso d: Recalcular y persistir totales del Invoice
            // --------------------------------------------------------
            $invoice->calculateTotals()->save();

            // --------------------------------------------------------
            // Paso e: Actualizar packing_slips.invoice_id
            // --------------------------------------------------------
            $psLocked->update(['invoice_id' => $invoice->id]);

            // Log para auditoria
            Log::info('Invoice creado desde Packing Slip', [
                'invoice_id'      => $invoice->id,
                'invoice_number'  => $invoice->invoice_number,
                'packing_slip_id' => $ps->id,
                'ps_number'       => $ps->ps_number,
                'lot_no'          => $lotNoForInvoice,
                'grand_total'     => $invoice->grand_total,
                'items_count'     => $ps->items->count(),
                'charges_count'   => $chargeTypes->count(),
                'items_without_price' => $itemsWithoutPrice,
                'created_by'      => Auth::id(),
            ]);

            if (! empty($itemsWithoutPrice)) {
                Log::warning('Invoice creado con items sin precio asignado', [
                    'invoice_id'          => $invoice->id,
                    'items_without_price' => $itemsWithoutPrice,
                ]);
            }

            // Recargar con relaciones para retornar el Invoice completo
            return $invoice->load(['items.part', 'packingSlip']);
        });
    }

    // ================================================================
    // Metodos privados de apoyo
    // ================================================================

    /**
     * Calcula el LOT NO. predominante del Invoice basado en shipped_at del PS.
     *
     * Logica (Decision P-12-01 resuelta):
     *   - Tomar $ps->shipped_at
     *   - Si es lunes → usar esa fecha; si no → retroceder al lunes anterior
     *   - Formato MMDDYY + sufijo determinado por workstation_type predominante del PS
     *
     * El workstation_type se determina tomando el precio activo de la parte
     * con mayor cantidad en el PS (la parte "predominante").
     *
     * @param  PackingSlip $ps  (con items y relaciones cargadas)
     * @return string|null      NULL si no se puede determinar el workstation_type
     */
    private function calculateLotNo(PackingSlip $ps): ?string
    {
        // Determinar el workstation_type predominante (de la parte con mayor cantidad)
        $dominantWorkstationType = $this->getDominantWorkstationType($ps);

        return $this->deriveLotNumber($ps, $dominantWorkstationType);
    }

    /**
     * Deriva el lot_number a partir de la fecha de envio y el workstation_type.
     *
     * Formato: MMDDYY + 'x' + sufijo
     *   - 'x01' para table / manual
     *   - 'x20' para machine / semi_automatic
     *
     * La fecha que se usa es el PRIMER LUNES ANTERIOR a $ps->shipped_at
     * (o el mismo dia si shipped_at ya es lunes).
     *
     * @param  PackingSlip $ps
     * @param  string|null $workstationType
     * @return string|null
     */
    private function deriveLotNumber(PackingSlip $ps, ?string $workstationType): ?string
    {
        $suffix = match ($workstationType) {
            'table'          => '01',
            'machine'        => '20',
            'semi_automatic' => '20',
            default          => null,
        };

        if ($suffix === null) {
            return null;
        }

        // Retroceder al lunes anterior (o usar el mismo dia si ya es lunes)
        $shippedAt  = Carbon::parse($ps->shipped_at);
        $lotMonday  = $shippedAt->copy()->startOfWeek(Carbon::MONDAY);

        // startOfWeek() en Carbon retrocede al lunes de la semana actual.
        // Si shipped_at ya es lunes, startOfWeek devuelve ese mismo lunes.
        // Si shipped_at es domingo o cualquier dia no-lunes, retrocede al lunes anterior.
        // No se necesita ajuste adicional: startOfWeek(MONDAY) ya da el comportamiento correcto.

        $dateComponent = $lotMonday->format('m')   // MM (mes con cero: 01-12)
                       . $lotMonday->format('d')   // DD (dia con cero: 01-31)
                       . $lotMonday->format('y');  // YY (ano 2 digitos: 26)

        return $dateComponent . 'x' . $suffix;
    }

    /**
     * Determina el workstation_type predominante del PS.
     *
     * Se toma la parte con mayor quantity_packed y se busca su precio activo.
     * Si hay empate, se usa el primero que aparezca.
     *
     * @param  PackingSlip $ps  (con items.lot.workOrder.purchaseOrder.part.prices cargados)
     * @return string|null
     */
    private function getDominantWorkstationType(PackingSlip $ps): ?string
    {
        // Ordenar por cantidad descendente, tomar el item con mayor cantidad
        $dominantItem = $ps->items->sortByDesc('quantity_packed')->first();

        if (! $dominantItem) {
            return null;
        }

        $part = $dominantItem->lot?->workOrder?->purchaseOrder?->part;

        if (! $part) {
            return null;
        }

        $price = Price::getActivePriceForPart($part->id);

        return $price?->workstation_type;
    }
}
