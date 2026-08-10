<?php

namespace App\Livewire\Admin\ProductionStatuses;

use App\Models\ProductionStatus;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Mini-CRUD del catálogo de estados de producción, dentro de un modal.
 *
 * El catálogo lo comparten mesas, semi-automáticos y máquinas, así que este
 * componente NO vive dentro de ningún módulo: se monta donde haga falta y se
 * abre con el evento `open-production-statuses`.
 *
 *     <livewire:admin.production-statuses.production-status-manager />
 *     <x-ui.btn wire:click="$dispatch('open-production-statuses')">Estados</x-ui.btn>
 *
 * Al guardar avisa a la pantalla anfitriona con `production-statuses-updated`
 * (para que refresque su selector o su tabla) y, cuando el estado es nuevo,
 * con `production-status-created` para que se pueda dejar seleccionado.
 */
class ProductionStatusManager extends Component
{
    public bool $show = false;

    /** null = alta; id = edición. Con $showForm en false se ve el listado. */
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $color = '#10b981';
    public string $order = '';
    public bool $active = true;
    public string $description = '';

    /** Aviso dentro del modal: aquí no sirve el flash, el modal no recarga la pantalla. */
    public ?string $feedback = null;
    public string $feedbackTone = 'success';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:production_statuses,name' . ($this->editingId ? ',' . $this->editingId : ''),
            'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'order' => 'required|integer|min:0',
            'active' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.unique' => 'Ya existe un estado con ese nombre.',
            'color.required' => 'Elige un color.',
            'color.regex' => 'El color debe venir en formato #RRGGBB.',
            'order.required' => 'El orden es obligatorio.',
            'order.integer' => 'El orden debe ser un número entero.',
        ];
    }

    #[On('open-production-statuses')]
    public function open(): void
    {
        $this->resetForm();
        $this->feedback = null;
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetForm();
        $this->feedback = null;
    }

    public function startCreate(): void
    {
        $this->resetForm();
        // Se sugiere el siguiente lugar de la lista para no obligar a inventarlo.
        $this->order = (string) (((int) ProductionStatus::max('order')) + 1);
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $status = ProductionStatus::find($id);

        if (!$status) {
            $this->feedbackTone = 'danger';
            $this->feedback = 'Ese estado ya no existe.';
            return;
        }

        $this->resetValidation();
        $this->editingId = $status->id;
        $this->name = $status->name;
        $this->color = $status->color;
        $this->order = (string) $status->order;
        $this->active = $status->active;
        $this->description = $status->description ?? '';
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $data = $this->validate();
        $isNew = $this->editingId === null;

        $status = ProductionStatus::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $data['name'],
                'color' => $data['color'],
                'order' => $data['order'],
                'active' => $data['active'],
                'description' => $data['description'] ?: null,
            ]
        );

        $this->resetForm();
        $this->feedbackTone = 'success';
        $this->feedback = $isNew
            ? 'Estado «' . $status->name . '» creado.'
            : 'Estado «' . $status->name . '» actualizado.';

        // La pantalla anfitriona refresca su selector / su tabla.
        $this->dispatch('production-statuses-updated');

        if ($isNew) {
            $this->dispatch('production-status-created', id: $status->id);
        }
    }

    public function delete(int $id): void
    {
        $status = ProductionStatus::find($id);

        if (!$status) {
            $this->feedbackTone = 'danger';
            $this->feedback = 'Ese estado ya no existe.';
            return;
        }

        if (!$status->canBeDeleted()) {
            $this->feedbackTone = 'danger';
            $this->feedback = 'No se puede eliminar «' . $status->name . '»: hay mesas, semi-automáticos o máquinas que lo usan.';
            return;
        }

        $status->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->feedbackTone = 'success';
        $this->feedback = 'Estado «' . $status->name . '» eliminado.';

        $this->dispatch('production-statuses-updated');
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'name', 'color', 'order', 'active', 'description']);
        $this->resetValidation();
    }

    public function render()
    {
        // Los conteos explican por qué un estado no se puede eliminar.
        $statuses = $this->show
            ? ProductionStatus::withCount(['tables', 'semiAutomatics', 'machines'])
                ->orderBy('order')
                ->orderBy('name')
                ->get()
            : collect();

        return view('livewire.admin.production-statuses.production-status-manager', [
            'statuses' => $statuses,
        ]);
    }
}
