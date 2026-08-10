<?php

namespace App\Livewire\Admin\Machines;

use App\Models\Machine;
use App\Models\Area;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MachineList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public string $filterArea = '';
    public string $filterStatus = '';
    public int $perPage = 10;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['name', 'brand', 'area_id', 'employees', 'setup_time', 'active', 'created_at'];

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

    /**
     * El modal del catálogo puede renombrar o desactivar un estado, y la tabla
     * lo muestra por renglón: basta recibir el evento para volver a pintarla.
     */
    #[On('production-statuses-updated')]
    public function refreshAfterStatusChange(): void
    {
        //
    }

    public function deleteMachine(int $id): void
    {
        $machine = Machine::find($id);

        if (!$machine) {
            session()->flash('error', 'No se encontró la máquina que quieres eliminar.');
            return;
        }

        // Soft delete: los estándares que la mencionan siguen siendo legibles.
        $machine->delete();

        session()->flash('message', 'Máquina ' . $machine->name . ' eliminada correctamente.');
    }

    public function render()
    {
        // productionStatus: la tabla lo muestra por renglón; sin esto es un N+1.
        $machines = Machine::with(['area', 'productionStatus'])
            ->when($this->search, fn ($query) => $query->search($this->search))
            ->when($this->filterArea, fn ($query) => $query->byArea($this->filterArea))
            ->when($this->filterStatus !== '', function ($query) {
                $this->filterStatus === '1' ? $query->active() : $query->inactive();
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.machines.machine-list', [
            'machines' => $machines,
            'areas' => Area::orderBy('name')->get(),
            'stats' => Machine::getStats(),
        ]);
    }
}
