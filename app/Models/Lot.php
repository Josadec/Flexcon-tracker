<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'work_order_id',
        'lot_number',
        'description',
        'quantity',
        'quantity_packed_final',
        'ready_for_shipping',
        'ready_for_shipping_at',
        'closed_by_type',
        'status',
        'comments',
        'raw_material_batch_numbers',
        'supplier_id',
        'supplier_name',
        'receipt_date',
        'expiration_date',
        'inspection_status',
        'inspection_comments',
        'inspection_completed_at',
        'inspection_completed_by',
        'material_status',
        'packaging_status',
        'packaging_comments',
        'packaging_inspected_by',
        'packaging_inspected_at',
        'viajero_received',
        'viajero_received_at',
        'viajero_received_by',
        'closure_decision',
        'closure_decided_by',
        'closure_decided_at',
        'surplus_received',
        'surplus_received_at',
        'surplus_received_by',
        'surplus_delivered',
        'surplus_delivered_at',
        'surplus_delivered_by',
        'completion_count',
        'packaging_label_count',
        'packaging_notified_at',
        'packaging_notified_by',
        'complete_crimp_qty',
        'complete_pieces_qty',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_packed_final' => 'integer',
        'ready_for_shipping' => 'boolean',
        'ready_for_shipping_at' => 'datetime',
        'raw_material_batch_numbers' => 'array',
        'receipt_date' => 'date',
        'expiration_date' => 'date',
        'inspection_completed_at' => 'datetime',
        'packaging_inspected_at' => 'datetime',
        'viajero_received' => 'boolean',
        'viajero_received_at' => 'datetime',
        'closure_decided_at' => 'datetime',
        'surplus_received' => 'boolean',
        'surplus_received_at' => 'datetime',
        'surplus_delivered' => 'boolean',
        'surplus_delivered_at' => 'datetime',
        'completion_count' => 'integer',
        'packaging_label_count' => 'integer',
        'packaging_notified_at' => 'datetime',
        'complete_crimp_qty' => 'integer',
        'complete_pieces_qty' => 'integer',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Inspection Status constants
     */
    public const INSPECTION_PENDING = 'pending';

    public const INSPECTION_APPROVED = 'approved';

    public const INSPECTION_REJECTED = 'rejected';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate lot_number when creating a new lot
        static::creating(function ($lot) {
            if (empty($lot->lot_number) && $lot->work_order_id) {
                $lot->lot_number = self::generateLotNumber($lot->work_order_id);
            }
        });

        // When a lot is created with completed status, update the work order's sent_pieces
        static::created(function ($lot) {
            if ($lot->status === self::STATUS_COMPLETED) {
                $lot->workOrder?->updateSentPieces();
            }
        });

        // When a lot status changes, update the work order's sent_pieces
        static::updated(function ($lot) {
            if ($lot->isDirty('status')) {
                $lot->workOrder?->updateSentPieces();
            }
        });

        // When a lot is deleted, update the work order's sent_pieces
        static::deleted(function ($lot) {
            if ($lot->status === self::STATUS_COMPLETED) {
                $lot->workOrder?->updateSentPieces();
            }
        });

        // When a lot is restored, update the work order's sent_pieces
        static::restored(function ($lot) {
            if ($lot->status === self::STATUS_COMPLETED) {
                $lot->workOrder?->updateSentPieces();
            }
        });
    }

    /**
     * Get the work order that owns the lot.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the kits that were created from this lot.
     */
    public function kits(): BelongsToMany
    {
        return $this->belongsToMany(Kit::class, 'kit_lot')->withPivot('created_at');
    }

    /**
     * Get the CRIMP lots (lotes de CRIMP) for this lot (viajero).
     * Sustituye al Kit en el flujo de partes con CRIMP.
     */
    public function crimpLots(): HasMany
    {
        return $this->hasMany(CrimpLot::class);
    }

    /**
     * Indica si este lote es un "viajero" (su parte tiene CRIMP).
     */
    public function isViajero(): bool
    {
        return (bool) ($this->workOrder?->purchaseOrder?->part?->is_crimp ?? false);
    }

    /**
     * Get the weighings (pesadas) for this lot.
     */
    public function weighings(): HasMany
    {
        return $this->hasMany(Weighing::class);
    }

    /**
     * Get the quality weighings for this lot.
     */
    public function qualityWeighings(): HasMany
    {
        return $this->hasMany(QualityWeighing::class);
    }

    /**
     * Get the audit trail for this lot.
     */
    public function auditTrail(): MorphMany
    {
        return $this->morphMany(AuditTrail::class, 'auditable');
    }

    /**
     * Get the inspections for this lot.
     * NOTE: Inspection model not implemented yet
     */
    // public function inspections(): HasMany
    // {
    //     return $this->hasMany(Inspection::class);
    // }

    // =====================================================
    // SHIPPING READINESS SCOPES (Packing Slip / FPL-10)
    // =====================================================

    /**
     * Scope: lotes listos para incluirse en un Packing Slip.
     * Un lote esta listo cuando ready_for_shipping = true y no ha sido
     * asignado a ningun PS (no existe registro en packing_slip_items).
     */
    public function scopeReadyForShipping(Builder $query): Builder
    {
        return $query->where('ready_for_shipping', true)
            ->whereDoesntHave('packingSlipItem');
    }

    /**
     * Scope: lotes listos para shipping (incluyendo los ya asignados a un PS).
     * Util para reportes y auditorias.
     */
    public function scopeShippingReady(Builder $query): Builder
    {
        return $query->where('ready_for_shipping', true);
    }

    // =====================================================
    // RELACIONES DE PACKING SLIP
    // =====================================================

    /**
     * El item de Packing Slip donde fue incluido este lote (si aplica).
     * Un lote solo puede estar en un PS a la vez (constraint unique en BD).
     */
    public function packingSlipItem(): HasOne
    {
        return $this->hasOne(PackingSlipItem::class);
    }

    /**
     * El Packing Slip al que pertenece este lote (acceso directo via item).
     */
    public function packingSlip(): HasOneThrough
    {
        return $this->hasOneThrough(PackingSlip::class, PackingSlipItem::class, 'lot_id', 'id', 'id', 'packing_slip_id');
    }

    /**
     * Verifica si el lote ya ha sido asignado a un Packing Slip.
     */
    public function isInPackingSlip(): bool
    {
        return $this->packingSlipItem()->exists();
    }

    // =====================================================
    // SCOPES DE STATUS (existentes)
    // =====================================================

    /**
     * Scope a query to only include lots with a specific status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending lots.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include in progress lots.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope a query to only include completed lots.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to search lots.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('lot_number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('workOrder', function ($woQuery) use ($search) {
                    $woQuery->where('wo_number', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_IN_PROGRESS => 'En Progreso',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_CANCELLED => 'Cancelado',
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
            self::STATUS_IN_PROGRESS => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'red',
            default => 'gray',
        };
    }

    /**
     * Generate a sequential lot number for a work order.
     */
    public static function generateLotNumber(int $workOrderId): string
    {
        $count = self::withTrashed()
            ->where('work_order_id', $workOrderId)
            ->count() + 1;

        return sprintf('%03d', $count);
    }

    /**
     * Check if the lot can be started.
     */
    public function canBeStarted(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the lot can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if the lot can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Check if the lot can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Generate the next lot number for a given work order.
     * Handles zero-padded string format (e.g., '001', '002') and includes
     * soft-deleted records to avoid unique constraint violations.
     */
    public static function generateNextLotNumber(int $workOrderId): string
    {
        // Include soft-deleted lots to avoid unique constraint collisions
        $allLotNumbers = static::withTrashed()
            ->where('work_order_id', $workOrderId)
            ->pluck('lot_number')
            ->toArray();

        // El viajero/lote (Lot) usa SIEMPRE 2 dígitos (01, 02, …), tanto CRIMP como
        // no-CRIMP. (El "lote de CRIMP" —CrimpLot— sí usa 3 dígitos, aparte.)
        $padLength = 2;

        // Find highest numeric value among all lot numbers
        $maxNumeric = 0;
        foreach ($allLotNumbers as $ln) {
            $num = (int) $ln;
            if ($num > $maxNumeric) {
                $maxNumeric = $num;
            }
        }

        return str_pad((string) ($maxNumeric + 1), $padLength, '0', STR_PAD_LEFT);
    }

    /**
     * Get complete traceability data for this lot (viajero).
     * En CRIMP la trazabilidad baja del viajero a sus lotes de CRIMP.
     */
    public function getTraceabilityData(): array
    {
        $isCrimp = (bool) ($this->workOrder->purchaseOrder->part->is_crimp ?? false);

        return [
            'is_crimp' => $isCrimp,
            'viajero' => $this->lot_number,
            'lot_number' => $this->lot_number,
            'work_order' => $this->workOrder->wo_number ?? null,
            'raw_material_batch_numbers' => $this->raw_material_batch_numbers ?? [],
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier_name,
            'receipt_date' => $this->receipt_date?->format('Y-m-d'),
            'expiration_date' => $this->expiration_date?->format('Y-m-d'),
            'quantity' => $this->quantity,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'crimp_lots' => $this->crimpLots->map(fn ($cl) => [
                'crimp_lot_number' => $cl->crimp_lot_number,
                'lote_fabricante' => $cl->lote_fabricante,
                'quantity' => $cl->quantity,
            ])->toArray(),
        ];
    }

    /**
     * Check if the lot has expired.
     */
    public function isExpired(): bool
    {
        if (! $this->expiration_date) {
            return false;
        }

        return $this->expiration_date->isPast();
    }

    /**
     * Get all available inspection statuses.
     */
    public static function getInspectionStatuses(): array
    {
        return [
            self::INSPECTION_PENDING => 'Pendiente',
            self::INSPECTION_APPROVED => 'Aprobado',
            self::INSPECTION_REJECTED => 'No Aprobado',
        ];
    }

    /**
     * Get the inspection status label.
     */
    public function getInspectionStatusLabelAttribute(): string
    {
        return self::getInspectionStatuses()[$this->inspection_status] ?? $this->inspection_status;
    }

    /**
     * Get the inspection status color for UI display.
     */
    public function getInspectionStatusColorAttribute(): string
    {
        return match ($this->inspection_status) {
            self::INSPECTION_PENDING => 'yellow',
            self::INSPECTION_APPROVED => 'green',
            self::INSPECTION_REJECTED => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if the lot can be inspected.
     *
     * Para CRIMP y NO-CRIMP la liberación se evalúa a nivel viajero (material_status).
     * El Kit dejó de ser el gate del flujo CRIMP: Materiales libera el viajero
     * (lots.material_status = 'released'), no por estado de Kit (M1/M7 reajuste CRIMP).
     */
    public function canBeInspected(): bool
    {
        return ($this->material_status ?? 'pending') === 'released';
    }

    /**
     * Get the released kit associated with this lot (if any).
     */
    public function getReleasedKit(): ?Kit
    {
        return $this->kits()
            ->where('status', Kit::STATUS_RELEASED)
            ->first();
    }

    /**
     * Get the reason why inspection is blocked.
     */
    public function getInspectionBlockedReason(): ?string
    {
        if ($this->canBeInspected()) {
            return null;
        }

        // Crimp y no-crimp: el gate es la liberación de material a nivel viajero/lote.
        // El texto NO-CRIMP se mantiene idéntico al original; solo CRIMP dice "viajero".
        $isCrimp = (bool) ($this->workOrder->purchaseOrder->part->is_crimp ?? true);
        $matStatus = $this->material_status ?? 'pending';

        if ($isCrimp) {
            return match ($matStatus) {
                'pending' => 'El material de este viajero aun no ha sido aprobado. Materiales debe aprobarlo primero.',
                'rejected' => 'El material de este viajero fue rechazado. Materiales debe corregir y aprobarlo.',
                default => 'El material de este viajero no tiene un status valido para inspeccion.',
            };
        }

        return match ($matStatus) {
            'pending' => 'El material de este lote aun no ha sido aprobado. Materiales debe aprobar el material primero.',
            'rejected' => 'El material de este lote fue rechazado. Materiales debe corregir y aprobar el material.',
            default => 'El material de este lote no tiene un status valido para inspeccion.',
        };
    }

    /**
     * ¿Producción puede pesar este lote?
     *
     * El flujo es secuencial: Material → Inspección → Producción. Pesar antes
     * de que Calidad apruebe la inspección deja piezas producidas sobre un lote
     * que quizá se rechace, así que la etapa está cerrada hasta entonces.
     *
     * Esta compuerta es el equivalente de canBeInspected() para la etapa
     * siguiente; antes no existía y Producción podía adelantarse.
     */
    public function canBeProduced(): bool
    {
        return ($this->inspection_status ?? self::INSPECTION_PENDING) === self::INSPECTION_APPROVED;
    }

    /**
     * Por qué Producción no puede pesar todavía (null si sí puede).
     */
    public function getProductionBlockedReason(): ?string
    {
        if ($this->canBeProduced()) {
            return null;
        }

        $isCrimp = (bool) ($this->workOrder->purchaseOrder->part->is_crimp ?? false);
        $unidad  = $isCrimp ? 'viajero' : 'lote';

        // Si ni siquiera se liberó el material, ese es el bloqueo de fondo:
        // se reporta ese, que es el que hay que resolver primero.
        if (! $this->canBeInspected()) {
            return $this->getInspectionBlockedReason();
        }

        return match ($this->inspection_status ?? self::INSPECTION_PENDING) {
            self::INSPECTION_REJECTED => "La inspección de este {$unidad} fue rechazada. Calidad debe resolverla antes de producir.",
            default                   => "Calidad todavía no inspecciona este {$unidad}. Producción no puede pesar hasta que se apruebe.",
        };
    }

    /**
     * ¿Calidad puede verificar piezas de este lote?
     *
     * Sólo se verifica lo que Producción ya registró. La compuerta es
     * transitiva: si Producción ni siquiera podía trabajar, Calidad tampoco.
     */
    public function canBeQualityChecked(): bool
    {
        return $this->canBeProduced() && $this->hasProductionWeighings();
    }

    /**
     * Piezas que Calidad ya revisó (aprobadas + rechazadas).
     */
    public function getQualityVerifiedPieces(): int
    {
        return $this->getQualityGoodPieces() + $this->getQualityBadPieces();
    }

    /**
     * INVARIANTE del flujo: Calidad no puede haber verificado más piezas de
     * las que Producción registró.
     *
     * Cuando esto es falso, el lote arrastra datos incoherentes — típicamente
     * porque se borraron pesadas de producción que Calidad ya había consumido.
     * Sus cifras de empaque y decisión salen de ahí, así que no son confiables
     * y hay que avisarlo en pantalla en vez de esconderlo.
     */
    public function hasConsistentQualityData(): bool
    {
        return $this->getQualityVerifiedPieces() <= $this->getProductionTotalWeighed();
    }

    /**
     * Cuántas piezas verificó Calidad sin respaldo en Producción.
     */
    public function getOrphanQualityPieces(): int
    {
        return max(0, $this->getQualityVerifiedPieces() - $this->getProductionTotalWeighed());
    }

    /**
     * ¿Se puede borrar esta pesada de producción sin romper el invariante?
     *
     * Borrar piezas que Calidad ya verificó es justo lo que produce lotes
     * incoherentes, así que se impide.
     */
    public function canDeleteProductionWeighing(Weighing $weighing): bool
    {
        $remaining = $this->getProductionTotalWeighed()
            - ((int) $weighing->good_pieces + (int) $weighing->bad_pieces);

        return $remaining >= $this->getQualityVerifiedPieces();
    }

    /**
     * Por qué no se puede borrar esa pesada (null si sí se puede).
     */
    public function getProductionWeighingDeleteBlockReason(Weighing $weighing): ?string
    {
        if ($this->canDeleteProductionWeighing($weighing)) {
            return null;
        }

        return 'Calidad ya verificó ' . number_format($this->getQualityVerifiedPieces())
            . ' piezas de este lote. Borrar esta pesada dejaría menos producción que la ya verificada.'
            . ' Elimina primero las pesadas de calidad correspondientes.';
    }

    /**
     * ¿Este lote ya tiene actividad de empaque o posterior?
     *
     * Se usa para NO esconder avance real: un lote que ya se empacó, se
     * entregó o se decidió sigue mostrando su estado aunque la cadena hacia
     * atrás esté incompleta (p.ej. porque se borraron pesadas viejas).
     */
    public function hasPackagingActivity(): bool
    {
        return $this->getPackagingPackedPieces() > 0
            || $this->packagingPieceWeighings()->exists()
            || $this->packagingCrimpWeighings()->exists()
            || $this->hasClosureDecision()
            || $this->isViajeroReceived();
    }

    /**
     * ¿Empaque puede empacar este lote?
     *
     * Para EMPEZAR hace falta la cadena completa: inspección aprobada,
     * producción registrada y piezas aprobadas por Calidad. Si el lote ya
     * tiene actividad de empaque, se deja pasar para no bloquear un flujo en
     * curso ni ocultar lo ya hecho.
     */
    public function canBePackaged(): bool
    {
        if ($this->hasPackagingActivity()) {
            return true;
        }

        return $this->canBeQualityChecked() && $this->getQualityGoodPieces() > 0;
    }

    /**
     * Por qué Empaque no puede empacar todavía (null si sí puede).
     */
    public function getPackagingBlockedReason(): ?string
    {
        if ($this->canBePackaged()) {
            return null;
        }

        $isCrimp = (bool) ($this->workOrder->purchaseOrder->part->is_crimp ?? false);
        $unidad  = $isCrimp ? 'viajero' : 'lote';

        // Se reporta el primer eslabón roto de la cadena, que es el que hay
        // que resolver primero.
        if (! $this->canBeProduced()) {
            return $this->getProductionBlockedReason();
        }

        if (! $this->hasProductionWeighings()) {
            return "Producción todavía no registra piezas de este {$unidad}. No hay nada que empacar.";
        }

        return "Calidad todavía no aprueba piezas de este {$unidad}. Empaque no puede empacar hasta que las verifique.";
    }

    /**
     * Scope a query to only include lots with pending inspection.
     */
    public function scopeInspectionPending($query)
    {
        return $query->where('inspection_status', self::INSPECTION_PENDING);
    }

    /**
     * Scope a query to only include lots with approved inspection.
     */
    public function scopeInspectionApproved($query)
    {
        return $query->where('inspection_status', self::INSPECTION_APPROVED);
    }

    /**
     * Scope a query to only include lots with rejected inspection.
     */
    public function scopeInspectionRejected($query)
    {
        return $query->where('inspection_status', self::INSPECTION_REJECTED);
    }

    /**
     * Relationship with the user who completed the inspection.
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspection_completed_by');
    }

    /**
     * Check if inspection is pending.
     */
    public function isInspectionPending(): bool
    {
        return $this->inspection_status === self::INSPECTION_PENDING;
    }

    /**
     * Check if inspection is approved.
     */
    public function isInspectionApproved(): bool
    {
        return $this->inspection_status === self::INSPECTION_APPROVED;
    }

    /**
     * Check if inspection is rejected.
     */
    public function isInspectionRejected(): bool
    {
        return $this->inspection_status === self::INSPECTION_REJECTED;
    }

    /**
     * Check if lot can proceed to packing/shipping (must be inspection approved).
     */
    public function canProceedToShipping(): bool
    {
        return $this->isInspectionApproved() && $this->status === self::STATUS_COMPLETED;
    }

    // =====================================================
    // QUALITY WEIGHING HELPERS
    // =====================================================

    /**
     * Get total good pieces from production weighings for this lot.
     */
    public function getProductionGoodPieces(): int
    {
        return (int) $this->weighings()->sum('good_pieces');
    }

    /**
     * Get total bad pieces from production weighings for this lot.
     */
    public function getProductionBadPieces(): int
    {
        return (int) $this->weighings()->sum('bad_pieces');
    }

    /**
     * Get total pieces already weighed by production.
     */
    public function getProductionTotalWeighed(): int
    {
        return $this->getProductionGoodPieces() + $this->getProductionBadPieces();
    }

    /**
     * Get total pieces already verified by quality (good + bad).
     */
    public function getQualityAlreadyWeighed(): int
    {
        return (int) $this->qualityWeighings()
            ->selectRaw('COALESCE(SUM(good_pieces), 0) + COALESCE(SUM(bad_pieces), 0) as total')
            ->value('total');
    }

    /**
     * Get pieces pending quality verification.
     * = Production good pieces - Quality already weighed
     */
    public function getQualityPendingPieces(): int
    {
        return max(0, $this->getProductionGoodPieces() - $this->getQualityAlreadyWeighed());
    }

    /**
     * Get total quality approved pieces.
     */
    public function getQualityGoodPieces(): int
    {
        return (int) $this->qualityWeighings()->sum('good_pieces');
    }

    /**
     * Get total quality rejected pieces.
     */
    public function getQualityBadPieces(): int
    {
        return (int) $this->qualityWeighings()->sum('bad_pieces');
    }

    /**
     * Get pieces pending rework (deprecated - rework removed, rejected = discard).
     */
    public function getReworkPendingPieces(): int
    {
        return 0;
    }

    /**
     * Get the quality semaphore status.
     * gray = no production weighings
     * yellow = pending (production has weighings, quality hasn't verified all)
     * green = all production good pieces verified by quality
     */
    public function getQualitySemaphoreStatus(): string
    {
        $prodGood = $this->getProductionGoodPieces();

        if ($prodGood <= 0) {
            return 'gray';
        }

        $qualityWeighed = $this->getQualityAlreadyWeighed();

        if ($qualityWeighed <= 0) {
            return 'yellow';
        }

        if ($qualityWeighed >= $prodGood) {
            return 'green';
        }

        return 'yellow';
    }

    /**
     * Check if this lot has production weighings available for quality inspection.
     */
    public function hasProductionWeighings(): bool
    {
        return $this->weighings()->exists();
    }

    // =====================================================
    // PACKAGING HELPERS
    // =====================================================

    /**
     * Get the packaging records for this lot.
     */
    public function packagingRecords(): HasMany
    {
        return $this->hasMany(PackagingRecord::class);
    }

    /**
     * Get the completion logs for this lot.
     */
    public function completionLogs(): HasMany
    {
        return $this->hasMany(LotCompletionLog::class);
    }

    /**
     * Devuelve el desglose de piezas cerradas por ciclo de completado.
     *
     * Incluye los ciclos intermedios registrados en LotCompletionLog (cada vez
     * que se usó "Completar Lote") y, si el lote ya tiene una decisión de cierre
     * ("Cerrar Lote" o "Nuevo Lote"), el ciclo final con las piezas empacadas
     * actuales — que no quedan registradas en LotCompletionLog.
     *
     * @return array<int, array{cycle:int, pieces:int}>
     */
    public function getCompletionCycles(): array
    {
        $cycles = [];

        foreach ($this->completionLogs->sortBy('cycle_number') as $log) {
            $cycles[] = ['cycle' => (int) $log->cycle_number, 'pieces' => (int) $log->packed_pieces];
        }

        // Ciclo final: el lote llegó a una decisión de cierre y aún no está en LotCompletionLog.
        if (! is_null($this->closure_decision)) {
            $finalCycle = ($this->completion_count ?? 0) + 1;
            $alreadyLogged = $this->completionLogs->contains('cycle_number', $finalCycle);

            if (! $alreadyLogged) {
                $finalPacked = (int) $this->packagingRecords->sum('packed_pieces');
                if ($finalPacked > 0) {
                    $cycles[] = ['cycle' => $finalCycle, 'pieces' => $finalPacked];
                }
            }
        }

        return $cycles;
    }

    /**
     * Total de piezas cerradas sumando todos los ciclos de completado del lote.
     */
    public function getTotalCompletedPieces(): int
    {
        return array_sum(array_column($this->getCompletionCycles(), 'pieces'));
    }

    /**
     * Relationship: viajero received by user.
     */
    public function viajeroReceivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viajero_received_by');
    }

    /**
     * Relationship: closure decided by user.
     */
    public function closureDecidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closure_decided_by');
    }

    /**
     * Relationship: surplus received by user.
     */
    public function surplusReceivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surplus_received_by');
    }

    /**
     * Get pieces available for packaging (quality approved pieces).
     */
    public function getPackagingAvailablePieces(): int
    {
        return $this->getQualityGoodPieces();
    }

    /**
     * Get total packed pieces across all packaging records.
     */
    public function getPackagingPackedPieces(): int
    {
        return (int) $this->packagingRecords()->sum('packed_pieces');
    }

    /**
     * Get pieces pending packaging.
     * Pendientes = Calidad − Empacadas − Sobrantes declarados (ya separados en registros).
     */
    public function getPackagingPendingPieces(): int
    {
        return max(
            0,
            $this->getPackagingAvailablePieces()
                - $this->getPackagingPackedPieces()
                - $this->getPackagingTotalSurplus()
        );
    }

    /**
     * Get total surplus declared across packaging records (uses adjusted value when present).
     */
    public function getPackagingTotalSurplus(): int
    {
        $recordsSurplus = $this->packagingRecords->sum(function ($r) {
            return $r->adjusted_surplus !== null ? (int) $r->adjusted_surplus : (int) $r->surplus_pieces;
        });

        return max(0, (int) $recordsSurplus);
    }

    /**
     * Check if lot has any packaging records.
     */
    public function hasPackagingRecords(): bool
    {
        return $this->packagingRecords()->exists();
    }

    // =====================================================
    // CRIMP PACKAGING HELPERS (M6 — pesadas piezas + CRIMP)
    // =====================================================

    /**
     * Pesadas de PIEZAS ("manguitas") de Empaque (flujo CRIMP) para este viajero.
     */
    public function packagingPieceWeighings(): HasMany
    {
        return $this->hasMany(PackagingPieceWeighing::class);
    }

    /**
     * Pesadas de CRIMP de Empaque para este viajero.
     */
    public function packagingCrimpWeighings(): HasMany
    {
        return $this->hasMany(PackagingCrimpWeighing::class);
    }

    /**
     * Total de piezas empacadas (suma de pesadas de piezas) del viajero.
     */
    public function getPackagedPiecesTotal(): int
    {
        return (int) $this->packagingPieceWeighings()->sum('quantity');
    }

    /**
     * Total de CRIMP empacados (suma de pesadas de CRIMP) del viajero.
     */
    public function getPackagedCrimpTotal(): int
    {
        return (int) $this->packagingCrimpWeighings()->sum('quantity');
    }

    /**
     * Objetivo de CRIMP del viajero = suma de cantidades de sus lotes de CRIMP.
     */
    public function getCrimpTargetTotal(): int
    {
        return (int) $this->crimpLots()->sum('quantity');
    }

    /**
     * Sobrante de piezas del viajero = disponibles (Calidad) − empacadas.
     */
    public function getPackagedPiecesSurplus(): int
    {
        return max(0, $this->getPackagingAvailablePieces() - $this->getPackagedPiecesTotal());
    }

    /**
     * Sobrante de CRIMP del viajero = objetivo − empacados.
     */
    public function getPackagedCrimpSurplus(): int
    {
        return max(0, $this->getCrimpTargetTotal() - $this->getPackagedCrimpTotal());
    }

    /**
     * Check if viajero has been received.
     */
    public function isViajeroReceived(): bool
    {
        return (bool) $this->viajero_received;
    }

    /**
     * Check if a closure decision has been made.
     */
    public function hasClosureDecision(): bool
    {
        return ! is_null($this->closure_decision);
    }

    /**
     * Check if surplus has been received by Control de Materiales.
     */
    public function isSurplusReceived(): bool
    {
        return (bool) $this->surplus_received;
    }

    /**
     * Get the packaging semaphore status.
     * gray = quality hasn't approved any pieces yet
     * yellow = pieces available but not all packed
     * green = fully packed & viajero received & closed
     * blue = viajero received, pending closure decision
     * orange = closed with surplus, pending material reception
     */
    public function getPackagingSemaphoreStatus(): string
    {
        $available = $this->getPackagingAvailablePieces();
        if ($available <= 0) {
            return 'gray';
        }

        if ($this->isSurplusReceived()) {
            return 'green';
        }

        if (in_array($this->closure_decision, ['close_as_is', 'new_lot']) && ! $this->isSurplusReceived()) {
            return 'orange';
        }

        if ($this->hasClosureDecision()) {
            return 'green';
        }

        if ($this->isViajeroReceived()) {
            return 'blue';
        }

        $packed = $this->getPackagingPackedPieces();
        if ($packed <= 0) {
            return 'yellow';
        }

        if ($packed >= $available && ! $this->isViajeroReceived()) {
            return 'yellow';
        }

        return 'yellow';
    }

    /**
     * Closure decision constants.
     */
    public const CLOSURE_COMPLETE_LOT = 'complete_lot';

    public const CLOSURE_NEW_LOT = 'new_lot';

    public const CLOSURE_CLOSE_AS_IS = 'close_as_is';

    // Decisiones del Paso 6 para CRIMP (diagrama 4): D2a / D2b / D2c.
    public const CLOSURE_COMPLETE_CRIMP = 'complete_crimp';

    public const CLOSURE_COMPLETE_PIECES = 'complete_pieces';

    public const CLOSURE_COMPLETE_BOTH = 'complete_both';

    /**
     * Decisiones D2 (Paso 6 CRIMP) que dejan trabajo pendiente y cuya marca de
     * shipping se difiere al Paso 7 (recepción del viajero), no al observer.
     */
    public const CLOSURE_COMPLETION_TYPES = [
        self::CLOSURE_COMPLETE_CRIMP,
        self::CLOSURE_COMPLETE_PIECES,
        self::CLOSURE_COMPLETE_BOTH,
    ];

    /**
     * ¿La decisión de cierre es un tipo "completar" (D2a/b/c)?
     * Se usa como gate para marcar ready_for_shipping en el Paso 7.
     */
    public function isCompletionClosure(): bool
    {
        return in_array($this->closure_decision, self::CLOSURE_COMPLETION_TYPES, true);
    }

    /**
     * Get the post-quality lifecycle state for visual indicators.
     * Returns 3 phases (viajero, decision, material) each with:
     *   - state: 'idle' | 'pending' | 'in_progress' | 'done'
     *   - actor: 'Empaque' | 'Materiales' | null
     *   - label: tooltip text
     */
    public function getPostQualityLifecycle(): array
    {
        $hasAvailable = $this->getPackagingAvailablePieces() > 0;
        $hasPacked = $this->getPackagingPackedPieces() > 0;
        $viajeroReceived = $this->isViajeroReceived();
        $hasDecision = $this->hasClosureDecision();
        $surplus = $this->getPackagingTotalSurplus();
        $hasSurplus = $surplus > 0;
        $surplusDelivered = (bool) $this->surplus_delivered;
        $surplusReceived = $this->isSurplusReceived();
        $isCrimp = (bool) ($this->workOrder->purchaseOrder->part->is_crimp ?? false);

        $decLabel = match ($this->closure_decision) {
            self::CLOSURE_COMPLETE_LOT => 'Decisión: Completar Lote',
            self::CLOSURE_NEW_LOT => 'Decisión: Nuevo Lote',
            self::CLOSURE_CLOSE_AS_IS => 'Decisión: Cerrar Lote',
            self::CLOSURE_COMPLETE_CRIMP => 'Decisión: Completar CRIMP',
            self::CLOSURE_COMPLETE_PIECES => 'Decisión: Completar piezas',
            self::CLOSURE_COMPLETE_BOTH => 'Decisión: Completar piezas y CRIMP',
            default => 'Decisión tomada',
        };

        if ($isCrimp) {
            // CRIMP (diagramas): Empaque → Decisión (Paso 6) → Entrega de viajero (Paso 7) → Sobrantes (Paso 8).
            if ($hasDecision) {
                $decision = ['state' => 'done', 'actor' => null, 'label' => $decLabel];
            } elseif ($hasPacked || $hasAvailable) {
                $decision = ['state' => 'pending', 'actor' => 'Materiales', 'label' => 'Materiales debe tomar decisión de cierre'];
            } else {
                $decision = ['state' => 'idle', 'actor' => null, 'label' => 'Esperando empaque'];
            }

            if ($viajeroReceived) {
                $viajero = ['state' => 'done', 'actor' => null, 'label' => 'Viajero recibido por Materiales'];
            } elseif ($hasDecision) {
                $viajero = ['state' => 'pending', 'actor' => 'Empaque', 'label' => 'Empaque debe entregar viajero'];
            } else {
                $viajero = ['state' => 'idle', 'actor' => null, 'label' => 'Esperando decisión de Materiales'];
            }
        } else {
            // NO-CRIMP — flujo ORIGINAL intacto: Empaque → Entrega de viajero → Decisión → Material.
            if ($viajeroReceived) {
                $viajero = ['state' => 'done', 'actor' => null, 'label' => 'Viajero recibido por Materiales'];
            } elseif ($hasPacked || $hasAvailable) {
                $viajero = ['state' => 'pending', 'actor' => 'Empaque', 'label' => 'Empaque debe entregar viajero'];
            } else {
                $viajero = ['state' => 'idle', 'actor' => null, 'label' => 'Sin actividad de empaque aún'];
            }

            if ($hasDecision) {
                $decision = ['state' => 'done', 'actor' => null, 'label' => $decLabel];
            } elseif ($viajeroReceived) {
                $decision = ['state' => 'pending', 'actor' => 'Materiales', 'label' => 'Materiales debe tomar decisión de cierre'];
            } else {
                $decision = ['state' => 'idle', 'actor' => null, 'label' => 'Esperando entrega de viajero'];
            }
        }

        // ── Material / Sobrantes — igual para ambos (tras la decisión) ──
        if ($surplusReceived) {
            $material = [
                'state' => 'done',
                'actor' => null,
                'label' => $hasSurplus
                    ? 'Material sobrante recibido ('.number_format($surplus).' pz)'
                    : 'Recepción de material confirmada',
            ];
        } elseif ($hasDecision) {
            if ($hasSurplus) {
                if (! $surplusDelivered) {
                    $material = ['state' => 'pending', 'actor' => 'Empaque', 'label' => 'Empaque debe entregar '.number_format($surplus).' pz sobrantes'];
                } else {
                    $material = ['state' => 'in_progress', 'actor' => 'Materiales', 'label' => 'Materiales debe recibir '.number_format($surplus).' pz sobrantes'];
                }
            } else {
                $material = ['state' => 'pending', 'actor' => 'Materiales', 'label' => 'Materiales debe confirmar recepción'];
            }
        } elseif (! $isCrimp && $viajeroReceived) {
            $material = ['state' => 'idle', 'actor' => null, 'label' => 'Esperando decisión de Materiales'];
        } else {
            $material = ['state' => 'idle', 'actor' => null, 'label' => $isCrimp ? 'Esperando decisión de Materiales' : 'Esperando entrega de viajero'];
        }

        return compact('viajero', 'decision', 'material');
    }

    /**
     * Get the next pending action for this lot (first non-done phase).
     * Returns null if all phases are done or idle.
     */
    public function getNextPendingAction(): ?array
    {
        foreach ($this->getPostQualityLifecycle() as $phase => $info) {
            if (in_array($info['state'], ['pending', 'in_progress'], true)) {
                return ['phase' => $phase] + $info;
            }
        }

        return null;
    }

    /**
     * High-level progress summary of the lot across the whole flow, for list UI.
     * Phases: pending → production → quality → packaging → closed → shipping.
     *
     * @return array{phase:string, label:string, color:string, percent:int, done:bool}
     */
    public function getProgressSummary(): array
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return ['phase' => 'cancelled', 'label' => 'Cancelado', 'color' => 'red', 'percent' => 0, 'done' => false];
        }

        if ($this->ready_for_shipping) {
            return ['phase' => 'shipping', 'label' => 'Listo para envío', 'color' => 'green', 'percent' => 100, 'done' => true];
        }

        if ($this->hasClosureDecision()) {
            return ['phase' => 'closed', 'label' => 'Cerrado por empaque', 'color' => 'emerald', 'percent' => 90, 'done' => false];
        }

        if ($this->getPackagingPackedPieces() > 0) {
            return ['phase' => 'packaging', 'label' => 'En empaque', 'color' => 'blue', 'percent' => 75, 'done' => false];
        }

        if ($this->getQualityGoodPieces() > 0 || $this->getQualityPendingPieces() > 0) {
            return ['phase' => 'quality', 'label' => 'En calidad', 'color' => 'cyan', 'percent' => 50, 'done' => false];
        }

        if ($this->getProductionTotalWeighed() > 0) {
            return ['phase' => 'production', 'label' => 'En producción', 'color' => 'amber', 'percent' => 25, 'done' => false];
        }

        return ['phase' => 'pending', 'label' => 'Pendiente', 'color' => 'zinc', 'percent' => 0, 'done' => false];
    }

    // =====================================================
    // RETURN TO PACKAGING HELPERS
    // =====================================================

    /**
     * Relationship: usuario que devolvio el lote a Empaque.
     */
    public function returnedToPackagingByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to_packaging_by');
    }

    /**
     * Verifica si el lote fue devuelto a Empaque en algun momento.
     */
    public function wasReturnedToPackaging(): bool
    {
        return ! is_null($this->returned_to_packaging_at);
    }
}
