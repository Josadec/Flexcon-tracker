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
    public string $filterFrom = '';
    public string $filterTo = '';
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
    public ?int $detailWeighingId = null;

    // Modal confirmar eliminación
    public bool $confirmingDeletion = false;
    public ?int $weighingToDelete = null;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['weighed_at', 'good_pieces', 'created_at'];

    public function mount(): void
    {
        $this->formWeighedAt = now()->format('Y-m-d\TH:i');
    }

    /**
     * Lotes que se pueden pesar: los que Calidad ya aprobó (`canBeProduced`).
     *
     * Al editar se incluye el lote de la pesada aunque hoy ya no califique,
     * para no dejar huérfano el registro que se está corrigiendo.
     */
    public function selectableLots()
    {
        return Lot::with(['workOrder.purchaseOrder.part'])
            ->where(function ($query) {
                $query->where('inspection_status', Lot::INSPECTION_APPROVED);

                if ($this->selectedLotId) {
                    $query->orWhere('id', $this->selectedLotId);
                }
            })
            ->orderBy('lot_number')
            ->get();
    }

    public function updatedSelectedLotId($value): void
    {
        $this->formQuantity = 0;

        if ($value && $lot = Lot::find($value)) {
            $this->formQuantity = $lot->quantity;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterFrom(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTo(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
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

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterFrom = '';
        $this->filterTo = '';
        $this->resetPage();
    }

    // ===============================================
    // CREAR / EDITAR
    // ===============================================

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->formWeighedAt = now()->format('Y-m-d\TH:i');
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $weighing = Weighing::find($id);

        if (!$weighing) {
            session()->flash('error', 'Pesada no encontrada.');
            return;
        }

        $this->resetForm();

        $this->editingWeighingId = $weighing->id;
        $this->selectedLotId = $weighing->lot_id;
        $this->formQuantity = $weighing->quantity;
        $this->formWeighedPieces = $weighing->good_pieces;
        $this->formWeighedAt = $weighing->weighed_at->format('Y-m-d\TH:i');
        $this->formComments = $weighing->comments ?? '';

        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'selectedLotId' => 'required|exists:lots,id',
            'formWeighedPieces' => 'required|integer|min:1',
            'formWeighedAt' => 'required|date',
            'formComments' => 'nullable|string|max:1000',
        ], [
            'selectedLotId.required' => 'Debe seleccionar un viajero.',
            'formWeighedPieces.required' => 'Las piezas pesadas son requeridas.',
            'formWeighedPieces.min' => 'Debe registrar al menos 1 pieza.',
            'formWeighedAt.required' => 'La fecha y hora son requeridas.',
        ]);

        $lot = Lot::find($this->selectedLotId);

        if (!$lot) {
            session()->flash('error', 'Viajero no encontrado.');
            return;
        }

        // El flujo es secuencial: sin inspección aprobada, Producción no entra.
        // Es el mismo guard del tablero; aquí faltaba y se podían registrar
        // pesadas de viajeros que Calidad todavía no libera.
        if (!$lot->canBeProduced()) {
            $this->addError('selectedLotId', $lot->getProductionBlockedReason() ?? 'Este viajero todavía no se puede pesar.');
            return;
        }

        $data = [
            'lot_id' => $lot->id,
            'quantity' => $lot->quantity,
            'good_pieces' => $this->formWeighedPieces,
            'bad_pieces' => 0,
            'weighed_at' => $this->formWeighedAt,
            'weighed_by' => auth()->id(),
            'comments' => $this->formComments ?: null,
        ];

        if ($this->editingWeighingId) {
            $weighing = Weighing::find($this->editingWeighingId);

            if (!$weighing) {
                session()->flash('error', 'Pesada no encontrada.');
                return;
            }

            // Misma invariante que al borrar: bajar la cantidad no puede dejar
            // al viajero con menos producción que lo que Calidad ya verificó.
            $verificadas = $lot->getQualityVerifiedPieces();
            $resultante = $lot->getProductionTotalWeighed()
                - ((int) $weighing->good_pieces + (int) $weighing->bad_pieces)
                + (int) $this->formWeighedPieces;

            if ($resultante < $verificadas) {
                $this->addError('formWeighedPieces',
                    'Calidad ya verificó ' . number_format($verificadas) . ' piezas de este viajero. '
                    . 'Con este cambio quedarían ' . number_format($resultante) . ' pesadas por Producción. '
                    . 'Elimina primero las pesadas de calidad correspondientes.');
                return;
            }

            // Las pesadas existentes conservan su kit_id (historial); no se reasigna.
            $weighing->update($data);
            session()->flash('message', 'Pesada actualizada correctamente.');
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
        if (!Weighing::whereKey($id)->exists()) {
            session()->flash('error', 'Pesada no encontrada.');
            return;
        }

        $this->detailWeighingId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->detailWeighingId = null;
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
        $weighing = $this->weighingToDelete ? Weighing::find($this->weighingToDelete) : null;

        if ($weighing) {
            $lot = $weighing->lot;

            // No se puede borrar producción que Calidad ya verificó: eso
            // deja al viajero con más piezas verificadas que producidas.
            if ($lot && !$lot->canDeleteProductionWeighing($weighing)) {
                session()->flash('error', $lot->getProductionWeighingDeleteBlockReason($weighing));
                $this->cancelDeletion();

                return;
            }

            $weighing->delete();
            session()->flash('message', 'Pesada eliminada correctamente.');
        }

        $this->cancelDeletion();
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
        $weighings = Weighing::with(['lot.workOrder.purchaseOrder.part', 'weighedBy'])
            ->when($this->search, function ($q) {
                $q->whereHas('lot', function ($lotQ) {
                    $lotQ->where('lot_number', 'like', "%{$this->search}%")
                        ->orWhereHas('workOrder.purchaseOrder', fn ($woQ) => $woQ->where('wo', 'like', "%{$this->search}%"))
                        ->orWhereHas('workOrder.purchaseOrder.part', fn ($partQ) => $partQ->where('number', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->filterFrom, fn ($q) => $q->whereDate('weighed_at', '>=', $this->filterFrom))
            ->when($this->filterTo, fn ($q) => $q->whereDate('weighed_at', '<=', $this->filterTo))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $hoy = Weighing::whereDate('weighed_at', today());

        return view('livewire.admin.production.weighing-management', [
            'weighings' => $weighings,
            // El selector sólo se arma cuando el modal está abierto: antes esta
            // lista vivía en una propiedad pública y viajaba completa —con sus
            // relaciones— en cada petición de Livewire.
            'selectableLots' => $this->showFormModal ? $this->selectableLots() : collect(),
            'detailWeighing' => $this->detailWeighingId
                ? Weighing::with(['lot.workOrder.purchaseOrder.part', 'weighedBy'])->find($this->detailWeighingId)
                : null,
            'stats' => [
                'total' => Weighing::count(),
                'piezas' => (int) Weighing::sum('good_pieces'),
                'hoy' => (clone $hoy)->count(),
                'piezas_hoy' => (int) (clone $hoy)->sum('good_pieces'),
            ],
        ])->layout('components.layouts.app');
    }
}
