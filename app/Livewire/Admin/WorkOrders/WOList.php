<?php

namespace App\Livewire\Admin\WorkOrders;

use App\Models\StatusWO;
use App\Models\WorkOrder;
use Livewire\Component;
use Livewire\WithPagination;

class WOList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'opened_date';
    public string $sortDirection = 'desc';
    public int $perPage = 10;
    public ?int $filterStatus = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingStartDate(): void
    {
        $this->resetPage();
    }

    public function updatingEndDate(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortField = $field;
    }

    public function deleteWorkOrder(int $id): void
    {
        $wo = WorkOrder::findOrFail($id);
        try {
            $wo->forceDeleteWithRelations();
            // Sólo `success`: esta pantalla ya pinta el aviso en línea con
            // <x-ui.note>. Si además se flasheara `flash.banner`, el layout
            // mostraría el mismo mensaje dos veces.
            session()->flash('success', 'Orden de trabajo y registros relacionados eliminados correctamente.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error al eliminar la Work Order: ' . $e->getMessage());
        }
    }

    // updateStatus() vivía aquí y no lo llamaba nadie (ni la vista vieja ni la
    // nueva): el cambio de estado se hace desde la ficha, con WOShow::updateStatus.
    // Se recupera de 51d4cea: WOList.php líneas 68-75 (y con él la inyección de
    // PurchaseOrderService de las líneas 7, 23 y 25-28, que era su único uso).

    public function clearFilters(): void
    {
        $this->reset(['filterStatus', 'startDate', 'endDate', 'search']);
        $this->resetPage();
    }

    public function render()
    {
        $query = WorkOrder::with(['purchaseOrder.part', 'status'])
            ->search($this->search)
            ->filterByStatus($this->filterStatus)
            ->filterByDateRange($this->startDate, $this->endDate);

        $workOrders = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $totalWOs = WorkOrder::count();

        // Una sola consulta agrupada: antes la vista contaba dentro del @foreach
        // de estados y disparaba una query por tarjeta.
        $statusCounts = WorkOrder::query()
            ->selectRaw('status_id, COUNT(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id')
            ->all();

        return view('livewire.admin.work-orders.wo-list', [
            'workOrders' => $workOrders,
            'statuses' => StatusWO::all(),
            'totalWOs' => $totalWOs,
            'statusCounts' => $statusCounts,
        ]);
    }
}
