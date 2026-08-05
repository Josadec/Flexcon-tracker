<?php

namespace App\Livewire\Admin\Materials;

use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\SentList;
use App\Support\PendingActions;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MaterialsHubDashboard extends Component
{
    /**
     * Tablero del área de Materiales.
     *
     * Materiales interviene en cuatro momentos del flujo: libera el material,
     * captura los lotes de CRIMP, toma la decisión de cierre y recibe los
     * sobrantes. El tablero se ordena por esos cuatro momentos.
     *
     * Los pendientes salen de App\Support\PendingActions, la misma fuente que
     * usan el tablero de administración y el de piso.
     */
    public function render()
    {
        $pending = PendingActions::make();

        $mine       = $pending->forActor('Materiales');
        $advisories = $pending->advisories('Materiales');
        $counts     = $pending->countsByPhase();

        // Lo que Materiales tiene enfrente, por momento del flujo.
        $byPhase = [
            'release'  => $counts['material_release'] + $counts['crimp_release'],
            'decision' => $counts['decision'],
            'surplus'  => $counts['surplus_receive'],
        ];

        // ── Inventario de CRIMP ──────────────────────────────────────────
        $crimpViajeros = Lot::has('crimpLots')->count();
        $crimpLotsTotal = CrimpLot::count();
        $crimpPieces = (int) CrimpLot::sum('quantity');

        // ── Listas de envío paradas en Materiales ────────────────────────
        $sentListsHere = SentList::with(['workOrders.purchaseOrder.part'])
            ->where('current_department', SentList::DEPT_MATERIALS)
            ->where('status', SentList::STATUS_PENDING)
            ->latest()
            ->take(8)
            ->get();

        // ── Sobrantes en tránsito: entregados por Empaque, sin recibir ───
        $surplusInTransit = $pending->items()
            ->where('phase', 'surplus_receive')
            ->sum(fn ($i) => $i['lot']->getPackagingTotalSurplus());

        return view('livewire.admin.materials.materials-hub-dashboard', [
            'mine'             => $mine,
            'advisories'       => $advisories,
            'byPhase'          => $byPhase,
            'totalPending'     => $mine->count(),
            'crimpViajeros'    => $crimpViajeros,
            'crimpLotsTotal'   => $crimpLotsTotal,
            'crimpPieces'      => $crimpPieces,
            'sentListsHere'    => $sentListsHere,
            'surplusInTransit' => (int) $surplusInTransit,
            'lotsLive'         => $pending->lots()->count(),
        ]);
    }
}
