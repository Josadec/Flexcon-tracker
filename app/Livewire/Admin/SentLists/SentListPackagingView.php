<?php

namespace App\Livewire\Admin\SentLists;

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
    public SentList $sentList;

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
    public int $decCrimpPacked = 0;
    public int $decCrimpSurplus = 0;
    public int $decCompletarCrimp = 0;

    // ── Create Lot form modal (from Decision) ────────────────────────────
    public bool $showCreateLotFormModal = false;
    public string $createLotName = '';
    public int $createLotQuantity = 0;
    public string $createLotType = ''; // 'complete' or 'new_lot'

    // ── CRIMP weighings (piezas + CRIMP) — captura manual, a nivel viajero ──
    public bool $showPieceWeighingModal = false;
    public ?int $pieceWeighingLotId = null;
    public int $pieceQty = 0;
    public ?float $pieceWeight = null;
    public string $pieceComments = '';
    public string $pieceWeighedAt = '';

    public bool $showCrimpWeighingModal = false;
    public ?int $crimpWeighingLotId = null;
    public ?int $crimpWeighingCrimpLotId = null;
    public int $crimpQty = 0;
    public ?float $crimpWeight = null;
    public string $crimpComments = '';
    public string $crimpWeighedAt = '';

    // ── Notify "Empaque terminado CRIMP" (correo M9) ─────────────────────
    public bool $showNotifyModal = false;
    public ?int $notifyLotId = null;
    public ?int $notifyLabelCount = null;
    public string $notifyComments = '';

    public function mount(SentList $sentList): void
    {
        $this->sentList = $sentList;
        $this->packedAt = now()->format('Y-m-d\TH:i');
    }

    // ── Packaging modal ──────────────────────────────────────────────────

    public function openPackagingModal(int $lotId): void
    {
        $lot = Lot::with('qualityWeighings')->findOrFail($lotId);

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
        $this->validate([
            'packedPieces'      => 'required|integer|min:0',
            'packedAt'          => 'required|date',
            'packagingComments' => 'nullable|string|max:500',
        ], [
            'packedPieces.required'  => 'Las piezas empacadas son obligatorias.',
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
        PackagingRecord::findOrFail($id)->delete();
        $this->sentList->refresh();
        session()->flash('message', 'Registro de empaque eliminado.');
    }

    public function receiveViajero(int $lotId): void
    {
        $lot = Lot::findOrFail($lotId);
        $lot->update([
            'viajero_received'    => true,
            'viajero_received_at' => now(),
            'viajero_received_by' => Auth::id(),
        ]);
        $this->sentList->refresh();
        session()->flash('message', "Viajero del lote {$lot->lot_number} confirmado.");
    }

    // ── Close list modal ─────────────────────────────────────────────────

    public function openCloseModal(): void
    {
        $this->showCloseModal = true;
    }

    public function closeList(): void
    {
        $this->sentList->update(['status' => SentList::STATUS_CONFIRMED]);
        session()->flash('message', 'Lista completada y cerrada exitosamente.');
        $this->redirect(route('admin.sent-lists.index'));
    }

    // ── Decision modal ───────────────────────────────────────────────────

    public function openDecisionModal(int $lotId): void
    {
        $lot = Lot::with(['workOrder.purchaseOrder.part', 'packagingRecords'])->find($lotId);

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

        // CRIMP (Paso 6, diagrama 4): CRIMP empacado/sobrante y la fórmula
        // "Completar CRIMP = piezas sobrantes − CRIMP sobrante".
        $this->decCrimpPacked    = $isCrimp ? $lot->getPackagedCrimpTotal() : 0;
        $this->decCrimpSurplus   = $isCrimp ? $lot->getPackagedCrimpSurplus() : 0;
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
        $this->decCrimpPacked         = 0;
        $this->decCrimpSurplus        = 0;
        $this->decCompletarCrimp      = 0;
        $this->resetErrorBag();
    }

    /**
     * Decision: Completar Lote — reset the SAME lot with missing pieces.
     * Saves a completion log, soft-deletes old records, resets pipeline.
     */
    public function decisionCompleteLot(): void
    {
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

    // ── CRIMP weighings: piezas ("manguitas") ────────────────────────────

    public function openPieceWeighingModal(int $lotId): void
    {
        $this->pieceWeighingLotId   = $lotId;
        $this->pieceQty             = 0;
        $this->pieceWeight          = null;
        $this->pieceComments        = '';
        $this->pieceWeighedAt       = now()->format('Y-m-d\TH:i');
        $this->showPieceWeighingModal = true;
    }

    public function savePieceWeighing(): void
    {
        $this->validate([
            'pieceQty'       => 'required|integer|min:1',
            'pieceWeight'    => 'nullable|numeric|min:0',
            'pieceWeighedAt' => 'required|date',
            'pieceComments'  => 'nullable|string|max:500',
        ], [
            'pieceQty.required' => 'La cantidad de piezas es obligatoria.',
            'pieceQty.min'      => 'La cantidad debe ser mayor a 0.',
        ]);

        PackagingPieceWeighing::create([
            'lot_id'     => $this->pieceWeighingLotId,
            'quantity'   => $this->pieceQty,
            'weight'     => $this->pieceWeight,
            'weighed_at' => $this->pieceWeighedAt,
            'weighed_by' => Auth::id(),
            'comments'   => $this->pieceComments ?: null,
        ]);

        $this->closePieceWeighingModal();
        $this->sentList->refresh();
        session()->flash('message', 'Pesada de piezas registrada.');
    }

    public function deletePieceWeighing(int $id): void
    {
        PackagingPieceWeighing::findOrFail($id)->delete();
        $this->sentList->refresh();
        session()->flash('message', 'Pesada de piezas eliminada.');
    }

    public function closePieceWeighingModal(): void
    {
        $this->showPieceWeighingModal = false;
        $this->pieceWeighingLotId     = null;
        $this->pieceQty               = 0;
        $this->pieceWeight            = null;
        $this->pieceComments          = '';
        $this->resetErrorBag();
    }

    // ── CRIMP weighings: CRIMP ───────────────────────────────────────────

    public function openCrimpWeighingModal(int $lotId): void
    {
        $this->crimpWeighingLotId   = $lotId;
        $this->crimpWeighingCrimpLotId = null;
        $this->crimpQty             = 0;
        $this->crimpWeight          = null;
        $this->crimpComments        = '';
        $this->crimpWeighedAt       = now()->format('Y-m-d\TH:i');
        $this->showCrimpWeighingModal = true;
    }

    public function saveCrimpWeighing(): void
    {
        $this->validate([
            'crimpWeighingCrimpLotId' => 'nullable|exists:crimp_lots,id',
            'crimpQty'       => 'required|integer|min:1',
            'crimpWeight'    => 'nullable|numeric|min:0',
            'crimpWeighedAt' => 'required|date',
            'crimpComments'  => 'nullable|string|max:500',
        ], [
            'crimpQty.required' => 'La cantidad de CRIMP es obligatoria.',
            'crimpQty.min'      => 'La cantidad debe ser mayor a 0.',
        ]);

        PackagingCrimpWeighing::create([
            'lot_id'       => $this->crimpWeighingLotId,
            'crimp_lot_id' => $this->crimpWeighingCrimpLotId ?: null,
            'quantity'     => $this->crimpQty,
            'weight'       => $this->crimpWeight,
            'weighed_at'   => $this->crimpWeighedAt,
            'weighed_by' => Auth::id(),
            'comments'   => $this->crimpComments ?: null,
        ]);

        $this->closeCrimpWeighingModal();
        $this->sentList->refresh();
        session()->flash('message', 'Pesada de CRIMP registrada.');
    }

    public function deleteCrimpWeighing(int $id): void
    {
        PackagingCrimpWeighing::findOrFail($id)->delete();
        $this->sentList->refresh();
        session()->flash('message', 'Pesada de CRIMP eliminada.');
    }

    public function closeCrimpWeighingModal(): void
    {
        $this->showCrimpWeighingModal = false;
        $this->crimpWeighingLotId     = null;
        $this->crimpWeighingCrimpLotId = null;
        $this->crimpQty               = 0;
        $this->crimpWeight            = null;
        $this->crimpComments          = '';
        $this->resetErrorBag();
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
        $this->validate([
            'notifyLabelCount' => 'nullable|integer|min:0',
            'notifyComments'   => 'nullable|string|max:1000',
        ], [
            'notifyLabelCount.integer' => 'El número de etiquetas debe ser un entero.',
        ]);

        $lot = Lot::with('workOrder.purchaseOrder.part')->findOrFail($this->notifyLotId);

        // Historial de notificación + No. de etiquetas (decisiones B.5).
        $lot->update([
            'packaging_label_count' => $this->notifyLabelCount,
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
                labelCount: $this->notifyLabelCount,
                extraComments: $this->notifyComments ?: null,
                packerName: Auth::user()?->name,
            ));
        }

        $this->closeNotifyModal();
        $this->sentList->refresh();
        session()->flash('message', empty($recipients)
            ? 'Empaque confirmado. No hay destinatarios configurados para el correo.'
            : 'Empaque confirmado y correo enviado a '.count($recipients).' destinatario(s).');
    }

    public function closeNotifyModal(): void
    {
        $this->showNotifyModal  = false;
        $this->notifyLotId      = null;
        $this->notifyLabelCount = null;
        $this->notifyComments   = '';
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
