<?php

namespace App\Livewire\Admin\Invoices;

use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceList extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $filterStatus  = 'all';
    public string $sortField     = 'created_at';
    public string $sortDirection = 'desc';
    public int    $perPage       = 15;

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

    public function render()
    {
        $query = Invoice::with(['packingSlip'])
            ->search($this->search);

        if ($this->filterStatus === Invoice::STATUS_DRAFT) {
            $query->draft();
        } elseif ($this->filterStatus === Invoice::STATUS_ISSUED) {
            $query->issued();
        } elseif ($this->filterStatus === Invoice::STATUS_PAID) {
            $query->paid();
        }

        $invoices = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $stats = [
            'total'  => Invoice::count(),
            'draft'  => Invoice::draft()->count(),
            'issued' => Invoice::issued()->count(),
            'paid'   => Invoice::paid()->count(),
        ];

        return view('livewire.admin.invoices.invoice-list', [
            'invoices' => $invoices,
            'stats'    => $stats,
        ]);
    }
}
