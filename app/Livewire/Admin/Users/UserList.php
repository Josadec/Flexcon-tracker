<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use App\Models\Department;
use App\Models\Area;
use Spatie\Permission\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserList extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $roleFilter = '';
    public string $departmentFilter = '';
    public string $typeFilter = self::TYPE_STAFF;
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public int $perPage = 10;

    public bool $showImportModal = false;
    public $importFile = null;
    public array $importResults = [];

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = ['name', 'email', 'account', 'created_at'];

    /**
     * Empleado de planta = usuario con este rol. Su alta, sus turnos y su área
     * de trabajo se administran en el módulo Empleados, no aquí.
     */
    public const EMPLOYEE_ROLE = 'employee';

    /** Ámbitos del listado. Por omisión esta pantalla NO muestra empleados. */
    public const TYPE_STAFF = 'staff';
    public const TYPE_EMPLOYEE = 'employee';
    public const TYPE_ALL = 'all';

    /**
     * Consulta base del listado: ámbito + filtros. La comparten el listado y la
     * exportación para que el CSV contenga exactamente lo que se ve en pantalla.
     */
    private function filteredQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->when($this->typeFilter === self::TYPE_STAFF, function ($query) {
                $query->whereDoesntHave('roles', fn ($q) => $q->where('name', self::EMPLOYEE_ROLE));
            })
            ->when($this->typeFilter === self::TYPE_EMPLOYEE, function ($query) {
                $query->whereHas('roles', fn ($q) => $q->where('name', self::EMPLOYEE_ROLE));
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('last_name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('account', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->roleFilter, function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->where('name', $this->roleFilter);
                });
            })
            ->when($this->departmentFilter, function ($query) {
                $query->whereHas('areas.department', function ($q) {
                    $q->where('id', $this->departmentFilter);
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    public function render()
    {
        $users = $this->filteredQuery()
            ->with(['roles', 'areas.department'])
            ->paginate($this->perPage);

        // Las métricas describen el ámbito propio de esta pantalla (sin empleados),
        // salvo la última, que existe justamente para decir dónde están los demás.
        $staff = User::whereDoesntHave('roles', fn ($q) => $q->where('name', self::EMPLOYEE_ROLE));
        $totalUsers = (clone $staff)->count();
        $withRole = (clone $staff)->has('roles')->count();

        return view('livewire.admin.users.user-list', [
            'users' => $users,
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::withCount('users')->orderBy('name')->get(),
            'totalUsers' => $totalUsers,
            'usersWithRole' => $withRole,
            'usersWithoutRole' => $totalUsers - $withRole,
            // whereHas y no ->role(): si el rol 'employee' aún no existe en la BD,
            // el scope de Spatie lanza RoleDoesNotExist y tumbaría la pantalla.
            'employeeCount' => User::whereHas('roles', fn ($q) => $q->where('name', self::EMPLOYEE_ROLE))->count(),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
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

    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes eliminar tu propia cuenta.');
            return;
        }

        // Liberar áreas supervisadas
        $user->areas()->update(['user_id' => null]);

        // Quitar roles asignados (Spatie) para no dejar filas huérfanas en model_has_roles
        $user->syncRoles([]);

        // Baja lógica, no borrado real. Antes esto era `forceDelete()` y, como
        // audit_trails.user_id iba en cascada, dar de baja a una persona borraba
        // TODO su rastro: justo lo contrario de conservar 5 años por ISO.
        // El usuario deja de entrar y de aparecer; lo que firmó sigue firmado.
        $user->delete();

        session()->flash('message', 'Usuario eliminado correctamente. Su historial se conserva.');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->departmentFilter = '';
        $this->typeFilter = self::TYPE_STAFF;
        $this->resetPage();
    }

    // ==========================================
    // CSV: Export, Template, Import
    // ==========================================

    /**
     * Columnas del CSV en orden fijo. Contrato para export e import.
     */
    public static function csvColumns(): array
    {
        return [
            'name',
            'last_name',
            'account',
            'email',
            'password',
            'role_name',
            'area_name',
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'usuarios_' . now()->format('Ymd_His') . '.csv';
        $columns = self::csvColumns();

        // Mismo ámbito y mismos filtros que el listado: lo exportado es lo que se ve.
        $users = $this->filteredQuery()->with(['roles', 'areas'])->get();

        return response()->streamDownload(function () use ($users, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            foreach ($users as $user) {
                fputcsv($out, [
                    $user->name,
                    $user->last_name,
                    $user->account,
                    $user->email,
                    '', // password nunca se exporta
                    $user->roles->first()?->name,
                    $user->areas->first()?->name,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $columns = self::csvColumns();
        $sampleRole = Role::orderBy('name')->value('name') ?? 'admin';
        $sampleArea = Area::orderBy('name')->value('name') ?? '';

        return response()->streamDownload(function () use ($columns, $sampleRole, $sampleArea) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);
            // Fila de ejemplo
            fputcsv($out, [
                'Juan',                  // name *
                'Pérez',                 // last_name
                'jperez',                // account (opcional, único)
                'juan.perez@flexcon.la', // email *
                'password123',           // password * (mínimo 8)
                $sampleRole,             // role_name * (debe existir)
                $sampleArea,             // area_name (sólo si rol=Supervisor; opcional)
            ]);
            fclose($out);
        }, 'plantilla_usuarios.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function openImportModal(): void
    {
        $this->reset(['importFile', 'importResults']);
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->reset(['importFile', 'importResults']);
    }

    public function importCsv(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'importFile.required' => 'Selecciona un archivo CSV.',
            'importFile.mimes' => 'El archivo debe ser CSV.',
            'importFile.max' => 'El archivo no puede pesar más de 5 MB.',
        ]);

        $path = $this->importFile->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->importResults = ['errors' => ['No se pudo abrir el archivo.'], 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
            return;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->importResults = ['errors' => ['El archivo está vacío.'], 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
            return;
        }

        $header = array_map(fn($h) => trim(strtolower($h)), $header);
        $expected = self::csvColumns();
        $missing = array_diff($expected, $header);

        if (!empty($missing)) {
            fclose($handle);
            $this->importResults = [
                'errors' => ['Faltan columnas en el CSV: ' . implode(', ', $missing)],
                'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0,
            ];
            return;
        }

        $rolesByName = Role::pluck('id', 'name');
        $areasByName = Area::pluck('id', 'name');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $rowNum = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $row = array_combine($header, array_pad($row, count($header), null));

                $name = trim((string) ($row['name'] ?? ''));
                $lastName = trim((string) ($row['last_name'] ?? ''));
                $account = trim((string) ($row['account'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));
                $password = trim((string) ($row['password'] ?? ''));
                $roleName = trim((string) ($row['role_name'] ?? ''));
                $areaName = trim((string) ($row['area_name'] ?? ''));

                if ($name === '' || $email === '' || $roleName === '') {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: faltan campos obligatorios (name, email, role_name).";
                    continue;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: email inválido ({$email}).";
                    continue;
                }

                if (!$rolesByName->has($roleName)) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: el rol '{$roleName}' no existe.";
                    continue;
                }

                $areaId = null;
                if ($areaName !== '') {
                    $areaId = $areasByName[$areaName] ?? null;
                    if (!$areaId) {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: el área '{$areaName}' no existe.";
                        continue;
                    }
                }

                $basePayload = [
                    'name' => $name,
                    'last_name' => $lastName ?: null,
                    'account' => $account ?: null,
                ];

                $existing = User::where('email', $email)->first();

                if ($existing) {
                    // Validar conflicto de account con otro user
                    if ($account !== '' && $account !== $existing->account) {
                        $conflict = User::where('account', $account)->where('id', '!=', $existing->id)->exists();
                        if ($conflict) {
                            $failed++;
                            $errors[] = "Fila {$rowNum}: la cuenta '{$account}' ya está usada por otro usuario.";
                            continue;
                        }
                    }

                    // Detectar cambios
                    $changed = false;
                    foreach ($basePayload as $key => $val) {
                        if ((string) $existing->{$key} !== (string) $val) {
                            $changed = true;
                            break;
                        }
                    }

                    // Rol actual
                    $currentRole = $existing->roles->first()?->name;
                    if ($currentRole !== $roleName) {
                        $changed = true;
                    }

                    // Área actual (la primera supervisada, si aplica)
                    $currentAreaId = $existing->areas()->first()?->id;
                    if ($roleName === 'Supervisor' && $areaId && $currentAreaId !== $areaId) {
                        $changed = true;
                    }

                    // Password si trae una nueva
                    $updatePassword = false;
                    if ($password !== '') {
                        if (strlen($password) < 8) {
                            $failed++;
                            $errors[] = "Fila {$rowNum}: la contraseña debe tener al menos 8 caracteres.";
                            continue;
                        }
                        if (!\Illuminate\Support\Facades\Hash::check($password, $existing->password)) {
                            $updatePassword = true;
                            $changed = true;
                        }
                    }

                    if (!$changed) {
                        $skipped++;
                        continue;
                    }

                    try {
                        if ($updatePassword) {
                            $basePayload['password'] = Hash::make($password);
                        }
                        $existing->update($basePayload);
                        $existing->syncRoles([$roleName]);

                        if ($roleName === 'Supervisor' && $areaId) {
                            // Liberar áreas previas y asignar la nueva
                            $existing->areas()->update(['user_id' => null]);
                            Area::where('id', $areaId)->update(['user_id' => $existing->id]);
                        }

                        $updated++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: error al actualizar ({$e->getMessage()}).";
                    }
                } else {
                    // INSERT
                    if ($password === '') {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: la contraseña es obligatoria para crear un usuario nuevo.";
                        continue;
                    }
                    if (strlen($password) < 8) {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: la contraseña debe tener al menos 8 caracteres.";
                        continue;
                    }
                    if ($account !== '' && User::where('account', $account)->exists()) {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: la cuenta '{$account}' ya existe.";
                        continue;
                    }

                    try {
                        $user = User::create(array_merge($basePayload, [
                            'email' => $email,
                            'password' => Hash::make($password),
                        ]));

                        $user->assignRole($roleName);

                        if ($areaId && $roleName === 'Supervisor') {
                            Area::where('id', $areaId)->update(['user_id' => $user->id]);
                        }

                        $created++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: error al crear ({$e->getMessage()}).";
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = 'Error general: ' . $e->getMessage();
        } finally {
            fclose($handle);
        }

        $this->importResults = compact('created', 'updated', 'skipped', 'failed', 'errors');

        if ($created > 0 || $updated > 0) {
            $parts = [];
            if ($created > 0) $parts[] = "{$created} creados";
            if ($updated > 0) $parts[] = "{$updated} actualizados";
            if ($skipped > 0) $parts[] = "{$skipped} sin cambios";
            if ($failed > 0)  $parts[] = "{$failed} fallaron";
            session()->flash('message', 'Importación de usuarios: ' . implode(', ', $parts) . '.');
        }
    }
}
