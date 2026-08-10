<?php

namespace App\Livewire\Admin\Materials;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\WorkOrder;
use App\Models\Lot;
use App\Models\CrimpLot;
use App\Models\SentList;

#[Layout('components.layouts.app')]
class MaterialsAreaDashboard extends Component
{
    public string $viewMode = 'work-orders'; // work-orders | lots

    public function switchView(string $mode): void
    {
        if (in_array($mode, ['work-orders', 'lots'], true)) {
            $this->viewMode = $mode;
        }
    }

    /**
     * Métricas de lo que Materiales tiene enfrente.
     *
     * Antes eran conteos generales del sistema (total de órdenes, total de
     * viajeros); ahora cuentan trabajo pendiente del área y separan CRIMP de
     * no-CRIMP, que es lo que cambia el procedimiento.
     */
    public function getStatsProperty(): array
    {
        $porLiberar = Lot::where('material_status', 'pending');
        $crimp = fn () => Lot::whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', true));

        return [
            'por_liberar' => (clone $porLiberar)->count(),
            'por_liberar_crimp' => (clone $porLiberar)->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', true))->count(),
            'viajeros_crimp' => $crimp()->count(),
            'crimp_sin_lotes' => $crimp()->doesntHave('crimpLots')->count(),
            'lotes_crimp' => CrimpLot::count(),
            'piezas_crimp' => (int) CrimpLot::sum('quantity'),
            'ordenes_activas' => WorkOrder::whereHas('lots')->count(),
        ];
    }

    public function render()
    {
        $pendingSentLists = SentList::with(['workOrders.purchaseOrder.part', 'workOrders.lots', 'unresolvedRejections'])
            ->where('current_department', SentList::DEPT_MATERIALS)
            ->where('status', SentList::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.admin.materials.materials-area-dashboard', [
            'stats' => $this->stats,
            'pendingSentLists' => $pendingSentLists,
        ]);
    }
}
