<?php

namespace App\Livewire\Admin\Holidays;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Holiday;

class Holidays extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'date';
    public string $sortDirection = 'desc';
    public string $filterWhen = 'all';
    public int $perPage = 10;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['date', 'name', 'created_at'];

    public function updatingSearch(): void
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
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterWhen = 'all';
        $this->resetPage();
    }

    public function deleteHoliday(int $id): void
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->delete();

        session()->flash('message', 'Día festivo «' . $holiday->name . '» eliminado correctamente.');
    }

    public function render(): mixed
    {
        $today = now()->startOfDay();

        $holidays = Holiday::search($this->search)
            ->when($this->filterWhen === 'upcoming', fn ($q) => $q->whereDate('date', '>=', $today))
            ->when($this->filterWhen === 'past', fn ($q) => $q->whereDate('date', '<', $today))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.holidays.holiday-list', [
            'holidays' => $holidays,
            'totalHolidays' => Holiday::count(),
            'upcomingHolidays' => Holiday::whereDate('date', '>=', $today)->count(),
            'pastHolidays' => Holiday::whereDate('date', '<', $today)->count(),
            'thisYearHolidays' => Holiday::whereYear('date', $today->year)->count(),
        ]);
    }
}
