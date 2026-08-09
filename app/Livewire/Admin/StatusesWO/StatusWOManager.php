<?php

namespace App\Livewire\Admin\StatusesWO;

use App\Models\StatusWO;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Administración de los estados de WO sin salir de la pantalla donde se usan.
 *
 * Antes, cambiar el color de un estado obligaba a salir del WO, entrar a
 * /admin/statuses-wo, editar, y volver a buscar el WO. Este componente es el
 * mismo CRUD (nombre, color, comentarios) montado como modal, siguiendo el
 * patrón del alta/edición de precios en la ficha de la parte (PartShow).
 *
 * Es el ÚNICO punto de administración del catálogo: la pantalla standalone
 * (/admin/statuses-wo) y su entrada de menú se eliminaron, así que aquí tienen
 * que estar todas las operaciones — alta, edición y baja.
 *
 * Se monta como hijo: la pantalla anfitriona sólo pone el botón que dispara
 * `open-statuses-wo-manager` y, cuando algo cambia, recibe `statuses-wo-updated`
 * para refrescar sus propias píldoras de estado.
 */
class StatusWOManager extends Component
{
    /**
     * Permisos del catálogo. Se conservan tras eliminar la pantalla standalone:
     * son el único candado que le queda a esta administración.
     */
    public const PERMISSION_VIEW   = 'catalogos.view-statuses-wo';
    public const PERMISSION_CREATE = 'catalogos.create-statuses-wo';
    public const PERMISSION_EDIT   = 'catalogos.edit-statuses-wo';
    public const PERMISSION_DELETE = 'catalogos.delete-statuses-wo';

    public bool $show = false;

    /**
     * Filas editables. Se trabaja sobre un array (no sobre modelos) para poder
     * editar todos los colores de una pasada y guardar una sola vez.
     *
     * @var array<int, array{id: int, name: string, color: string, comments: string, work_orders_count: int}>
     */
    public array $rows = [];

    /** Alta de un estado nuevo: colapsada mientras no se pide. */
    public bool $showNewForm = false;

    public string $newName = '';

    public string $newColor = StatusWO::DEFAULT_COLOR;

    public string $newComments = '';

    /** Aviso dentro del modal (el modal no se cierra al guardar). */
    public ?string $feedback = null;

    public string $feedbackTone = 'success';

    // ===============================================
    // APERTURA / CIERRE
    // ===============================================

    #[On('open-statuses-wo-manager')]
    public function open(): void
    {
        $this->authorizeAction(self::PERMISSION_VIEW);

        $this->resetValidation();
        $this->feedback    = null;
        $this->showNewForm = false;
        $this->resetNewForm();
        $this->loadRows();

        $this->show = true;
    }

    public function close(): void
    {
        $this->show        = false;
        $this->showNewForm = false;
        $this->feedback    = null;
        $this->rows        = [];
        $this->resetNewForm();
        $this->resetValidation();
    }

    private function loadRows(): void
    {
        $this->rows = StatusWO::withCount('workOrders')
            ->orderBy('name')
            ->get()
            ->map(fn (StatusWO $status) => [
                'id'                => $status->id,
                'name'              => $status->name,
                'color'             => StatusWO::normalizeColor($status->color),
                'comments'          => (string) ($status->comments ?? ''),
                'work_orders_count' => (int) $status->work_orders_count,
            ])
            ->all();
    }

    // ===============================================
    // EDICIÓN EN BLOQUE (colores, nombres, comentarios)
    // ===============================================

    public function saveAll(): void
    {
        $this->authorizeAction(self::PERMISSION_EDIT);

        // El usuario puede teclear "3b82f6" o "#3b8"; se normaliza antes de
        // validar para no rechazar algo que sí se entiende.
        foreach ($this->rows as $i => $row) {
            $this->rows[$i]['color'] = StatusWO::normalizeColor($row['color'] ?? '');
            $this->rows[$i]['name']  = trim((string) ($row['name'] ?? ''));
        }

        $this->validate($this->rowRules(), $this->validationMessages());

        if (! $this->assertNamesAreUniqueWithinForm()) {
            return;
        }

        $changed = 0;

        DB::transaction(function () use (&$changed) {
            foreach ($this->rows as $row) {
                $status = StatusWO::find($row['id']);

                if (! $status) {
                    continue;
                }

                $status->fill([
                    'name'     => $row['name'],
                    'color'    => $row['color'],
                    'comments' => $row['comments'] !== '' ? $row['comments'] : null,
                ]);

                if ($status->isDirty()) {
                    $status->save();
                    $changed++;
                }
            }
        });

        $this->loadRows();

        if ($changed === 0) {
            $this->setFeedback('No había cambios que guardar.', 'info');

            return;
        }

        $this->setFeedback(
            $changed === 1 ? 'Se actualizó 1 estado.' : "Se actualizaron {$changed} estados.",
            'success'
        );

        $this->dispatch('statuses-wo-updated');
    }

    /**
     * Devuelve un estado a su color por defecto sin tener que teclear el hex.
     */
    public function resetRowColor(int $index): void
    {
        if (isset($this->rows[$index])) {
            $this->rows[$index]['color'] = StatusWO::DEFAULT_COLOR;
        }
    }

    // ===============================================
    // ALTA
    // ===============================================

