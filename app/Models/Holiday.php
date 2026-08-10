<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    /** @use HasFactory<\Database\Factories\HolidayFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'date',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Buscar por nombre o descripción.
     *
     * Las dos condiciones van agrupadas: sueltas, el `orWhere` se escapaba de
     * cualquier otro filtro de la consulta y devolvía festivos de más.
     */
    public function scopeSearch($query, $term)
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeDate($query, $date)
    {
        return $query->where('date', $date);
    }

}
