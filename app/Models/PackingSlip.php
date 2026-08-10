<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PackingSlip extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // =========================================================
    // Constantes de estado del ciclo de vida
    // =========================================================
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT     => 'Borrador',
        self::STATUS_PENDING   => 'Pendiente',
        self::STATUS_SHIPPED   => 'Despachado',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    protected $fillable = [
        'ps_number',
        'created_by',
        'status',
        'document_date',
        'shipped_at',
        'shipped_by',
        'invoice_id',  // FK al Invoice generado para este PS (Decision D-12-04).
        'notes',
    ];

    protected $casts = [
        'document_date' => 'date',
        'shipped_at'    => 'datetime',
        'invoice_id'    => 'integer',
    ];

    // =========================================================
    // Route Model Binding — usa ps_number en la URL
    // =========================================================

    /**
     * Indica a Laravel qué columna usar para el Route Model Binding.
     */
    public function getRouteKeyName(): string
    {
        return 'ps_number';
    }

    /**
     * Retorna el valor que se incrusta en la URL (lowercase + URL-encoded para URLs limpias y seguras).
     * Ejemplo: PS-2026-0002 → ps-2026-0002
     * Ejemplo: #0001234    → %230001234  (el # se codifica para no romperse como fragmento HTML)
     *
     * Nota: rawurlencode() no modifica letras, dígitos ni los caracteres - _ . ~
     * por lo que los ps_number con formato estándar PS-YYYY-NNNN no cambian.
     */
    public function getRouteKey(): string
    {
        return rawurlencode(strtolower($this->ps_number));
    }

    /**
     * Resuelve el binding de forma case-insensitive para que la URL
     * ps-2026-0002 encuentre el registro PS-2026-0002 en la BD.
     *
     * El router de Laravel/Symfony decodifica los segmentos %XX antes de invocar
     * este método, por lo que $value llega como '#0001234' (no '%230001234').
     * Usamos strtoupper() para normalizar al formato almacenado en la BD.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where('ps_number', strtoupper($value))->firstOrFail();
    }

    // =========================================================
    // Boot: auto-generacion de ps_number
    // =========================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PackingSlip $ps) {
            if (empty($ps->ps_number)) {
                $ps->ps_number = static::generatePsNumber();
            }
            // document_date NO se asigna automaticamente: debe quedar NULL hasta que
            // el usuario confirme la fecha del documento. Los botones de PDF en la vista
            // dependen de que document_date tenga valor, por lo que asignarla aqui
            // causaria que aparecieran desde el momento de creacion (bug reportado).
        });
    }

    /**
     * Genera un numero unico de Packing Slip con el formato PS-YYYY-NNNN.
     * Incluye registros soft-deleted para evitar colisiones de unicidad.
     */
    public static function generatePsNumber(): string
    {
        $year   = Carbon::now()->year;
        $prefix = "PS-{$year}-";

        $last = static::withTrashed()
            ->where('ps_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(ps_number, -4) AS UNSIGNED) DESC')
            ->first();

        $next = $last ? ((int) substr($last->ps_number, -4)) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // =========================================================
    // Relaciones
    // =========================================================

    /**
     * Usuario que creo el Packing Slip.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que realizo el despacho.
     */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    /**
     * Items (lotes) incluidos en este Packing Slip.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PackingSlipItem::class);
    }

    /**
     * Invoice generado para este Packing Slip.
     * Decision D-12-03: relacion 1:1 — un PS genera exactamente un Invoice de tipo product.
     * Decision D-12-04: la relacion hasOne se define aqui para convenencia de navegacion
     * bidireccional ($ps->invoice), complementada por el campo invoice_id en packing_slips.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // =========================================================
    // Scopes
    // =========================================================

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SHIPPED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('ps_number', 'like', "%{$search}%");
    }

    // =========================================================
    // Helpers de estado
    // =========================================================

    /**
     * Indica si este Packing Slip ya tiene un Invoice asociado.
     * Decision D-12-04: usa el campo invoice_id para la verificacion sin consulta adicional.
     * El campo invoice_id se llena en InvoiceFromPackingSlipService al crear el Invoice.
     */
    public function hasInvoice(): bool
    {
        return $this->invoice_id !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isShipped(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
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
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'yellow',
            self::STATUS_PENDING   => 'orange',
            self::STATUS_SHIPPED   => 'green',
            self::STATUS_CANCELLED => 'red',
            default                => 'gray',
        };
    }

    /**
     * Total de piezas empacadas sumando todos los items del PS.
     */
    public function getTotalQuantityAttribute(): int
    {
        if ($this->relationLoaded('items')) {
            return (int) $this->items->sum('quantity_packed');
        }
        return (int) $this->items()->sum('quantity_packed');
    }

    /**
     * Total de lineas (items) en este PS.
     */
    public function getTotalItemsAttribute(): int
    {
        if ($this->relationLoaded('items')) {
            return $this->items->count();
        }
        return $this->items()->count();
    }
}
