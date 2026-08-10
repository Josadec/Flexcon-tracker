<?php

namespace App\Livewire\Admin\Packaging;

use App\Models\Lot;
use App\Models\PackagingRecord;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mesa de trabajo de Empaque (flujo sin CRIMP).
 *
 * Lo que hace Empaque aquí: registrar cuántas de las piezas que Calidad aprobó
 * se empacaron de cada viajero y cuántas sobraron. Ese registro es el que
 * alimenta la decisión de cierre y la recepción de sobrantes de Materiales, así
 * que las cantidades tienen que cuadrar con lo aprobado.
 *
 * Los viajeros CRIMP no se empacan aquí: llevan doble pesada (manguitas + CRIMP)
 * y se registran en «Pesadas de Empaque».
 */
#[Layout('components.layouts.app')]
class PackagingManagement extends Component
{
    use WithPagination;

    // Búsqueda y filtros
    public string $searchTerm = '';
    public string $filterWorkOrderId = '';
    public string $filterLotId = '';
    public string $filterAdjusted = ''; // '' | yes | no

    // Ordenamiento
    public string $sortField = 'packed_at';
    public string $sortDirection = 'desc';

    // Modal de alta / edición
    public bool $showModal = false;
    public ?int $editingId = null;
    public ?int $formLotId = null;
    public ?int $formPackedPieces = null;
    public ?int $formSurplusPieces = null;
    public ?int $formAdjustedSurplus = null;
    public string $formAdjustmentReason = '';
    public string $formComments = '';
    public string $formPackedAt = '';

    // Modal de borrado
    public bool $showDeleteModal = false;
    public ?int $deletingId = null;

    /** Campos por los que se puede ordenar la tabla. */
    private const SORTABLE = ['packed_at', 'packed_pieces', 'surplus_pieces', 'available_pieces'];

    // ===============================================
    // DATOS DEL MODAL
    // ===============================================

    #[Computed]
    public function formLot(): ?Lot
    {
        return $this->formLotId
            ? Lot::with('workOrder.purchaseOrder.part')->find($this->formLotId)
            : null;
    }

    #[Computed]
    public function deletingRecord(): ?PackagingRecord
    {
        return $this->deletingId
            ? PackagingRecord::with('lot.workOrder.purchaseOrder')->find($this->deletingId)
            : null;
    }

    /**
     * Piezas del viajero que todavía no están comprometidas en un registro de
     * empaque: aprobadas por Calidad − (empacadas + sobrante ya declarado).
     *
     * Antes la pantalla comparaba contra las aprobadas a secas, así que se
     * podían capturar tres registros de 1,000 piezas sobre un viajero con 1,000
     * aprobadas. El remanente es lo único que se puede empacar de verdad.
     */
    public function remainingFor(Lot $lot, ?int $excludeRecordId = null): int
    {
        return max(0, $lot->getPackagingAvailablePieces() - $this->committedPieces($lot, $excludeRecordId));
    }

    private function committedPieces(Lot $lot, ?int $excludeRecordId = null): int
    {
        return (int) $lot->packagingRecords()
            ->when($excludeRecordId, fn ($q) => $q->whereKeyNot($excludeRecordId))
            ->selectRaw('COALESCE(SUM(packed_pieces + COALESCE(adjusted_surplus, surplus_pieces)), 0) as total')
            ->value('total');
    }

    /** Remanente del viajero abierto en el modal, descontando el registro que se edita. */
    #[Computed]
    public function modalRemaining(): int
    {
        $lot = $this->formLot;

        return $lot ? $this->remainingFor($lot, $this->editingId) : 0;
    }

    /** Lo que quedaría sin empacar si se guardara el modal tal como está. */
    #[Computed]
    public function modalLeftover(): int
    {
        return max(0, $this->modalRemaining - (int) $this->formPackedPieces - $this->effectiveSurplus());
    }

    private function effectiveSurplus(): int
    {
        return max(0, $this->formAdjustedSurplus ?? (int) $this->formSurplusPieces);
    }

