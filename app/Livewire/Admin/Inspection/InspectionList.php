<?php

namespace App\Livewire\Admin\Inspection;

use App\Models\Lot;
use App\Models\Kit;
use Livewire\Component;
use Livewire\WithPagination;

class InspectionList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterInspectionStatus = '';
    public int $perPage = 10;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // Modal state
    public bool $showInspectionModal = false;
    public ?int $selectedLotId = null;
    public ?Lot $selectedLot = null;
    public string $inspectionAction = '';
    public string $inspectionComments = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterInspectionStatus(): void
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

    public function openInspectionModal(int $lotId): void
    {
        $this->selectedLotId = $lotId;
        $this->selectedLot = Lot::with(['workOrder.purchaseOrder.part', 'kits', 'inspector'])->find($lotId);
        $this->inspectionAction = '';
        $this->inspectionComments = '';
        $this->showInspectionModal = true;
    }

    public function closeInspectionModal(): void
    {
        $this->showInspectionModal = false;
        $this->selectedLotId = null;
        $this->selectedLot = null;
        $this->inspectionAction = '';
        $this->inspectionComments = '';
    }

    public function submitInspectionDecision(): void
    {
        $this->validate([
            'inspectionAction' => 'required|in:approved,rejected',
            'inspectionComments' => 'nullable|string|max:1000',
        ]);

        if (!$this->selectedLotId) {
            session()->flash('error', 'No se ha seleccionado un lote.');
            return;
        }

        $lot = Lot::find($this->selectedLotId);

        if (!$lot) {
            session()->flash('error', 'Lote no encontrado.');
            return;
        }

        if (!$lot->canBeInspected()) {
            session()->flash('error', $lot->getInspectionBlockedReason() ?? 'Este lote no puede ser inspeccionado.');
            $this->closeInspectionModal();
            return;
        }

        $lot->update([
            'inspection_status' => $this->inspectionAction,
            'inspection_comments' => $this->inspectionComments,
            'inspection_completed_at' => now(),
            'inspection_completed_by' => auth()->id(),
        ]);

        // If approved, transition the kit to in_assembly
        if ($this->inspectionAction === Lot::INSPECTION_APPROVED) {
            $releasedKit = $lot->getReleasedKit();
            if ($releasedKit) {
                $releasedKit->update(['status' => Kit::STATUS_IN_ASSEMBLY]);
            }
            session()->flash('message', 'Lote aprobado correctamente. El kit ha sido enviado a ensamble.');
        } else {
            // If rejected, mark the kit as rejected
            $releasedKit = $lot->getReleasedKit();
            if ($releasedKit) {
                $releasedKit->update(['status' => Kit::STATUS_REJECTED]);
            }
            session()->flash('message', 'Lote rechazado. El kit ha sido marcado para correccion.');
        }

        $this->closeInspectionModal();
    }

    public function render()
    {
        $query = Lot::with(['workOrder.purchaseOrder.part', 'kits', 'inspector'])
            ->search($this->search)
            ->when($this->filterInspectionStatus, function ($q) {
                return $q->where('inspection_status', $this->filterInspectionStatus);
            })
            // Flujo vigente: la inspección se habilita cuando Materiales libera el
            // viajero/lote (material_status = 'released'), no por estado de Kit
            // (ver Lot::canBeInspected). Se incluyen los ya inspeccionados (historial).
            ->where(function ($q) {
                $q->where('material_status', 'released')
                    ->orWhereNotNull('inspection_completed_at');
            })
            ->orderBy($this->sortField, $this->sortDirection);

        $lots = $query->paginate($this->perPage);

        // Statistics
        $stats = [
            'pending' => Lot::where('material_status', 'released')
                ->where('inspection_status', Lot::INSPECTION_PENDING)
                ->count(),
            'approved' => Lot::where('inspection_status', Lot::INSPECTION_APPROVED)->count(),
            'rejected' => Lot::where('inspection_status', Lot::INSPECTION_REJECTED)->count(),
        ];

        return view('livewire.admin.inspection.inspection-list', [
            'lots' => $lots,
            'inspectionStatuses' => Lot::getInspectionStatuses(),
            'stats' => $stats,
        ]);
    }
}
