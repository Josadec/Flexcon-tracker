<?php

namespace App\Livewire\Admin\Packaging;

use App\Models\Lot;
use App\Models\PackagingRecord;
use App\Models\SentList;
use App\Support\PendingActions;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PackagingDashboard extends Component
{
    /**
     * Tablero del área de Empaque.
     *
     * Empaque cierra el ciclo: empaca las piezas que Calidad aprobó, entrega el
     * viajero y devuelve los sobrantes a Materiales. El dato que más importa
     * aquí son los sobrantes, porque son piezas físicas que alguien tiene que
     * mover y que se pierden si nadie las reclama.
     *
     * Los pendientes salen de App\Support\PendingActions, la misma fuente que
     * usan el tablero de administración y el de piso.
     */
    public function render()
    {
        $pending = PendingActions::make();

        $mine    = $pending->forActor('Empaque');
        $viajero = $mine->where('phase', 'viajero')->values();
        $surplus = $mine->where('phase', 'surplus_deliver')->values();

        // Piezas sobrantes que Empaque todavía tiene en su poder.
        $surplusToDeliver = (int) $surplus->sum(fn ($i) => $i['lot']->getPackagingTotalSurplus());

        // ── Producción de empaque ────────────────────────────────────────
        $packedTotal   = (int) PackagingRecord::sum('packed_pieces');
        $surplusTotal  = (int) PackagingRecord::sum('surplus_pieces');
        $todayPacked   = (int) PackagingRecord::whereDate('packed_at', today())->sum('packed_pieces');
        $todayRecords  = PackagingRecord::whereDate('packed_at', today())->count();

        // ── Ciclos cerrados ──────────────────────────────────────────────
        $lotsClosed = Lot::whereNotNull('closure_decision')->count();

        // ── Últimos registros de empaque ─────────────────────────────────
        $recentRecords = PackagingRecord::with(['lot.workOrder.purchaseOrder.part', 'packedBy'])
            ->orderByDesc('packed_at')
            ->limit(8)
            ->get();

        // ── Listas de envío paradas en Empaque ───────────────────────────
        $sentListsHere = SentList::with(['workOrders.purchaseOrder.part'])
            ->where('current_department', SentList::DEPT_SHIPPING)
            ->where('status', SentList::STATUS_PENDING)
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.admin.packaging.packaging-dashboard', [
            'mine'             => $mine,
            'viajeroQueue'     => $viajero,
            'surplusQueue'     => $surplus,
            'totalPending'     => $mine->count(),
            'surplusToDeliver' => $surplusToDeliver,
            'packedTotal'      => $packedTotal,
            'surplusTotal'     => $surplusTotal,
            'todayPacked'      => $todayPacked,
            'todayRecords'     => $todayRecords,
            'lotsClosed'       => $lotsClosed,
            'recentRecords'    => $recentRecords,
            'sentListsHere'    => $sentListsHere,
        ]);
    }
}
