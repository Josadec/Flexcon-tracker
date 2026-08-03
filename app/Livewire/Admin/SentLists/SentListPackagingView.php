<?php

namespace App\Livewire\Admin\SentLists;

use App\Livewire\Concerns\GuardsSentListDepartment;
use App\Mail\EmpaqueTerminadoCrimpViajero;
use App\Models\Lot;
use App\Models\LotCompletionLog;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PackagingRecord;
use App\Models\QualityWeighing;
use App\Models\SentList;
use App\Models\User;
use App\Models\Weighing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class SentListPackagingView extends Component
{
    use GuardsSentListDepartment;

    public SentList $sentList;

    protected function guardedDepartment(): string
    {
        return SentList::DEPT_SHIPPING;
    }

    // Packaging modal
    public bool $showPackagingModal = false;
    public ?int $packagingLotId = null;
    public int $packedPieces = 0;
    public int $surplusPieces = 0;
    public string $packagingComments = '';
    public string $packedAt = '';
    public int $modalAvailable = 0;

    // Close list modal
    public bool $showCloseModal = false;

    // ── Decision modal (Control de Materiales) ──────────────────────────
    public bool $showDecisionModal = false;
    public $selectedLotForDecision = null;
    public int $decLotTotal = 0;
    public int $decPacked = 0;
    public int $decSurplus = 0;
    public int $decMissing = 0;
    public bool $decIsCrimp = false;
    public $decClosureDecision = null;
    public bool $decSurplusDelivered = false;
    public bool $decSurplusReceived = false;
    // CRIMP (Paso 6): cifras para las decisiones D2a-c.
    public int $decCrimpTotal = 0;
    public int $decCrimpPacked = 0;
    public int $decCrimpSurplus = 0;
    public int $decCrimpMissing = 0;
    public int $decCompletarCrimp = 0;

    // ── Create Lot form modal (from Decision) ────────────────────────────
    public bool $showCreateLotFormModal = false;
    public string $createLotName = '';
    public int $createLotQuantity = 0;
    public string $createLotType = ''; // 'complete' or 'new_lot'

    // ── Notify "Empaque terminado CRIMP" (correo M9) ─────────────────────
    public bool $showNotifyModal = false;
    public ?int $notifyLotId = null;
    public ?int $notifyLabelCount = null;
    public string $notifyComments = '';

    // ── Paso 5: Modal de Confirmación de Empaque (CRIMP) ─────────────────
    // Un solo modal sobre la pantalla de Empaque (diagrama 3 / wireframe):
    // 1) selecciona lote de CRIMP, 2) captura pesadas (piezas + CRIMP),
    // 3) confirma cantidades → genera "Empaque Terminado" (sin PDF).
    public bool $showConfirmModal = false;
    public ?int $confirmLotId = null;          // viajero (Lot)
    public ?int $confirmCrimpLotId = null;     // lote de CRIMP seleccionado
    public bool $confirmDone = false;          // confirmado → muestra Empaque Terminado
    public int $cPieceQty = 0;                 // alta inline de pesada de piezas
    public ?float $cPieceWeight = null;        // (deprecado) ya no se captura kg en Paso 5
    public int $cCrimpQty = 0;                 // alta inline de pesada de CRIMP
    public ?float $cCrimpWeight = null;        // (deprecado) ya no se captura kg en Paso 5
    public ?int $confirmLabelCount = null;     // No. etiquetas (opcional, B.5)
    public string $confirmComments = '';
    // Edición inline de pesadas ya registradas (corregir cantidad sin borrar)
    public ?int $editPieceWId = null;
    public int $editPieceWQty = 0;
    public ?int $editCrimpWId = null;
    public int $editCrimpWQty = 0;

    public function mount(SentList $sentList): void
    {
        $this->sentList = $sentList;
        $this->packedAt = now()->format('Y-m-d\TH:i');
    }

    // ── Packaging modal ──────────────────────────────────────────────────

    public function openPackagingModal(int $lotId): void
    {
        $lot = Lot::with('qualityWeighings')->whereIn('id', $this->sentListLotIds())->findOrFail($lotId);

        $this->packagingLotId      = $lotId;
        $this->packedPieces        = 0;
        $this->surplusPieces       = 0;
        $this->packagingComments   = '';
        $this->packedAt            = now()->format('Y-m-d\TH:i');
        $this->modalAvailable      = (int) $lot->qualityWeighings->sum('good_pieces');
        $this->showPackagingModal  = true;
    }

    public function savePackaging(): void
    {
        $this->ensureCanEditDepartment();

        // Anti-IDOR: el lote debe pertenecer a esta lista.
        abort_unless($this->sentListLotIds()->contains($this->packagingLotId), 403);

        $this->validate([
            'packedPieces'      => 'required|integer|min:1',
            'packedAt'          => 'required|date',
            'packagingComments' => 'nullable|string|max:500',
        ], [
            'packedPieces.required'  => 'Las piezas empacadas son obligatorias.',
            'packedPieces.min'       => 'Debes registrar al menos 1 pieza empacada.',
            'packedAt.required'      => 'La fecha/hora es obligatoria.',
        ]);

        $surplus = max(0, (int) $this->surplusPieces);

        PackagingRecord::create([
            'lot_id'           => $this->packagingLotId,
            'available_pieces' => $this->modalAvailable,
            'packed_pieces'    => $this->packedPieces,
            'surplus_pieces'   => $surplus,
            'comments'         => $this->packagingComments ?: null,
            'packed_at'        => $this->packedAt,
            'packed_by'        => Auth::id(),
        ]);

        $message = 'Empaque registrado correctamente.';
        if ($surplus > 0) {
            $message .= ' ' . number_format($surplus) . ' piezas sobrantes.';
        }

        $this->showPackagingModal = false;
        $this->packagingLotId    = null;
        $this->packedPieces      = 0;
        $this->surplusPieces     = 0;
        $this->packagingComments = '';
        $this->modalAvailable    = 0;
        $this->sentList->refresh();
        session()->flash('message', $message);
    }

    public function closePackagingModal(): void
    {
        $this->showPackagingModal = false;
        $this->packagingLotId    = null;
        $this->packedPieces      = 0;
        $this->surplusPieces     = 0;
        $this->packagingComments = '';
        $this->modalAvailable    = 0;
    }

    public function deletePackaging(int $id): void
    {
        $this->ensureCanEditDepartment();

        PackagingRecord::whereIn('lot_id', $this->sentListLotIds())->findOrFail($id)->delete();
        $this->sentList->refresh();
        session()->flash('message', 'Registro de empaque eliminado.');
    }

    public function receiveViajero(int $lotId): void
    {
        $this->ensureCanEditDepartment();

        $lot = Lot::whereIn('id', $this->sentListLotIds())->findOrFail($lotId);
        $lot->update([
            'viajero_received'    => true,
            'viajero_received_at' => now(),
            'viajero_received_by' => Auth::id(),
        ]);
        $this->sentList->refresh();
        session()->flash('message', "Viajero del lote {$lot->lot_number} confirmado.");
    }

    // ── Modal Entrega de Viajero (Paso 7) — CRIMP ────────────────────────
    public bool $showViajeroModal = false;
    public ?int $viajeroModalLotId = null;

    public function openViajeroModal(int $lotId): void
    {
        $this->viajeroModalLotId = $lotId;
        $this->showViajeroModal = true;
    }

    public function closeViajeroModal(): void
    {
        $this->showViajeroModal = false;
        $this->viajeroModalLotId = null;
    }

    public function markViajeroReceived(int $lotId): void
    {
        $this->ensureCanEditDepartment();

        $lot = Lot::whereIn('id', $this->sentListLotIds())->findOrFail($lotId);
        $lot->update([
            'viajero_received'    => true,
            'viajero_received_at' => now(),
            'viajero_received_by' => Auth::id(),
        ]);
        $this->closeViajeroModal();
        $this->sentList->refresh();
        session()->flash('message', "Viajero {$lot->lot_number} recibido. Continúa al Paso 8 (sobrantes).");
    }

    public function revertViajeroReceived(int $lotId): void
    {
        $this->ensureCanEditDepartment();

        $lot = Lot::whereIn('id', $this->sentListLotIds())->findOrFail($lotId);
        $lot->update([
            'viajero_received'    => false,
            'viajero_received_at' => null,
            'viajero_received_by' => null,
        ]);
        $this->closeViajeroModal();
        $this->sentList->refresh();
        session()->flash('message', "Entrega del viajero {$lot->lot_number} revertida (marcado como NO recibido).");
    }

    /**
     * Paso 8 · recepción del material sobrante, desde el renglón del lote.
     *
     * El botón «Recibí material» de la lista llamaba a este método desde hace
     * tiempo, pero el método no existía: la pantalla tronaba con
     * MethodNotFoundException. Hace lo mismo que confirmSurplusReceived(), que
     * sólo funciona dentro del modal de decisión porque depende de
     * $selectedLotForDecision; aquí el lote llega por id.
     */
    public function markSurplusReceived(int $lotId): void
    {
        $this->ensureCanEditDepartment();

        // Acotado a los lotes de ESTA lista: un id ajeno no debe poder tocarse.
        $lot = Lot::whereIn('id', $this->sentListLotIds())->findOrFail($lotId);

        if ($lot->isSurplusReceived()) {
            session()->flash('error', "El sobrante del lote {$lot->lot_number} ya estaba recibido.");

            return;
        }

        $lot->update([
            'surplus_received'    => true,
            'surplus_received_at' => now(),
            'surplus_received_by' => Auth::id(),
            'status'              => Lot::STATUS_COMPLETED,
            'packaging_status'    => 'approved',
        ]);

        $this->sentList->refresh();
        session()->flash('message', "Material sobrante del lote {$lot->lot_number} recibido. Lote completado.");
    }

    // ── Close list modal ─────────────────────────────────────────────────

    public function openCloseModal(): void
    {
        $this->showCloseModal = true;
    }

    public function closeList(): void
    {
        $this->ensureCanEditDepartment();

        $this->sentList->update(['status' => SentList::STATUS_CONFIRMED]);
        session()->flash('message', 'Lista completada y cerrada exitosamente.');
        $this->redirect(route('admin.sent-lists.index'));
    }

    // ── Decision modal ───────────────────────────────────────────────────

    public function openDecisionModal(int $lotId): void
    {
        $lot = Lot::with(['workOrder.purchaseOrder.part', 'packagingRecords'])
            ->whereIn('id', $this->sentListLotIds())
            ->find($lotId);

        if (!$lot) {
            session()->flash('error', 'Lote no encontrado.');
            return;
        }

        $isCrimp = (bool) ($lot->workOrder->purchaseOrder->part->is_crimp ?? false);

        $this->selectedLotForDecision = $lot;
        $this->decLotTotal            = $lot->quantity;
        // En CRIMP las "empacadas/sobrante" se calculan desde las pesadas de piezas;
        // en NO-CRIMP, desde los registros de empaque (PackagingRecord).
        $this->decPacked              = $isCrimp ? $lot->getPackagedPiecesTotal() : $lot->getPackagingPackedPieces();
        $this->decSurplus             = $isCrimp ? $lot->getPackagedPiecesSurplus() : $lot->getPackagingTotalSurplus();
        $this->decMissing             = max(0, $this->decLotTotal - $this->decPacked - $this->decSurplus);
        $this->decIsCrimp             = $isCrimp;
        $this->decClosureDecision     = $lot->closure_decision;
        $this->decSurplusDelivered    = (bool) $lot->surplus_delivered;
        $this->decSurplusReceived     = (bool) $lot->surplus_received;

        // CRIMP (Paso 6, diagrama 4): CRIMP objetivo/empacado/sobrante/faltante y la
        // fórmula "Completar CRIMP = piezas sobrantes − CRIMP sobrante".
        $this->decCrimpTotal     = $isCrimp ? $lot->getCrimpTargetTotal() : 0;
        $this->decCrimpPacked    = $isCrimp ? $lot->getPackagedCrimpTotal() : 0;
        $this->decCrimpSurplus   = $isCrimp ? $lot->getPackagedCrimpSurplus() : 0;
        $this->decCrimpMissing   = $isCrimp ? max(0, $this->decCrimpTotal - $this->decCrimpPacked - $this->decCrimpSurplus) : 0;
        $this->decCompletarCrimp = $isCrimp ? max(0, $this->decSurplus - $this->decCrimpSurplus) : 0;

        $this->showDecisionModal      = true;
    }

    public function closeDecisionModal(): void
    {
        $this->showDecisionModal      = false;
        $this->selectedLotForDecision = null;
        $this->decLotTotal            = 0;
        $this->decPacked              = 0;
        $this->decSurplus             = 0;
        $this->decMissing             = 0;
        $this->decIsCrimp             = false;
        $this->decClosureDecision     = null;
        $this->decSurplusReceived     = false;
        $this->decCrimpTotal          = 0;
        $this->decCrimpPacked         = 0;
        $this->decCrimpSurplus        = 0;
        $this->decCrimpMissing        = 0;
        $this->decCompletarCrimp      = 0;
        $this->resetErrorBag();
    }

    /**
     * Decision: Completar Lote — reset the SAME lot with missing pieces.
     * Saves a completion log, soft-deletes old records, resets pipeline.
     */
    public function decisionCompleteLot(): void
    {
        $this->ensureCanEditDepartment();

        if (!$this->selectedLotForDecision) return;

        $lot     = $this->selectedLotForDecision;
        $missing = $this->decMissing;

        if ($missing <= 0) {
            session()->flash('error', 'No hay piezas faltantes para completar.');
            return;
        }

        $newCycle = ($lot->completion_count ?? 0) + 1;

        // 1. Save completion log
        LotCompletionLog::create([
            'lot_id'                 => $lot->id,
            'cycle_number'           => $newCycle,
            'original_quantity'      => $lot->quantity,
            'packed_pieces'          => $this->decPacked,
            'surplus_pieces'         => $this->decSurplus,
            'missing_pieces'         => $missing,
            'production_good_pieces' => $lot->getProductionGoodPieces(),
            'quality_good_pieces'    => $lot->getQualityGoodPieces(),
            'completed_by'           => Auth::id(),
            'completed_at'           => now(),
        ]);

        // 2. Soft-delete old records so the lot starts a fresh cycle
        Weighing::where('lot_id', $lot->id)->delete();
        QualityWeighing::where('lot_id', $lot->id)->delete();
        PackagingRecord::where('lot_id', $lot->id)->delete();

        // 3. Reset lot with missing quantity and fresh statuses
        $lot->update([
            'quantity'               => $missing,
            'completion_count'       => $newCycle,
            'closure_decision'       => null,
            'closure_decided_by'     => null,
            'closure_decided_at'     => null,
            'status'                 => Lot::STATUS_IN_PROGRESS,
            'material_status'        => 'pending',
            'inspection_status'      => Lot::INSPECTION_PENDING,
            'inspection_comments'    => null,
            'inspection_completed_at' => null,
            'inspection_completed_by' => null,
            'packaging_status'       => 'pending',
            'packaging_comments'     => null,
            'packaging_inspected_by' => null,
            'packaging_inspected_at' => null,
            'viajero_received'       => false,
            'viajero_received_at'    => null,
            'viajero_received_by'    => null,
            'surplus_received'       => false,
            'surplus_received_at'    => null,
            'surplus_received_by'    => null,
            'surplus_delivered'       => false,
            'surplus_delivered_at'    => null,
            'surplus_delivered_by'    => null,
        ]);

        // Nota CRIMP: los lotes de CRIMP cuelgan del viajero y se gestionan en Materiales;
        // ya no se reinician kits aquí (el flujo CRIMP dejó de usar Kit).

        session()->flash('message', 'Lote completado (Completado ' . $newCycle . '). Se reinició con ' . number_format($missing) . ' piezas faltantes para reprocesar.');
        $this->closeDecisionModal();
        $this->sentList->refresh();
    }

    /**
     * Decision: Nuevo Lote — close current lot, create new lot with missing pieces,
     * and send SentList back to Materiales.
     */
    public function decisionNewLot(): void
    {
        $this->ensureCanEditDepartment();

        if (!$this->selectedLotForDecision) return;

        $this->createLotType = 'new_lot';
        if ($this->decIsCrimp) {
            // D3 (diagrama 4): Nuevo lote = sobrante de piezas (manguitas),
            // redondeado HACIA ABAJO a múltiplos de 100.
            $this->createLotQuantity = intdiv(max(0, $this->decSurplus), 100) * 100;
        } else {
            $this->createLotQuantity = max(0, $this->decLotTotal - $this->decPacked);
        }
        $this->createLotName = Lot::generateNextLotNumber($this->selectedLotForDecision->work_order_id);
        $this->showCreateLotFormModal = true;
    }

    /**
     * Registra una decisión de "Completar" del Paso 6 (CRIMP) y muestra el resumen.
     * No reinicia el lote: el viajero continúa al Paso 7 (entrega) y los faltantes
     * los surte Materiales según las cantidades registradas (diagrama 4).
     */
    private function recordCrimpCompletion(string $decision, ?int $crimpQty, ?int $piecesQty, string $message): void
    {
        $this->ensureCanEditDepartment();

        $lot = $this->selectedLotForDecision;

        $lot->update([
            'closure_decision'    => $decision,
            'closure_decided_by'  => Auth::id(),
            'closure_decided_at'  => now(),
            'complete_crimp_qty'  => $crimpQty,
            'complete_pieces_qty' => $piecesQty,
        ]);

        session()->flash('message', $message);
        $this->openDecisionModal($lot->id);
        $this->sentList->refresh();
    }

    /** D2a — Solo completar CRIMP. */
    public function decisionCompleteCrimp(): void
    {
        if (!$this->selectedLotForDecision) return;

        $this->recordCrimpCompletion(
            Lot::CLOSURE_COMPLETE_CRIMP,
            $this->decCompletarCrimp,
            null,
            'Decisión D2a — Completar CRIMP: '.number_format($this->decCompletarCrimp).' CRIMP por completar (piezas sobrantes − CRIMP sobrante).'
        );
    }

    /** D2b — Solo completar piezas (manguitas). */
    public function decisionCompletePieces(): void
    {
        if (!$this->selectedLotForDecision) return;

        $this->recordCrimpCompletion(
            Lot::CLOSURE_COMPLETE_PIECES,
            null,
            $this->decSurplus,
            'Decisión D2b — Completar piezas: '.number_format($this->decSurplus).' piezas por completar. Materiales enviará la cantidad a Empaque.'
        );
    }

    /** D2c — Completar piezas y CRIMP. */
    public function decisionCompleteBoth(): void
    {
        if (!$this->selectedLotForDecision) return;

        $this->recordCrimpCompletion(
            Lot::CLOSURE_COMPLETE_BOTH,
            $this->decCompletarCrimp,
            $this->decSurplus,
            'Decisión D2c — Completar piezas y CRIMP: '.number_format($this->decSurplus).' piezas y '.number_format($this->decCompletarCrimp).' CRIMP por completar.'
        );
    }

    /**
     * Decision: Cerrar Lote aceptando faltantes.
     */
    public function decisionCloseAsIs(): void
    {
        $this->ensureCanEditDepartment();

        if (!$this->selectedLotForDecision) {
            session()->flash('error', 'Lote no encontrado.');
            return;
        }

        $lot     = $this->selectedLotForDecision;
        $missing = $this->decMissing;

        $lot->update([
            'closure_decision'   => Lot::CLOSURE_CLOSE_AS_IS,
            'closure_decided_by' => Auth::id(),
            'closure_decided_at' => now(),
            'status'             => Lot::STATUS_COMPLETED,
            'packaging_status'   => 'approved',
        ]);

        session()->flash('message', $missing > 0
            ? "Lote cerrado aceptando " . number_format($missing) . " piezas faltantes."
            : 'Lote cerrado sin faltantes.');

        $this->openDecisionModal($lot->id);
        $this->sentList->refresh();
    }

    /**
     * Reopen a lot: clear its closure decision and reset status.
     */
    public function reopenLot(): void
    {
        $this->ensureCanEditDepartment();

        if (!$this->selectedLotForDecision) {
            session()->flash('error', 'Lote no encontrado.');
            return;
        }

        $lot = $this->selectedLotForDecision;

        $lot->update([
            'closure_decision'     => null,
            'closure_decided_by'   => null,
            'closure_decided_at'   => null,
            'surplus_delivered'    => false,
            'surplus_delivered_at' => null,
            'surplus_delivered_by' => null,
            'surplus_received'     => false,
            'surplus_received_at'  => null,
            'surplus_received_by'  => null,
            'status'               => Lot::STATUS_IN_PROGRESS,
            'packaging_status'     => 'pending',
        ]);

        session()->flash('message', 'Lote ' . $lot->lot_number . ' reabierto exitosamente.');
        $this->openDecisionModal($lot->id);
        $this->sentList->refresh();
    }

    /**
     * Confirm surplus material received.
     */
    public function confirmSurplusReceived(): void
    {
        $this->ensureCanEditDepartment();

        if (!$this->selectedLotForDecision) {
            session()->flash('error', 'Lote no encontrado.');
            return;
        }

        $lot = $this->selectedLotForDecision;

        $lot->update([
            'surplus_received'    => true,
            'surplus_received_at' => now(),
            'surplus_received_by' => Auth::id(),
            'status'              => Lot::STATUS_COMPLETED,
            'packaging_status'    => 'approved',
        ]);

        session()->flash('message', 'Material sobrante recibido. Lote completado.');
        $this->openDecisionModal($lot->id);
        $this->sentList->refresh();
    }

    // ── Create Lot modal ─────────────────────────────────────────────────

    public function closeCreateLotFormModal(): void
    {
        $this->showCreateLotFormModal = false;
        $this->createLotName          = '';
        $this->createLotQuantity      = 0;
        $this->createLotType          = '';
        $this->resetErrorBag();
    }

    /**
     * Confirm creation of the new lot (and kit if crimp).
     * If type is 'new_lot', also sends the SentList back to Materiales.
     */
    public function confirmCreateLot(): void
    {
        $this->ensureCanEditDepartment();

        $this->validate([
            'createLotName'     => 'required|string|max:255',
            'createLotQuantity' => 'required|integer|min:1',
        ], [
            'createLotName.required'     => 'El nombre del lote es requerido.',
            'createLotQuantity.required' => 'La cantidad es requerida.',
            'createLotQuantity.min'      => 'La cantidad debe ser mayor a 0.',
        ]);

        if (!$this->selectedLotForDecision) {
            session()->flash('error', 'Lote no encontrado.');
            return;
        }

        $lot    = $this->selectedLotForDecision;
        $part   = $lot->workOrder->purchaseOrder->part;

        // Create new lot (viajero). Para CRIMP, los lotes de CRIMP se capturan luego en
        // Materiales (cuelgan del viajero); ya no se crea un Kit automático.
        Lot::create([
            'work_order_id' => $lot->work_order_id,
            'lot_number'    => $this->createLotName,
            'quantity'      => $this->createLotQuantity,
            'description'   => $part->description,
            'status'        => Lot::STATUS_PENDING,
        ]);

        $message = "Lote #{$this->createLotName} creado con " . number_format($this->createLotQuantity) . " piezas.";

        if ($this->createLotType === 'complete') {
            // Completar Lote: reset viajero so flow continues on original lot
            $lot->update([
                'viajero_received'    => false,
                'viajero_received_at' => null,
                'viajero_received_by' => null,
                'closure_decision'    => null,
                'closure_decided_by'  => null,
                'closure_decided_at'  => null,
            ]);
        } elseif ($this->createLotType === 'new_lot') {
            // Nuevo Lote: close current lot
            $lot->update([
                'closure_decision'   => Lot::CLOSURE_NEW_LOT,
                'closure_decided_by' => Auth::id(),
                'closure_decided_at' => now(),
                'status'             => Lot::STATUS_COMPLETED,
                'packaging_status'   => 'approved',
            ]);

            $surplus = $lot->getPackagingTotalSurplus();
            if ($surplus > 0) {
                $message .= " Sobrantes ({$surplus} pz) pendientes de devolución.";
            }

            // Send SentList back to Materiales
            $this->sentList->update([
                'current_department'    => SentList::DEPT_MATERIALS,
                'materials_approved_at' => null,
                'materials_approved_by' => null,
                'inspection_approved_at' => null,
                'inspection_approved_by' => null,
                'production_approved_at' => null,
                'production_approved_by' => null,
                'quality_approved_at'   => null,
                'quality_approved_by'   => null,
            ]);

            $message .= ' La lista regresó a Materiales para el nuevo lote.';
        }

        session()->flash('message', $message);
        $this->closeCreateLotFormModal();
        $this->openDecisionModal($lot->id);
        $this->sentList->refresh();
    }

    // ── CRIMP weighings: borrado desde el historial (captura en modal Paso 5) ──

    public function deletePieceWeighing(int $id): void
    {
        $this->ensureCanEditDepartment();

        PackagingPieceWeighing::whereIn('lot_id', $this->sentListLotIds())->findOrFail($id)->delete();
        $this->sentList->refresh();
        session()->flash('message', 'Pesada de piezas eliminada.');
    }

    public function deleteCrimpWeighing(int $id): void
    {
        $this->ensureCanEditDepartment();

        PackagingCrimpWeighing::whereIn('lot_id', $this->sentListLotIds())->findOrFail($id)->delete();
        $this->sentList->refresh();
        session()->flash('message', 'Pesada de CRIMP eliminada.');
    }

    // ── Notify "Empaque terminado CRIMP" (correo M9) ─────────────────────

    public function openNotifyModal(int $lotId): void
    {
        $this->notifyLotId      = $lotId;
        $this->notifyLabelCount = null;
        $this->notifyComments   = '';
        $this->showNotifyModal  = true;
    }

    public function confirmAndNotify(): void
    {
        $this->ensureCanEditDepartment();

        $this->validate([
            'notifyLabelCount' => 'nullable|integer|min:0',
            'notifyComments'   => 'nullable|string|max:1000',
        ], [
            'notifyLabelCount.integer' => 'El número de etiquetas debe ser un entero.',
        ]);

        $count = $this->dispatchEmpaqueTerminado($this->notifyLotId, $this->notifyLabelCount, $this->notifyComments);

        $this->closeNotifyModal();
        $this->sentList->refresh();
        session()->flash('message', $count === 0
            ? 'Empaque confirmado. No hay destinatarios configurados para el correo.'
            : 'Empaque confirmado y correo enviado a '.$count.' destinatario(s).');
    }

    /**
     * Genera el resumen "Empaque Terminado", guarda el historial de notificación
     * (No. etiquetas, B.5) y envía el correo a Empaques + Materiales + la empacadora.
     * Destinatarios: roles Empaques/Materiales + usuario actual. Devuelve cuántos.
     */
    private function dispatchEmpaqueTerminado(int $lotId, ?int $labelCount, ?string $comments): int
    {
        $lot = Lot::with('workOrder.purchaseOrder.part')->whereIn('id', $this->sentListLotIds())->findOrFail($lotId);

        // Historial de notificación + No. de etiquetas (decisiones B.5).
        $lot->update([
            'packaging_label_count' => $labelCount,
            'packaging_notified_at' => now(),
            'packaging_notified_by' => Auth::id(),
        ]);

        // Destinatarios: usuarios con rol Empaques o Materiales + la empacadora actual.
        $roleNames  = Role::whereIn('name', ['Empaques', 'Materiales'])->pluck('name')->all();
        $recipients = collect();
        if (! empty($roleNames)) {
            $recipients = User::role($roleNames)->pluck('email');
        }
        $recipients = $recipients
            ->push(Auth::user()?->email)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($recipients)) {
            Mail::to($recipients)->send(new EmpaqueTerminadoCrimpViajero(
                viajero: $lot,
                labelCount: $labelCount,
                extraComments: $comments ?: null,
                packerName: Auth::user()?->name,
            ));
        }

        return count($recipients);
    }

    public function closeNotifyModal(): void
    {
        $this->showNotifyModal  = false;
        $this->notifyLotId      = null;
        $this->notifyLabelCount = null;
        $this->notifyComments   = '';
        $this->resetErrorBag();
    }

    // ── Paso 5: Modal de Confirmación de Empaque (CRIMP) ─────────────────

    public function openConfirmModal(int $lotId): void
    {
        $lot = Lot::with('crimpLots')->whereIn('id', $this->sentListLotIds())->find($lotId);
        if (!$lot) {
            session()->flash('error', 'El viajero ya no existe. Actualiza la página.');
            return;
        }

        $this->confirmLotId       = $lotId;
        $this->confirmCrimpLotId  = $lot->crimpLots->first()?->id;
        $this->confirmDone        = false;
        $this->cPieceQty          = 0;
        $this->cPieceWeight       = null;
        $this->cCrimpQty          = 0;
        $this->cCrimpWeight       = null;
        $this->editPieceWId       = null;
        $this->editPieceWQty      = 0;
        $this->editCrimpWId       = null;
        $this->editCrimpWQty      = 0;
        $this->confirmLabelCount  = $lot->packaging_label_count;
        $this->confirmComments    = '';
        $this->resetErrorBag();
        $this->showConfirmModal   = true;
    }

    /** Paso 5 · captura — agrega una pesada de piezas ("manguitas") al lote de CRIMP. */
    public function addConfirmPieceWeighing(): void
    {
        $this->ensureCanEditDepartment();
        abort_unless($this->sentListLotIds()->contains($this->confirmLotId), 403);

        $this->validate([
            'confirmCrimpLotId' => 'required|exists:crimp_lots,id',
            'cPieceQty'         => 'required|integer|min:1',
        ], [
            'confirmCrimpLotId.required' => 'Selecciona primero el lote de CRIMP.',
            'cPieceQty.required'         => 'La cantidad de piezas es obligatoria.',
            'cPieceQty.min'              => 'La cantidad debe ser mayor a 0.',
        ]);

        PackagingPieceWeighing::create([
            'lot_id'       => $this->confirmLotId,
            'crimp_lot_id' => $this->confirmCrimpLotId,
            'quantity'     => $this->cPieceQty,
            'weight'       => null,
            'weighed_at'   => now(),
            'weighed_by'   => Auth::id(),
        ]);

        $this->cPieceQty    = 0;
        $this->cPieceWeight = null;
        $this->confirmDone  = false; // cambió la captura → re-confirmar
        $this->sentList->refresh();
    }

    /** Paso 5 · captura — agrega una pesada de CRIMP al lote de CRIMP. */
    public function addConfirmCrimpWeighing(): void
    {
        $this->ensureCanEditDepartment();
        abort_unless($this->sentListLotIds()->contains($this->confirmLotId), 403);

        $this->validate([
            'confirmCrimpLotId' => 'required|exists:crimp_lots,id',
            'cCrimpQty'         => 'required|integer|min:1',
        ], [
            'confirmCrimpLotId.required' => 'Selecciona primero el lote de CRIMP.',
            'cCrimpQty.required'         => 'La cantidad de CRIMP es obligatoria.',
            'cCrimpQty.min'              => 'La cantidad debe ser mayor a 0.',
        ]);

        PackagingCrimpWeighing::create([
            'lot_id'       => $this->confirmLotId,
            'crimp_lot_id' => $this->confirmCrimpLotId,
            'quantity'     => $this->cCrimpQty,
            'weight'       => null,
            'weighed_at'   => now(),
            'weighed_by'   => Auth::id(),
        ]);

        $this->cCrimpQty    = 0;
        $this->cCrimpWeight = null;
        $this->confirmDone  = false;
        $this->sentList->refresh();
    }

    public function deleteConfirmPieceWeighing(int $id): void
    {
        $this->ensureCanEditDepartment();

        PackagingPieceWeighing::whereIn('lot_id', $this->sentListLotIds())->find($id)?->delete();
        $this->confirmDone = false;
        $this->sentList->refresh();
    }

    public function deleteConfirmCrimpWeighing(int $id): void
    {
        $this->ensureCanEditDepartment();

        PackagingCrimpWeighing::whereIn('lot_id', $this->sentListLotIds())->find($id)?->delete();
        $this->confirmDone = false;
        $this->sentList->refresh();
    }

    // ── Edición inline de pesadas ya registradas (corregir cantidad) ──
    public function editConfirmPieceWeighing(int $id): void
    {
        $w = PackagingPieceWeighing::whereIn('lot_id', $this->sentListLotIds())->find($id);
        if (!$w) { $this->sentList->refresh(); return; }
        $this->editPieceWId  = $w->id;
        $this->editPieceWQty = $w->quantity;
        $this->resetErrorBag('editPieceWQty');
    }

    public function saveConfirmPieceWeighing(): void
    {
        $this->ensureCanEditDepartment();

        $this->validate(
            ['editPieceWQty' => 'required|integer|min:1'],
            ['editPieceWQty.required' => 'La cantidad es obligatoria.', 'editPieceWQty.min' => 'La cantidad debe ser mayor a 0.']
        );
        $w = PackagingPieceWeighing::whereIn('lot_id', $this->sentListLotIds())->find($this->editPieceWId);
        if ($w) { $w->update(['quantity' => $this->editPieceWQty]); }
        $this->editPieceWId  = null;
        $this->editPieceWQty = 0;
        $this->confirmDone   = false;
        $this->sentList->refresh();
    }

    public function cancelEditPieceWeighing(): void
    {
        $this->editPieceWId  = null;
        $this->editPieceWQty = 0;
        $this->resetErrorBag('editPieceWQty');
    }

    public function editConfirmCrimpWeighing(int $id): void
    {
        $w = PackagingCrimpWeighing::whereIn('lot_id', $this->sentListLotIds())->find($id);
        if (!$w) { $this->sentList->refresh(); return; }
        $this->editCrimpWId  = $w->id;
        $this->editCrimpWQty = $w->quantity;
        $this->resetErrorBag('editCrimpWQty');
    }

    public function saveConfirmCrimpWeighing(): void
    {
        $this->ensureCanEditDepartment();

        $this->validate(
            ['editCrimpWQty' => 'required|integer|min:1'],
            ['editCrimpWQty.required' => 'La cantidad es obligatoria.', 'editCrimpWQty.min' => 'La cantidad debe ser mayor a 0.']
        );
        $w = PackagingCrimpWeighing::whereIn('lot_id', $this->sentListLotIds())->find($this->editCrimpWId);
        if ($w) { $w->update(['quantity' => $this->editCrimpWQty]); }
        $this->editCrimpWId  = null;
        $this->editCrimpWQty = 0;
        $this->confirmDone   = false;
        $this->sentList->refresh();
    }

    public function cancelEditCrimpWeighing(): void
    {
        $this->editCrimpWId  = null;
        $this->editCrimpWQty = 0;
        $this->resetErrorBag('editCrimpWQty');
    }

    /** Paso 5 · paso 3 — el empacador confirma las cantidades → genera "Empaque Terminado". */
    public function confirmPackaging(): void
    {
        $this->ensureCanEditDepartment();

        if (! $this->confirmLotId) return;

        $this->confirmDone = true;
        session()->flash('message', 'Cantidades confirmadas. Se generó el resumen "Empaque Terminado".');
    }

    /** Paso 5 · genera el resumen y notifica a Empaque + Materiales (correo M9). */
    public function confirmAndNotifyFromModal(): void
    {
        $this->ensureCanEditDepartment();

        if (! $this->confirmLotId) return;

        $this->validate([
            'confirmLabelCount' => 'nullable|integer|min:0',
            'confirmComments'   => 'nullable|string|max:1000',
        ], [
            'confirmLabelCount.integer' => 'El número de etiquetas debe ser un entero.',
        ]);

        $count = $this->dispatchEmpaqueTerminado($this->confirmLotId, $this->confirmLabelCount, $this->confirmComments);

        $this->confirmDone = true;
        $this->sentList->refresh();
        session()->flash('message', $count === 0
            ? 'Empaque confirmado. No hay destinatarios configurados para el correo.'
            : 'Empaque confirmado y correo "Empaque Terminado" enviado a '.$count.' destinatario(s).');
    }

    /** Paso 5 → Paso 6: cierra confirmación y abre la toma de decisión del viajero. */
    public function goToDecisionFromConfirm(): void
    {
        $lotId = $this->confirmLotId;
        $this->closeConfirmModal();
        if ($lotId) {
            $this->openDecisionModal($lotId);
        }
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal  = false;
        $this->confirmLotId      = null;
        $this->confirmCrimpLotId = null;
        $this->confirmDone       = false;
        $this->cPieceQty         = 0;
        $this->cPieceWeight      = null;
        $this->cCrimpQty         = 0;
        $this->cCrimpWeight      = null;
        $this->editPieceWId      = null;
        $this->editPieceWQty     = 0;
        $this->editCrimpWId      = null;
        $this->editCrimpWQty     = 0;
        $this->confirmLabelCount = null;
        $this->confirmComments   = '';
        $this->resetErrorBag();
    }

    // ── Render ───────────────────────────────────────────────────────────

    public function render()
    {
        $this->sentList->load([
            'purchaseOrders.workOrder.purchaseOrder.part',
            'purchaseOrders.workOrder.lots.qualityWeighings',
            'purchaseOrders.workOrder.lots.packagingRecords.packedBy',
            'purchaseOrders.workOrder.lots.packagingPieceWeighings.weighedBy',
            'purchaseOrders.workOrder.lots.packagingCrimpWeighings.weighedBy',
            'purchaseOrders.workOrder.lots.packagingCrimpWeighings.crimpLot',
            'purchaseOrders.workOrder.lots.crimpLots',
            'purchaseOrders.workOrder.lots.viajeroReceivedByUser',
            'workOrders.purchaseOrder.part',
            'workOrders.lots.qualityWeighings',
            'workOrders.lots.packagingRecords.packedBy',
            'workOrders.lots.packagingPieceWeighings.weighedBy',
            'workOrders.lots.packagingCrimpWeighings.weighedBy',
            'workOrders.lots.packagingCrimpWeighings.crimpLot',
            'workOrders.lots.crimpLots',
            'workOrders.lots.viajeroReceivedByUser',
        ]);

        $workOrders = $this->sentList->getEffectiveWorkOrders();
        $allLots    = $workOrders->flatMap->lots;

        // Un lote "está empacado" si: NO-CRIMP → tiene PackagingRecord;
        // CRIMP → tiene al menos una pesada de piezas o de CRIMP.
        $allLotsHavePackaging = $allLots->isNotEmpty()
            && $workOrders->every(function ($wo) {
                $isCrimp = (bool) ($wo->purchaseOrder->part->is_crimp ?? false);

                return $wo->lots->every(function ($l) use ($isCrimp) {
                    return $isCrimp
                        ? ($l->packagingPieceWeighings->isNotEmpty() || $l->packagingCrimpWeighings->isNotEmpty())
                        : $l->packagingRecords->isNotEmpty();
                });
            });

        return view('livewire.admin.sent-lists.packaging-view', compact('workOrders', 'allLotsHavePackaging'));
    }
}
