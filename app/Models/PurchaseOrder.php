<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'wo',
        'part_id',
        'workstation_type',
        'po_date',
        'due_date',
        'quantity',
        'unit_price',
        'status',
        'comments',
        'pdf_path',
        'signed_document_path',
    ];

    protected $casts = [
        'po_date' => 'date',
        'due_date' => 'date',
        'quantity' => 'integer',
        'unit_price' => 'decimal:4',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PENDING_CORRECTION = 'pending_correction';

    /**
     * Normaliza el numero de WO al guardar: elimina espacios en los extremos.
     *
     * Evita que un valor sucio (p.ej. " 2040057" con espacio inicial) se propague
     * al codigo "W0..." que se arma con getEffectiveWoNumber() en la cola de envio
     * y en los Packing Slips (FPL-10). Null-safe: null permanece null.
     */
    protected function wo(): Attribute
    {
        return Attribute::set(fn ($value) => $value === null ? null : trim($value));
    }

    /**
     * Get the part that owns the purchase order.
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * Get the work order associated with this purchase order.
     */
    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    /**
     * Get the sent lists that include this purchase order (many-to-many).
     */
    public function sentLists(): BelongsToMany
    {
        return $this->belongsToMany(SentList::class, 'sent_list_purchase_orders')
            ->withPivot([
                'quantity',
                'required_hours',
                'lot_number',
                'is_carryover',
                'carryover_from_sent_list_id',
                'pending_quantity_at_carryover',
            ])
            ->withTimestamps();
    }

    /**
     * Get the signatures for this purchase order.
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentSignature::class);
    }

    /**
     * Check if the purchase order has been signed.
     */
    public function isSigned(): bool
    {
        return $this->signatures()->exists();
    }

    /**
     * Scope a query to only include pending purchase orders.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include approved purchase orders.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope a query to only include rejected purchase orders.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope a query to only include purchase orders pending price correction.
     */
    public function scopePendingCorrection(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_CORRECTION);
    }

    /**
     * Scope: POs with active carryover — approved, previously in a SentList,
     * and WO still has pending pieces (sent_pieces < purchase_orders.quantity).
     */
    public function scopeWithCarryover(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->whereHas('sentLists')
            ->whereHas('workOrder', function ($q) {
                $q->whereColumn('sent_pieces', '<', 'purchase_orders.quantity');
            });
    }

    /**
     * Returns pending pieces according to the linked WO.
     * Falls back to full PO quantity when no WO exists.
     */
    public function getPendingQuantityAttribute(): int
    {
        return $this->workOrder
            ? $this->workOrder->pending_quantity
            : $this->quantity;
    }

    /**
     * True when the PO has an incomplete WO with at least one piece already shipped.
     */
    public function hasActiveCarryover(): bool
    {
        return $this->workOrder !== null
            && ! $this->workOrder->isComplete()
            && $this->workOrder->sent_pieces > 0;
    }

    /**
     * Scope a query to search purchase orders.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('po_number', 'like', "%{$search}%")
                ->orWhere('wo', 'like', "%{$search}%")
                ->orWhereHas('part', function ($partQuery) use ($search) {
                    $partQuery->where('number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status) || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Check if the purchase order can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the purchase order has a work order.
     */
    public function hasWorkOrder(): bool
    {
        return $this->workOrder()->exists();
    }

    /**
     * Returns the reason this PO cannot be deleted, or null when deletion is safe.
     *
     * A PO is locked once it has entered production: included in an active
     * SentList, or its WorkOrder already has shipped pieces, lots or kits.
     * Deleting in that state would force-delete production records permanently.
     */
    public function getDeletionBlockReason(): ?string
    {
        $inActiveList = $this->sentLists()
            ->whereIn('status', [SentList::STATUS_PENDING, SentList::STATUS_CONFIRMED])
            ->exists();

        if ($inActiveList) {
            return 'está incluida en una lista de envío activa.';
        }

        $workOrder = $this->workOrder;

        if ($workOrder) {
            if ($workOrder->sent_pieces > 0) {
                return "su Work Order ya tiene {$workOrder->sent_pieces} pieza(s) enviada(s).";
            }

            if ($workOrder->lots()->exists()) {
                return 'su Work Order tiene lotes en producción.';
            }

            if ($workOrder->kits()->exists()) {
                return 'su Work Order tiene kits asociados.';
            }
        }

        return null;
    }

    /**
     * Check if this purchase order can be deleted.
     * Blocks deletion once the PO has entered the production flow.
     */
    public function canBeDeleted(): bool
    {
        return $this->getDeletionBlockReason() === null;
    }

    /**
     * Force delete this purchase order and all related records.
     */
    public function forceDeleteWithRelations(): bool
    {
        // Force delete the related work order first — include soft-deleted rows,
        // otherwise the work_orders.purchase_order_id FK (onDelete restrict)
        // would block this PO's deletion forever.
        $workOrder = $this->workOrder()->withTrashed()->first();
        if ($workOrder) {
            $workOrder->forceDeleteWithRelations();
        }

        // Delete signatures
        $this->signatures()->forceDelete();

        // Detach from sent lists
        $this->sentLists()->detach();

        // Finally force delete the purchase order
        return $this->forceDelete();
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_REJECTED => 'Rechazada',
            self::STATUS_PENDING_CORRECTION => 'Corrección de Precio',
        ];
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            self::STATUS_PENDING_CORRECTION => 'orange',
            default => 'gray',
        };
    }
}
