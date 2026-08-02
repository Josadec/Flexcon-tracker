<?php

namespace App\Livewire\Admin\Quality;

use App\Models\Lot;
use App\Models\QualityWeighing;
use App\Models\WorkOrder;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use Livewire\Component;
use App\Traits\ComputesAreaStats;

class QualityAreaDashboard extends Component
{
    use ComputesAreaStats;
    public function render()
    {
        // ── WO metrics ──
        $totalWOs = WorkOrder::count();
        $activeWOs = WorkOrder::whereHas('purchaseOrder', fn($q) => $q->where('status', 'active'))
            ->count();
        $closedWOs = WorkOrder::whereHas('purchaseOrder', fn($q) => $q->where('status', 'closed'))
            ->count();

        // ── Inspection metrics ──
        // El gate del flujo vigente es la liberación a nivel viajero/lote
        // (material_status = 'released'), no el estado de Kit (ver Lot::canBeInspected).
        $pendingInspection = Lot::where('material_status', 'released')
            ->where('inspection_status', Lot::INSPECTION_PENDING)
            ->count();
        $approvedInspection = Lot::where('inspection_status', Lot::INSPECTION_APPROVED)->count();
        $rejectedInspection = Lot::where('inspection_status', Lot::INSPECTION_REJECTED)->count();

        // ── Quality weighing metrics ──
        $totalWithProd = Lot::whereHas('weighings')->count();
        $pendingQuality = Lot::whereHas('weighings')
            ->where(function ($q) {
                $q->whereDoesntHave('qualityWeighings')
                    ->orWhereRaw('(SELECT COALESCE(SUM(good_pieces),0) + COALESCE(SUM(bad_pieces),0) FROM quality_weighings WHERE quality_weighings.lot_id = lots.id AND quality_weighings.deleted_at IS NULL) < (SELECT COALESCE(SUM(good_pieces),0) FROM weighings WHERE weighings.lot_id = lots.id AND weighings.deleted_at IS NULL)');
            })->count();
        $completedQuality = Lot::whereHas('weighings')
            ->whereRaw('(SELECT COALESCE(SUM(good_pieces),0) + COALESCE(SUM(bad_pieces),0) FROM quality_weighings WHERE quality_weighings.lot_id = lots.id AND quality_weighings.deleted_at IS NULL) >= (SELECT COALESCE(SUM(good_pieces),0) FROM weighings WHERE weighings.lot_id = lots.id AND weighings.deleted_at IS NULL)')
            ->count();
        $withRejected = Lot::whereHas('qualityWeighings', function ($q) {
            $q->where('bad_pieces', '>', 0);
        })->count();

        // ── Recent quality weighings ──
        $recentQualityWeighings = QualityWeighing::with(['lot.workOrder.purchaseOrder.part', 'weighedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Calidad maneja tanto el paso de Inspección como el de Calidad
        $pendingSentLists = SentList::with(['workOrders.purchaseOrder.part', 'workOrders.lots'])
            ->whereIn('current_department', [SentList::DEPT_INSPECTION, SentList::DEPT_QUALITY])
            ->where('status', SentList::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();

        // ── CRIMP: viajeros pendientes de Inspección / Calidad ───────────
        $crimpViajeros = Lot::query()
            ->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', true))
            ->where('status', '!=', Lot::STATUS_COMPLETED)
            ->with(['workOrder.purchaseOrder.part', 'crimpLots', 'weighings', 'qualityWeighings'])
            ->get();

        $qInspeccion = 0;
        $qVerificar = 0;
        $qPendientes = collect();

        foreach ($crimpViajeros as $vj) {
            if (($vj->material_status ?? 'pending') !== 'released') {
                continue; // esperando liberación de Materiales
            }
            if (($vj->inspection_status ?? 'pending') === 'pending') {
                $qInspeccion++;
                $qPendientes->push(['lot' => $vj, 'action' => 'Inspeccionar viajero', 'kind' => 'inspect']);
                continue;
            }
            if ($vj->hasProductionWeighings()) {
                $pend = $vj->getQualityPendingPieces();
                if ($pend > 0) {
                    $qVerificar++;
                    $qPendientes->push(['lot' => $vj, 'action' => 'Verificar calidad ('.number_format($pend).' pz)', 'kind' => 'verify']);
                }
            }
        }

        return view('livewire.admin.quality.quality-area-dashboard', [
            'qInspeccion'  => $qInspeccion,
            'qVerificar'   => $qVerificar,
            'qPendientes'  => $qPendientes,
            'areaStats' => $this->computeAreaStats(),
            'pendingSentLists'  => $pendingSentLists,
            'totalWOs' => $totalWOs,
            'activeWOs' => $activeWOs,
            'closedWOs' => $closedWOs,
            'pendingInspection' => $pendingInspection,
            'approvedInspection' => $approvedInspection,
            'rejectedInspection' => $rejectedInspection,
            'totalWithProd' => $totalWithProd,
            'pendingQuality' => $pendingQuality,
            'completedQuality' => $completedQuality,
            'withRejected' => $withRejected,
            'recentQualityWeighings' => $recentQualityWeighings,
        ])->layout('components.layouts.app');
    }
}
