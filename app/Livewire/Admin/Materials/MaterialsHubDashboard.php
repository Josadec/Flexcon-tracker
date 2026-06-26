<?php

namespace App\Livewire\Admin\Materials;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\WorkOrder;
use App\Models\Lot;
use App\Models\CrimpLot;
use App\Models\SentList;
use App\Traits\ComputesAreaStats;

#[Layout('components.layouts.app')]
class MaterialsHubDashboard extends Component
{
    use ComputesAreaStats;
    public function render()
    {
        // ── Work Order metrics ──
        $totalWOs = WorkOrder::whereHas('lots')->count();
        $activeWOs = WorkOrder::whereHas('purchaseOrder', fn($q) => $q->where('status', 'active'))
            ->whereHas('lots')
            ->count();
        $closedWOs = WorkOrder::whereHas('purchaseOrder', fn($q) => $q->where('status', 'closed'))
            ->whereHas('lots')
            ->count();

        // ── Lot metrics ──
        $totalLots = Lot::count();
        $pendingLots = Lot::where('status', 'pending')->count();
        $inProgressLots = Lot::where('status', 'in_progress')->count();
        $completedLots = Lot::where('status', 'completed')->count();

        // ── Lotes de CRIMP (reemplaza a las métricas de Kit) ──
        $totalCrimpLots   = CrimpLot::count();
        $crimpLotsQty     = (int) CrimpLot::sum('quantity');
        $viajerosConCrimp = Lot::has('crimpLots')->count();

        // ── Sent List metrics ──
        $totalSentLists = SentList::count();
        $recentSentLists = SentList::with(['workOrders'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // ── CRIMP: acciones pendientes de Materiales ─────────────────────
        $crimpViajeros = Lot::query()
            ->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', true))
            ->where('status', '!=', Lot::STATUS_COMPLETED)
            ->with([
                'workOrder.purchaseOrder.part',
                'crimpLots',
                'qualityWeighings', 'weighings', 'packagingRecords',
                'packagingPieceWeighings', 'packagingCrimpWeighings',
            ])
            ->get();

        $matLiberar = 0;
        $matDecision = 0;
        $matSobrantes = 0;
        $matPendientes = collect();

        foreach ($crimpViajeros as $vj) {
            if (($vj->material_status ?? 'pending') !== 'released') {
                $matLiberar++;
                $matPendientes->push(['lot' => $vj, 'action' => 'Liberar material', 'kind' => 'release']);
                continue;
            }
            $next = $vj->getNextPendingAction();
            if ($next && ($next['actor'] ?? null) === 'Materiales') {
                if ($next['phase'] === 'decision') {
                    $matDecision++;
                    $matPendientes->push(['lot' => $vj, 'action' => 'Tomar decisión (Paso 6)', 'kind' => 'decision']);
                } elseif ($next['phase'] === 'material') {
                    $matSobrantes++;
                    $matPendientes->push(['lot' => $vj, 'action' => $next['label'], 'kind' => 'surplus']);
                }
            }
        }

        return view('livewire.admin.materials.materials-hub-dashboard', [
            'matLiberar'     => $matLiberar,
            'matDecision'    => $matDecision,
            'matSobrantes'   => $matSobrantes,
            'matPendientes'  => $matPendientes,
            'areaStats' => $this->computeAreaStats(),
            'totalWOs' => $totalWOs,
            'activeWOs' => $activeWOs,
            'closedWOs' => $closedWOs,
            'totalLots' => $totalLots,
            'pendingLots' => $pendingLots,
            'inProgressLots' => $inProgressLots,
            'completedLots' => $completedLots,
            'totalCrimpLots' => $totalCrimpLots,
            'crimpLotsQty' => $crimpLotsQty,
            'viajerosConCrimp' => $viajerosConCrimp,
            'totalSentLists' => $totalSentLists,
            'recentSentLists' => $recentSentLists,
        ]);
    }
}
