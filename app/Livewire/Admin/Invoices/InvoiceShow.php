<?php

namespace App\Livewire\Admin\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceDeleteService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

class InvoiceShow extends Component
{
    public Invoice $invoice;

    // Edicion inline de LOT NO.
    public bool    $editingLotNo  = false;
    public string  $lotNoValue    = '';

    // Edicion inline de cargos fijos
    public ?int    $editingChargeId     = null;
    public string  $editingChargeAmount = '';

    // Edicion inline de lot_number por item
    public ?int    $editingLotItemId    = null;
    public string  $editingLotItemValue = '';

    // Confirmacion de acciones de estado
    public bool $confirmingIssue  = false;
    public bool $confirmingCancel = false;
    public bool $confirmingDelete = false;

    public function mount(Invoice $invoice): void
    {
        $this->invoice   = $invoice->load([
            'packingSlip',
            'productItems',
            'chargeItems.chargeType',
            'creator',
            'issuer',
        ]);
        $this->lotNoValue = $this->invoice->lot_no ?? '';
    }

    // -----------------------------------------------------------------------
    // Edicion del LOT NO.
    // -----------------------------------------------------------------------

    public function startEditingLotNo(): void
    {
        $this->lotNoValue    = $this->invoice->lot_no ?? '';
        $this->editingLotNo  = true;
    }

    public function cancelEditingLotNo(): void
    {
        $this->editingLotNo = false;
        $this->resetErrorBag('lotNoValue');
    }

    /**
     * Actualiza el LOT NO. del Invoice y propaga el valor a todos los
     * invoice_items.lot_number del mismo Invoice.
     * Solo permitido en estado draft o issued. Solo Admin.
     */
    public function updateLotNo(): void
    {
        // Validacion de formato: MMDDYY + 'x' + sufijo (ej: 030926x01 o 030926x20)
        $this->validate([
            'lotNoValue' => ['required', 'string', 'max:20', 'regex:/^\d{6}x\d{2}$/'],
        ], [
            'lotNoValue.required' => 'El LOT NO. es obligatorio.',
            'lotNoValue.regex'    => 'Formato inválido. Use MMDDYY + x + 2 dígitos (ej: 030926x01).',
        ]);

        $newLotNo = trim($this->lotNoValue);

        // Actualizar invoice.lot_no
        $this->invoice->update(['lot_no' => $newLotNo]);

        // Propagar a todos los invoice_items.lot_number de este Invoice
        InvoiceItem::where('invoice_id', $this->invoice->id)
            ->where('is_fixed_charge', false)
            ->update(['lot_number' => $newLotNo]);

        $this->editingLotNo = false;

        // Recargar para reflejar los cambios
        $this->invoice->refresh()->load([
            'packingSlip',
            'productItems',
            'chargeItems.chargeType',
            'creator',
            'issuer',
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'LOT NO. actualizado correctamente en el Invoice y todos sus items.']);
    }

    // -----------------------------------------------------------------------
    // Edicion de montos de cargos fijos
    // -----------------------------------------------------------------------

    public function startEditingCharge(int $itemId): void
    {
        if (! $this->invoice->canBeModified()) {
            return;
        }

        $item = $this->invoice->chargeItems->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $this->editingChargeId     = $itemId;
        $this->editingChargeAmount = (string) $item->unit_cost;
    }

    public function cancelEditingCharge(): void
    {
        $this->editingChargeId     = null;
        $this->editingChargeAmount = '';
        $this->resetErrorBag('editingChargeAmount');
    }

    /**
     * Actualiza el monto de un cargo fijo y recalcula los totales del Invoice.
     * Solo disponible en estado draft.
     */
    public function updateChargeAmount(): void
    {
        if (! $this->invoice->canBeModified()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Solo se pueden editar cargos en Invoices en borrador.']);
            return;
        }

        $this->validate([
            'editingChargeAmount' => ['required', 'numeric', 'min:0'],
        ], [
            'editingChargeAmount.required' => 'El monto es obligatorio.',
            'editingChargeAmount.numeric'  => 'El monto debe ser un número válido.',
            'editingChargeAmount.min'      => 'El monto no puede ser negativo.',
        ]);

        $item = InvoiceItem::where('invoice_id', $this->invoice->id)
            ->where('id', $this->editingChargeId)
            ->where('is_fixed_charge', true)
            ->firstOrFail();

        $amount = (string) $this->editingChargeAmount;

        $item->unit_cost  = $amount;
        $item->line_total = round((float) bcmul('1', $amount, 6), 2);
        $item->save();

        // Recalcular totales del Invoice
        $this->invoice->calculateTotals()->save();

        $this->editingChargeId     = null;
        $this->editingChargeAmount = '';

        $this->invoice->refresh()->load([
            'packingSlip',
            'productItems',
            'chargeItems.chargeType',
            'creator',
            'issuer',
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Cargo actualizado y totales recalculados.']);
    }

    // -----------------------------------------------------------------------
    // Edicion de lot_number por item de producto
    // -----------------------------------------------------------------------

    public function startEditingLotItem(int $itemId): void
    {
        if (! $this->invoice->isDraft()) {
            return;
        }

        $item = $this->invoice->productItems->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $this->editingLotItemId    = $itemId;
        $this->editingLotItemValue = (string) ($item->lot_number ?? '');
    }

    public function cancelEditingLotItem(): void
    {
        $this->editingLotItemId    = null;
        $this->editingLotItemValue = '';
        $this->resetErrorBag('editingLotItemValue');
    }

    /**
     * Actualiza el lot_number de un item de producto individual.
     * Solo disponible en estado draft.
     */
    public function saveLotItem(): void
    {
        if (! $this->invoice->isDraft()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Solo se puede editar el LOT NO. en Invoices en borrador.']);
            return;
        }

        $this->validate([
            'editingLotItemValue' => ['required', 'string', 'max:50'],
        ], [
            'editingLotItemValue.required' => 'El LOT NO. es obligatorio.',
            'editingLotItemValue.max'      => 'El LOT NO. no puede superar 50 caracteres.',
        ]);

        InvoiceItem::where('invoice_id', $this->invoice->id)
            ->where('id', $this->editingLotItemId)
            ->where('is_fixed_charge', false)
            ->update(['lot_number' => trim($this->editingLotItemValue)]);

        $this->editingLotItemId    = null;
        $this->editingLotItemValue = '';

        $this->invoice->refresh()->load([
            'packingSlip',
            'productItems',
            'chargeItems.chargeType',
            'creator',
            'issuer',
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'LOT NO. del item actualizado correctamente.']);
    }

    // -----------------------------------------------------------------------
    // Edicion inline del Invoice Number
    // -----------------------------------------------------------------------

    /**
     * Actualiza el invoice_number del Invoice.
     * Solo permitido en estado draft.
     * Redirige a la nueva URL porque el Route Model Binding usa invoice_number como slug.
     */
    public function updateInvoiceNumber(string $value): void
    {
        if (! $this->invoice->isDraft()) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'Solo se puede editar el número en Invoices en borrador.',
            ]);
            return;
        }

