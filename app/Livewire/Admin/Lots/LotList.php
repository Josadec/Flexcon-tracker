<?php

namespace App\Livewire\Admin\Lots;

use App\Models\Lot;
use App\Models\WorkOrder;
use Livewire\Component;
use Livewire\WithPagination;

class LotList extends Component
{
    use WithPagination;

    public ?int $workOrderId = null;
    public ?WorkOrder $workOrder = null;
    public string $search = '';
    public string $filterStatus = '';
    public int $perPage = 10;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public bool $confirmingDeletion = false;
    public ?int $lotToDelete = null;
    
    // Modal para cambiar estado
    public bool $showStatusModal = false;
    public ?int $selectedLotId = null;
    public string $newStatus = '';

    public function mount(?int $workOrderId = null): void
    {
        $this->workOrderId = $workOrderId;
        if ($workOrderId) {
            $this->workOrder = WorkOrder::with('purchaseOrder.part')->find($workOrderId);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['lot_number', 'quantity', 'status', 'created_at'];

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (!in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function confirmDeletion(int $id): void
    {
        $this->lotToDelete = $id;
        $this->confirmingDeletion = true;
    }

    public function delete(): void
    {
        $lot = $this->lotToDelete ? Lot::find($this->lotToDelete) : null;

        if (!$lot) {
            session()->flash('error', 'No se encontró el viajero que quieres eliminar.');
            $this->cancelDeletion();

            return;
        }

        // El `|| status === completed` que había aquí saltaba el guard justo
        // para los viajeros que más historial tienen.
        if ($motivo = $lot->getDeleteBlockReason()) {
            session()->flash('error', $motivo);
            $this->cancelDeletion();

            return;
        }

        $lot->delete();
        session()->flash('message', 'Viajero eliminado correctamente.');

        $this->cancelDeletion();
    }

    public function cancelDeletion(): void
    {
        $this->confirmingDeletion = false;
        $this->lotToDelete = null;
    }

    public function startLot(int $id): void
    {
        $lot = Lot::find($id);
        if ($lot && $lot->canBeStarted()) {
            $lot->update(['status' => Lot::STATUS_IN_PROGRESS]);
            session()->flash('message', 'Lote iniciado.');
        }
    }

    public function completeLot(int $id): void
    {
        $lot = Lot::find($id);
        if ($lot && $lot->canBeCompleted()) {
            $lot->update(['status' => Lot::STATUS_COMPLETED]);
            session()->flash('message', 'Lote completado.');
        }
    }

    public function cancelLot(int $id): void
    {
        $lot = Lot::find($id);
        if ($lot && $lot->canBeCancelled()) {
            $lot->update(['status' => Lot::STATUS_CANCELLED]);
            session()->flash('message', 'Lote cancelado.');
        }
    }

    public function openStatusModal(int $id): void
    {
        $lot = Lot::find($id);
        if ($lot) {
            $this->selectedLotId = $id;
            $this->newStatus = $lot->status;
            $this->showStatusModal = true;
        }
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
        $this->selectedLotId = null;
        $this->newStatus = '';
    }

    public function setNewStatus(string $status): void
    {
        $this->newStatus = $status;
    }

    /**
     * ¿A qué estados puede moverse este viajero desde el que tiene?
     *
     * Se calcula con los mismos guards que usan los botones de la tabla; el
     * modal no puede ser una puerta trasera para saltarse el flujo.
     */
    public function allowedTransitions(Lot $lot): array
    {
        $permitidos = [$lot->status];

        if ($lot->canBeStarted()) {
            $permitidos[] = Lot::STATUS_IN_PROGRESS;
        }

        if ($lot->canBeCompleted()) {
            $permitidos[] = Lot::STATUS_COMPLETED;
        }

        if ($lot->canBeCancelled()) {
            $permitidos[] = Lot::STATUS_CANCELLED;
        }

        return array_values(array_unique($permitidos));
    }

    public function updateLotStatus(): void
    {
        if (!$this->selectedLotId || !$this->newStatus) {
            return;
        }

        $lot = Lot::find($this->selectedLotId);

        if (!$lot) {
            session()->flash('error', 'Viajero no encontrado.');
            $this->closeStatusModal();

            return;
        }

        // Antes se escribía cualquier cadena que llegara del cliente, sin
        // validar el valor ni respetar las transiciones válidas.
        if (!in_array($this->newStatus, $this->allowedTransitions($lot), true)) {
            session()->flash('error', 'Ese cambio de estado no es válido para este viajero.');
            $this->closeStatusModal();

            return;
        }

        if ($this->newStatus === $lot->status) {
            $this->closeStatusModal();

            return;
        }

        $lot->update(['status' => $this->newStatus]);

        $etiquetas = Lot::getStatuses();
        session()->flash('message', 'Estado del viajero actualizado a: ' . ($etiquetas[$this->newStatus] ?? $this->newStatus) . '.');

        $this->closeStatusModal();
    }

    public function render()
    {
        $query = Lot::with(['workOrder.purchaseOrder.part', 'crimpLots'])
            ->search($this->search)
            ->when($this->filterStatus, fn($q) => $q->status($this->filterStatus))
            ->when($this->workOrderId, fn($q) => $q->where('work_order_id', $this->workOrderId))
            ->orderBy($this->sortField, $this->sortDirection);

        $lots = $query->paginate($this->perPage);

        $base = fn () => Lot::query()->when($this->workOrderId, fn ($q) => $q->where('work_order_id', $this->workOrderId));

        return view('livewire.admin.lots.lot-list', [
            'lots' => $lots,
            'statuses' => Lot::getStatuses(),
            'stats' => [
                'total' => $base()->count(),
                'en_progreso' => $base()->where('status', Lot::STATUS_IN_PROGRESS)->count(),
                'completados' => $base()->where('status', Lot::STATUS_COMPLETED)->count(),
                'piezas' => (int) $base()->sum('quantity'),
            ],
        ]);
    }
}
