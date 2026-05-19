<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\SentList;
use Illuminate\Support\Collection;

class CarryoverService
{
    /**
     * Returns POs with active carryover: approved, with prior SentList,
     * WO has sent_pieces < purchase_order.quantity, not in a pending SentList already.
     *
     * @param array $excludePoIds PO IDs already present in the wizard
     */
    public function getCarryoverCandidates(array $excludePoIds = []): Collection
    {
        return PurchaseOrder::with(['workOrder', 'part', 'sentLists'])
            ->withCarryover()
            ->whereNotIn('id', $excludePoIds)
            ->whereDoesntHave('sentLists', function ($q) {
                $q->where('status', SentList::STATUS_PENDING);
            })
            ->get();
    }

    /**
     * Returns the pending quantity for a PO based on its WO.
     * Falls back to the PO quantity if no WO exists.
     */
    public function getPendingQuantityForPO(PurchaseOrder $po): int
    {
        return $po->workOrder
            ? $po->workOrder->pending_quantity
            : $po->quantity;
    }

    /**
     * Returns the most recent SentList this PO was part of.
     */
    public function getLastSentListForPO(PurchaseOrder $po): ?SentList
    {
        return $po->sentLists()->orderByDesc('created_at')->first();
    }

    /**
     * Builds the pivot data array for a carryover attachment.
     */
    public function buildCarryoverPivotData(
        PurchaseOrder $po,
        int $quantity,
        float $requiredHours,
        ?string $lotNumber = null
    ): array {
        $lastSentList = $this->getLastSentListForPO($po);

        return [
            'quantity'                      => $quantity,
            'required_hours'                => $requiredHours,
            'lot_number'                    => $lotNumber,
            'is_carryover'                  => true,
            'carryover_from_sent_list_id'   => $lastSentList?->id,
            'pending_quantity_at_carryover' => $po->workOrder?->pending_quantity ?? $po->quantity,
        ];
    }

    /**
     * Builds the pivot data array for a standard (non-carryover) attachment.
     */
    public function buildStandardPivotData(
        int $quantity,
        float $requiredHours,
        ?string $lotNumber = null
    ): array {
        return [
            'quantity'                      => $quantity,
            'required_hours'                => $requiredHours,
            'lot_number'                    => $lotNumber,
            'is_carryover'                  => false,
            'carryover_from_sent_list_id'   => null,
            'pending_quantity_at_carryover' => null,
        ];
    }
}
