<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Table;
use App\Models\Area;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class TableList extends Component
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

    /**
     * El modal del catálogo puede renombrar o desactivar un estado, y la tabla
     * lo muestra por renglón: basta recibir el evento para volver a pintarla.
     */
    #[On('production-statuses-updated')]
    public function refreshAfterStatusChange(): void
    {
        //
    }

    public function deleteTable(int $id): void
    {
        $table = Table::find($id);

        if (!$table) {
            session()->flash('error', 'No se encontró la mesa que quieres eliminar.');
            return;
        }

        // Soft delete: los estándares y registros que la mencionan siguen siendo legibles.
        $table->delete();

        session()->flash('message', 'Mesa ' . $table->number . ' eliminada correctamente.');
    }

    public function render()
    {
        // productionStatus: la tabla lo muestra por renglón; sin esto es un N+1.
        $tables = Table::with(['area', 'productionStatus'])
            ->when($this->search, fn ($query) => $query->search($this->search))
            ->when($this->filterArea, fn ($query) => $query->byArea($this->filterArea))
            ->when($this->filterStatus !== '', function ($query) {
                $this->filterStatus === '1' ? $query->active() : $query->inactive();
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.tables.table-list', [
            'tables' => $tables,
            'areas' => Area::orderBy('name')->get(),
            'stats' => Table::getStats(),
        ]);
    }
}
