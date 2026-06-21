<?php

namespace App\Livewire\Admin\SentLists;

use App\Models\Lot;
use App\Models\QualityWeighing;
use App\Models\SentList;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SentListQualityView extends Component
{
    public SentList $sentList;

    // Quality weighing modal
    public bool $showWeighingModal = false;
    public ?int $weighingLotId = null;
    public int $goodPieces = 0;
    public int $badPieces = 0;
    public string $weighingComments = '';
    public string $weighingAt = '';
    public ?int $editingId = null;

    // Computed modal data
    public int $productionGoodPieces = 0;
    public int $alreadyWeighed = 0;
    public int $remainingPieces = 0;
    public array $weighingsList = [];

    // Send to packaging modal
    public bool $showSendModal = false;
    public string $sendNotes = '';

    public function mount(SentList $sentList): void
    {
        $this->sentList   = $sentList;
        $this->weighingAt = now()->format('Y-m-d\TH:i');
    }

    public function openWeighingModal(int $lotId): void
    {
        $lot = Lot::with(['weighings', 'qualityWeighings.weighedBy'])->findOrFail($lotId);

        $this->weighingLotId = $lotId;
        $this->editingId     = null;

        // Production good pieces (lot-level, no kit)
        $prodGood = (int) $lot->weighings->whereNull('kit_id')->sum('good_pieces');
        $this->productionGoodPieces = $prodGood;

        // Already weighed in quality (lot-level, no kit)
        $lotQualWeighings = $lot->qualityWeighings->whereNull('kit_id');
        $qualAlready = (int) $lotQualWeighings->sum(fn($qw) => $qw->good_pieces + $qw->bad_pieces);
        $this->alreadyWeighed  = $qualAlready;
        $this->remainingPieces = max(0, $prodGood - $qualAlready);

        // Build list of existing quality weighings
        $this->weighingsList = $lotQualWeighings->map(fn($qw) => [
            'id'          => $qw->id,
            'good_pieces' => $qw->good_pieces,
            'bad_pieces'  => $qw->bad_pieces,
            'disposition' => $qw->disposition,
            'weighed_at'  => $qw->weighed_at->format('d/m/Y H:i'),
            'weighed_by'  => $qw->weighedBy->name ?? 'N/A',
            'comments'    => $qw->comments,
        ])->values()->toArray();

        $this->goodPieces       = 0;
        $this->badPieces        = 0;
        $this->weighingComments = '';
        $this->weighingAt       = now()->format('Y-m-d\TH:i');
        $this->showWeighingModal = true;
    }

    public function editQualityWeighing(int $lotId, int $id): void
    {
        $qw = QualityWeighing::find($id);
        if (!$qw) return;

        // Pesada a nivel viajero (CRIMP ya no usa kit).
        $this->openWeighingModal($lotId);

        $this->editingId        = $id;
        $this->goodPieces       = $qw->good_pieces;
        $this->badPieces        = $qw->bad_pieces;
        $this->weighingComments = $qw->comments ?? '';
        $this->weighingAt       = $qw->weighed_at->format('Y-m-d\TH:i');

        // Recalculate remaining adding back this record's pieces
        $this->remainingPieces += ($qw->good_pieces + $qw->bad_pieces);
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->goodPieces = 0;
        $this->badPieces = 0;
        $this->weighingComments = '';
        $this->weighingAt = now()->format('Y-m-d\TH:i');

        // Refresh modal to recalculate remaining
        if ($this->weighingLotId) {
            $this->openWeighingModal($this->weighingLotId);
        }
    }

    public function saveWeighing(): void
    {
        $this->validate([
            'goodPieces'       => 'required|integer|min:0',
            'badPieces'        => 'required|integer|min:0',
            'weighingAt'       => 'required|date',
            'weighingComments' => 'nullable|string|max:500',
        ], [
            'goodPieces.required' => 'Las piezas aprobadas son obligatorias.',
            'badPieces.required'  => 'Las piezas rechazadas son obligatorias.',
            'weighingAt.required' => 'La fecha/hora es obligatoria.',
        ]);

        $total = $this->goodPieces + $this->badPieces;

        if ($total > $this->remainingPieces) {
            $this->addError('goodPieces', 'La suma de piezas (' . number_format($total) . ') sobrepasa la cantidad pendiente (' . number_format($this->remainingPieces) . ').');
            return;
        }

        if ($total <= 0) {
            $this->addError('goodPieces', 'Debe registrar al menos 1 pieza.');
            return;
        }

        $lot = Lot::findOrFail($this->weighingLotId);

        $data = [
            'lot_id'                 => $this->weighingLotId,
            'production_good_pieces' => $this->productionGoodPieces,
            'good_pieces'            => $this->goodPieces,
            'bad_pieces'             => $this->badPieces,
            'disposition'            => $this->badPieces > 0 ? QualityWeighing::DISPOSITION_SCRAP : null,
            'rework_status'          => null,
            'weighed_at'             => $this->weighingAt,
            'weighed_by'             => Auth::id(),
            'comments'               => $this->weighingComments ?: null,
        ];

        if ($this->editingId) {
            $qw = QualityWeighing::find($this->editingId);
            if ($qw) {
                // Pesadas existentes conservan su kit_id (historial); no se reasigna.
                $qw->update($data);
                $message = 'Pesada de calidad actualizada.';
            } else {
                session()->flash('error', 'Pesada no encontrada.');
                return;
            }
        } else {
            // Pesada a nivel viajero: kit_id = null (CRIMP ya no usa kit).
            $data['kit_id'] = null;
            QualityWeighing::create($data);
            $message = 'Pesada de calidad registrada.';
        }

        if ($this->badPieces > 0) {
            $message .= ' ' . number_format($this->badPieces) . ' piezas descartadas.';
        }

        session()->flash('message', $message);

        // Refresh the modal data
        $this->openWeighingModal($this->weighingLotId);
    }

    public function closeWeighingModal(): void
    {
        $this->showWeighingModal = false;
        $this->weighingLotId     = null;
        $this->goodPieces        = 0;
        $this->badPieces         = 0;
        $this->weighingComments  = '';
        $this->editingId         = null;
        $this->productionGoodPieces = 0;
        $this->alreadyWeighed    = 0;
        $this->remainingPieces   = 0;
        $this->weighingsList     = [];
    }

    public function deleteWeighing(int $id): void
    {
        $qw = QualityWeighing::findOrFail($id);
        $qw->delete();

        // Refresh the modal if open (pesada a nivel viajero).
        if ($this->weighingLotId && $this->showWeighingModal) {
            $this->openWeighingModal($this->weighingLotId);
        }

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

    public function sendToPackaging(): void
    {
        if (!empty($this->sendNotes)) {
            $this->sentList->update([
                'notes' => trim(($this->sentList->notes ?? '') . "\n[Calidad " . now()->format('d/m/Y H:i') . '] ' . $this->sendNotes),
            ]);
        }

        $this->sentList->moveToNextDepartment(Auth::id());
        session()->flash('message', 'Enviado a Empaque correctamente.');
        $this->redirect(route('admin.sent-lists.show', $this->sentList));
    }

    public function render()
    {
        $this->sentList->load([
            'purchaseOrders.workOrder.purchaseOrder.part',
            'purchaseOrders.workOrder.lots.weighings',
            'purchaseOrders.workOrder.lots.qualityWeighings.weighedBy',
            'workOrders.purchaseOrder.part',
            'workOrders.lots.weighings',
            'workOrders.lots.qualityWeighings.weighedBy',
        ]);

        $workOrders = $this->sentList->getEffectiveWorkOrders();

        return view('livewire.admin.sent-lists.quality-view', compact('workOrders'));
    }
}
