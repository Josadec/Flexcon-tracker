<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'quantity',
        'comments',
    ];

    protected $casts = [
        'quantity' => 'integer',
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
}