    public function toggleNewForm(): void
    {
        $this->authorizeAction(self::PERMISSION_CREATE);

        $this->showNewForm = ! $this->showNewForm;
        $this->resetValidation();

        if (! $this->showNewForm) {
            $this->resetNewForm();
        }
    }

    public function createStatus(): void
    {
        $this->authorizeAction(self::PERMISSION_CREATE);

        $this->newColor = StatusWO::normalizeColor($this->newColor);
        $this->newName  = trim($this->newName);

        $this->validate([
            'newName'     => ['required', 'string', 'max:255', Rule::unique('statuses_wo', 'name')],
            'newColor'    => ['required', 'string', 'regex:' . StatusWO::COLOR_REGEX],
            'newComments' => ['nullable', 'string', 'max:1000'],
        ], $this->validationMessages());

        StatusWO::create([
            'name'     => $this->newName,
            'color'    => $this->newColor,
            'comments' => $this->newComments !== '' ? $this->newComments : null,
        ]);

        $this->resetNewForm();
        $this->showNewForm = false;
        $this->loadRows();

        $this->setFeedback('Estado creado correctamente.', 'success');
        $this->dispatch('statuses-wo-updated');
    }

    private function resetNewForm(): void
    {
        $this->newName     = '';
        $this->newColor    = StatusWO::DEFAULT_COLOR;
        $this->newComments = '';
    }

    // ===============================================
    // BAJA
    // ===============================================

    public function deleteStatus(int $id): void
    {
        $this->authorizeAction(self::PERMISSION_DELETE);

        $status = StatusWO::find($id);

        if (! $status) {
            $this->setFeedback('Ese estado ya no existe.', 'warn');
            $this->loadRows();

            return;
        }

        // La FK de work_orders.status_id es RESTRICT: borrar un estado en uso
        // reventaría en la base. Se avisa con el número de WOs que lo usan.
        if (! $status->canBeDeleted()) {
            $used = $status->workOrders()->count();

            $this->setFeedback(
                "No se puede eliminar «{$status->name}»: lo usan {$used} " .
                ($used === 1 ? 'orden de trabajo' : 'órdenes de trabajo') . '.',
                'danger'
            );

            return;
        }

        $name = $status->name;
        $status->delete();

        $this->loadRows();
        $this->setFeedback("Estado «{$name}» eliminado.", 'success');
        $this->dispatch('statuses-wo-updated');
    }

    // ===============================================
    // VALIDACIÓN / AUTORIZACIÓN
    // ===============================================

    /** @return array<string, array<int, mixed>> */
    private function rowRules(): array
    {
        $rules = [];

        foreach ($this->rows as $i => $row) {
            $rules["rows.{$i}.name"]     = ['required', 'string', 'max:255', Rule::unique('statuses_wo', 'name')->ignore($row['id'])];
            $rules["rows.{$i}.color"]    = ['required', 'string', 'regex:' . StatusWO::COLOR_REGEX];
            $rules["rows.{$i}.comments"] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }

    /**
     * `unique` mira la base, no el formulario: dos filas renombradas al mismo
     * nombre pasarían la validación y se pisarían entre sí.
     */
    private function assertNamesAreUniqueWithinForm(): bool
    {
        $seen = [];
        $ok   = true;

        foreach ($this->rows as $i => $row) {
            $key = mb_strtolower($row['name']);

            if (isset($seen[$key])) {
                $this->addError("rows.{$i}.name", 'Ya hay otro estado con este nombre en la lista.');
                $ok = false;

                continue;
            }

            $seen[$key] = $i;
        }

        return $ok;
    }

    /** @return array<string, string> */
    private function validationMessages(): array
    {
        return [
            'rows.*.name.required'  => 'El nombre del estado es obligatorio.',
            'rows.*.name.unique'    => 'Ya existe otro estado con este nombre.',
            'rows.*.name.max'       => 'El nombre no puede pasar de 255 caracteres.',
            'rows.*.color.required' => 'El color es obligatorio.',
            'rows.*.color.regex'    => 'El color debe ser hexadecimal, por ejemplo #3B82F6.',
            'rows.*.comments.max'   => 'El comentario no puede pasar de 1000 caracteres.',
            'newName.required'      => 'El nombre del estado es obligatorio.',
            'newName.unique'        => 'Ya existe un estado con este nombre.',
            'newColor.required'     => 'El color es obligatorio.',
            'newColor.regex'        => 'El color debe ser hexadecimal, por ejemplo #3B82F6.',
            'newComments.max'       => 'El comentario no puede pasar de 1000 caracteres.',
        ];
    }

    /**
     * Sin el permiso del catálogo la acción no se ejecuta aunque se invoque a
     * mano. Al no haber ya una ruta con middleware delante, esta comprobación
     * es la única protección del catálogo.
     */
    private function authorizeAction(string $permission): void
    {
        if (! auth()->check() || ! auth()->user()->can($permission)) {
            abort(403, 'No tienes permiso para administrar los estados de WO.');
        }
    }

    private function setFeedback(string $text, string $tone): void
    {
        $this->feedback     = $text;
        $this->feedbackTone = $tone;
    }

    // ===============================================
    // RENDER
    // ===============================================

    public function render()
    {
        return view('livewire.admin.statuses-wo.status-wo-manager');
    }
}
