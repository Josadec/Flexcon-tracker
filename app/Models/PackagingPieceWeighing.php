<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pesada de PIEZAS ("manguitas") en Empaque (flujo CRIMP). Cuelga del viajero (Lot).
 */
class PackagingPieceWeighing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lot_id',
        'quantity',
        'weight',
        'weighed_by',
        'weighed_at',
        'comments',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'weight'     => 'decimal:3',
        'weighed_at' => 'datetime',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function weighedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'weighed_by');
    }
}
