<?php

namespace App\Livewire\Admin\SemiAutomatics;

use App\Models\Semi_Automatic;
use App\Models\Area;
use Livewire\Component;

class SemiAutomaticEdit extends Component
{
    public Semi_Automatic $semiAutomatic;
    public string $number = '';
    public string $employees = '';
    public bool $active = true;
    public string $comments = '';
    public string $area_id = '';

    public function mount(Semi_Automatic $semiAutomatic): void
    {
        $this->semiAutomatic = $semiAutomatic;
        $this->number = $semiAutomatic->number;
        $this->employees = $semiAutomatic->employees ?? '';
        $this->active = $semiAutomatic->active;
        $this->comments = $semiAutomatic->comments ?? '';
        $this->area_id = $semiAutomatic->area_id;
    }

    public function rules(): array
    {
        // La tabla real es `semi__automatics` (doble guion bajo, ver
        // Semi_Automatic::$table). Apuntar a `semi_automatics` hacía que la
        // validación consultara una tabla inexistente y guardar reventara.
        return [
            'number' => 'required|string|max:255|unique:semi__automatics,number,' . $this->semiAutomatic->id,
            'employees' => 'nullable|integer|min:1',
            'active' => 'boolean',
            'comments' => 'nullable|string',
            'area_id' => 'required|exists:areas,id',
        ];
    }

    public function update()
    {
        $this->validate();

        $this->semiAutomatic->update([
            'number' => $this->number,
            'employees' => $this->employees ?: null,
            'active' => $this->active,
            'comments' => $this->comments ?: null,
            'area_id' => $this->area_id,
        ]);

        session()->flash('message', 'Semi-automático actualizado correctamente.');

        // Las rutas del panel viven bajo el nombre `admin.`; sin el prefijo,
        // guardar terminaba en RouteNotFoundException.
        return redirect()->route('admin.semi-automatics.index');
    }

    public function render()
    {
        $areas = Area::orderBy('name')->get();

        return view('livewire.admin.semi-automatics.semi-automatic-edit', compact('areas'));
    }
}
