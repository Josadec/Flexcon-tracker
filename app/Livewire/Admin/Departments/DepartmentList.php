<?php

namespace App\Livewire\Admin\Departments;

use App\Models\Area;
use App\Models\Department;
use Livewire\Component;
use Livewire\WithPagination;

class DepartmentList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public int $perPage = 10;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['name', 'created_at'];

    public function updatingSearch(): void
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
        $this->resetPage();
    }

    public function deleteDepartment(int $id): void
    {
        $department = Department::findOrFail($id);

        if (!$department->canBeDeleted()) {
            session()->flash('error', 'No se puede eliminar «' . $department->name . '» porque todavía tiene áreas asociadas. Muévelas o elimínalas primero.');
            return;
        }

        $department->delete();

        session()->flash('message', 'Departamento eliminado correctamente.');
    }

    public function render()
    {
        // withCount: la tabla sólo necesita cuántas áreas hay, no las áreas.
        $departments = Department::withCount('areas')
            ->search($this->search)
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.departments.department-list', [
            'departments' => $departments,
            'totalDepartments' => Department::count(),
            'totalAreas' => Area::count(),
            'emptyDepartments' => Department::doesntHave('areas')->count(),
        ]);
    }
}
