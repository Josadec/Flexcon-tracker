<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    // =========================================================
    // Constantes de tipo de item
    // =========================================================
    public const TYPE_PART   = 'part';
    public const TYPE_CHARGE = 'charge';

    protected $fillable = [
        'invoice_id',
        'packing_slip_item_id',
        'invoice_charge_type_id',
        // Referencias de navegacion al origen
        'lot_id',
        'work_order_id',
        'purchase_order_id',
        'part_id',
        // Snapshot inmutable del item
        'description',
        'item_number',
        'lot_number',
        'po_number',
        'wo_number',
        // Cantidades y precios
        'quantity',
        'unit_cost',
        'line_total',
        // Clasificacion y orden
        'sort_order',
        'is_fixed_charge',
    ];

    protected $casts = [
        'unit_cost'       => 'decimal:4',
        'line_total'      => 'decimal:2',
        'is_fixed_charge' => 'boolean',
        'quantity'        => 'integer',
        'sort_order'      => 'integer',
    ];

    // =========================================================
    // Relaciones
    // =========================================================

    /**
     * Invoice al que pertenece este item.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * PackingSlipItem origen.
     * NULL para items de cargos fijos.
     */
    public function packingSlipItem(): BelongsTo
    {
        return $this->belongsTo(PackingSlipItem::class);
    }

    /**
     * Tipo de cargo del catalogo invoice_charge_types.
     * NULL para items de producto.
     * Decision D-12-16: vincula el snapshot con el tipo del catalogo que lo origino.
     */
    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(InvoiceChargeType::class, 'invoice_charge_type_id');
    }

    /**
     * Lote origen del item.
     * withTrashed() para que los lotes soft-deleted sigan siendo accesibles
     * como referencia historica desde el Invoice (documento de auditoria).
     * NULL para cargos fijos.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class)->withTrashed();
    }

    /**
     * Work Order origen del item.
     * withTrashed() para integridad del historial.
     * NULL para cargos fijos.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class)->withTrashed();
    }

    /**
     * Purchase Order origen del item.
     * withTrashed() para integridad del historial.
     * NULL para cargos fijos.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    /**
     * Parte asociada al item.
     * withTrashed() para integridad del historial.
     * NULL para cargos fijos.
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    // =========================================================
    // Metodos auxiliares de calculo
    // =========================================================

    /**
     * Recalcula el line_total a partir de quantity y unit_cost, y guarda el item.
     *
     * Decision D-12-05: usa bcmul() para evitar errores de punto flotante
     * con precios de 4 decimales y cantidades del orden de 100,000+ piezas.
     *
     * Ejemplo: 108,000 * 0.1796 con float PHP = 19396.800000000002 (error).
     * Con bcmul(6 decimales) + round(2) = 19396.80 (exacto).
     */
    public function recalculateLineTotal(): void
    {
        $this->line_total = round(
            (float) bcmul((string) $this->quantity, (string) $this->unit_cost, 6),
            2
        );
        $this->save();
    }

    // =========================================================
    // Accessors
    // =========================================================

    /**
     * Total de la linea formateado para display en la UI.
     * Ejemplo: '19,396.80'
     */
    public function getFormattedTotalCostAttribute(): string
    {
        return number_format((float) $this->line_total, 2);
    }

    /**
     * Precio unitario formateado para display en la UI (4 decimales).
     * Ejemplo: '0.1796'
     */
    public function getFormattedUnitCostAttribute(): string
    {
        return number_format((float) $this->unit_cost, 4);
    }

    /**
     * Indica si este item es un cargo fijo (no es un item de producto).
     * Helper de conveniencia.
     */
    public function isFixedCharge(): bool
    {
        return (bool) $this->is_fixed_charge;
    }

    /**
     * Indica si este item es un item de producto (no es un cargo fijo).
     */
    public function isProductItem(): bool
    {
        return ! $this->is_fixed_charge;
    }
}
