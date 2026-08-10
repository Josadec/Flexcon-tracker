<?php

namespace App\Livewire\Admin\Materials;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\WorkOrder;
use App\Models\Lot;
use App\Models\StatusWO;
use Illuminate\Validation\Rule;

/**
 * Mesa de trabajo del área de Materiales.
 *
 * Lo que hace Materiales en el flujo: crear los viajeros de la orden, liberar
 * su material (paso 3) y, en CRIMP, atender la decisión del paso 6 y la
 * recepción de sobrantes del paso 8 —esas dos se toman en el tablero, aquí se
 * listan y se entra con un clic.
 */
class DynamicSentListView extends Component
{
    use WithPagination;

    // Búsqueda y filtros
    public string $searchTerm = '';
    public string $filterStatus = '';
    public string $filterType = '';    // '' | crimp | standard
    public string $filterMaterial = ''; // '' | pending | released | rejected

    // Modales
    public bool $showCreateLotModal = false;
    public bool $showEditLotModal = false;
    public bool $showDeleteLotConfirm = false;
    public bool $showEditWOStatusModal = false;
    public bool $showMaterialModal = false;

    // Selección
    public ?int $selectedLotId = null;
    public ?int $selectedWorkOrderId = null;
    public ?int $selectedWOStatusId = null;
    public string $woStatusAction = '';

    // Formulario de viajero
    public string $newLotNumber = '';
    public int $newLotQuantity = 0;
    public string $lotStatus = '';
    public string $lotComments = '';
    public string $lotDescription = '';
    public int $lotQuantity = 0;

    // Material
    public ?int $materialLotId = null;
    public string $materialStatus = 'pending';

    #[Computed]
    public function selectedLot(): ?Lot
    {
        return $this->selectedLotId
            ? Lot::with(['workOrder.purchaseOrder.part', 'crimpLots'])->find($this->selectedLotId)
            : null;
    }

    #[Computed]
    public function materialLot(): ?Lot
    {
        return $this->materialLotId
            ? Lot::with(['workOrder.purchaseOrder.part', 'crimpLots'])->find($this->materialLotId)
            : null;
    }

    #[Computed]
    public function selectedWorkOrder(): ?WorkOrder
    {
        return $this->selectedWorkOrderId
            ? WorkOrder::with(['purchaseOrder.part', 'lots'])->find($this->selectedWorkOrderId)
            : null;
    }

    #[Computed]
    public function woStatuses()
    {
        return StatusWO::orderBy('name')->get();
    }