    // ===============================================
    // FILTROS Y ORDEN
    // ===============================================

    public function updatedSearchTerm(): void
    {
        $this->resetPage();
    }

    public function updatedFilterLotId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAdjusted(): void
    {
        $this->resetPage();
    }

    public function updatedFilterWorkOrderId(): void
    {
        // El filtro de viajero cuelga del de orden: dejarlo puesto tras cambiar
        // de orden devolvía una tabla vacía sin explicación.
        $this->filterLotId = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->searchTerm = '';
        $this->filterWorkOrderId = '';
        $this->filterLotId = '';
        $this->filterAdjusted = '';
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    // ===============================================
    // ALTA Y EDICIÓN
    // ===============================================

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->formPackedAt = now()->format('Y-m-d\TH:i');
        $this->showModal = true;
    }

    public function openCreateForLot(int $lotId): void
    {
        $lot = Lot::with('workOrder.purchaseOrder.part')->find($lotId);

        if (! $lot) {
            session()->flash('error', 'Viajero no encontrado.');

            return;
        }

        // La cadena Material → Inspección → Producción → Calidad tiene que estar
        // completa: sin piezas aprobadas no hay nada que empacar.
        if ($motivo = $lot->getPackagingBlockedReason()) {
            session()->flash('error', $motivo);

            return;
        }

        $this->resetForm();
        $this->formLotId = $lot->id;
        $this->formPackedAt = now()->format('Y-m-d\TH:i');
        $this->formPackedPieces = $this->remainingFor($lot) ?: null;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $record = PackagingRecord::find($id);

        if (! $record) {
            session()->flash('error', 'Registro de empaque no encontrado.');

            return;
        }

        $this->resetForm();
        $this->editingId = $record->id;
        $this->formLotId = $record->lot_id;
        $this->formPackedPieces = $record->packed_pieces;
        $this->formSurplusPieces = $record->surplus_pieces;
        $this->formAdjustedSurplus = $record->adjusted_surplus;
        $this->formAdjustmentReason = $record->adjustment_reason ?? '';
        $this->formComments = $record->comments ?? '';
        $this->formPackedAt = $record->packed_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        $this->showModal = true;
    }

    /** Volver al selector de viajero sin cerrar el modal. */
    public function clearLotSelection(): void
    {
        if ($this->editingId) {
            return; // el viajero de un registro guardado no se cambia
        }

        $this->formLotId = null;
        $this->formPackedPieces = null;
        $this->resetErrorBag();
    }

    public function updatedFormLotId($value): void
    {
        $this->resetErrorBag();

        $lot = $value ? Lot::find((int) $value) : null;
        $this->formPackedPieces = $lot ? ($this->remainingFor($lot) ?: null) : null;
    }

