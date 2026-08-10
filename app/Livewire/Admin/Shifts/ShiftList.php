<?php

namespace App\Livewire\Admin\Shifts;

use App\Models\Shift;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class ShiftList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public string $filterStatus = '';
    public int $perPage = 10;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['name', 'start_time', 'end_time', 'active', 'created_at'];

    public function updatingSearch(): void
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
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function deleteShift(int $id): void
    {
        $shift = Shift::findOrFail($id);

        if (!$shift->canBeDeleted()) {
            session()->flash('error', 'No se puede eliminar «' . $shift->name . '» porque tiene empleados, descansos o tiempo extra asociados.');
            return;
        }

        $shift->delete();

        session()->flash('message', 'Turno «' . $shift->name . '» eliminado correctamente.');
    }

    public function render()
    {
        // Los tres conteos deciden si el turno se puede eliminar (canBeDeleted)
        // y se muestran en la tabla, así que se piden de una vez.
        $shifts = Shift::withCount(['employees', 'allEmployees', 'BreakTimes', 'overTimes'])
            ->search($this->search)
            ->when($this->filterStatus !== '', function ($query) {
                $this->filterStatus === '1' ? $query->active() : $query->inactive();
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.shifts.shift-list', [
            'shifts' => $shifts,
            'totalShifts' => Shift::count(),
            'activeShifts' => Shift::active()->count(),
            'inactiveShifts' => Shift::inactive()->count(),
            'employeesAssigned' => (int) User::role('employee')->whereNotNull('shift_id')->active()->count(),
        ]);
    }
}
