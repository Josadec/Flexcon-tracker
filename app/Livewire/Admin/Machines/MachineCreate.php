<?php

namespace App\Livewire\Admin\Machines;

use App\Models\Machine;
use App\Models\Area;
use App\Models\ProductionStatus;
use Livewire\Attributes\On;
use Livewire\Component;

class MachineCreate extends Component
{
    public string $name = '';
    public string $brand = '';
    public string $model = '';
    public string $sn = '';
    public string $asset_number = '';
    public string $employees = '';
    public string $setup_time = '';
    public string $maintenance_time = '';
    public bool $active = true;
    public string $comments = '';
    public string $area_id = '';
    public string $production_status_id = '';

    /** El catálogo de estados se administra desde el modal compartido. */
    #[On('production-statuses-updated')]
    public function refreshProductionStatuses(): void
    {
        //
    }

    /** Un estado recién creado desde el modal queda seleccionado si no había ninguno. */
    #[On('production-status-created')]
    public function useNewProductionStatus(int $id): void
    {
        if ($this->production_status_id === '') {
            $this->production_status_id = (string) $id;
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'sn' => 'nullable|string|max:255',
            // La columna es única en la base: sin esta regla, un activo repetido
            // salía como error de SQL en vez de como mensaje de validación.
            'asset_number' => 'nullable|string|max:255|unique:machines,asset_number',
            'employees' => 'nullable|integer|min:1',
            'setup_time' => 'nullable|numeric|min:0',
            'maintenance_time' => 'nullable|numeric|min:0',
            'active' => 'boolean',
            'comments' => 'nullable|string',
            'area_id' => 'required|exists:areas,id',
            'production_status_id' => 'nullable|exists:production_statuses,id',
        ];
    }

    protected function messages(): array
    {
        return [
            'asset_number.unique' => 'Ya hay otra máquina con ese número de activo.',
        ];
    }

    public function save()
    {
        $this->validate();

        Machine::create([
            'name' => $this->name,
            'brand' => $this->brand ?: null,
            'model' => $this->model ?: null,
            'sn' => $this->sn ?: null,
            'asset_number' => $this->asset_number ?: null,
            'employees' => $this->employees ?: null,
            'setup_time' => $this->setup_time ?: null,
            'maintenance_time' => $this->maintenance_time ?: null,
            'active' => $this->active,
            'comments' => $this->comments ?: null,
            'area_id' => $this->area_id,
            'production_status_id' => $this->production_status_id ?: null,
        ]);

        session()->flash('message', 'Máquina creada correctamente.');

        return redirect()->route('admin.machines.index');
    }

    public function render()
    {
        return view('livewire.admin.machines.machine-create', [
            'areas' => Area::orderBy('name')->get(),
            'productionStatuses' => ProductionStatus::active()->ordered()->get(),
        ]);
    }
}
