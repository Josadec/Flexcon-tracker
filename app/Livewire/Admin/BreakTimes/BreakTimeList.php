<?php

namespace App\Livewire\Admin\BreakTimes;

use App\Models\BreakTime;
use App\Models\Shift;
use Livewire\Component;
use Livewire\WithPagination;

class BreakTimeList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public int $perPage = 10;
    public string $filterActive = 'all';
    public string $filterShift = 'all';

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['name', 'start_break_time', 'end_break_time', 'shift_id', 'active', 'created_at'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActive(): void
    {
        $this->resetPage();
    }

    public function updatingFilterShift(): void
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
        $this->filterActive = 'all';
        $this->filterShift = 'all';
        $this->resetPage();
    }

    public function deleteBreakTime(int $id): void
    {
        $breakTime = BreakTime::findOrFail($id);

        if (!$breakTime->canBeDeleted()) {
            session()->flash('error', 'No se puede eliminar el descanso «' . $breakTime->name . '».');
            return;
        }

        $breakTime->delete();

        session()->flash('message', 'Descanso «' . $breakTime->name . '» eliminado correctamente.');
    }

    public function render()
    {
        $query = BreakTime::with('shift')->search($this->search);

        if ($this->filterActive === 'active') {
            $query->active();
        } elseif ($this->filterActive === 'inactive') {
            $query->inactive();
        }

        if ($this->filterShift !== 'all') {
            $query->where('shift_id', $this->filterShift);
        }

        $breakTimes = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.break-times.break-time-list', [
            'breakTimes' => $breakTimes,
            'shifts' => Shift::orderBy('name')->get(),
            'totalBreakTimes' => BreakTime::count(),
            'activeBreakTimes' => BreakTime::active()->count(),
            'inactiveBreakTimes' => BreakTime::inactive()->count(),
            // Turnos sin ningún descanso: se les cuenta el turno completo como productivo.
            'shiftsWithoutBreaks' => Shift::active()->doesntHave('BreakTimes')->count(),
        ]);
    }
}
