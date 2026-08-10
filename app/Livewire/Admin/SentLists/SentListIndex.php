<?php

namespace App\Livewire\Admin\SentLists;

use App\Models\SentList;
use App\Services\ReopeningService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class SentListIndex extends Component
{
    use WithPagination;

    /**
     * Listado de listas preliminares.
     *
     * Antes era un controlador con una tabla estática: no había búsqueda,
     * filtros ni orden, y para cambiar el estado había que entrar a una pantalla
     * aparte. Ahora todo se resuelve aquí, sin recargar.
     */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $filterStatus = 'all';

    #[Url(except: 'all')]
    public string $filterDepartment = 'all';

    #[Url(except: 'created_at')]
    public string $sortField = 'created_at';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    public int $perPage = 15;

    /** Modal de cambio de estado. */
    public ?int $statusModalId = null;

    public string $newStatus = '';

    public function updated($property): void
    {
        // Cualquier filtro devuelve a la primera página.
        if (in_array($property, ['search', 'filterStatus', 'filterDepartment', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
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
        $this->reset(['search', 'filterStatus', 'filterDepartment']);
        $this->resetPage();
    }

    // ── Cambio de estado sin salir del listado ───────────────────────────

    /**
     * El estado se puede cambiar en cualquier dirección, incluido regresar a
     * «Pendiente». Antes sólo se dejaba salir de pendiente y un clic
     * equivocado era irreversible.
     */
    public function openStatusModal(int $id): void
    {
        $list = SentList::findOrFail($id);

        $this->statusModalId = $id;
        $this->newStatus = $list->status;
        $this->resetValidation();
    }

    public function closeStatusModal(): void
    {
        $this->statusModalId = null;
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

        $list = SentList::findOrFail($this->statusModalId);

        // Sacar una lista ya confirmada de su estado es reabrirla: vuelve a ser
        // editable por los departamentos. Antes lo podía hacer cualquiera de los
        // cinco roles operativos, sobre cualquier lista, desde un <select>.
        if ($list->status === SentList::STATUS_CONFIRMED
            && $this->newStatus !== SentList::STATUS_CONFIRMED
            && ! app(ReopeningService::class)->allows(auth()->user())) {
            session()->flash('error',
                "La lista #{$list->id} ya está confirmada. Sólo Administración puede reabrirla.");
            $this->closeStatusModal();

            return;
        }

        // Cancelar borra la lista, salvo que alguna de sus órdenes ya esté
        // corriendo en piso: en ese caso se conserva como evidencia.
        if ($this->newStatus === SentList::STATUS_CANCELED) {
            $running = $list->getRunningWorkOrders();

            if ($running->isEmpty()) {
                $id = $list->id;
                $list->delete();

                session()->flash('message', "Lista #{$id} cancelada y eliminada: ninguna de sus órdenes había empezado.");
                $this->closeStatusModal();

                return;
            }

            $list->update(['status' => SentList::STATUS_CANCELED]);

            session()->flash('message',
                "Lista #{$list->id} cancelada. No se eliminó porque {$running->count()} "
                . Str::plural('orden', $running->count())
                . ' ya está' . ($running->count() === 1 ? '' : 'n') . ' corriendo.');

            $this->closeStatusModal();

            return;
        }

        $list->update(['status' => $this->newStatus]);

        session()->flash('message', "Lista #{$list->id}: estado actualizado a «{$list->fresh()->status_label}».");
        $this->closeStatusModal();
    }

    public function deleteSentList(int $id): void
    {
        $list = SentList::findOrFail($id);

        if (! $list->canBeDeleted()) {
            $reason = ! $list->isPending()
                ? ($list->status === SentList::STATUS_CANCELED
                    ? 'No se pueden eliminar listas canceladas.'
                    : 'No se pueden eliminar listas confirmadas.')
                : 'No se puede eliminar: la lista ya tiene órdenes de trabajo asociadas.';

            session()->flash('error', $reason);

            return;
        }

        $list->delete();
        session()->flash('message', "Lista #{$id} eliminada.");
    }

    public function render()
    {
        $query = SentList::query()
            ->with(['purchaseOrders.part', 'workOrders', 'shifts']);

        if ($this->search !== '') {
            $like = '%' . $this->search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('id', 'like', $like)
                    ->orWhereHas('purchaseOrders', fn ($po) => $po->where('po_number', 'like', $like))
                    ->orWhereHas('purchaseOrders.part', fn ($p) => $p->where('number', 'like', $like)
                        ->orWhere('description', 'like', $like));
            });
        }

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterDepartment !== 'all') {
            $query->where('current_department', $this->filterDepartment);
        }

        $sortable = ['id', 'created_at', 'start_date', 'num_persons', 'status'];
        $field = in_array($this->sortField, $sortable, true) ? $this->sortField : 'created_at';
        $query->orderBy($field, $this->sortDirection === 'asc' ? 'asc' : 'desc');

        // Los totales se calculan sobre TODA la tabla, no sobre la página
        // actual. Antes se contaban desde el paginador y por eso las tarjetas
        // mostraban cifras que cambiaban al pasar de página.
        $counts = SentList::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admin.sent-lists.sent-list-index', [
            'sentLists'   => $query->paginate($this->perPage),
            'totalAll'    => (int) $counts->sum(),
            'totalPend'   => (int) ($counts[SentList::STATUS_PENDING] ?? 0),
            'totalConf'   => (int) ($counts[SentList::STATUS_CONFIRMED] ?? 0),
            'totalCanc'   => (int) ($counts[SentList::STATUS_CANCELED] ?? 0),
            'departments' => SentList::getDepartments(),
            'modalList'   => $this->statusModalId ? SentList::find($this->statusModalId) : null,
        ]);
    }
}
