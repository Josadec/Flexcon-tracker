<?php

namespace App\Livewire\Admin\Invoices;

use App\Models\Invoice;
use App\Models\PackingSlip;
use App\Services\InvoiceDeleteService;
use App\Services\InvoiceFromPackingSlipService;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

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

    // -----------------------------------------------------------------------
    // Packing Slips pendientes de Invoice
    // -----------------------------------------------------------------------

    /**
     * Retorna los Packing Slips en estado shipped que aún no tienen Invoice.
     * Se usan para mostrar la sección de "Packing Slips listos para Invoice"
     * en la parte superior de la lista, permitiendo al depto. de Ordenes
     * iniciar el borrador del Invoice desde aquí.
     */
    protected function pendingPackingSlips()
    {
        return PackingSlip::with(['items'])
            ->shipped()
            ->whereNull('invoice_id')
            ->orderBy('shipped_at', 'asc')
            ->get();
    }

    /**
     * Genera un Invoice en estado borrador a partir de un Packing Slip.
     * Delega toda la logica al servicio InvoiceFromPackingSlipService.
     * Redirige al detalle del Invoice recién creado.
     */
    public function createInvoice(int $packingSlipId, InvoiceFromPackingSlipService $service): mixed
    {
        $packingSlip = PackingSlip::findOrFail($packingSlipId);

        try {
            $invoice = $service->createFromPackingSlip($packingSlip);

            return redirect()
                ->route('admin.invoices.show', $invoice->invoice_number)
                ->with('success', "Invoice #{$invoice->invoice_number} creado correctamente desde el Packing Slip {$packingSlip->ps_number}.");

        } catch (RuntimeException $e) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // -----------------------------------------------------------------------
    // Eliminacion de Invoice desde la lista
    // -----------------------------------------------------------------------

    /**
     * Elimina un Invoice directamente desde la fila de la lista.
     * Solo disponible para Invoices en estado draft o cancelled.
     * Usa wire:confirm nativo de Livewire 3 en la vista para la confirmacion.
     */
    public function deleteInvoice(int $invoiceId, InvoiceDeleteService $service): void
    {
        $invoice = Invoice::findOrFail($invoiceId);

        try {
            $service->delete($invoice);

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "Invoice #{$invoice->invoice_number} eliminado correctamente.",
            ]);

        } catch (RuntimeException $e) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------

    public function render()
    {
        $query = Invoice::with(['packingSlip'])
            ->search($this->search);

        if ($this->filterStatus === Invoice::STATUS_DRAFT) {
            $query->draft();
        } elseif ($this->filterStatus === Invoice::STATUS_ISSUED) {
            $query->issued();
        }

        $invoices = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $stats = [
            'total'  => Invoice::count(),
            'draft'  => Invoice::draft()->count(),
            'issued' => Invoice::issued()->count(),
        ];

        return view('livewire.admin.invoices.invoice-list', [
            'invoices'              => $invoices,
            'stats'                 => $stats,
            'pendingPackingSlips'   => $this->pendingPackingSlips(),
        ]);
    }
}