    public function updatedSearchTerm(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMaterial(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->searchTerm = '';
        $this->filterStatus = '';
        $this->filterType = '';
        $this->filterMaterial = '';
        $this->resetPage();
    }

    // ===============================================
    // VIAJEROS — ALTA
    // ===============================================

    public function openCreateLotModal(int $workOrderId): void
    {
        $this->selectedWorkOrderId = $workOrderId;
        $this->newLotNumber = '';
        $this->newLotQuantity = 0;
        $this->resetErrorBag();
        $this->showCreateLotModal = true;
    }

    public function closeCreateLotModal(): void
    {
        $this->showCreateLotModal = false;
        $this->selectedWorkOrderId = null;
        $this->newLotNumber = '';
        $this->newLotQuantity = 0;
        $this->resetErrorBag();
    }

    public function createLot(): void
    {
        $this->validate([
            // La base tiene índice único (work_order_id, lot_number): sin esta
            // regla, un número repetido salía como error de SQL en pantalla.
            'newLotNumber' => [
                'required', 'string', 'max:255',
                Rule::unique('lots', 'lot_number')
                    ->where('work_order_id', $this->selectedWorkOrderId)
                    ->whereNull('deleted_at'),
            ],
            'newLotQuantity' => 'required|integer|min:1',
        ], [
            'newLotNumber.required' => 'El número de viajero es obligatorio.',
            'newLotNumber.unique' => 'Esa orden ya tiene un viajero con ese número.',
            'newLotQuantity.required' => 'La cantidad es obligatoria.',
            'newLotQuantity.min' => 'La cantidad debe ser mayor a 0.',
        ]);

        $workOrder = WorkOrder::findOrFail($this->selectedWorkOrderId);

        $currentTotal = $workOrder->lots()->sum('quantity');
        if (($currentTotal + $this->newLotQuantity) > $workOrder->original_quantity) {
            $available = $workOrder->original_quantity - $currentTotal;
            $this->addError('newLotQuantity', 'La suma de viajeros sobrepasaría la cantidad de la orden ('
                . number_format($workOrder->original_quantity) . '). Máximo disponible: ' . number_format($available));

            return;
        }

        Lot::create([
            'work_order_id' => $workOrder->id,
            'lot_number' => $this->newLotNumber,
            'quantity' => $this->newLotQuantity,
            'description' => $workOrder->purchaseOrder->part->description ?? '',
            'status' => Lot::STATUS_PENDING,
        ]);

        session()->flash('message', 'Viajero creado correctamente.');
        $this->closeCreateLotModal();
    }

    // ===============================================
    // VIAJEROS — EDICIÓN
    // ===============================================

    public function openEditLotModal(int $lotId): void
    {
        $this->selectedLotId = $lotId;
        $lot = $this->selectedLot;

        if (!$lot) {
            session()->flash('error', 'Viajero no encontrado.');

            return;
        }

        $this->lotStatus = $lot->status;
        $this->lotDescription = $lot->description ?? '';
        $this->lotComments = $lot->comments ?? '';
        $this->lotQuantity = $lot->quantity;
        $this->resetErrorBag();
        $this->showEditLotModal = true;
    }

    public function closeEditLotModal(): void
    {
        $this->showEditLotModal = false;
        $this->resetLotForm();
    }

    public function updateLot(): void
    {
        $this->validate([
            'lotStatus' => 'required|in:pending,in_progress,completed,cancelled',
            'lotDescription' => 'nullable|string|max:255',
            'lotComments' => 'nullable|string|max:500',
            'lotQuantity' => 'required|integer|min:1',
        ]);

        $lot = Lot::findOrFail($this->selectedLotId);

        $workOrder = $lot->workOrder;
        $otherLotsTotal = $workOrder->lots()->where('id', '!=', $lot->id)->sum('quantity');
        if (($otherLotsTotal + $this->lotQuantity) > $workOrder->original_quantity) {
            $available = $workOrder->original_quantity - $otherLotsTotal;
            $this->addError('lotQuantity', 'La suma de viajeros sobrepasaría la cantidad de la orden ('
                . number_format($workOrder->original_quantity) . '). Máximo disponible: ' . number_format($available));

            return;
        }

        $lot->update([
            'status' => $this->lotStatus,
            'description' => $this->lotDescription,
            'comments' => $this->lotComments,
            'quantity' => $this->lotQuantity,
        ]);

        session()->flash('message', 'Viajero actualizado correctamente.');
        $this->closeEditLotModal();
    }

    // ===============================================
    // VIAJEROS — BORRADO
    // ===============================================

    public function confirmDeleteLot(int $lotId): void
    {
        $this->selectedLotId = $lotId;
        $this->showDeleteLotConfirm = true;
    }

    public function deleteLot(): void
    {
        $lot = Lot::find($this->selectedLotId);

        if (!$lot) {
            session()->flash('error', 'Viajero no encontrado.');
            $this->cancelDeleteLot();

            return;
        }

        // canBeDeleted() bloquea los viajeros con historial: borrarlos arrastra
        // en cascada sus pesadas y su empaque.
        if ($motivo = $lot->getDeleteBlockReason()) {
            session()->flash('error', $motivo);
            $this->cancelDeleteLot();

            return;
        }

        $lot->delete();

        session()->flash('message', 'Viajero eliminado correctamente.');
        $this->cancelDeleteLot();
    }

    public function cancelDeleteLot(): void
    {
        $this->showDeleteLotConfirm = false;
        $this->selectedLotId = null;
    }

    // ===============================================
    // ESTADO DE LA ORDEN
    // ===============================================

    public function openEditWOStatusModal(int $workOrderId): void
    {
        $this->selectedWorkOrderId = $workOrderId;
        $workOrder = $this->selectedWorkOrder;

        if (!$workOrder) {
            session()->flash('error', 'Orden no encontrada.');

            return;
        }

        $this->selectedWOStatusId = $workOrder->status_id;
        $this->woStatusAction = '';
        $this->showEditWOStatusModal = true;
    }

    public function closeEditWOStatusModal(): void
    {
        $this->showEditWOStatusModal = false;
        $this->selectedWorkOrderId = null;
        $this->selectedWOStatusId = null;
        $this->woStatusAction = '';
    }

    public function updateWOStatus(): void
    {
        $this->validate([
            'selectedWOStatusId' => 'required|exists:statuses_wo,id',
        ], [
            'selectedWOStatusId.required' => 'Debes elegir un estado.',
        ]);

        $workOrder = WorkOrder::findOrFail($this->selectedWorkOrderId);
        $workOrder->update(['status_id' => $this->selectedWOStatusId]);

        session()->flash('message', 'Estado de la orden actualizado.');
        $this->closeEditWOStatusModal();
    }

    // ===============================================
    // MATERIAL (PASO 3 DEL FLUJO)
    // ===============================================

    public function openMaterialModal(int $lotId): void
    {
        $lot = Lot::find($lotId);

        if (!$lot) {
            session()->flash('error', 'Viajero no encontrado.');

            return;
        }

        $this->materialLotId = $lotId;
        $this->materialStatus = $lot->material_status ?? 'pending';
        $this->resetErrorBag();
        $this->showMaterialModal = true;
    }

    public function closeMaterialModal(): void
    {
        $this->showMaterialModal = false;
        $this->materialLotId = null;
        $this->materialStatus = 'pending';
        $this->resetErrorBag();
    }

    public function setMaterialStatus(string $status): void
    {
        $this->materialStatus = $status;
    }

    public function saveMaterialStatus(): void
    {
        $lot = $this->materialLot;

        if (!$lot) {
            session()->flash('error', 'Viajero no encontrado.');
            $this->closeMaterialModal();

            return;
        }

        $this->validate([
            'materialStatus' => 'required|in:released,rejected',
        ], [
            'materialStatus.required' => 'Debes elegir Liberado o Rechazado.',
            'materialStatus.in' => 'Debes elegir Liberado o Rechazado.',
        ]);

        // Rechazar material que Producción ya empezó a pesar deja al viajero en
        // un estado imposible: producido pero sin material liberado.
        if ($this->materialStatus === 'rejected' && $lot->getProductionTotalWeighed() > 0) {
            $this->addError('materialStatus',
                'Este viajero ya tiene ' . number_format($lot->getProductionTotalWeighed())
                . ' piezas pesadas por Producción; no se puede rechazar su material.');

            return;
        }

        $lot->update(['material_status' => $this->materialStatus]);

        $etiqueta = $this->materialStatus === 'released' ? 'Liberado' : 'Rechazado';
        session()->flash('message', 'Material del viajero ' . $lot->lot_number . ': ' . $etiqueta . '.');

        $this->closeMaterialModal();
    }

    // ===============================================
    // HELPERS
    // ===============================================

    private function resetLotForm(): void
    {
        $this->selectedLotId = null;
        $this->lotStatus = '';
        $this->lotDescription = '';
        $this->lotComments = '';
        $this->lotQuantity = 0;
        $this->resetErrorBag();
    }

    /**
     * Viajeros que están esperando una acción de Materiales, en todas las
     * órdenes visibles. Antes esto vivía escondido dentro de cada renglón.
     */
    public function pendingActions($workOrders): array
    {
        $pendientes = [];

        foreach ($workOrders as $wo) {
            foreach ($wo->lots as $lot) {
                $esCrimp = (bool) ($wo->purchaseOrder?->part?->is_crimp ?? false);

                // Paso 3: liberar material. Aplica a CRIMP y a no-CRIMP.
                if (($lot->material_status ?? 'pending') === 'pending') {
                    $pendientes[] = [
                        'lot' => $lot,
                        'wo' => $wo,
                        'is_crimp' => $esCrimp,
                        'label' => 'Liberar material',
                        'here' => true,
                    ];

                    continue;
                }

                // Pasos 6 y 8: se toman en el tablero.
                $accion = $lot->getNextPendingAction();
                if ($accion && ($accion['actor'] ?? null) === 'Materiales') {
                    $pendientes[] = [
                        'lot' => $lot,
                        'wo' => $wo,
                        'is_crimp' => $esCrimp,
                        'label' => $accion['label'] ?? 'Acción pendiente',
                        'here' => false,
                    ];
                }
            }
        }

        return $pendientes;
    }

    public function render()
    {
        $closedStatusIds = StatusWO::whereIn('name', ['Completed', 'Cancelled'])->pluck('id')->toArray();

        $query = WorkOrder::with([
            'purchaseOrder.part',
            'lots.crimpLots',
            'lots.workOrder.purchaseOrder.part',
            'lots.qualityWeighings',
            'lots.weighings',
            'lots.packagingRecords',
            'lots.packagingPieceWeighings',
            'lots.packagingCrimpWeighings',
        ])->whereNotIn('status_id', $closedStatusIds);

        if (!empty($this->searchTerm)) {
            $query->where(function ($q) {
                $q->where('wo_number', 'like', "%{$this->searchTerm}%")
                    ->orWhereHas('purchaseOrder', fn ($poQuery) => $poQuery
                        ->where('po_number', 'like', "%{$this->searchTerm}%")
                        ->orWhere('wo', 'like', "%{$this->searchTerm}%"))
                    ->orWhereHas('purchaseOrder.part', fn ($partQuery) => $partQuery
                        ->where('description', 'like', "%{$this->searchTerm}%")
                        ->orWhere('number', 'like', "%{$this->searchTerm}%"));
            });
        }

        if (!empty($this->filterStatus)) {
            $query->whereHas('lots', fn ($q) => $q->where('status', $this->filterStatus));
        }

        // Filtro nuevo: con o sin CRIMP, que es lo que cambia el flujo.
        if ($this->filterType === 'crimp') {
            $query->whereHas('purchaseOrder.part', fn ($q) => $q->where('is_crimp', true));
        } elseif ($this->filterType === 'standard') {
            $query->whereHas('purchaseOrder.part', fn ($q) => $q->where('is_crimp', false));
        }

        if ($this->filterMaterial !== '') {
            $query->whereHas('lots', fn ($q) => $q->where('material_status', $this->filterMaterial));
        }

        $workOrders = $query->latest()->paginate(15);

        return view('livewire.admin.materials.dynamic-sent-list-view', [
            'workOrders' => $workOrders,
            'pendingActions' => $this->pendingActions($workOrders),
        ]);
    }
}
