<?php

namespace App\Livewire\Admin\Quality;

use App\Models\Lot;
use App\Models\QualityWeighing;
use App\Models\SentList;
use App\Support\PendingActions;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class QualityAreaDashboard extends Component
{
    /**
     * Tablero del área de Calidad.
     *
     * Calidad interviene dos veces y son cosas distintas:
     *   1. Inspección — aprueba el lote ANTES de que Producción lo trabaje.
     *   2. Verificación — pesa y aprueba las piezas DESPUÉS de producirlas.
     * El tablero las separa porque son dos colas de trabajo diferentes.
     *
     * Los pendientes salen de App\Support\PendingActions, la misma fuente que
     * usan el tablero de administración y el de piso.
     */
    public function render()
    {
        $pending = PendingActions::make();

        $mine       = $pending->forActor('Calidad');
        $inspection = $mine->where('phase', 'inspection')->values();
        $verify     = $mine->where('phase', 'quality')->values();

        // ── Resultado de las inspecciones ────────────────────────────────
        $approved = Lot::where('inspection_status', Lot::INSPECTION_APPROVED)->count();
        $rejected = Lot::where('inspection_status', Lot::INSPECTION_REJECTED)->count();

        // Lotes rechazados que siguen detenidos: son los que hay que destrabar.
        $rejectedLots = Lot::with(['workOrder.purchaseOrder.part'])
            ->where('inspection_status', Lot::INSPECTION_REJECTED)
            ->where('status', '!=', Lot::STATUS_COMPLETED)
            ->latest('updated_at')
            ->take(8)
            ->get();

        // ── Piezas verificadas y descarte ────────────────────────────────
        $goodPieces = (int) QualityWeighing::sum('good_pieces');
        $badPieces  = (int) QualityWeighing::sum('bad_pieces');
        $totalSeen  = $goodPieces + $badPieces;
        $rejectRate = $totalSeen > 0 ? round(($badPieces / $totalSeen) * 100, 1) : 0.0;

        $todayGood = (int) QualityWeighing::whereDate('weighed_at', today())->sum('good_pieces');
        $todayBad  = (int) QualityWeighing::whereDate('weighed_at', today())->sum('bad_pieces');

        // ── Últimas verificaciones ───────────────────────────────────────
        $recentWeighings = QualityWeighing::with(['lot.workOrder.purchaseOrder.part', 'weighedBy'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // ── Listas de envío paradas en Calidad o Inspección ──────────────
        $sentListsHere = SentList::with(['workOrders.purchaseOrder.part'])
            ->whereIn('current_department', [SentList::DEPT_INSPECTION, SentList::DEPT_QUALITY])
            ->where('status', SentList::STATUS_PENDING)
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.admin.quality.quality-area-dashboard', [
            'mine'            => $mine,
            'inspectionQueue' => $inspection,
            'verifyQueue'     => $verify,
            'totalPending'    => $mine->count(),
            'piecesPending'   => $pending->piecesPendingQuality(),
            'approved'        => $approved,
            'rejected'        => $rejected,
            'rejectedLots'    => $rejectedLots,
            'goodPieces'      => $goodPieces,
            'badPieces'       => $badPieces,
            'rejectRate'      => $rejectRate,
            'todayGood'       => $todayGood,
            'todayBad'        => $todayBad,
            'recentWeighings' => $recentWeighings,
            'sentListsHere'   => $sentListsHere,
        ]);
    }
}
