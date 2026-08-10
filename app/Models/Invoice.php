<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // =========================================================
    // Constantes de estado del ciclo de vida
    // =========================================================
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ISSUED    = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT     => 'Borrador',
        self::STATUS_ISSUED    => 'Emitido',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    // =========================================================
    // Constantes de tipo de Invoice
    // =========================================================
    public const TYPE_PRODUCT    = 'product';
    public const TYPE_STANDALONE = 'standalone';

    public const TYPES = [
        self::TYPE_PRODUCT    => 'Desde Packing Slip',
        self::TYPE_STANDALONE => 'Standalone',
    ];

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'lot_no',          // Decision D-12-21: MUTABLE, editable por Admin en draft e issued.
        'status',
        'type',
        'packing_slip_id',
        // Snapshot de datos del cliente
        'fob_location',
        'sold_to_name',
        'sold_to_address',
        'shipped_to_name',
        'shipped_to_address',
        // Totales calculados (persistidos por calculateTotals())
        'total_quantity',
        'subtotal_items',
        'subtotal_charges',
        'grand_total',
        // Control y auditoria
        'notes',
        'issued_at',
        'paid_at',
        'created_by',
        'issued_by',
    ];

    protected $casts = [
        'invoice_date'    => 'date',
        'issued_at'       => 'datetime',
        'paid_at'         => 'datetime',
        'subtotal_items'  => 'decimal:2',
        'subtotal_charges'=> 'decimal:2',
        'grand_total'     => 'decimal:2',
        'total_quantity'  => 'integer',
    ];

    // =========================================================
    // Route Model Binding — usa invoice_number en la URL
    // =========================================================

    /**
     * Indica a Laravel que columna usar para el Route Model Binding.
     * Decision D-12-12: usar invoice_number como clave de ruta.
     */
    public function getRouteKeyName(): string
    {
        return 'invoice_number';
    }

    /**
     * Retorna el valor que se incrusta en la URL.
     * El invoice_number es numerico — no se aplica strtolower.
     */
    public function getRouteKey(): string
    {
        return (string) $this->invoice_number;
    }

    // =========================================================
    // Boot: auto-generacion de invoice_number
    // =========================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }
        });
    }

    /**
     * Genera el siguiente invoice_number en la secuencia del sistema.
     *
     * Decision P-12-03 RESUELTA (D-12-23): el sistema arranca desde #00001.
     * Los Invoices de Excel (#01006 y anteriores) son documentos externos.
     * Formato: str_pad($next, 5, '0', STR_PAD_LEFT) → '00001', '00002', etc.
     *
     * Incluye registros soft-deleted para evitar colisiones de unicidad.
     */
    public static function generateInvoiceNumber(): string
    {
        $last = static::withTrashed()
            ->orderByRaw('CAST(invoice_number AS UNSIGNED) DESC')
            ->first();

        if ($last) {
            $next = ((int) $last->invoice_number) + 1;
        } else {
            $next = config('invoice.number_format.first_number', 1);
        }

        return str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // =========================================================
    // Relaciones
    // =========================================================

    /**
     * Packing Slip origen de este Invoice.
     * NULL para Invoices standalone.
     */
    public function packingSlip(): BelongsTo
    {
        return $this->belongsTo(PackingSlip::class);
    }

    /**
     * Todos los items de este Invoice (productos + cargos fijos).
     * Ordenados por sort_order para renderizar el PDF correctamente.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * Solo los items de cargos fijos (Machine Maintenance, Admin Fee, Shipping Cost, etc.).
     * Decision D-12-16: los cargos fijos son InvoiceItems con is_fixed_charge = true.
     */
    public function chargeItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)
                    ->where('is_fixed_charge', true)
                    ->orderBy('sort_order');
    }

    /**
     * Solo los items de producto (provenientes del Packing Slip).
     */
    public function productItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)
                    ->where('is_fixed_charge', false)
                    ->orderBy('sort_order');
    }

    /**
     * Usuario que creo el Invoice.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que emitio el Invoice.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // =========================================================
    // Calculos financieros
    // =========================================================

    /**
     * Recalcula y actualiza los totales del Invoice desde la base de datos.
     *
     * Decision D-12-16 / 15.5:
     *   - subtotal_items: suma de line_total donde is_fixed_charge = false
     *   - subtotal_charges: suma de line_total donde is_fixed_charge = true
     *   - grand_total: bcadd(subtotal_items, subtotal_charges, 2) — precision exacta
     *   - total_quantity: suma de quantity donde is_fixed_charge = false
     *
     * Retorna $this para encadenamiento: $invoice->calculateTotals()->save()
     */
    public function calculateTotals(): static
    {
        $productItems = $this->items()->where('is_fixed_charge', false);
        $chargeItems  = $this->items()->where('is_fixed_charge', true);

        $this->total_quantity   = (int) $productItems->sum('quantity');
        $this->subtotal_items   = (string) $productItems->sum('line_total');
        $this->subtotal_charges = (string) $chargeItems->sum('line_total');
        $this->grand_total      = bcadd(
            (string) $this->subtotal_items,
            (string) $this->subtotal_charges,
            2
        );

        return $this;
    }

    // =========================================================
    // Scopes
    // =========================================================

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopeProduct(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PRODUCT);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('invoice_number', 'like', "%{$search}%");
    }

    // =========================================================
    // Helpers de estado
    // =========================================================

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Indica si el Invoice puede ser modificado (solo en estado draft).
     */
    public function canBeModified(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Indica si el PDF del Invoice esta disponible para descarga.
     * El PDF se genera para Invoices en estado issued.
     */
    public function isPdfAvailable(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    /**
     * Etiqueta legible del estado actual.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Color de badge para UI (Tailwind CSS).
     * draft=yellow, issued=green, cancelled=red
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT      => 'yellow',
            self::STATUS_ISSUED     => 'green',
            self::STATUS_CANCELLED  => 'red',
            default                 => 'gray',
        };
    }

    /**
     * Grand Total formateado para display en la UI.
     * Ejemplo: '$82,478.59'
     */
    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format((float) $this->grand_total, 2);
    }

    /**
     * Formatea el grand_total para el PDF del Invoice.
     * Ejemplo: '82,478.59'
     */
    public function getFormattedGrandTotalForPdfAttribute(): string
    {
        return number_format((float) $this->grand_total, 2);
    }
}