    public function save(): void
    {
        $this->validate([
            'formLotId' => 'required|exists:lots,id',
            'formPackedPieces' => 'required|integer|min:1',
            'formSurplusPieces' => 'nullable|integer|min:0',
            'formAdjustedSurplus' => 'nullable|integer|min:0',
            'formAdjustmentReason' => $this->formAdjustedSurplus !== null ? 'required|string|max:500' : 'nullable|string|max:500',
            'formComments' => 'nullable|string|max:1000',
            'formPackedAt' => 'required|date|before_or_equal:now',
        ], [
            'formLotId.required' => 'Debes elegir el viajero que empacaste.',
            'formPackedPieces.required' => 'Las piezas empacadas son obligatorias.',
            'formPackedPieces.min' => 'Debes registrar al menos 1 pieza empacada.',
            'formSurplusPieces.min' => 'Las piezas sobrantes no pueden ser negativas.',
            'formAdjustedSurplus.min' => 'El sobrante ajustado no puede ser negativo.',
            'formAdjustmentReason.required' => 'Si corriges el sobrante, escribe por qué: ese dato viaja al cierre del viajero.',
            'formPackedAt.required' => 'La fecha de empaque es obligatoria.',
            'formPackedAt.before_or_equal' => 'La fecha de empaque no puede estar en el futuro.',
        ]);

        $lot = Lot::find($this->formLotId);

        if (! $lot) {
            $this->addError('formLotId', 'Viajero no encontrado.');

            return;
        }

        if ($motivo = $lot->getPackagingBlockedReason()) {
            $this->addError('formLotId', $motivo);

            return;
        }

        $aprobadas = $lot->getPackagingAvailablePieces();
        $disponible = $this->remainingFor($lot, $this->editingId);
        $sobrante = $this->effectiveSurplus();
        $total = (int) $this->formPackedPieces + $sobrante;

        // Empacadas + sobrante salen del mismo bolsón de piezas aprobadas: si la
        // suma lo excede, el viajero cerraría con más piezas de las que existen.
        if ($total > $disponible) {
            $campo = $sobrante > 0 ? 'formSurplusPieces' : 'formPackedPieces';
            $this->addError($campo, 'Empacadas + sobrante ('.number_format($total).') superan lo que queda del viajero ('
                .number_format($disponible).' pz de '.number_format($aprobadas).' aprobadas por Calidad).');

            return;
        }

        $data = [
            'lot_id' => $lot->id,
            'available_pieces' => $aprobadas,
            'packed_pieces' => (int) $this->formPackedPieces,
            'surplus_pieces' => (int) $this->formSurplusPieces,
            'adjusted_surplus' => $this->formAdjustedSurplus,
            'adjustment_reason' => $this->formAdjustmentReason ?: null,
            'comments' => $this->formComments ?: null,
            'packed_at' => $this->formPackedAt,
            'packed_by' => Auth::id(),
        ];

        if ($this->editingId) {
            PackagingRecord::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Registro de empaque del viajero '.$lot->lot_number.' actualizado.');
        } else {
            PackagingRecord::create($data);
            $mensaje = 'Empaque del viajero '.$lot->lot_number.' registrado: '.number_format((int) $this->formPackedPieces).' pz.';
            if ($sobrante > 0) {
                $mensaje .= ' '.number_format($sobrante).' pz de sobrante.';
            }
            session()->flash('message', $mensaje);
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formLotId = null;
        $this->formPackedPieces = null;
        $this->formSurplusPieces = null;
        $this->formAdjustedSurplus = null;
        $this->formAdjustmentReason = '';
        $this->formComments = '';
        $this->formPackedAt = '';
        $this->resetValidation();
    }

    // ===============================================
    // BORRADO
    // ===============================================

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function deleteRecord(): void
    {
        $record = PackagingRecord::with('lot')->find($this->deletingId);

        if (! $record) {
            session()->flash('error', 'Registro de empaque no encontrado.');
            $this->cancelDelete();

            return;
        }

        // Borrar empaque de un viajero ya cerrado deja la decisión de Materiales
        // apoyada en números que ya no existen.
        if ($motivo = $this->deleteBlockReason($record)) {
            session()->flash('error', $motivo);
            $this->cancelDelete();

            return;
        }

        $lotNumber = $record->lot?->lot_number ?? '—';
        $record->delete();

        session()->flash('message', 'Registro de empaque del viajero '.$lotNumber.' eliminado.');
        $this->cancelDelete();
    }

    /** Por qué no se puede borrar este registro (null si sí se puede). */
    public function deleteBlockReason(PackagingRecord $record): ?string
    {
        $lot = $record->lot;

        if (! $lot) {
            return null;
        }

        if ($lot->hasClosureDecision()) {
            return 'El viajero '.$lot->lot_number.' ya tiene decisión de cierre tomada sobre estas cantidades. '
                .'Corrige el registro en vez de borrarlo.';
        }

        if ($lot->isViajeroReceived()) {
            return 'El viajero '.$lot->lot_number.' ya fue recibido por Materiales. Corrige el registro en vez de borrarlo.';
        }

        return null;
    }

    // ===============================================
    // RENDER
    // ===============================================

    /** Métricas del área, no conteos generales del sistema. */
    #[Computed]
    public function stats(): array
    {
        $sobranteEfectivo = (int) PackagingRecord::query()
            ->selectRaw('COALESCE(SUM(COALESCE(adjusted_surplus, surplus_pieces)), 0) as total')
            ->value('total');

        return [
            'listos_sin_empacar' => $this->pendingLotsQuery()->count(),
            'empacadas' => (int) PackagingRecord::sum('packed_pieces'),
            'sobrante' => $sobranteEfectivo,
            'ajustados' => PackagingRecord::whereNotNull('adjusted_surplus')->count(),
            'registros' => PackagingRecord::count(),
        ];
    }

    /**
     * Viajeros con piezas aprobadas por Calidad y sin ningún registro de empaque.
     *
     * Sólo flujo sin CRIMP: en CRIMP lo empacado se mide con pesadas, así que
     * incluirlos aquí los mostraría como pendientes para siempre.
     */
    private function pendingLotsQuery()
    {
        return Lot::query()
            ->whereHas('qualityWeighings', fn ($q) => $q->where('good_pieces', '>', 0))
            ->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', false))
            ->doesntHave('packagingRecords')
            ->where('status', '!=', Lot::STATUS_COMPLETED);
    }

    public function render()
    {
        $query = PackagingRecord::with(['lot.workOrder.purchaseOrder.part', 'packedBy']);

        if ($this->searchTerm !== '') {
            $term = '%'.$this->searchTerm.'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('lot', fn ($lq) => $lq->where('lot_number', 'like', $term))
                    ->orWhereHas('lot.workOrder.purchaseOrder', fn ($poq) => $poq->where('wo', 'like', $term))
                    ->orWhereHas('lot.workOrder.purchaseOrder.part', fn ($pq) => $pq->where('number', 'like', $term))
                    ->orWhereHas('packedBy', fn ($uq) => $uq->where('name', 'like', $term))
                    ->orWhere('comments', 'like', $term);
            });
        }

