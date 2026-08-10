<?php

namespace App\Livewire\Admin\Inspection;

use App\Models\Kit;
use App\Models\Lot;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mesa de trabajo de Inspección (paso 4 del flujo).
 *
 * Lo que hace Calidad aquí: mirar el viajero que Materiales acaba de liberar y
 * decidir si entra a Producción o se detiene. La decisión es la compuerta del
 * paso siguiente —Producción no puede pesar sin inspección aprobada—, así que
 * un rechazo tiene que quedar explicado.
 */
class InspectionList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterInspectionStatus = '';
    public string $filterType = ''; // '' | crimp | standard
    public int $perPage = 10;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // Modal de decisión
    public bool $showInspectionModal = false;
    public ?int $selectedLotId = null;
    public string $inspectionAction = '';
    public string $inspectionComments = '';

    /** Columnas ordenables. Sin esta lista, `orderBy` recibe lo que mande el navegador. */
    private const SORTABLE = ['lot_number', 'quantity', 'inspection_status', 'created_at'];

    private const PER_PAGE = [10, 25, 50, 100];

    #[Computed]
    public function selectedLot(): ?Lot
    {
        return $this->selectedLotId
            ? Lot::with(['workOrder.purchaseOrder.part', 'crimpLots', 'inspector'])->find($this->selectedLotId)
            : null;
    }

    // ===============================================
    // FILTROS Y ORDEN
    // ===============================================

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterInspectionStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage($value): void
    {
        // Livewire acepta cualquier valor que mande el navegador: sin este ajuste
        // un "perPage=100000" tumba la pantalla.
        $this->perPage = in_array((int) $value, self::PER_PAGE, true) ? (int) $value : 10;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterInspectionStatus = '';
        $this->filterType = '';
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
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    // ===============================================
    // DECISIÓN DE INSPECCIÓN
    // ===============================================

    public function openInspectionModal(int $lotId): void
    {
        $lot = Lot::find($lotId);

        if (! $lot) {
            session()->flash('error', 'Viajero no encontrado.');

            return;
        }

        $this->selectedLotId = $lotId;
        // Al revisar una decisión ya tomada se muestra la que está puesta, para
        // que se vea qué se está cambiando y no se capture desde cero.
        $this->inspectionAction = in_array($lot->inspection_status, [Lot::INSPECTION_APPROVED, Lot::INSPECTION_REJECTED], true)
            ? $lot->inspection_status
            : '';
        $this->inspectionComments = $lot->inspection_comments ?? '';
        $this->resetErrorBag();
        $this->showInspectionModal = true;
    }

    public function closeInspectionModal(): void
    {
        $this->showInspectionModal = false;
        $this->selectedLotId = null;
        $this->inspectionAction = '';
        $this->inspectionComments = '';
        $this->resetErrorBag();
    }

    public function setInspectionAction(string $action): void
    {
        if (in_array($action, [Lot::INSPECTION_APPROVED, Lot::INSPECTION_REJECTED], true)) {
            $this->inspectionAction = $action;
        }
    }

    public function submitInspectionDecision(): void
    {
        $this->validate([
            'inspectionAction' => 'required|in:approved,rejected',
            // Un rechazo detiene el viajero: sin motivo escrito, nadie sabe qué
            // corregir ni por qué se frenó.
            'inspectionComments' => $this->inspectionAction === Lot::INSPECTION_REJECTED
                ? 'required|string|min:10|max:1000'
                : 'nullable|string|max:1000',
        ], [
            'inspectionAction.required' => 'Debes elegir Aprobar o Rechazar.',
            'inspectionAction.in' => 'Debes elegir Aprobar o Rechazar.',
            'inspectionComments.required' => 'Escribe por qué se rechaza: es lo que Materiales tiene que corregir.',
            'inspectionComments.min' => 'Explica el rechazo con al menos 10 caracteres.',
        ]);

        $lot = Lot::find($this->selectedLotId);

        if (! $lot) {
            session()->flash('error', 'Viajero no encontrado.');
            $this->closeInspectionModal();

            return;
        }

        if (! $lot->canBeInspected()) {
            session()->flash('error', $lot->getInspectionBlockedReason() ?? 'Este viajero no se puede inspeccionar.');
            $this->closeInspectionModal();

            return;
        }

        // Rechazar lo que Producción ya empezó a pesar deja al viajero en un
        // estado imposible: producido pero con la compuerta anterior cerrada.
        if ($this->inspectionAction === Lot::INSPECTION_REJECTED && $lot->getProductionTotalWeighed() > 0) {
            $this->addError('inspectionAction', 'Producción ya registró '.number_format($lot->getProductionTotalWeighed())
                .' piezas de este viajero; no se puede rechazar la inspección. Levanta un rechazo en la lista de envío.');

            return;
        }

        $eraOtra = $lot->inspection_status !== $this->inspectionAction
            && in_array($lot->inspection_status, [Lot::INSPECTION_APPROVED, Lot::INSPECTION_REJECTED], true);

        $lot->update([
            'inspection_status' => $this->inspectionAction,
            'inspection_comments' => $this->inspectionComments ?: null,
            'inspection_completed_at' => now(),
            'inspection_completed_by' => auth()->id(),
        ]);

        // El Kit dejó de ser la compuerta del flujo, pero cuando existe se
        // arrastra su estado para no dejarlo desincronizado.
        $kit = $lot->getReleasedKit();
        if ($this->inspectionAction === Lot::INSPECTION_APPROVED) {
            $kit?->update(['status' => Kit::STATUS_IN_ASSEMBLY]);
            $mensaje = 'Viajero '.$lot->lot_number.' aprobado. Producción ya puede pesarlo.';
        } else {
            $kit?->update(['status' => Kit::STATUS_REJECTED]);
            $mensaje = 'Viajero '.$lot->lot_number.' rechazado. Queda detenido hasta que Materiales lo corrija.';
        }

        if ($eraOtra) {
            $mensaje = 'Decisión cambiada. '.$mensaje;
        }

        session()->flash('message', $mensaje);
        $this->closeInspectionModal();
    }

    // ===============================================
    // RENDER
    // ===============================================

    /** Trabajo que Inspección tiene enfrente, no conteos generales del sistema. */
    #[Computed]
    public function stats(): array
    {
        return [
            'por_inspeccionar' => $this->pendingLotsQuery()->count(),
            'aprobados' => Lot::where('inspection_status', Lot::INSPECTION_APPROVED)->count(),
            'rechazados' => Lot::where('inspection_status', Lot::INSPECTION_REJECTED)->count(),
            'esperando_material' => Lot::where('material_status', 'pending')->count(),
        ];
    }

    /** Viajeros liberados por Materiales que siguen sin decisión de Inspección. */
    private function pendingLotsQuery()
    {
        return Lot::query()
            ->where('material_status', 'released')
            ->where(fn ($q) => $q->where('inspection_status', Lot::INSPECTION_PENDING)->orWhereNull('inspection_status'))
            ->whereNotIn('status', [Lot::STATUS_COMPLETED, Lot::STATUS_CANCELLED]);
    }

    public function render()
    {
        $query = Lot::with(['workOrder.purchaseOrder.part', 'crimpLots', 'inspector'])
            // Se listan los liberados (trabajo por hacer) y los ya inspeccionados
            // (historial y corrección de decisiones).
            ->where(function ($q) {
                $q->where('material_status', 'released')->orWhereNotNull('inspection_completed_at');
            });

        // El buscador del modelo no cubre el WO ni el número de parte, que es
        // justo lo que se ve en la tabla y lo que la gente teclea.
        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('lot_number', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('workOrder', fn ($woq) => $woq->where('wo_number', 'like', $term))
                    ->orWhereHas('workOrder.purchaseOrder', fn ($poq) => $poq->where('wo', 'like', $term))
                    ->orWhereHas('workOrder.purchaseOrder.part', fn ($pq) => $pq->where('number', 'like', $term)
                        ->orWhere('description', 'like', $term))
                    ->orWhereHas('crimpLots', fn ($clq) => $clq->where('crimp_lot_number', 'like', $term));
            });
        }

        if ($this->filterInspectionStatus !== '') {
            $query->where('inspection_status', $this->filterInspectionStatus);
        }

        if ($this->filterType === 'crimp') {
            $query->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', true));
        } elseif ($this->filterType === 'standard') {
            $query->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', false));
        }

        $lots = $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $pendingLots = $this->pendingLotsQuery()
            ->with('workOrder.purchaseOrder.part')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return view('livewire.admin.inspection.inspection-list', [
            'lots' => $lots,
            'pendingLots' => $pendingLots,
            'inspectionStatuses' => Lot::getInspectionStatuses(),
            'perPageOptions' => self::PER_PAGE,
            'stats' => $this->stats,
        ]);
    }
}
