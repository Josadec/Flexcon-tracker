<?php

namespace App\Livewire\Admin\Employees;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\User;
use App\Models\Area;
use App\Models\Shift;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeList extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $filterArea = '';
    public $filterShift = '';
    public $filterStatus = '';
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $perPage = 10;

    public bool $showImportModal = false;
    public $importFile = null;
    public array $importResults = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'filterArea' => ['except' => ''],
        'filterShift' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterArea()
    {
        $this->resetPage();
    }

    public function updatingFilterShift()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'filterArea', 'filterShift', 'filterStatus']);
        $this->resetPage();
    }

    public function deleteEmployee($employeeId)
    {
        $employee = User::find($employeeId);

        if ($employee) {
            $employee->delete();
            session()->flash('flash.banner', 'Empleado eliminado correctamente.');
            session()->flash('flash.bannerStyle', 'success');
        } else {
            session()->flash('flash.banner', 'No se puede eliminar el empleado.');
            session()->flash('flash.bannerStyle', 'danger');
        }
    }

    // ==========================================
    // CSV: Export, Template, Import
    // ==========================================

    /**
     * Columnas del CSV en orden fijo. Sirve como contrato para export e import.
     */
    public static function csvColumns(): array
    {
        return [
            'employee_number',
            'name',
            'last_name',
            'email',
            'password',
            'position',
            'birth_date',
            'entry_date',
            'area_name',
            'shift_name',
            'active',
            'comments',
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'empleados_' . now()->format('Ymd_His') . '.csv';
        $columns = self::csvColumns();

        $employees = User::role('employee')
            ->with(['area', 'shift'])
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->filterArea, fn($q) => $q->byArea($this->filterArea))
            ->when($this->filterShift, fn($q) => $q->byShift($this->filterShift))
            ->when($this->filterStatus !== '', function ($q) {
                if ($this->filterStatus === '1') return $q->active();
                if ($this->filterStatus === '0') return $q->inactive();
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->get();

        return response()->streamDownload(function () use ($employees, $columns) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            foreach ($employees as $emp) {
                fputcsv($out, [
                    $emp->employee_number,
                    $emp->name,
                    $emp->last_name,
                    $emp->email,
                    '', // password nunca se exporta
                    $emp->position,
                    optional($emp->birth_date)->format('Y-m-d'),
                    optional($emp->entry_date)->format('Y-m-d'),
                    $emp->area?->name,
                    $emp->shift?->name,
                    $emp->active ? '1' : '0',
                    $emp->comments,
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
        $sampleArea = Area::orderBy('name')->value('name') ?? 'Producción';
        $sampleShift = Shift::active()->orderBy('name')->value('name') ?? 'First Shift (Morning)';

        return response()->streamDownload(function () use ($columns, $sampleArea, $sampleShift) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);
            // Fila de ejemplo
            fputcsv($out, [
                'EMP260001',          // employee_number (opcional, dejar vacío si null)
                'Juan',                // name *
                'Pérez',               // last_name *
                'juan.perez@flexcon.la', // email *
                'password123',         // password * (mínimo 8)
                'Operador',            // position
                '1990-05-15',          // birth_date (YYYY-MM-DD)
                '2026-01-10',          // entry_date (YYYY-MM-DD)
                $sampleArea,           // area_name * (debe existir)
                $sampleShift,          // shift_name * (debe existir)
                '1',                   // active (1=activo, 0=inactivo)
                'Empleado de muestra', // comments
            ]);
            fclose($out);
        }, 'plantilla_empleados.csv', [
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
            $this->importResults = ['errors' => ['No se pudo abrir el archivo.'], 'created' => 0, 'failed' => 0];
            return;
        }

        // BOM strip
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->importResults = ['errors' => ['El archivo está vacío.'], 'created' => 0, 'failed' => 0];
            return;
        }

        $header = array_map(fn($h) => trim(strtolower($h)), $header);
        $expected = self::csvColumns();
        $missing = array_diff($expected, $header);

        if (!empty($missing)) {
            fclose($handle);
            $this->importResults = [
                'errors' => ['Faltan columnas en el CSV: ' . implode(', ', $missing)],
                'created' => 0,
                'failed' => 0,
            ];
            return;
        }

        $areasByName = Area::pluck('id', 'name');
        $shiftsByName = Shift::pluck('id', 'name');

        $created = 0;
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
                $email = trim((string) ($row['email'] ?? ''));
                $password = trim((string) ($row['password'] ?? ''));
                $areaName = trim((string) ($row['area_name'] ?? ''));
                $shiftName = trim((string) ($row['shift_name'] ?? ''));

                if ($name === '' || $lastName === '' || $email === '' || $password === '' || $areaName === '' || $shiftName === '') {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: faltan campos obligatorios (name, last_name, email, password, area_name, shift_name).";
                    continue;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: email inválido ({$email}).";
                    continue;
                }

                if (strlen($password) < 8) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: la contraseña debe tener al menos 8 caracteres.";
                    continue;
                }

                if (User::where('email', $email)->exists()) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: el email '{$email}' ya está registrado.";
                    continue;
                }

                $areaId = $areasByName[$areaName] ?? null;
                if (!$areaId) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: área '{$areaName}' no existe.";
                    continue;
                }

                $shiftId = $shiftsByName[$shiftName] ?? null;
                if (!$shiftId) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: turno '{$shiftName}' no existe.";
                    continue;
                }

                $employeeNumber = trim((string) ($row['employee_number'] ?? '')) ?: null;
                if ($employeeNumber && User::where('employee_number', $employeeNumber)->exists()) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: número de empleado '{$employeeNumber}' ya existe.";
                    continue;
                }

                try {
                    $user = User::create([
                        'name' => $name,
                        'last_name' => $lastName,
                        'email' => $email,
                        'password' => Hash::make($password),
                        'employee_number' => $employeeNumber,
                        'position' => trim((string) ($row['position'] ?? '')) ?: null,
                        'birth_date' => trim((string) ($row['birth_date'] ?? '')) ?: null,
                        'entry_date' => trim((string) ($row['entry_date'] ?? '')) ?: null,
                        'active' => in_array(trim((string) ($row['active'] ?? '1')), ['1', 'true', 'TRUE', 'si', 'sí'], true),
                        'comments' => trim((string) ($row['comments'] ?? '')) ?: null,
                        'area_id' => $areaId,
                        'shift_id' => $shiftId,
                    ]);
                    $user->assignRole('employee');
                    $created++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: error al crear ({$e->getMessage()}).";
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = 'Error general: ' . $e->getMessage();
        } finally {
            fclose($handle);
        }

        $this->importResults = [
            'created' => $created,
            'failed' => $failed,
            'errors' => $errors,
        ];

        if ($created > 0) {
            session()->flash('flash.banner', "Importados {$created} empleados." . ($failed > 0 ? " {$failed} fila(s) fallaron." : ''));
            session()->flash('flash.bannerStyle', $failed > 0 ? 'warning' : 'success');
        }
    }

    public function render()
    {
        // Obtener usuarios con rol 'employee'
        $employees = User::query()
            ->role('employee')
            ->with(['area', 'shift'])
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->filterArea, fn($q) => $q->byArea($this->filterArea))
            ->when($this->filterShift, fn($q) => $q->byShift($this->filterShift))
            ->when($this->filterStatus !== '', function ($q) {
                if ($this->filterStatus === '1') {
                    return $q->active();
                } elseif ($this->filterStatus === '0') {
                    return $q->inactive();
                }
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $totalEmployees = User::role('employee')->count();
        $activeEmployees = User::role('employee')->where('active', true)->count();
        $inactiveEmployees = User::role('employee')->where('active', false)->count();

        return view('livewire.admin.employees.employee-list', [
            'employees' => $employees,
            'areas' => Area::orderBy('name')->get(),
            'shifts' => Shift::active()->orderBy('name')->get(),
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'inactiveEmployees' => $inactiveEmployees,
        ]);
    }
}