        if ($this->filterWorkOrderId !== '') {
            $query->whereHas('lot', fn ($q) => $q->where('work_order_id', $this->filterWorkOrderId));
        }

        if ($this->filterLotId !== '') {
            $query->where('lot_id', $this->filterLotId);
        }

        if ($this->filterAdjusted === 'yes') {
            $query->whereNotNull('adjusted_surplus');
        } elseif ($this->filterAdjusted === 'no') {
            $query->whereNull('adjusted_surplus');
        }

        $records = $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderByDesc('id')
            ->paginate(20);

        // Órdenes y viajeros que realmente tienen registros: filtrar por algo que
        // no existe en la tabla sólo produce pantallas vacías.
        $workOrdersForFilter = WorkOrder::whereHas('lots.packagingRecords')
            ->with('purchaseOrder.part')
            ->orderByDesc('wo_number')
            ->get();

        $lotsForFilter = Lot::whereHas('packagingRecords')
            ->when($this->filterWorkOrderId !== '', fn ($q) => $q->where('work_order_id', $this->filterWorkOrderId))
            ->with('workOrder.purchaseOrder.part')
            ->orderBy('lot_number')
            ->get();

        // Viajeros que se pueden empacar: los que tienen piezas aprobadas por
        // Calidad y no están cerrados.
        $lotsForCreate = Lot::query()
            ->whereHas('qualityWeighings', fn ($q) => $q->where('good_pieces', '>', 0))
            ->where('status', '!=', Lot::STATUS_COMPLETED)
            ->with('workOrder.purchaseOrder.part')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $pendingLots = $this->pendingLotsQuery()
            ->with('workOrder.purchaseOrder.part')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return view('livewire.admin.packaging.packaging-management', [
            'records' => $records,
            'workOrdersForFilter' => $workOrdersForFilter,
            'lotsForFilter' => $lotsForFilter,
            'lotsForCreate' => $lotsForCreate,
            'pendingLots' => $pendingLots,
            'stats' => $this->stats,
        ]);
    }
}
