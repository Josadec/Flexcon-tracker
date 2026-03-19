<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotCompletionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_id',
        'cycle_number',
        'original_quantity',
        'packed_pieces',
        'surplus_pieces',
        'missing_pieces',
        'production_good_pieces',
        'quality_good_pieces',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
