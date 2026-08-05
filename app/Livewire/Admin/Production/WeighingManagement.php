<?php

namespace App\Livewire\Admin\Production;

use App\Models\Lot;
use App\Models\Weighing;
use Livewire\Component;
use Livewire\WithPagination;

class WeighingManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public int $perPage = 15;
    public string $sortField = 'weighed_at';
    public string $sortDirection = 'desc';

    // Modal crear/editar
    public bool $showFormModal = false;
    public ?int $editingWeighingId = null;
    public ?int $selectedLotId = null;
    public int $formQuantity = 0;
    public int $formWeighedPieces = 0;
    public string $formWeighedAt = '';
    public string $formComments = '';

    // Modal ver detalle
    public bool $showDetailModal = false;
    public ?Weighing $detailWeighing = null;

    // Modal confirmar eliminación
    public bool $confirmingDeletion = false;
    public ?int $weighingToDelete = null;

    // Datos para selects
    public $lots = [];

    public function mount(): void
    {
        $this->formWeighedAt = now()->format('Y-m-d\TH:i');
        $this->loadLots();
    }

    public function loadLots(): void
    {
        $this->lots = Lot::with(['workOrder.purchaseOrder.part'])
            ->orderBy('lot_number')
            ->get();
    }

    public function updatedSelectedLotId($value): void
    {
        $this->formQuantity = 0;

        if ($value) {
            $lot = Lot::find($value);
            if ($lot) {
                $this->formQuantity = $lot->quantity;
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // ===============================================
    // CREAR
    // ===============================================

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->loadLots();
        $this->formWeighedAt = now()->format('Y-m-d\TH:i');
        $this->showFormModal = true;
    }

    // ===============================================
    // EDITAR
    // ===============================================

    public function openEditModal(int $id): void
    {
        $weighing = Weighing::find($id);

        if (!$weighing) {
            session()->flash('error', 'Pesada no encontrada.');
            return;
        }

        $this->resetForm();
        $this->loadLots();

        $this->editingWeighingId = $weighing->id;
        $this->selectedLotId = $weighing->lot_id;
        $this->formQuantity = $weighing->quantity;
        $this->formWeighedPieces = $weighing->good_pieces;
        $this->formWeighedAt = $weighing->weighed_at->format('Y-m-d\TH:i');
        $this->formComments = $weighing->comments ?? '';

        $this->showFormModal = true;
    }

    // ===============================================
    // GUARDAR (crear o editar)
    // ===============================================

    public function save(): void
    {
        $this->validate([
            'selectedLotId' => 'required|exists:lots,id',
            'formWeighedPieces' => 'required|integer|min:1',
            'formWeighedAt' => 'required|date',
            'formComments' => 'nullable|string|max:1000',
        ], [
            'selectedLotId.required' => 'Debe seleccionar un lote.',
            'formWeighedPieces.required' => 'Las piezas pesadas son requeridas.',
            'formWeighedPieces.min' => 'Debe registrar al menos 1 pieza.',
            'formWeighedAt.required' => 'La fecha y hora son requeridas.',
        ]);

        $lot = Lot::find($this->selectedLotId);

        $data = [
            'lot_id' => $this->selectedLotId,
            'quantity' => $lot->quantity,
            'good_pieces' => $this->formWeighedPieces,
            'bad_pieces' => 0,
            'weighed_at' => $this->formWeighedAt,
            'weighed_by' => auth()->id(),
            'comments' => $this->formComments ?: null,
        ];

        if ($this->editingWeighingId) {
            $weighing = Weighing::find($this->editingWeighingId);
            if ($weighing) {
                // Las pesadas existentes conservan su kit_id (historial); no se reasigna.
                $weighing->update($data);
                session()->flash('message', 'Pesada actualizada correctamente.');
            }
        } else {
            // Pesada a nivel viajero: kit_id = null (CRIMP ya no usa kit).
            $data['kit_id'] = null;
            Weighing::create($data);
            session()->flash('message', 'Pesada registrada correctamente.');
        }

        $this->closeFormModal();
    }

    // ===============================================
    // VER DETALLE
    // ===============================================

    public function openDetailModal(int $id): void
    {
        $this->detailWeighing = Weighing::with(['lot.workOrder.purchaseOrder.part', 'kit', 'weighedBy'])->find($id);

        if (!$this->detailWeighing) {
            session()->flash('error', 'Pesada no encontrada.');
            return;
        }

        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->detailWeighing = null;
    }

    // ===============================================
    // ELIMINAR
    // ===============================================

    public function confirmDeletion(int $id): void
    {
        $this->weighingToDelete = $id;
        $this->confirmingDeletion = true;
    }

    public function delete(): void
    {
        if ($this->weighingToDelete) {
            $weighing = Weighing::find($this->weighingToDelete);
            if ($weighing) {
                $lot = $weighing->lot;

                // No se puede borrar producción que Calidad ya verificó: eso
                // deja al lote con más piezas verificadas que producidas.
                if ($lot && ! $lot->canDeleteProductionWeighing($weighing)) {
                    session()->flash('error', $lot->getProductionWeighingDeleteBlockReason($weighing));
                    $this->confirmingDeletion = false;
                    $this->weighingToDelete = null;

                    return;
                }

                $weighing->delete();
                session()->flash('message', 'Pesada eliminada correctamente.');
            }
        }
        $this->confirmingDeletion = false;
        $this->weighingToDelete = null;
    }

    public function cancelDeletion(): void
    {
        $this->confirmingDeletion = false;
        $this->weighingToDelete = null;
    }

    // ===============================================
    // HELPERS
    // ===============================================

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingWeighingId = null;
        $this->selectedLotId = null;
        $this->formQuantity = 0;
        $this->formWeighedPieces = 0;
        $this->formWeighedAt = now()->format('Y-m-d\TH:i');
        $this->formComments = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = Weighing::with(['lot.workOrder.purchaseOrder.part', 'kit', 'weighedBy'])
            ->when($this->search, function ($q) {
                $q->whereHas('lot', function ($lotQ) {
                    $lotQ->where('lot_number', 'like', "%{$this->search}%")
                        ->orWhereHas('workOrder.purchaseOrder', function ($woQ) {
                            $woQ->where('wo', 'like', "%{$this->search}%");
                        })
                        ->orWhereHas('workOrder.purchaseOrder.part', function ($partQ) {
                            $partQ->where('number', 'like', "%{$this->search}%");
                        });
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);

        $weighings = $query->paginate($this->perPage);

        return view('livewire.admin.production.weighing-management', [
            'weighings' => $weighings,
        ])->layout('components.layouts.app');
    }
}
