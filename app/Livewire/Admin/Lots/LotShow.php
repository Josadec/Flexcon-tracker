<?php

namespace App\Livewire\Admin\Lots;

use App\Models\Lot;
use Livewire\Component;

class LotShow extends Component
{
    public Lot $lot;

    // Modal para cambiar estado
    public bool $showStatusModal = false;
    public string $newStatus = '';

    public function mount(Lot $lot): void
    {
        $this->lot = $lot->load(['workOrder.purchaseOrder.part', 'crimpLots']);
    }

    public function startLot(): void
    {
        if ($this->lot->canBeStarted()) {
            $this->lot->update(['status' => Lot::STATUS_IN_PROGRESS]);
            session()->flash('message', 'Lote iniciado.');
        }
    }

    public function completeLot(): void
    {
        if ($this->lot->canBeCompleted()) {
            $this->lot->update(['status' => Lot::STATUS_COMPLETED]);
            session()->flash('message', 'Lote completado. Las piezas enviadas de la WO han sido actualizadas.');
        }
    }

    public function cancelLot(): void
    {
        if ($this->lot->canBeCancelled()) {
            $this->lot->update(['status' => Lot::STATUS_CANCELLED]);
            session()->flash('message', 'Lote cancelado.');
        }
    }

    public function openStatusModal(): void
    {
        $this->newStatus = $this->lot->status;
        $this->showStatusModal = true;
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
        $this->newStatus = '';
    }

    public function setNewStatus(string $status): void
    {
        $this->newStatus = $status;
    }

    /**
     * ¿A qué estados puede moverse este viajero? Mismos guards que los botones:
     * el modal no puede ser una puerta trasera para saltarse el flujo.
     */
    public function allowedTransitions(): array
    {
        $permitidos = [$this->lot->status];

        if ($this->lot->canBeStarted()) {
            $permitidos[] = Lot::STATUS_IN_PROGRESS;
        }

        if ($this->lot->canBeCompleted()) {
            $permitidos[] = Lot::STATUS_COMPLETED;
        }

        if ($this->lot->canBeCancelled()) {
            $permitidos[] = Lot::STATUS_CANCELLED;
        }

        return array_values(array_unique($permitidos));
    }

    public function updateLotStatus(): void
    {
        if (!$this->newStatus) {
            return;
        }

        // Antes se escribía cualquier cadena que llegara del cliente.
        if (!in_array($this->newStatus, $this->allowedTransitions(), true)) {
            session()->flash('error', 'Ese cambio de estado no es válido para este viajero.');
            $this->closeStatusModal();

            return;
        }

        if ($this->newStatus === $this->lot->status) {
            $this->closeStatusModal();

            return;
        }

        $this->lot->update(['status' => $this->newStatus]);
        $this->lot->refresh();

        $etiquetas = Lot::getStatuses();
        session()->flash('message', 'Estado del viajero actualizado a: ' . ($etiquetas[$this->newStatus] ?? $this->newStatus) . '.');

        $this->closeStatusModal();
    }

    public function render()
    {
        return view('livewire.admin.lots.lot-show');
    }
}
