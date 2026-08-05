<?php

namespace App\Http\Controllers;

use App\Models\SentList;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SentListController extends Controller
{
    /**
     * NOTA: el listado y el detalle ya no viven aquí.
     *
     * `admin.sent-lists.index` → App\Livewire\Admin\SentLists\SentListIndex
     * `admin.sent-lists.show`  → App\Livewire\Admin\SentLists\SentListShow
     *
     * Se movieron a Livewire para poder buscar, filtrar, ordenar y cambiar el
     * estado sin recargar la página. Aquí sólo quedan las acciones que no son
     * pantallas: exportar el PDF, el formulario de estado y el borrado.
     */

    /**
     * Export the shipping list (Lista de Envío, FPL-02) of a single SentList as PDF.
     *
     * Reproduce el documento físico de Ensambles Formula con las WOs de ESTA lista
     * preliminar (reflejando sus últimos cambios), agrupadas en bandas:
     *   Atrasados (POs carryover) → Mesas → Máquinas → Máquinas Semi-Automáticas.
     * Las WOs se resuelven igual que la vista enfocada de ShippingListDisplay:
     * por sent_list_id directo o vía el pivot PO ↔ SentList.
     * La banda de cada WO no-carryover usa PO.workstation_type con fallback al
     * modo de ensamble del Standard activo.
     */
    public function exportPdf(SentList $sentList)
    {
        $workOrders = WorkOrder::with([
                'purchaseOrder.part.standards' => fn ($q) => $q->active(),
                'lots',
                'lots.crimpLots' => fn ($q) => $q->orderBy('crimp_lot_number'),
                'sentList',
            ])
            ->where(function ($q) use ($sentList) {
                $q->where('sent_list_id', $sentList->id)
                  ->orWhereHas('purchaseOrder.sentLists', fn ($sub) => $sub->where('sent_lists.id', $sentList->id));
            })
            ->orderBy('wo_number')
            ->get();

        // POs marcadas como carryover en cualquier lista → banda "Atrasados".
        $carryoverPoIds = DB::table('sent_list_purchase_orders')
            ->where('is_carryover', true)
            ->pluck('purchase_order_id')
            ->flip();

        $resolveWorkstation = function (WorkOrder $wo) {
            $woType = $wo->purchaseOrder->workstation_type ?? null;
            $mode = $woType ?: optional($wo->purchaseOrder->part->standards->first())->getAssemblyMode();

            return match ($mode) {
                'manual', 'table' => 'Mesas',
                'machine'         => 'Maquinas',
                'semi_automatic'  => 'Maquinas Semi-Automaticas',
                default           => 'Sin Clasificar',
            };
        };

        $groups = [];
        foreach ($workOrders as $wo) {
            $band = isset($carryoverPoIds[$wo->purchase_order_id])
                ? 'Atrasados'
                : $resolveWorkstation($wo);
            $groups[$band][] = $wo;
        }

        // Orden fijo de las bandas, omitiendo las vacías.
        $bandOrder = ['Atrasados', 'Mesas', 'Maquinas', 'Maquinas Semi-Automaticas', 'Sin Clasificar'];
        $ordered = [];
        foreach ($bandOrder as $band) {
            if (! empty($groups[$band])) {
                $ordered[$band] = collect($groups[$band]);
            }
        }

        $pdf = Pdf::loadView('sent-lists.pdf.shipping-list', [
            'groups'      => $ordered,
            'sentList'    => $sentList,
            'generatedAt' => now(),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('lista-de-envio-' . $sentList->id . '-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SentList $sentList)
    {
        if (!$sentList->isPending()) {
            return redirect()->route('admin.sent-lists.show', $sentList)
                ->with('error', 'Solo las listas pendientes pueden ser editadas.');
        }

        $sentList->load(['purchaseOrders.part', 'workOrders']);

        return view('sent-lists.edit', compact('sentList'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SentList $sentList)
    {
        // Only pending sent lists can be updated
        if (!$sentList->isPending()) {
            return redirect()->route('admin.sent-lists.show', $sentList)
                ->with('error', 'Solo las listas pendientes pueden ser actualizadas.');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,canceled',
        ]);

        $sentList->update($validated);

        // 'message' es la clave que leen las vistas Livewire de listas.
        return redirect()->route('admin.sent-lists.show', $sentList)
            ->with('message', 'Estado de la lista actualizado a «'.$sentList->fresh()->status_label.'».');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SentList $sentList)
    {
        if (!$sentList->canBeDeleted()) {
            // Identificar la causa real para informar correctamente al usuario
            if (!$sentList->isPending()) {
                $message = $sentList->isCanceled()
                    ? 'No se pueden eliminar listas canceladas.'
                    : 'No se pueden eliminar listas confirmadas.';
            } else {
                $message = 'No se puede eliminar: la lista ya tiene órdenes de trabajo asociadas.';
            }

            return redirect()->route('admin.sent-lists.index')
                ->with('error', $message);
        }

        $id = $sentList->id;
        $sentList->delete();

        return redirect()->route('admin.sent-lists.index')
            ->with('message', "Lista #{$id} eliminada.");
    }
}
