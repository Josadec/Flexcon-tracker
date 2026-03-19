<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InvoiceChargeType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'label',
        'default_amount',
        'is_active',
        'always_include',
        'sort_order',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active'      => 'boolean',
        'always_include' => 'boolean',
        'sort_order'     => 'integer',
    ];

    // =========================================================
    // Relaciones
    // =========================================================

    /**
     * Items de Invoice que referencian este tipo de cargo.
     * Permite verificar si el tipo fue usado en algun Invoice (hasInvoiceUsage).
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_charge_type_id');
    }

    /**
     * Usuario que creo este tipo de cargo.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que realizo la ultima modificacion.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // =========================================================
    // Scopes
    // =========================================================

    /**
     * Solo tipos activos y no eliminados.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Solo tipos que se incluyen automaticamente en todos los Invoices nuevos.
     */
    public function scopeAlwaysInclude(Builder $query): Builder
    {
        return $query->where('always_include', true);
    }

    /**
     * Orden por sort_order ascendente (menor numero = aparece primero en el PDF).
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    // =========================================================
    // Metodos estaticos
    // =========================================================

    /**
     * Retorna la coleccion de tipos de cargo activos que se incluyen
     * automaticamente al crear un nuevo Invoice.
     *
     * Usado por InvoiceFromPackingSlipService para poblar los InvoiceItems
     * de cargos fijos (Machine Maintenance, Administration Fee, SHIPPING COST).
     *
     * Decision D-12-19: el servicio carga los tipos desde este metodo en lugar
     * de leer valores hardcodeados de config/invoice.php.
     *
     * @return Collection<int, InvoiceChargeType>
     */
    public static function getActiveTypesForNewInvoice(): Collection
    {
        return static::active()->alwaysInclude()->ordered()->get();
    }

    // =========================================================
    // Metodos de instancia
    // =========================================================

    /**
     * Verifica si este tipo de cargo fue usado en algun InvoiceItem.
     *
     * Usado en la UI de administracion para determinar si el tipo puede
     * eliminarse fisicamente o solo desactivarse.
     *
     * Decision 15.7: si hasInvoiceUsage() = true, el boton Eliminar aparece
     * deshabilitado con el mensaje "No eliminable — usado en X Invoice(s)".
     */
    public function hasInvoiceUsage(): bool
    {
        return $this->invoiceItems()->exists();
    }

    /**
     * Cuenta cuantos InvoiceItems referencian este tipo de cargo.
     * Usado en el tooltip del boton Eliminar en la UI de administracion.
     */
    public function invoiceUsageCount(): int
    {
        return $this->invoiceItems()->count();
    }

    // =========================================================
    // Accessors
    // =========================================================

    /**
     * Monto por defecto formateado para display.
     * Ejemplo: '$1,200.00'
     */
    public function getFormattedDefaultAmountAttribute(): string
    {
        return '$' . number_format((float) $this->default_amount, 2);
    }
}
