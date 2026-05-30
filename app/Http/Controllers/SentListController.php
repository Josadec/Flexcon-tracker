<?php

namespace App\Http\Controllers;

use App\Models\SentList;
use Illuminate\Http\Request;

class SentListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Todas las áreas ven todas las listas preliminares, sin importar la
        // etapa del flujo en la que estén (visibilidad de solo lectura para
        // todos los roles autorizados por la ruta).
        $sentLists = SentList::with(['purchaseOrder.part', 'purchaseOrders.part', 'workOrders', 'shifts'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('sent-lists.index', compact('sentLists'));
    }

    /**
     * Display the specified resource.
     * Routes to the appropriate department view based on current_department.
     */
    public function show(SentList $sentList)
    {
        $sentList->load([
            'purchaseOrders.part',
            'workOrders.purchaseOrder.part',
            'workOrders.lots.weighings',
            'workOrders.lots.qualityWeighings',
            'workOrders.lots.packagingRecords',
            'workOrders.kits',
            'shifts',
            'materialsApprover',
            'inspectionApprover',
            'productionApprover',
            'qualityApprover',
            'shippingApprover',
            'unresolvedRejections.rejectedBy',
            'unresolvedRejections.lot',
        ]);

        return view('sent-lists.show', compact('sentList'));
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

        return redirect()->route('admin.sent-lists.show', $sentList)
            ->with('success', 'Lista de envío actualizada exitosamente.');
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

        $sentList->delete();

        return redirect()->route('admin.sent-lists.index')
            ->with('success', 'Lista de envío eliminada exitosamente.');
    }
}
