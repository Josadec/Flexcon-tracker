<?php

namespace App\Livewire\Admin\Production;

use App\Models\SentList;
use App\Models\Weighing;
use App\Support\PendingActions;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProductionHubDashboard extends Component
{
    /**
     * Tablero del área de Producción.
     *
     * Producción tiene un solo trabajo en el flujo: pesar las piezas de los
     * lotes que Calidad ya aprobó en inspección. El tablero gira alrededor de
     * eso y de la productividad del turno.
     *
     * Los pendientes salen de App\Support\PendingActions, la misma fuente que
     * usan el tablero de administración y el de piso.
     */
    public function render()
    {
        $pending = PendingActions::make();

        $mine = $pending->forActor('Producción');

        // ── Productividad ────────────────────────────────────────────────
        $todayWeighings = Weighing::whereDate('weighed_at', today())->count();
        $todayPieces    = (int) Weighing::whereDate('weighed_at', today())->sum('good_pieces');
        $weekPieces     = (int) Weighing::where('weighed_at', '>=', now()->startOfWeek())->sum('good_pieces');
        $totalPieces    = (int) Weighing::sum('good_pieces');

        // Piezas por día de la última semana, para ver la tendencia del turno.
        $dailySeries = collect(range(6, 0))->map(function ($daysAgo) {
            $day = now()->subDays($daysAgo);

            return [
                'label'  => $day->translatedFormat('D'),
                'date'   => $day->format('d/m'),
                'pieces' => (int) Weighing::whereDate('weighed_at', $day)->sum('good_pieces'),
            ];
        });
        $maxDaily = max(1, $dailySeries->max('pieces'));

        // ── Quién está pesando (últimos 30 días) ─────────────────────────
        $topOperators = Weighing::query()
            ->select('weighed_by', DB::raw('COUNT(*) as total_weighings'), DB::raw('SUM(good_pieces) as total_good'))
            ->where('weighed_at', '>=', now()->subDays(30))
            ->whereNotNull('weighed_by')
            ->groupBy('weighed_by')
            ->orderByDesc('total_good')
            ->limit(5)
            ->with('weighedBy')
            ->get();

        // ── Últimas pesadas ──────────────────────────────────────────────
        $recentWeighings = Weighing::with(['lot.workOrder.purchaseOrder.part', 'weighedBy'])
            ->orderByDesc('weighed_at')
            ->limit(8)
            ->get();

        // ── Listas de envío paradas en Producción ────────────────────────
        $sentListsHere = SentList::with(['workOrders.purchaseOrder.part'])
            ->where('current_department', SentList::DEPT_PRODUCTION)
            ->where('status', SentList::STATUS_PENDING)
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.admin.production.production-hub-dashboard', [
            'mine'            => $mine,
            'totalPending'    => $mine->count(),
            'piecesPending'   => $pending->piecesPendingProduction(),
            'todayWeighings'  => $todayWeighings,
            'todayPieces'     => $todayPieces,
            'weekPieces'      => $weekPieces,
            'totalPieces'     => $totalPieces,
            'dailySeries'     => $dailySeries,
            'maxDaily'        => $maxDaily,
            'topOperators'    => $topOperators,
            'recentWeighings' => $recentWeighings,
            'sentListsHere'   => $sentListsHere,
        ]);
    }
}
