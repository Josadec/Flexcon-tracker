<?php

namespace App\Livewire\Admin\Departments;

use App\Models\Department;
use Livewire\Component;

class DepartmentShow extends Component
{
    public Department $department;
    public array $stats = [];

    public function mount(Department $department): void
    {
        // areas.user: la tabla muestra el responsable de cada área; sin esto
        // se dispara una consulta por renglón.
        $this->department = $department->load('areas.user');
        $this->stats = $department->getStats();
    }

    public function render()
    {
        return view('livewire.admin.departments.department-show');
    }
}
