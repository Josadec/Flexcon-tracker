<?php

namespace App\Livewire\Admin\Lots;

use App\Models\Lot;
use App\Services\ReopeningService;
use Livewire\Component;

class LotShow extends Component
{
    public Lot $lot;

    // Modal para cambiar estado
    public bool $showStatusModal = false;
    public string $newStatus = '';

    // Modal para reabrir un viajero ya terminado.
    public bool $showReopenModal = false;
    public string $reopenReason = '';

    /** Qué más hay que deshacer para reabrir: packing slip, factura. */
    public array $reopenCascade = [];

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
     * ¿Puede el usuario actual reabrir viajeros cerrados?
     *
     * Se pregunta al servicio y no al rol: el permiso es la fuente única, y así
     * la ficha no se queda desincronizada si mañana cambia quién lo tiene.
     */
    public function puedeReabrir(): bool
    {
        return app(ReopeningService::class)->allows(auth()->user());
    }

    /**
     * Abre la confirmación de reapertura desde la propia ficha del viajero.
     *
     * Hasta ahora este botón sólo existía dentro del modal de decisión del
     * tablero. El problema: al marcar un viajero como completado, su orden se
     * quedaba sin viajeros abiertos y sin piezas pendientes, así que salía del
     * tablero — y con ella el único botón que permitía deshacerlo. Quien se
     * equivocaba se quedaba sin salida y había que reabrir por consola.
     *
     * La cascada se calcula ANTES de tocar nada: quien decide tiene que ver que
     * reabrir este viajero puede implicar reabrir su packing slip y su factura.
     */
    public function openReopenModal(): void
    {
        if (! $this->puedeReabrir()) {
            session()->flash('error', 'Sólo Administración puede reabrir un documento cerrado.');

            return;
        }

        if ($motivo = $this->lot->getReopenBlockReason()) {
            session()->flash('error', $motivo);

            return;
        }

        $this->reopenReason = '';
        $this->reopenCascade = app(ReopeningService::class)
            ->cascadeFor($this->lot->loadMissing('packingSlipItem.packingSlip.invoice'));
        $this->showReopenModal = true;
    }

    public function closeReopenModal(): void
    {
        $this->showReopenModal = false;
        $this->reopenReason = '';
        $this->reopenCascade = [];
    }

    /**
     * Reabre el viajero. Toda la lógica —permiso, motivo, cascada y auditoría—
     * vive en el servicio: aquí sólo se traduce el fallo a un mensaje.
     */
    public function reopenLot(): void
    {
        try {
            app(ReopeningService::class)->reopenLot($this->lot, $this->reopenReason);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->closeReopenModal();
        $this->lot->refresh();

        session()->flash('message', 'Viajero '.$this->lot->lot_number
            .' reabierto. Ya vuelve a aparecer en la lista de envío.');
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
