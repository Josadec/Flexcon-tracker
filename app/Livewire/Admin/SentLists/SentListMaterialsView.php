<?php

namespace App\Livewire\Admin\SentLists;

use App\Livewire\Concerns\GuardsSentListDepartment;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\SentList;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SentListMaterialsView extends Component
{
    use GuardsSentListDepartment;

    public SentList $sentList;

    protected function guardedDepartment(): string
    {
        return SentList::DEPT_MATERIALS;
    }

    // Lot (viajero) modal
    public bool $showLotModal = false;
    public ?int $selectedWorkOrderId = null;
    public array $lots = [];

    // Crimp lot modal (CRIMP only) — lotes de CRIMP del viajero (sustituye al Kit)
    public bool $showCrimpLotModal = false;
    public ?int $crimpLotViajeroId = null;
    public string $crimpLotViajeroLabel = '';
    public int $crimpLotViajeroQty = 0;
    public array $crimpLots = [];

    // Send to inspection modal
    public bool $showSendModal = false;
    public string $sendNotes = '';

    // Material status modal (viajero / lote)
    public bool $showMaterialModal = false;
    public ?int $materialLotId = null;
    public string $materialStatus = 'pending';

    public function mount(SentList $sentList): void
    {
        $this->sentList = $sentList;
    }

    // ─── LOT (VIAJERO) MODAL ──────────────────────────────────────────────────

    public function openLotModal(int $workOrderId): void
    {
        $this->selectedWorkOrderId = $workOrderId;
        $wo = WorkOrder::with('lots')->whereIn('id', $this->sentListWorkOrderIds())->findOrFail($workOrderId);

        $this->lots = $wo->lots->map(fn($l) => [
            'id'       => $l->id,
            'number'   => $l->lot_number,
            'quantity' => $l->quantity,
        ])->toArray();

        if (empty($this->lots)) {
            $this->lots = [['id' => null, 'number' => '', 'quantity' => 0]];
        }

        $this->showLotModal = true;
    }

    public function addLotRow(): void
    {
        $this->lots[] = ['id' => null, 'number' => '', 'quantity' => 0];
    }

    public function removeLotRow(int $index): void
    {
        $this->ensureCanEditDepartment();

        if (!empty($this->lots[$index]['id'])) {
            Lot::whereIn('id', $this->sentListLotIds())->find($this->lots[$index]['id'])?->delete();
        }

        unset($this->lots[$index]);
        $this->lots = array_values($this->lots);
    }

    public function saveLots(): void
    {
        $this->ensureCanEditDepartment();

        $this->validate([
            'lots.*.number'   => 'required|string|max:100',
            'lots.*.quantity' => 'required|integer|min:1',
        ], [
            'lots.*.number.required'   => 'El número de lote es obligatorio.',
            'lots.*.quantity.required' => 'La cantidad es obligatoria.',
            'lots.*.quantity.min'      => 'La cantidad debe ser mayor a 0.',
        ]);

        $wo    = WorkOrder::with('purchaseOrder.part')->whereIn('id', $this->sentListWorkOrderIds())->findOrFail($this->selectedWorkOrderId);
        $total = collect($this->lots)->sum('quantity');

        if ($total > $wo->original_quantity) {
            $this->addError('lots', 'La suma de lotes (' . number_format($total) . ') excede la cantidad del WO (' . number_format($wo->original_quantity) . ').');
            return;
        }

        // Bind WO to this SentList if not already linked
        if ($wo->sent_list_id !== $this->sentList->id) {
            $wo->update(['sent_list_id' => $this->sentList->id]);
        }

        $ownLotIds = $this->sentListLotIds();

        foreach ($this->lots as $row) {
            if (!empty($row['id'])) {
                Lot::whereIn('id', $ownLotIds)->find($row['id'])?->update([
                    'lot_number' => $row['number'],
                    'quantity'   => $row['quantity'],
                ]);
            } else {
                Lot::create([
                    'work_order_id' => $wo->id,
                    'lot_number'    => $row['number'],
                    'quantity'      => $row['quantity'],
                    'description'   => $wo->purchaseOrder->part->description ?? '',
                    'status'        => Lot::STATUS_PENDING,
                ]);
            }
        }

        $this->sentList->refresh();
        $this->showLotModal = false;
        $this->selectedWorkOrderId = null;
        $this->lots = [];
        session()->flash('message', 'Lotes guardados correctamente.');
    }

    public function closeLotModal(): void
    {
        $this->showLotModal        = false;
        $this->selectedWorkOrderId = null;
        $this->lots                = [];
    }

    // ─── CRIMP LOT MODAL (CRIMP) ──────────────────────────────────────────────
    // Los lotes de CRIMP cuelgan del viajero (Lot) — decisión B.1 Opción A.

    public function openCrimpLotModal(int $lotId): void
    {
        $lot = Lot::with(['crimpLots', 'workOrder.purchaseOrder'])->whereIn('id', $this->sentListLotIds())->findOrFail($lotId);

        $this->crimpLotViajeroId    = $lot->id;
        $this->crimpLotViajeroLabel = trim(($lot->workOrder->purchaseOrder->wo ?? $lot->workOrder->wo_number ?? '')
            . ' — Viajero ' . $lot->lot_number);
        $this->crimpLotViajeroQty = (int) $lot->quantity;

        $this->crimpLots = $lot->crimpLots->map(fn($cl) => [
            'id'               => $cl->id,
            'crimp_lot_number' => $cl->crimp_lot_number,
            'lote_fabricante'  => $cl->lote_fabricante,
            'quantity'         => $cl->quantity,
            'comments'         => $cl->comments,
        ])->toArray();

        if (empty($this->crimpLots)) {
            $this->crimpLots = [$this->emptyCrimpLotRow()];
        }

        $this->showCrimpLotModal = true;
    }

    private function emptyCrimpLotRow(): array
    {
        return ['id' => null, 'crimp_lot_number' => '', 'lote_fabricante' => '', 'quantity' => 0, 'comments' => ''];
    }

    public function addCrimpLotRow(): void
    {
        $this->crimpLots[] = $this->emptyCrimpLotRow();
    }

    public function removeCrimpLotRow(int $index): void
    {
        $this->ensureCanEditDepartment();

        if (!empty($this->crimpLots[$index]['id'])) {
            CrimpLot::whereIn('lot_id', $this->sentListLotIds())->find($this->crimpLots[$index]['id'])?->delete();
        }

        unset($this->crimpLots[$index]);
        $this->crimpLots = array_values($this->crimpLots);
    }

    public function saveCrimpLots(): void
    {
        $this->ensureCanEditDepartment();

        // Lote de fabricante OPCIONAL; sin tope de cantidad vs viajero (decisiones B.1).
        $this->validate([
            'crimpLots.*.crimp_lot_number' => 'required|string|max:100',
            'crimpLots.*.lote_fabricante'  => 'nullable|string|max:100',
            'crimpLots.*.quantity'         => 'required|integer|min:1',
            'crimpLots.*.comments'         => 'nullable|string|max:500',
        ], [
            'crimpLots.*.crimp_lot_number.required' => 'El número de lote de CRIMP es obligatorio.',
            'crimpLots.*.quantity.required'         => 'La cantidad es obligatoria.',
            'crimpLots.*.quantity.min'              => 'La cantidad debe ser mayor a 0.',
        ]);

        $ownLotIds = $this->sentListLotIds();
        $lot = Lot::whereIn('id', $ownLotIds)->findOrFail($this->crimpLotViajeroId);

        foreach ($this->crimpLots as $row) {
            $payload = [
                'crimp_lot_number' => $row['crimp_lot_number'],
                'lote_fabricante'  => $row['lote_fabricante'] ?: null,
                'quantity'         => $row['quantity'],
                'comments'         => $row['comments'] ?: null,
            ];

            if (!empty($row['id'])) {
                CrimpLot::whereIn('lot_id', $ownLotIds)->find($row['id'])?->update($payload);
            } else {
                CrimpLot::create(['lot_id' => $lot->id] + $payload);
            }
        }

        $this->sentList->refresh();
        $this->closeCrimpLotModal();
        session()->flash('message', 'Lotes de CRIMP guardados correctamente.');
    }

    public function closeCrimpLotModal(): void
    {
        $this->showCrimpLotModal    = false;
        $this->crimpLotViajeroId    = null;
        $this->crimpLotViajeroLabel = '';
        $this->crimpLotViajeroQty   = 0;
        $this->crimpLots            = [];
    }

    // ─── SEND TO INSPECTION ───────────────────────────────────────────────────

    public function openSendModal(): void
    {
        $this->sentList->load([
            'purchaseOrders.workOrder.purchaseOrder.part',
            'purchaseOrders.workOrder.lots.crimpLots',
            'workOrders.purchaseOrder.part',
            'workOrders.lots.crimpLots',
        ]);

        $workOrders = $this->sentList->workOrders
            ->merge($this->sentList->purchaseOrders->map->workOrder->filter())
            ->unique('id');

        if ($workOrders->isEmpty()) {
            session()->flash('error', 'Esta lista no tiene Work Orders asignados.');
            return;
        }

        foreach ($workOrders as $wo) {
            if ($wo->lots->isEmpty()) {
                session()->flash('error', 'El WO ' . $wo->wo_number . ' no tiene lotes asignados.');
                return;
            }

            $isCrimp = $wo->purchaseOrder->part->is_crimp ?? false;
            if ($isCrimp && $wo->lots->every(fn($lot) => $lot->crimpLots->isEmpty())) {
                session()->flash('error', 'El WO ' . $wo->wo_number . ' (CRIMP) no tiene lotes de CRIMP asignados.');
                return;
            }
        }

        $this->sendNotes    = '';
        $this->showSendModal = true;
    }

    public function sendToInspection(): void
    {
        $this->ensureCanEditDepartment();

        $this->sentList->unresolvedRejections->each(fn($r) => $r->update(['resolved_at' => now()]));

        if (!empty($this->sendNotes)) {
            $this->sentList->update([
                'notes' => trim(($this->sentList->notes ?? '') . "\n[Materiales " . now()->format('d/m/Y H:i') . '] ' . $this->sendNotes),
            ]);
        }

        // Update semaphore statuses so the display page reflects materials approval
        $this->sentList->load([
            'workOrders.lots',
            'purchaseOrders.workOrder.lots',
        ]);

        $allWorkOrders = $this->sentList->workOrders
            ->merge($this->sentList->purchaseOrders->map->workOrder->filter())
            ->unique('id');

        // Liberación de material a nivel viajero/lote — igual para CRIMP y NO-CRIMP.
        // En CRIMP ya no se libera por estado de Kit (decisión: liberación a nivel viajero).
        foreach ($allWorkOrders as $wo) {
            $wo->lots->each(fn($lot) => $lot->update(['material_status' => 'released']));
        }

        $this->sentList->moveToNextDepartment(Auth::id());
        session()->flash('message', 'Lista enviada a Inspección correctamente.');
        $this->redirect(route('admin.sent-lists.show', $this->sentList));
    }

    public function closeSendModal(): void
    {
        $this->showSendModal = false;
        $this->sendNotes     = '';
    }

    // ─── MATERIAL STATUS MODAL (viajero / lote) ───────────────────────────────

    public function openMaterialModal(int $lotId): void
    {
        $lot = Lot::whereIn('id', $this->sentListLotIds())->findOrFail($lotId);
        $this->materialLotId  = $lotId;
        $this->materialStatus = $lot->material_status ?? 'pending';
        $this->showMaterialModal = true;
    }

    public function saveMaterial(): void
    {
        $this->ensureCanEditDepartment();

        Lot::whereIn('id', $this->sentListLotIds())->findOrFail($this->materialLotId)->update(['material_status' => $this->materialStatus]);
        $this->showMaterialModal = false;
        $this->materialLotId     = null;
        $this->sentList->refresh();
        session()->flash('message', 'Estado de material actualizado.');
    }

    public function closeMaterialModal(): void
    {
        $this->showMaterialModal = false;
        $this->materialLotId     = null;
        $this->materialStatus    = 'pending';
    }

    public function render()
    {
        $this->sentList->load([
            'purchaseOrders.workOrder.purchaseOrder.part',
            'purchaseOrders.workOrder.lots.crimpLots',
            'workOrders.purchaseOrder.part',
            'workOrders.lots.crimpLots',
            'unresolvedRejections.rejectedBy',
            'unresolvedRejections.lot',
        ]);

        // Merge WOs from direct sent_list_id AND from the PO pivot (old-system data)
        $directWOs  = $this->sentList->workOrders;
        $pivotWOs   = $this->sentList->purchaseOrders->map->workOrder->filter()->values();
        $workOrders = $directWOs->merge($pivotWOs)->unique('id')->values();

        return view('livewire.admin.sent-lists.materials-view', compact('workOrders'));
    }
}
