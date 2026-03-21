<?php

namespace App\Http\Controllers;

use App\Models\SentList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SentListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $query = SentList::with(['purchaseOrder.part', 'purchaseOrders.part', 'workOrders', 'shifts'])
            ->orderBy('created_at', 'desc');

        // Filtrar por departamento según el rol (admin y Materiales ven todo)
        if (!$user->hasRole('admin') && !$user->hasRole('Materiales')) {
            if ($user->hasRole('Produccion')) {
                $query->where(function ($q) {
                    $q->where('current_department', SentList::DEPT_PRODUCTION)
                      ->orWhereNotNull('production_approved_at');
                });
            } elseif ($user->hasRole('Calidad')) {
                $query->where(function ($q) {
                    $q->whereIn('current_department', [SentList::DEPT_QUALITY, SentList::DEPT_INSPECTION])
                      ->orWhereNotNull('quality_approved_at')
                      ->orWhereNotNull('inspection_approved_at');
                });
            } elseif ($user->hasRole('Empaques')) {
                $query->where(function ($q) {
                    $q->where('current_department', SentList::DEPT_SHIPPING)
                      ->orWhereNotNull('shipping_approved_at');
                });
            }
        }

        $sentLists = $query->paginate(15);

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
            return redirect()->route('admin.sent-lists.index')
                ->with('error', 'No se pueden eliminar listas confirmadas.');
        }

        $sentList->delete();

        return redirect()->route('admin.sent-lists.index')
            ->with('success', 'Lista de envío eliminada exitosamente.');
    }
}
