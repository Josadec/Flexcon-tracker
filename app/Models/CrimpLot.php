<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lote de CRIMP — hijo del "viajero" (Lot). Sustituye al Kit en el flujo de partes con CRIMP.
 * 1 viajero (Lot) -> N lotes de CRIMP. Cada lote lleva su lote de fabricante (campo manual).
 */
class CrimpLot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lot_id',
        'crimp_lot_number',
        'lote_fabricante',
        'date_code',
        'quantity',
        'comments',
        'surplus_pieces',
        'surplus_crimps',
        'surplus_captured_at',
        'surplus_captured_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'surplus_pieces' => 'integer',
        'surplus_crimps' => 'integer',
        'surplus_captured_at' => 'datetime',
    ];

    /**
     * El viajero (Lot) al que pertenece este lote de CRIMP.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * Alias de dominio: el "viajero" es el Lot padre.
     */
    public function viajero(): BelongsTo
    {
        return $this->lot();
    }

    /**
     * Pesadas de PIEZAS ("manguitas") capturadas en Empaque para este lote de CRIMP.
     */
    public function packagingPieceWeighings(): HasMany
    {
        return $this->hasMany(PackagingPieceWeighing::class);
    }

    /**
     * Pesadas de CRIMP capturadas en Empaque para este lote de CRIMP.
     */
    public function packagingCrimpWeighings(): HasMany
    {
        return $this->hasMany(PackagingCrimpWeighing::class);
    }

    /**
     * Piezas ("manguitas") empacadas de este lote de CRIMP (suma de pesadas de piezas).
     */
    public function getPackagedPiecesTotal(): int
    {
        return (int) $this->packagingPieceWeighings->sum('quantity');
    }

    /**
     * CRIMP empacados de este lote de CRIMP (suma de pesadas de CRIMP).
     */
    public function getPackagedCrimpTotal(): int
    {
        return (int) $this->packagingCrimpWeighings->sum('quantity');
    }

    /**
     * Usuario que capturó por última vez el sobrante declarado (Paso 5 · Empaque).
     */
    public function surplusCapturedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surplus_captured_by');
    }

    /**
     * ¿Ya se declaró un sobrante (manguitas o CRIMP) en este lote de CRIMP?
     * NULL en ambas columnas = todavía no se capturó nada.
     */
    public function hasSurplusCaptured(): bool
    {
        return $this->surplus_pieces !== null || $this->surplus_crimps !== null;
    }
}
