<?php

namespace App\Livewire\Admin\Roles;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Livewire\Component;

class RoleCreate extends Component
{
    public string $name = '';
    public array $selectedPermissions = [];

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name',
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'exists:permissions,id',
        ];
    }

    /**
     * Marca o desmarca de golpe todos los permisos de un grupo.
     *
     * Los ids llegan desde la vista porque el agrupado se arma ahí (por el
     * prefijo del nombre) y no todos los permisos tienen prefijo.
     */
    public function toggleGroup(array $ids): void
    {
        $ids = array_map('strval', $ids);
        $selected = array_map('strval', $this->selectedPermissions);

        $todosMarcados = empty(array_diff($ids, $selected));

        $this->selectedPermissions = $todosMarcados
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique(array_merge($selected, $ids)));
    }

    public function saveRole(): void
    {
        $this->validate();

        $role = Role::create(['name' => $this->name]);

        if (!empty($this->selectedPermissions)) {
            // Validar que los permisos seleccionados existan en la base de datos
            $existingPermissionIds = Permission::whereIn('id', $this->selectedPermissions)->pluck('id')->toArray();
            
            // Solo sincronizar los permisos que realmente existen
            $role->syncPermissions($existingPermissionIds);
        }

        session()->flash('message', 'Rol creado correctamente.');

        $this->redirect(route('admin.roles.index'), navigate: true);
    }

    public function render()
    {
        $permissions = Permission::orderBy('name')->get();

        $groupedPermissions = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            return count($parts) > 1 ? $parts[0] : 'otros';
        });

        $groupLabels = [
            'admin' => 'Administración General',
            'usuarios' => 'Usuarios & Seguridad',
            'catalogos' => 'Catálogos',
            'ordenes' => 'Órdenes (PO / WO)',
            'produccion' => 'Producción',
            'calidad' => 'Calidad',
            'materiales' => 'Materiales',
            'otros' => 'Otros',
        ];

        return view('livewire.admin.roles.role-create', [
            'permissions' => $permissions,
            'groupedPermissions' => $groupedPermissions,
            'groupLabels' => $groupLabels,
        ]);
    }
}
