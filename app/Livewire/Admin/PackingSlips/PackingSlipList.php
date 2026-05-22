<?php

namespace App\Livewire\Admin\PackingSlips;

use App\Models\PackingSlip;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PackingSlipList extends Component
{
    use WithPagination;

    /** Tab activo: 'queue' (WO Listos para SL) o 'list' (Shipping List). Se persiste en la URL como ?tab=. */
    #[Url(as: 'tab')]
    public string $activeTab = 'queue';

    public string $search = '';

    public string $filterStatus = 'all';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public ?int $deleteId = null;

    public bool $confirmingDeletion = false;

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['queue', 'list'])) {
            $this->activeTab = $tab;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortField = $field;
    }

    public function confirmDeletion(int $id): void
    {
        $packingSlip = PackingSlip::findOrFail($id);

        $this->deleteId = $id;
        $this->confirmingDeletion = true;
    }

    public function delete(): void
    {
        $packingSlip = PackingSlip::findOrFail($this->deleteId);

        // Eliminar explicitamente los items antes del soft-delete del PS.
        // Esto libera los lotes (packing_slip_items.lot_id UNIQUE) para que
        // puedan ser asignados a un nuevo Packing Slip. El cascade de BD no
        // se activa con SoftDeletes ya que no es un DELETE SQL real.
        $packingSlip->items()->delete();

        $packingSlip->delete();

        session()->flash('flash.banner', 'Packing Slip eliminado correctamente.');
        session()->flash('flash.bannerStyle', 'success');

        $this->confirmingDeletion = false;
        $this->deleteId = null;
    }

    public function cancelDeletion(): void
    {
        $this->confirmingDeletion = false;
        $this->deleteId = null;
    }

    public function render()
    {
        // Solo ejecutar las queries del tab 'list' cuando ese tab está activo
        if ($this->activeTab === 'list') {
            $query = PackingSlip::with(['creator', 'items'])
                ->search($this->search);

            if ($this->filterStatus === 'draft') {
                $query->draft();
            } elseif ($this->filterStatus === 'pending') {
                $query->pending();
            } elseif ($this->filterStatus === 'shipped') {
                $query->shipped();
            } elseif ($this->filterStatus === 'cancelled') {
                $query->cancelled();
            }

            $packingSlips = $query->orderBy($this->sortField, $this->sortDirection)
                ->paginate($this->perPage);

            $stats = [
                'total' => PackingSlip::count(),
                'draft' => PackingSlip::draft()->count(),
                'pending' => PackingSlip::pending()->count(),
                'shipped' => PackingSlip::shipped()->count(),
                'cancelled' => PackingSlip::cancelled()->count(),
            ];
        } else {
            // Tab 'queue' activo: se pasan colecciones vacías para evitar errores en la vista
            $packingSlips = PackingSlip::query()->paginate(0);
            $stats = ['total' => 0, 'draft' => 0, 'pending' => 0, 'shipped' => 0, 'cancelled' => 0];
        }

        return view('livewire.admin.packing-slips.packing-slip-list-v2', [
            'packingSlips' => $packingSlips,
            'stats' => $stats,
        ]);
    }
}
