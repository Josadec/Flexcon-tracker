<?php

namespace App\Livewire\Admin\Roles;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Livewire\Component;
use Livewire\WithPagination;

class RoleList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public int $perPage = 10;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['name', 'created_at'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
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
        $this->resetPage();
    }

    public function deleteRole(int $id): void
    {
        $role = Role::findOrFail($id);

        if ($role->users()->count() > 0) {
            session()->flash('error', 'No se puede eliminar «' . $role->name . '» porque tiene usuarios asignados.');
            return;
        }

        $role->delete();

        session()->flash('message', 'Rol «' . $role->name . '» eliminado correctamente.');
    }

    public function render()
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.roles.role-list', [
            'roles' => $roles,
            'totalRoles' => Role::count(),
            'totalPermissions' => Permission::count(),
            'rolesWithoutPermissions' => Role::doesntHave('permissions')->count(),
            'rolesWithoutUsers' => Role::doesntHave('users')->count(),
        ]);
    }
}
