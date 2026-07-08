<?php

namespace App\Livewire\Admin\Packaging;

use App\Models\Lot;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de Empaque para consultar/gestionar las pesadas del flujo CRIMP:
 *  - Pesadas de PIEZAS ("manguitas")  -> PackagingPieceWeighing
 *  - Pesadas de CRIMP                 -> PackagingCrimpWeighing
 *
 * Se agrupan por viajero (Lot). El alta de pesadas ocurre en el flujo de
 * confirmación (Paso 5); aquí se listan, editan y eliminan.
 */
#[Layout('components.layouts.app')]
class PackagingWeighings extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // Modal de detalle del viajero
    public bool $showDetailModal = false;
    public ?int $selectedLotId = null;
    public $selectedLot = null;
    public array $pieceWeighings = [];
    public array $crimpWeighings = [];
    public int $pieceTotal = 0;
    public int $crimpTotal = 0;
    public int $crimpTarget = 0;

    // Modal de edición de una pesada
    public bool $showEditModal = false;
    public string $editType = '';          // 'piece' | 'crimp'
    public ?int $editId = null;
    public ?int $editCrimpLotId = null;
    public int $editQuantity = 0;
    public ?string $editWeight = null;
    public string $editWeighedAt = '';
    public string $editComments = '';

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

    public function openDetailModal(int $lotId): void
    {
        $lot = Lot::with([
            'workOrder.purchaseOrder.part',
            'crimpLots',
            'packagingPieceWeighings.weighedBy',
            'packagingPieceWeighings.crimpLot',
            'packagingCrimpWeighings.weighedBy',
            'packagingCrimpWeighings.crimpLot',
        ])->find($lotId);

        if (! $lot) {
            session()->flash('error', 'Viajero no encontrado.');
            return;
        }

        $this->selectedLotId = $lotId;
        $this->selectedLot = $lot;

        $this->pieceWeighings = $lot->packagingPieceWeighings
            ->sortByDesc('weighed_at')
            ->map(fn ($w) => $this->mapWeighing($w))
            ->values()
            ->toArray();

        $this->crimpWeighings = $lot->packagingCrimpWeighings
            ->sortByDesc('weighed_at')
            ->map(fn ($w) => $this->mapWeighing($w))
            ->values()
            ->toArray();

        $this->pieceTotal  = $lot->getPackagedPiecesTotal();
        $this->crimpTotal  = $lot->getPackagedCrimpTotal();
        $this->crimpTarget = $lot->getCrimpTargetTotal();

        $this->showDetailModal = true;
    }

    private function mapWeighing($w): array
    {
        return [
            'id'          => $w->id,
            'crimp_lot'   => $w->crimpLot?->crimp_lot_number,
            'quantity'    => $w->quantity,
            'weight'      => $w->weight,
            'weighed_at'  => optional($w->weighed_at)->format('d/m/Y H:i'),
            'weighed_by'  => $w->weighedBy->name ?? 'N/A',
            'comments'    => $w->comments,
        ];
    }

    public function closeDetailModal(): void
    {
        $this->reset([
            'showDetailModal', 'selectedLotId', 'selectedLot',
            'pieceWeighings', 'crimpWeighings',
            'pieceTotal', 'crimpTotal', 'crimpTarget',
        ]);
    }

    public function editWeighing(string $type, int $id): void
    {
        $model = $this->resolveModel($type, $id);
        if (! $model) {
            return;
        }

        $this->editType       = $type;
        $this->editId         = $model->id;
        $this->editCrimpLotId = $model->crimp_lot_id;
        $this->editQuantity   = $model->quantity;
        $this->editWeight     = $model->weight !== null ? (string) $model->weight : null;
        $this->editWeighedAt  = optional($model->weighed_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        $this->editComments   = $model->comments ?? '';
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->reset([
            'showEditModal', 'editType', 'editId', 'editCrimpLotId',
            'editQuantity', 'editWeight', 'editWeighedAt', 'editComments',
        ]);
        $this->resetErrorBag();
    }

    public function saveWeighing(): void
    {
        $this->validate([
            'editQuantity'   => 'required|integer|min:1',
            'editWeight'     => 'nullable|numeric|min:0',
            'editWeighedAt'  => 'required|date',
            'editComments'   => 'nullable|string|max:1000',
            'editCrimpLotId' => 'nullable|integer',
        ], [
            'editQuantity.required' => 'La cantidad es obligatoria.',
            'editQuantity.min'      => 'La cantidad debe ser mayor a 0.',
            'editWeighedAt.required' => 'La fecha y hora son requeridas.',
        ]);

        $model = $this->resolveModel($this->editType, $this->editId);
        if (! $model) {
            session()->flash('error', 'Pesada no encontrada.');
            return;
        }

        $model->update([
            'crimp_lot_id' => $this->editCrimpLotId ?: null,
            'quantity'     => $this->editQuantity,
            'weight'       => $this->editWeight !== null && $this->editWeight !== '' ? $this->editWeight : null,
            'weighed_at'   => $this->editWeighedAt,
            'comments'     => $this->editComments ?: null,
        ]);

        session()->flash('message', 'Pesada actualizada.');
        $lotId = $this->selectedLotId;
        $this->closeEditModal();
        if ($lotId) {
            $this->openDetailModal($lotId);
        }
    }

    public function deleteWeighing(string $type, int $id): void
    {
        $model = $this->resolveModel($type, $id);
        if ($model) {
            $model->delete();
            session()->flash('message', 'Pesada eliminada.');
            if ($this->selectedLotId) {
                $this->openDetailModal($this->selectedLotId);
            }
        }
    }

    private function resolveModel(string $type, ?int $id)
    {
        if ($id === null) {
            return null;
        }

        return $type === 'crimp'
            ? PackagingCrimpWeighing::find($id)
            : PackagingPieceWeighing::find($id);
    }

    public function render()
    {
        // Viajeros de partes con CRIMP que tengan al menos una pesada de Empaque.
        $query = Lot::with([
            'workOrder.purchaseOrder.part',
            'crimpLots',
            'packagingPieceWeighings',
            'packagingCrimpWeighings',
        ])
            ->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', true))
            ->where(function ($q) {
                $q->whereHas('packagingPieceWeighings')
                    ->orWhereHas('packagingCrimpWeighings');
            })
            ->search($this->search)
            ->orderBy($this->sortField, $this->sortDirection);

        $lots = $query->paginate($this->perPage);

        // Estadísticas globales
        $stats = [
            'lots'         => Lot::whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', true))
                ->where(function ($q) {
                    $q->whereHas('packagingPieceWeighings')->orWhereHas('packagingCrimpWeighings');
                })->count(),
            'pieces'       => (int) PackagingPieceWeighing::sum('quantity'),
            'crimp'        => (int) PackagingCrimpWeighing::sum('quantity'),
            'records'      => PackagingPieceWeighing::count() + PackagingCrimpWeighing::count(),
        ];

        return view('livewire.admin.packaging.packaging-weighings', [
            'lots'  => $lots,
            'stats' => $stats,
        ]);
    }
}
