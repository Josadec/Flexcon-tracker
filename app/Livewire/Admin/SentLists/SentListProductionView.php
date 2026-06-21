<?php

namespace App\Livewire\Admin\SentLists;

use App\Models\SentList;
use App\Models\Weighing;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SentListProductionView extends Component
{
    public SentList $sentList;

    // Add weighing modal
    public bool $showWeighingModal = false;
    public ?int $weighingLotId = null;
    public int $weighingQuantity = 0;
    public string $weighingComments = '';
    public string $weighingAt = '';
    public ?int $editingWeighingId = null;

    // Send to quality modal
    public bool $showSendModal = false;
    public string $sendNotes = '';

    public function mount(SentList $sentList): void
    {
        $this->sentList   = $sentList;
        $this->weighingAt = now()->format('Y-m-d\TH:i');
    }

    public function openWeighingModal(int $lotId): void
    {
        $this->weighingLotId       = $lotId;
        $this->weighingQuantity    = 0;
        $this->weighingComments    = '';
        $this->weighingAt          = now()->format('Y-m-d\TH:i');
        $this->editingWeighingId   = null;
        $this->showWeighingModal   = true;
    }

    public function editWeighing(int $weighingId): void
    {
        $w = Weighing::with('lot')->find($weighingId);
        if (!$w) return;

        $this->weighingLotId       = $w->lot_id;
        $this->weighingQuantity    = $w->good_pieces;
        $this->weighingComments    = $w->comments ?? '';
        $this->weighingAt          = $w->weighed_at->format('Y-m-d\TH:i');
        $this->editingWeighingId   = $w->id;
        $this->showWeighingModal   = true;
    }

    public function saveWeighing(): void
    {
        $this->validate([
            'weighingQuantity' => 'required|integer|min:1',
            'weighingAt'       => 'required|date',
            'weighingComments' => 'nullable|string|max:500',
        ], [
            'weighingQuantity.required' => 'La cantidad es obligatoria.',
            'weighingQuantity.min'      => 'La cantidad debe ser mayor a 0.',
            'weighingAt.required'       => 'La fecha/hora es obligatoria.',
        ]);

        $lot = \App\Models\Lot::findOrFail($this->weighingLotId);

        $data = [
            'lot_id'      => $this->weighingLotId,
            'quantity'    => $lot->quantity,
            'good_pieces' => $this->weighingQuantity,
            'bad_pieces'  => 0,
            'weighed_at'  => $this->weighingAt,
            'weighed_by'  => Auth::id(),
            'comments'    => $this->weighingComments ?: null,
        ];

        if ($this->editingWeighingId) {
            $w = Weighing::find($this->editingWeighingId);
            if ($w) {
                // Pesadas existentes conservan su kit_id (historial); no se reasigna.
                $w->update($data);
                $message = 'Pesada actualizada correctamente.';
            } else {
                session()->flash('error', 'Pesada no encontrada.');
                return;
            }
        } else {
            // Pesada a nivel viajero: kit_id = null (CRIMP ya no usa kit).
            $data['kit_id'] = null;
            Weighing::create($data);
            $message = 'Pesada registrada correctamente.';
        }

        // Update lot status to in_progress if still pending
        if ($lot->status === \App\Models\Lot::STATUS_PENDING) {
            $lot->update(['status' => \App\Models\Lot::STATUS_IN_PROGRESS]);
        }

        $this->showWeighingModal  = false;
        $this->weighingLotId      = null;
        $this->weighingQuantity   = 0;
        $this->weighingComments   = '';
        $this->editingWeighingId  = null;
        $this->sentList->refresh();
        session()->flash('message', $message);
    }

    public function closeWeighingModal(): void
    {
        $this->showWeighingModal  = false;
        $this->weighingLotId      = null;
        $this->weighingQuantity   = 0;
        $this->weighingComments   = '';
        $this->editingWeighingId  = null;
    }

    public function deleteWeighing(int $weighingId): void
    {
        Weighing::findOrFail($weighingId)->delete();
        $this->sentList->refresh();
        session()->flash('message', 'Pesada eliminada.');
    }

    public function openSendModal(): void
    {
        $this->sendNotes    = '';
        $this->showSendModal = true;
    }

    public function closeSendModal(): void
    {
        $this->showSendModal = false;
        $this->sendNotes     = '';
    }

    public function sendToQuality(): void
    {
        if (!empty($this->sendNotes)) {
            $this->sentList->update([
                'notes' => trim(($this->sentList->notes ?? '') . "\n[Producción " . now()->format('d/m/Y H:i') . '] ' . $this->sendNotes),
            ]);
        }

        $this->sentList->moveToNextDepartment(Auth::id());
        session()->flash('message', 'Enviado a Calidad correctamente.');
        $this->redirect(route('admin.sent-lists.show', $this->sentList));
    }

    public function markLotComplete(int $lotId): void
    {
        $lot = \App\Models\Lot::findOrFail($lotId);
        $lot->update(['status' => \App\Models\Lot::STATUS_COMPLETED]);
        $this->sentList->refresh();
        session()->flash('message', "Lote {$lot->lot_number} marcado como completado.");
    }

    public function reopenLot(int $lotId): void
    {
        $lot = \App\Models\Lot::findOrFail($lotId);
        $lot->update(['status' => \App\Models\Lot::STATUS_IN_PROGRESS]);
        $this->sentList->refresh();
        session()->flash('message', "Lote {$lot->lot_number} reabierto para correcciones.");
    }

    public function render()
    {
        $this->sentList->load([
            'purchaseOrders.workOrder.purchaseOrder.part',
            'purchaseOrders.workOrder.lots.weighings.weighedBy',
            'workOrders.purchaseOrder.part',
            'workOrders.lots.weighings.weighedBy',
        ]);

        $workOrders = $this->sentList->getEffectiveWorkOrders();

        return view('livewire.admin.sent-lists.production-view', compact('workOrders'));
    }
}
