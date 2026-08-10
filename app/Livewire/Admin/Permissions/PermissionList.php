<?php

namespace App\Livewire\Admin\Permissions;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;

class PermissionList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public string $filterGroup = '';
    public int $perPage = 15;

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['name', 'created_at'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterGroup(): void
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
        $this->filterGroup = '';
        $this->resetPage();
    }

    public function deletePermission(int $id): void
    {
        $permission = Permission::findOrFail($id);

        if ($permission->roles()->count() > 0) {
            session()->flash('error', 'No se puede eliminar «' . $permission->name . '» porque está asignado a uno o más roles.');
            return;
        }

        $permission->delete();

        session()->flash('message', 'Permiso «' . $permission->name . '» eliminado correctamente.');
    }

    public function render()
    {
        $permissions = Permission::withCount('roles')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->filterGroup, fn ($query) => $query->where('name', 'like', $this->filterGroup . '.%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        // Los permisos se nombran «grupo.accion»; el prefijo sirve de familia.
        $groups = Permission::pluck('name')
            ->map(fn ($name) => str_contains($name, '.') ? explode('.', $name)[0] : null)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('livewire.admin.permissions.permission-list', [
            'permissions' => $permissions,
            'groups' => $groups,
            'totalPermissions' => Permission::count(),
            'totalRoles' => Role::count(),
            'orphanPermissions' => Permission::doesntHave('roles')->count(),
        ]);
    }
}
