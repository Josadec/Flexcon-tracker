<?php

namespace App\Services;

use App\Models\Price;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use App\Models\WOStatusLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Validate the price of a purchase order against the registered price.
     *
     * @return array{valid: bool, expected_price: float|null, message: string}
     */
    public function validatePrice(PurchaseOrder $purchaseOrder): array
    {
        // Use POPriceDetectionService to get the correct price based on workstation type
        $priceDetectionService = app(POPriceDetectionService::class);
        $detection = $priceDetectionService->detectPrice($purchaseOrder);

        if (! $detection->found) {
            return [
                'valid' => false,
                'expected_price' => null,
                'message' => $detection->error ?? 'No se pudo detectar el precio correcto.',
            ];
        }

        // Get the expected price based on quantity tier
        $expectedPrice = $detection->price->getPriceForQuantity($purchaseOrder->quantity);

        if ($expectedPrice === null) {
            return [
                'valid' => false,
                'expected_price' => null,
                'message' => 'No se pudo determinar el precio para la cantidad especificada.',
            ];
        }

        // Compare prices (using tolerance for decimal precision)
        $poPrice = (float) $purchaseOrder->unit_price;
        $tolerance = 0.0001; // Allow small floating point differences

        if (abs($poPrice - $expectedPrice) <= $tolerance) {
            return [
                'valid' => true,
                'expected_price' => $expectedPrice,
                'message' => 'El precio es válido.',
            ];
        }

        $typeLabel = Price::WORKSTATION_TYPES[$detection->workstationType] ?? $detection->workstationType;

        return [
            'valid' => false,
            'expected_price' => $expectedPrice,
            'message' => sprintf(
                'El precio no coincide. Precio en PO: $%.4f, Precio esperado: $%.4f (Tipo de estación: %s)',
                $poPrice,
                $expectedPrice,
                $typeLabel
            ),
        ];
    }

    /**
     * Mark a purchase order as pending price correction.
     */
    public function markAsPendingCorrection(PurchaseOrder $purchaseOrder, string $reason): PurchaseOrder
    {
        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_PENDING_CORRECTION,
            'comments' => $reason,
        ]);

        return $purchaseOrder;
    }

    /**
     * Approve a purchase order after price validation.
     *
     * @return array{success: bool, message: string, purchase_order: PurchaseOrder}
     */
    public function approve(PurchaseOrder $purchaseOrder): array
    {
        // First validate the price
        $validation = $this->validatePrice($purchaseOrder);

        if (! $validation['valid']) {
            // Mark as pending correction
            $this->markAsPendingCorrection($purchaseOrder, $validation['message']);

            return [
                'success' => false,
                'message' => $validation['message'],
                'purchase_order' => $purchaseOrder->fresh(),
            ];
        }

        // Price is valid, approve the PO
        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);

        return [
            'success' => true,
            'message' => 'Orden de compra aprobada correctamente.',
            'purchase_order' => $purchaseOrder->fresh(),
        ];
    }

    /**
     * Reject a purchase order.
     */
    public function reject(PurchaseOrder $purchaseOrder, ?string $reason = null): PurchaseOrder
    {
        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_REJECTED,
            'comments' => $reason ?? $purchaseOrder->comments,
        ]);

        return $purchaseOrder;
    }

    /**
     * Get the expected price for a purchase order based on quantity.
     */
    public function getExpectedPrice(int $partId, int $quantity): ?float
    {
        $priceDetectionService = app(POPriceDetectionService::class);
        $detection = $priceDetectionService->detectPriceForPart($partId, $quantity);

        if (! $detection->found) {
            return null;
        }

        return $detection->price->getPriceForQuantity($quantity);
    }

    /**
     * Create a Work Order from an approved Purchase Order.
     *
     * @return array{success: bool, message: string, work_order: WorkOrder|null}
     */
    public function createFromPO(PurchaseOrder $purchaseOrder): array
    {
        // Verify PO is approved
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_APPROVED) {
            return [
                'success' => false,
                'message' => 'Solo se pueden crear Work Orders de POs aprobadas.',
                'work_order' => null,
            ];
        }

        // Check if WO already exists
        if ($purchaseOrder->workOrder) {
            return [
                'success' => false,
                'message' => 'Ya existe una Work Order para esta PO.',
                'work_order' => $purchaseOrder->workOrder,
            ];
        }

        // Get the "Open" status
        $openStatus = StatusWO::where('name', 'Open')->first();

        if (! $openStatus) {
            return [
                'success' => false,
                'message' => 'No se encontró el estado "Open" para Work Orders.',
                'work_order' => null,
            ];
        }

        $workOrder = DB::transaction(function () use ($purchaseOrder, $openStatus) {
            return $this->createWorkOrderRecord($purchaseOrder, $openStatus);
        });

        return [
            'success' => true,
            'message' => 'Work Order creada correctamente.',
            'work_order' => $workOrder,
        ];
    }

    /**
     * Create the WorkOrder record plus its initial status log.
     *
     * Retries on wo_number collisions: generateWONumber() is a read-then-insert
     * sequence, so two concurrent callers could pick the same number. Must run
     * inside a DB transaction.
     */
    private function createWorkOrderRecord(PurchaseOrder $purchaseOrder, StatusWO $openStatus): WorkOrder
    {
        $attempts = 0;

        while (true) {
            try {
                $workOrder = WorkOrder::create([
                    'wo_number' => WorkOrder::generateWONumber(),
                    'purchase_order_id' => $purchaseOrder->id,
                    'status_id' => $openStatus->id,
                    'sent_pieces' => 0,
                    'scheduled_send_date' => $purchaseOrder->due_date,
                    'opened_date' => Carbon::now(),
                ]);

                WOStatusLog::create([
                    'work_order_id' => $workOrder->id,
                    'from_status_id' => null,
                    'to_status_id' => $openStatus->id,
                    'user_id' => Auth::id(),
                    'comments' => 'Work Order creada desde PO '.$purchaseOrder->po_number,
                ]);

                return $workOrder;
            } catch (UniqueConstraintViolationException $e) {
                // Another caller took the same wo_number — regenerate and retry.
                if (++$attempts >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Approve a PO and atomically create its Work Order.
     *
     * The status change and the WO creation run inside a single transaction:
     * if anything fails, the PO keeps its original status (no "approved
     * without WorkOrder" limbo state).
     *
     * @return array{success: bool, message: string, purchase_order: PurchaseOrder, work_order: WorkOrder|null}
     */
    public function approveAndCreateWO(PurchaseOrder $purchaseOrder): array
    {
        // Guard: only pending / pending-correction POs may be approved.
        // Prevents re-approving a rejected or already-approved PO.
        if (! in_array($purchaseOrder->status, [
            PurchaseOrder::STATUS_PENDING,
            PurchaseOrder::STATUS_PENDING_CORRECTION,
        ], true)) {
            return [
                'success' => false,
                'message' => 'Solo se pueden aprobar órdenes de compra pendientes o en corrección de precio.',
                'purchase_order' => $purchaseOrder->fresh(),
                'work_order' => null,
            ];
        }

        // Validate the price before writing anything
        $validation = $this->validatePrice($purchaseOrder);

        if (! $validation['valid']) {
            $this->markAsPendingCorrection($purchaseOrder, $validation['message']);

            return [
                'success' => false,
                'message' => $validation['message'],
                'purchase_order' => $purchaseOrder->fresh(),
                'work_order' => null,
            ];
        }

        // The "Open" status must exist before opening the transaction
        $openStatus = StatusWO::where('name', 'Open')->first();

        if (! $openStatus) {
            return [
                'success' => false,
                'message' => 'No se encontró el estado "Open" para Work Orders. La PO no fue aprobada.',
                'purchase_order' => $purchaseOrder->fresh(),
                'work_order' => null,
            ];
        }

        try {
            $workOrder = DB::transaction(function () use ($purchaseOrder, $openStatus) {
                // Approve the PO
                $purchaseOrder->update(['status' => PurchaseOrder::STATUS_APPROVED]);

                // Idempotency: do not create a second WO if one already exists
                $existing = $purchaseOrder->workOrder()->first();
                if ($existing) {
                    return $existing;
                }

                return $this->createWorkOrderRecord($purchaseOrder, $openStatus);
            });
        } catch (\Throwable $e) {
            // Full rollback — the PO keeps its original status
            return [
                'success' => false,
                'message' => 'No se pudo completar la aprobación: '.$e->getMessage(),
                'purchase_order' => $purchaseOrder->fresh(),
                'work_order' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'PO aprobada y Work Order creada correctamente.',
            'purchase_order' => $purchaseOrder->fresh(),
            'work_order' => $workOrder,
        ];
    }

    /**
     * Update Work Order status with logging.
     */
    public function updateWorkOrderStatus(WorkOrder $workOrder, int $newStatusId, ?string $comments = null): WorkOrder
    {
        $oldStatusId = $workOrder->status_id;

        // Update the status
        $workOrder->update([
            'status_id' => $newStatusId,
        ]);

        // Log the change
        WOStatusLog::create([
            'work_order_id' => $workOrder->id,
            'from_status_id' => $oldStatusId,
            'to_status_id' => $newStatusId,
            'user_id' => Auth::id(),
            'comments' => $comments,
        ]);

        return $workOrder->fresh();
    }
}
