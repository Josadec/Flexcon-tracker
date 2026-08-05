<?php

namespace App\Livewire\Admin\SentLists;

use App\Models\SentList;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SentListShow extends Component
{
    /**
     * Detalle de una lista preliminar.
     *
     * La pestaña activa vive en la URL, así que se puede compartir el enlace a
     * la vista de un departamento concreto y recargar sin perder el sitio.
     */
    public SentList $sentList;

    #[Url(as: 'dept', except: '')]
    public string $tab = '';

    public bool $showStatusModal = false;

    public string $newStatus = '';

    public function mount(SentList $sentList): void
    {
        $this->sentList = $sentList;

        $allowed = $this->allowedTabs();

        // Si la URL trae una pestaña a la que el usuario no tiene acceso, se
        // cae a la primera permitida en lugar de mostrar una pestaña vacía.
        if ($this->tab === '' || ! in_array($this->tab, $allowed, true)) {
            $this->tab = in_array($sentList->current_department, $allowed, true)
                ? $sentList->current_department
                : ($allowed[0] ?? '');
        }
    }

    /** Departamentos que el rol del usuario puede consultar en esta lista. */
    public function allowedTabs(): array
    {
        $user = auth()->user();

        return match (true) {
            $user->hasRole('admin')      => ['materiales', 'inspeccion', 'produccion', 'calidad', 'envios'],
            $user->hasRole('Materiales') => ['materiales'],
            $user->hasRole('Produccion') => ['produccion'],
            $user->hasRole('Calidad')    => ['calidad', 'inspeccion'],
            $user->hasRole('Empaques')   => ['envios'],
            default                      => [],
        };
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, $this->allowedTabs(), true)) {
            $this->tab = $tab;
        }
    }

    // ── Cambio de estado ─────────────────────────────────────────────────

    /**
     * El estado se puede cambiar en cualquier dirección, incluido regresar a
     * «Pendiente» desde confirmada o cancelada. Antes sólo se dejaba salir de
     * pendiente, así que un clic equivocado era irreversible.
     */
    public function openStatusModal(): void
    {
        $this->newStatus = $this->sentList->status;
        $this->showStatusModal = true;
        $this->resetValidation();
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
        $this->newStatus = '';
        $this->resetValidation();
    }

    public function saveStatus(): void
    {
        $this->validate([
            'newStatus' => 'required|in:pending,confirmed,canceled',
        ], [
            'newStatus.required' => 'Selecciona el nuevo estado.',
            'newStatus.in'       => 'El estado seleccionado no es válido.',
        ]);

        // Cancelar borra la lista, salvo que alguna de sus órdenes ya esté
        // corriendo en piso: en ese caso se conserva como evidencia.
        if ($this->newStatus === SentList::STATUS_CANCELED) {
            $running = $this->sentList->getRunningWorkOrders();

            if ($running->isEmpty()) {
                $id = $this->sentList->id;
                $this->sentList->delete();

                session()->flash('message', "Lista #{$id} cancelada y eliminada: ninguna de sus órdenes había empezado.");

                $this->redirect(route('admin.sent-lists.index'), navigate: true);

                return;
            }

            $this->sentList->update(['status' => SentList::STATUS_CANCELED]);
            $this->sentList->refresh();

            $labels = $running
                ->map(fn ($wo) => $wo->purchaseOrder->wo ?? $wo->wo_number)
                ->filter()
                ->take(3)
                ->join(', ');

            $extra = $running->count() > 3 ? ' y ' . ($running->count() - 3) . ' más' : '';

            session()->flash('message',
                "Lista cancelada. No se eliminó porque {$running->count()} "
                . Str::plural('orden', $running->count())
                . " ya está" . ($running->count() === 1 ? '' : 'n')
                . " corriendo ({$labels}{$extra}).");

            $this->closeStatusModal();

            return;
        }

        $this->sentList->update(['status' => $this->newStatus]);
        $this->sentList->refresh();

        session()->flash('message', "Estado actualizado a «{$this->sentList->status_label}».");
        $this->closeStatusModal();
    }

    public function render()
    {
        $this->sentList->loadMissing([
            'purchaseOrders.part',
            'workOrders.purchaseOrder.part',
            'shifts',
            'unresolvedRejections.rejectedBy',
            'unresolvedRejections.lot',
        ]);

        // Etapas del flujo con su sello de aprobación, para la barra de avance.
        $stages = [
            'materiales' => ['label' => 'Materiales', 'at' => $this->sentList->materials_approved_at],
            'inspeccion' => ['label' => 'Inspección', 'at' => $this->sentList->inspection_approved_at],
            'produccion' => ['label' => 'Producción', 'at' => $this->sentList->production_approved_at],
            'calidad'    => ['label' => 'Calidad',    'at' => $this->sentList->quality_approved_at],
            'envios'     => ['label' => 'Empaque',    'at' => $this->sentList->shipping_approved_at],
        ];

        $doneStages = collect($stages)->filter(fn ($s) => ! is_null($s['at']))->count();

        return view('livewire.admin.sent-lists.sent-list-show', [
            'allowedTabs' => $this->allowedTabs(),
            'departments' => SentList::getDepartments(),
            'stages'      => $stages,
            'doneStages'  => $doneStages,
            'workOrders'  => $this->sentList->getEffectiveWorkOrders(),
        ]);
    }
}