        $value = trim($value);

        if (empty($value) || strlen($value) > 10) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'El número de Invoice no puede estar vacío ni superar 10 caracteres.',
            ]);
            return;
        }

        // Verificar unicidad incluyendo soft-deleted para evitar conflictos con el índice UNIQUE
        $exists = Invoice::withTrashed()
            ->where('invoice_number', $value)
            ->where('id', '!=', $this->invoice->id)
            ->exists();

        if ($exists) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => "El número '{$value}' ya está en uso por otro Invoice.",
            ]);
            return;
        }

        $this->invoice->update(['invoice_number' => $value]);

        // Redirigir a la nueva URL porque el slug de la ruta cambió
        $this->redirect(route('admin.invoices.show', $value));
    }

    // -----------------------------------------------------------------------
    // Transiciones de estado
    // -----------------------------------------------------------------------

    public function confirmIssue(): void
    {
        $this->confirmingIssue = true;
    }

    public function cancelIssue(): void
    {
        $this->confirmingIssue = false;
    }

    /**
     * Emite el Invoice (draft -> issued).
     * Valida que no haya items de producto con unit_cost = 0.
     */
    public function issueInvoice(): void
    {
        if (! $this->invoice->isDraft()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Solo se puede emitir un Invoice en estado borrador.']);
            $this->confirmingIssue = false;
            return;
        }

        $zeroItems = $this->invoice->productItems->filter(fn ($i) => (float) $i->unit_cost === 0.0)->count();

        if ($zeroItems > 0) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => "Advertencia: hay {$zeroItems} item(s) sin precio definido. El Invoice se emitirá de todas formas.",
            ]);
        }

        $this->invoice->update([
            'status'    => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
            'issued_by' => Auth::id(),
        ]);

        $this->confirmingIssue = false;

        $this->invoice->refresh()->load([
            'packingSlip',
            'productItems',
            'chargeItems.chargeType',
            'creator',
            'issuer',
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => "Invoice #{$this->invoice->invoice_number} emitido correctamente."]);
    }

    public function confirmCancel(): void
    {
        $this->confirmingCancel = true;
    }

    public function cancelCancelation(): void
    {
        $this->confirmingCancel = false;
    }

    /**
     * Cancela el Invoice.
     * Solo permitido desde draft.
     */
    public function cancelInvoice(): void
    {
        if (! $this->invoice->isDraft()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Solo se puede cancelar un Invoice en estado borrador.']);
            $this->confirmingCancel = false;
            return;
        }

        $this->invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        $this->confirmingCancel = false;

        $this->invoice->refresh()->load([
            'packingSlip',
            'productItems',
            'chargeItems.chargeType',
            'creator',
            'issuer',
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => "Invoice #{$this->invoice->invoice_number} cancelado."]);
    }

    // -----------------------------------------------------------------------
    // Eliminacion del Invoice
    // -----------------------------------------------------------------------

    public function confirmDelete(): void
    {
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    /**
     * Elimina el Invoice usando InvoiceDeleteService.
     *
     * Solo disponible para Invoices en estado draft o cancelled.
     * Redirige a la lista de Invoices con mensaje de exito.
     * Si el servicio lanza RuntimeException, despacha un toast de error.
     */
    public function deleteInvoice(InvoiceDeleteService $service): mixed
    {
        $invoiceNumber = $this->invoice->invoice_number;

        try {
            $service->delete($this->invoice);

            return redirect()
                ->route('admin.invoices.index')
                ->with('success', "Invoice #{$invoiceNumber} eliminado correctamente.");

        } catch (RuntimeException $e) {
            $this->confirmingDelete = false;
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------

    public function render()
    {
        $zeroUnitCostCount = $this->invoice->productItems
            ->filter(fn ($i) => (float) $i->unit_cost === 0.0)
            ->count();

        return view('livewire.admin.invoices.invoice-show', [
            'zeroUnitCostCount' => $zeroUnitCostCount,
        ]);
    }
}
