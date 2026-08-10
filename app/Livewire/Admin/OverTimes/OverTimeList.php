<?php

namespace App\Livewire\Admin\OverTimes;

use App\Models\OverTime;
use App\Models\Shift;
use Livewire\Component;
use Livewire\WithPagination;

class OverTimeList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'date';
    public string $sortDirection = 'desc';
    public string $filterShift = '';
    public string $filterWhen = 'all';
    public int $perPage = 10;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['date', 'name', 'start_time', 'shift_id', 'created_at'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterShift(): void
    {
        $this->resetPage();
    }

    public function updatingFilterWhen(): void
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
        $this->filterShift = '';
        $this->filterWhen = 'all';
        $this->resetPage();
    }

    public function deleteOverTime(int $id): void
    {
        $overTime = OverTime::findOrFail($id);
        $overTime->delete();

        session()->flash('message', 'Tiempo extra «' . $overTime->name . '» eliminado correctamente.');
    }

    public function render()
    {
        // withCount('users'): total_hours es un accessor calculado que multiplica
        // las horas netas por el número de empleados. Sin el conteo sería un N+1.
        $overTimes = OverTime::with('shift')
            ->withCount('users')
            ->search($this->search)
            ->when($this->filterShift, fn ($q) => $q->byShift((int) $this->filterShift))
            ->when($this->filterWhen === 'upcoming', fn ($q) => $q->active())
            ->when($this->filterWhen === 'past', fn ($q) => $q->past())
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $totalHours = OverTime::withCount('users')->get()->sum(fn (OverTime $o) => $o->total_hours);
        $upcomingHours = OverTime::active()->withCount('users')->get()->sum(fn (OverTime $o) => $o->total_hours);

        return view('livewire.admin.over-times.over-time-list', [
            'overTimes' => $overTimes,
            'shifts' => Shift::orderBy('name')->get(),
            'stats' => [
                'total' => OverTime::count(),
                'upcoming' => OverTime::active()->count(),
                'total_hours' => round($totalHours, 2),
                'upcoming_hours' => round($upcomingHours, 2),
            ],
        ]);
    }
}
