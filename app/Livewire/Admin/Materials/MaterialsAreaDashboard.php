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
    public string $viewMode = 'work-orders'; // Default to work-orders view
    public string $searchTerm = '';

    /**
     * Switch between different views.
     */
    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
        $this->dispatch('view-switched', mode: $mode);
    }

    /**
     * Get statistics for the dashboard.
     */
    public function getStatsProperty(): array
    {
        return [
            'total_work_orders' => WorkOrder::whereHas('lots')->count(),
            'total_lots' => Lot::count(),
            'pending_lots' => Lot::where('status', 'pending')->count(),
            'total_crimp_lots' => CrimpLot::count(),
            'crimp_lots_qty' => (int) CrimpLot::sum('quantity'),
            'viajeros_con_crimp' => Lot::has('crimpLots')->count(),
        ];
    }

    /**
     * Render the component.
     */
    public function render()
    {
        $pendingSentLists = SentList::with(['workOrders.purchaseOrder.part', 'workOrders.lots', 'unresolvedRejections'])
            ->where('current_department', SentList::DEPT_MATERIALS)
            ->where('status', SentList::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.admin.materials.materials-area-dashboard', [
            'stats'            => $this->stats,
            'pendingSentLists' => $pendingSentLists,
        ]);
    }
}
