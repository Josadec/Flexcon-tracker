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

        return view('livewire.admin.materials.materials-hub-dashboard', [
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
