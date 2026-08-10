<?php

namespace App\Livewire\Admin\SemiAutomatics;

use App\Models\Semi_Automatic;
use App\Models\Area;
use Livewire\Component;
use Livewire\WithPagination;

class SemiAutomaticList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'number';
    public string $sortDirection = 'asc';
    public string $filterArea = '';
    public string $filterStatus = '';
    public int $perPage = 10;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['number', 'area_id', 'employees', 'active', 'created_at'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterArea(): void
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
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterArea = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function deleteSemiAutomatic(int $id): void
    {
        $semiAutomatic = Semi_Automatic::find($id);

        if (!$semiAutomatic) {
            session()->flash('error', 'No se encontró el semi-automático que quieres eliminar.');
            return;
        }

        // Soft delete: los estándares que lo mencionan siguen siendo legibles.
        $semiAutomatic->delete();

        session()->flash('message', 'Semi-automático ' . $semiAutomatic->number . ' eliminado correctamente.');
    }

    public function render()
    {
        // productionStatus: la tabla lo muestra por renglón; sin esto es un N+1.
        $semiAutomatics = Semi_Automatic::with(['area', 'productionStatus'])
            ->when($this->search, fn ($query) => $query->search($this->search))
            ->when($this->filterArea, fn ($query) => $query->byArea($this->filterArea))
            ->when($this->filterStatus !== '', function ($query) {
                $this->filterStatus === '1' ? $query->active() : $query->inactive();
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.semi-automatics.semi-automatic-list', [
            'semiAutomatics' => $semiAutomatics,
            'areas' => Area::orderBy('name')->get(),
            'stats' => Semi_Automatic::getStats(),
        ]);
    }
}
