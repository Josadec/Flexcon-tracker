<?php

namespace App\Livewire\Admin\PackingSlips;

use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Services\ReopeningService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PackingSlipShow extends Component
{
    public PackingSlip $packingSlip;
    public string $selectedStatus = '';
    public string $notesValue = '';

    // Panel de edicion de lotes (fusionado desde PackingSlipEdit)
    public bool $editingLots = false;
    public array $selectedLotIds = [];
    public array $dateSpecs     = [];

    public function mount(PackingSlip $packingSlip): void
    {
        $this->packingSlip   = $packingSlip->load(['creator', 'shipper', 'items.lot.workOrder.purchaseOrder.part', 'invoice']);
        $this->selectedStatus = $this->packingSlip->status;
        $this->notesValue     = $this->packingSlip->notes ?? '';

        $this->initLotSelection();

        // Consumir notificaciones pendientes de un redirect previo (ej. updatePsNumber)
        if (session()->has('notify')) {
            $this->dispatch('notify', session()->get('notify'));
        }
    }

    public function goBackToShippingList(): void
    {
        // Guarda en sesión el tab de retorno para que PackingSlipList
        // lo lea en mount() y active el tab correcto sin query strings en la URL.
        session(['shipping_list_return_tab' => 'list']);
        $this->redirect(route('admin.shipping-list.index'));
    }

    // -----------------------------------------------------------------------
    // Inicializar la selección de lotes desde los items actuales del PS
    // -----------------------------------------------------------------------
    protected function initLotSelection(): void
    {
        $this->selectedLotIds = [];
        $this->dateSpecs      = [];

        foreach ($this->packingSlip->items as $item) {
            $this->selectedLotIds[]         = $item->lot_id;
            $this->dateSpecs[$item->lot_id] = $item->lot_date_code ?? $item->lot?->lot_number ?? '';
        }
    }

    // -----------------------------------------------------------------------
    // Gestión de estado del PS
    // -----------------------------------------------------------------------
    public function updateStatus(): void
    {
        $validStatuses = array_keys(PackingSlip::STATUSES);
        if (!in_array($this->selectedStatus, $validStatuses)) {
            return;
        }

        // Sacar un packing slip YA DESPACHADO de vuelta a borrador es una
        // reapertura: el documento salió de la planta. Antes cualquiera con rol
        // Empaques podía hacerlo desde un <select>, sin motivo y sin rastro.
        if ($this->packingSlip->isShipped() && $this->selectedStatus !== PackingSlip::STATUS_SHIPPED) {
            if (! app(ReopeningService::class)->allows(Auth::user())) {
                $this->dispatch('notify', [
                    'type'    => 'error',
                    'message' => 'Este packing slip ya salió despachado. Sólo Administración puede regresarlo a borrador.',
                ]);

                $this->selectedStatus = $this->packingSlip->status;

                return;
            }
        }

        $data = ['status' => $this->selectedStatus];

        // Si se cambia A shipped, registrar quien y cuando
        if ($this->selectedStatus === PackingSlip::STATUS_SHIPPED) {
            $data['shipped_at'] = now();
            $data['shipped_by'] = Auth::id();
        }

        // Si se cambia DESDE shipped a otro estado, limpiar shipped_at/shipped_by
        if ($this->packingSlip->isShipped() && $this->selectedStatus !== PackingSlip::STATUS_SHIPPED) {
            $data['shipped_at'] = null;
            $data['shipped_by'] = null;
        }

        $this->packingSlip->update($data);
        $this->packingSlip->refresh()->load(['creator', 'shipper', 'items.lot.workOrder.purchaseOrder.part', 'invoice']);

        // Si ahora está shipped, cerrar el panel de lotes si estuviera abierto
        if ($this->packingSlip->isShipped()) {
            $this->editingLots = false;
        }

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Estado actualizado a: ' . PackingSlip::STATUSES[$this->selectedStatus],
        ]);
    }

    // -----------------------------------------------------------------------
    // Edición inline del PS Number
    // -----------------------------------------------------------------------
    public function updatePsNumber(string $value): void
    {
        $value = strtoupper(trim($value));

        // Editable solo en Borrador o Pendiente; bloqueado en Despachado o Cancelado
        // (mismo criterio que updateItemDate / updateCrimpLotDate).
        if ($this->packingSlip->isShipped() || $this->packingSlip->isCancelled()) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'No se puede editar el Numero de PS de un Packing Slip despachado o cancelado.',
            ]);
            return;
        }

        if (empty($value) || strlen($value) > 30) {
            return;
        }

        if (PackingSlip::where('ps_number', $value)->where('id', '!=', $this->packingSlip->id)->exists()) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => "El número '{$value}' ya está en uso por otro Packing Slip.",
            ]);
            return;
        }

        $this->packingSlip->update(['ps_number' => $value]);
        $this->packingSlip->refresh()->load(['creator', 'shipper', 'items.lot.workOrder.purchaseOrder.part', 'invoice']);

        // Construir la URL de redirect usando getRouteKey() que ya aplica rawurlencode(),
        // evitando que route() haga doble-encoding en versiones de Livewire con wire:navigate.
        // getRouteKey() retorna ej: %23000012544 para ps_number=#000012544
        // La ruta de esta pantalla es admin/shipping-list/{packingSlip} (admin.shipping-list.show).
        $redirectUrl = url('/admin/shipping-list/' . $this->packingSlip->getRouteKey());

        session()->flash('notify', [
            'type'    => 'success',
            'message' => "Número de PS actualizado a: {$value}",
        ]);

        $this->redirect($redirectUrl);
    }

    // -----------------------------------------------------------------------
    // Edición inline de Notas (notes)
    // -----------------------------------------------------------------------
    public function updateNotes(): void
    {
        if ($this->packingSlip->isShipped() || $this->packingSlip->isCancelled()) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'No se pueden editar las notas en el estado actual del Packing Slip.',
            ]);
            return;
        }

        $this->packingSlip->update(['notes' => trim($this->notesValue) ?: null]);
        $this->packingSlip->refresh()->load(['creator', 'shipper', 'items.lot.workOrder.purchaseOrder.part', 'invoice']);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Notas actualizadas correctamente.',
        ]);
    }

    // -----------------------------------------------------------------------
    // Edición inline de items (Date y Label Spec)
    // -----------------------------------------------------------------------
    public function updateItemDate(int $itemId, string $value): void
    {
        // Editable solo en Borrador o Pendiente; bloqueado si Despachado o Cancelado.
        if ($this->packingSlip->isShipped() || $this->packingSlip->isCancelled()) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'No se puede editar el Date de un Packing Slip despachado o cancelado.',
            ]);
            return;
        }

        $item = $this->packingSlip->items()->findOrFail($itemId);
        $item->update(['lot_date_code' => trim($value) ?: null]);
        $this->packingSlip->load(['creator', 'shipper', 'items.lot.workOrder.purchaseOrder.part']);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Date actualizado correctamente.',
        ]);
    }

    // -----------------------------------------------------------------------
    // Edición inline del Date por Lote de CRIMP (FPL-10)
    // Cada lote de CRIMP es una fila con su propio date_code manual (ej. 260602B01).
    // -----------------------------------------------------------------------
    public function updateCrimpLotDate(int $crimpLotId, string $value): void
    {
        // Editable solo en Borrador o Pendiente; bloqueado si Despachado o Cancelado.
        if ($this->packingSlip->isShipped() || $this->packingSlip->isCancelled()) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'No se puede editar el Date de un Packing Slip despachado o cancelado.',
            ]);
            return;
        }

        // Verificar que el lote de CRIMP pertenece a un lote (viajero) de este Packing Slip.
        $lotIds = $this->packingSlip->items()->pluck('lot_id')->toArray();

        $crimpLot = CrimpLot::whereIn('lot_id', $lotIds)->findOrFail($crimpLotId);
        $crimpLot->update(['date_code' => trim($value) ?: null]);

        $this->packingSlip->load(['creator', 'shipper', 'items.lot.workOrder.purchaseOrder.part', 'items.lot.crimpLots']);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Date actualizado correctamente.',
        ]);
    }

    // -----------------------------------------------------------------------
    // Panel de edición de lotes
    // -----------------------------------------------------------------------
    public function toggleEditingLots(): void
    {
        if ($this->packingSlip->isShipped() || $this->packingSlip->isPending() || $this->packingSlip->isCancelled()) {
            return;
        }

        $this->editingLots = !$this->editingLots;

        // Al abrir, sincronizar la selección con el estado actual del PS
        if ($this->editingLots) {
            $this->packingSlip->load(['creator', 'shipper', 'items.lot.workOrder.purchaseOrder.part']);
            $this->initLotSelection();
            $this->resetErrorBag();
        }
    }

    public function toggleLot(int $lotId): void
    {
        if (in_array($lotId, $this->selectedLotIds)) {
            $this->selectedLotIds = array_values(
                array_filter($this->selectedLotIds, fn ($id) => $id !== $lotId)
            );
            unset($this->dateSpecs[$lotId]);
        } else {
            $this->selectedLotIds[] = $lotId;
            if (!isset($this->dateSpecs[$lotId]) || $this->dateSpecs[$lotId] === '') {
                $lot = Lot::with('workOrder.purchaseOrder.part')->find($lotId);
                // Pre-llenar Date con lot_number como valor provisional (D-06-01)
                $this->dateSpecs[$lotId] = $lot?->lot_number ?? '';
            }
        }
    }

    protected function rulesForLots(): array
    {
        return [
            'selectedLotIds'   => 'required|array|min:1',
            'selectedLotIds.*' => 'integer|exists:lots,id',
            'dateSpecs'        => 'array',
            'dateSpecs.*'      => 'nullable|string|max:20',
        ];
    }

    public function updateLots(): void
    {
        if ($this->packingSlip->isShipped() || $this->packingSlip->isPending() || $this->packingSlip->isCancelled()) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'Este Packing Slip no se puede editar en el estado actual.',
            ]);
            $this->editingLots = false;
            return;
        }

        $this->validate($this->rulesForLots(), [
            'selectedLotIds.required' => 'Debe seleccionar al menos un lote.',
            'selectedLotIds.min'      => 'Debe seleccionar al menos un lote.',
        ]);

        // Verificar que todos los lotes tengan WO con external_wo_number
        $lots = Lot::with('workOrder.purchaseOrder.part')->whereIn('id', $this->selectedLotIds)->get();

        foreach ($lots as $lot) {
            if (!$lot->workOrder->hasExternalWoNumber()) {
                $this->addError(
                    'selectedLotIds',
                    "El lote {$lot->lot_number} pertenece a una WO sin número de WO disponible. Verifique que la Purchase Order tenga el campo WO configurado."
                );
                return;
            }
        }

        // IDs de items actuales en el PS
        $currentLotIds = $this->packingSlip->items()->pluck('lot_id')->toArray();
        $newLotIds     = $this->selectedLotIds;

        // Eliminar items que fueron deseleccionados
        $toRemove = array_diff($currentLotIds, $newLotIds);
        if (!empty($toRemove)) {
            $this->packingSlip->items()->whereIn('lot_id', $toRemove)->delete();
        }

        // Agregar nuevos items y actualizar label_spec/lot_date_code de existentes
        foreach ($lots as $lot) {
            $existing = $this->packingSlip->items()->where('lot_id', $lot->id)->first();

            if ($existing) {
                $existing->update([
                    'label_spec'    => $lot->workOrder?->purchaseOrder?->part?->label_spec ?? null,
                    'lot_date_code' => $this->dateSpecs[$lot->id] ?: null,
                ]);
            } else {
                // Pre-llenar lot_date_code con lot_number como valor provisional (D-06-01)
                PackingSlipItem::create([
                    'packing_slip_id' => $this->packingSlip->id,
                    'lot_id'          => $lot->id,
                    'quantity_packed' => $lot->quantity_packed_final ?? $lot->quantity ?? 0,
                    'wo_number_ps'    => $lot->workOrder->buildWoCode((int) $lot->lot_number),
                    'lot_date_code'   => $this->dateSpecs[$lot->id] ?: ($lot->lot_number ?? null),
                    'label_spec'      => $lot->workOrder?->purchaseOrder?->part?->label_spec ?? null,
                ]);
            }
        }

        // Recargar el PS con todas las relaciones
        $this->packingSlip->refresh()->load(['creator', 'shipper', 'items.lot.workOrder.purchaseOrder.part', 'invoice']);

        // Cerrar el panel y sincronizar la seleccion
        $this->editingLots = false;
        $this->initLotSelection();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "Lotes del Packing Slip {$this->packingSlip->ps_number} actualizados correctamente.",
        ]);
    }

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------
    public function render()
    {
        // Agrupar items por PO para mostrar subtotales por grupo en la vista,
        // replicando la estructura del Excel FPL-10 (columna C agrupada con subtotal).
        // Dentro de cada grupo PO, los items se ordenan de mayor a menor cantidad
        // segun el requerimiento del cliente (formato FPL-10).
        // CRIMP (FPL-10): asegurar el desglose viajero -> lotes de CRIMP disponible en la vista,
        // sea cual sea el load path previo. Solo se usa si la parte es is_crimp.
        $this->packingSlip->loadMissing('items.lot.crimpLots');

        $itemsGroupedByPo = $this->packingSlip->items
            ->groupBy(fn ($item) => $item->lot?->workOrder?->purchaseOrder?->po_number ?? 'Sin PO')
            ->map(fn ($poItems) => $poItems->sortByDesc('quantity_packed')->values());

        // Lotes disponibles para el panel de edicion:
        // Los que están readyForShipping + los que ya están en este PS (para mantenerlos visibles)
        $availableLots = collect();
        if ($this->editingLots && $this->packingSlip->isDraft()) {
            $currentLotIds = $this->packingSlip->items()->pluck('lot_id')->toArray();

            $availableLots = Lot::with(['workOrder.purchaseOrder.part'])
                ->where(function ($q) use ($currentLotIds) {
                    $q->readyForShipping()
                      ->orWhereIn('id', $currentLotIds);
                })
                ->orderBy('lot_number')
                ->get();
        }

        return view('livewire.admin.packing-slips.packing-slip-show', [
            'itemsGroupedByPo' => $itemsGroupedByPo,
            'availableLots'    => $availableLots,
        ]);
    }
}
