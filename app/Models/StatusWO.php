<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusWO extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'statuses_wo';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'color',
        'comments',
    ];

    /**
     * Nombres de los estados que el flujo consulta por código.
     *
     * Este catálogo se administra desde la pantalla de Estados, así que
     * cualquiera puede renombrar una fila. Como el tablero y la mesa de
     * Materiales deciden qué ocultar comparando por NOMBRE, un cambio ahí
     * rompería la pantalla en silencio. Al menos ahora el nombre vive en un
     * solo sitio y `closedIds()` es lo que se usa para filtrar.
     */
    public const OPEN = 'Open';

    public const IN_PROGRESS = 'In Progress';

    public const COMPLETED = 'Completed';

    public const CANCELLED = 'Cancelled';

    public const ON_HOLD = 'On Hold';

    /** Los estados que sacan una orden del trabajo diario. */
    public const CLOSED = [self::COMPLETED, self::CANCELLED];

    /**
     * Ids de los estados cerrados, para filtrar sin repetir la consulta.
     *
     * @return array<int, int>
     */
    public static function closedIds(): array
    {
        return static::whereIn('name', self::CLOSED)->pluck('id')->all();
    }

    /**
     * Relationships with other models
     */

    /**
     * Get the work orders for this status.
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'status_id');
    }

    /**
     * Scopes
     */

    /**
     * Search statuses by name or comments.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('comments', 'like', "%{$search}%");
    }

    /**
     * Order by field dynamically.
     */
    public function scopeSortByField($query, $field = 'name', $direction = 'asc')
    {
        return $query->orderBy($field, $direction);
    }

    /**
     * Auxiliary methods
     */

    /**
     * Check if this status can be deleted.
     * A status cannot be deleted if it has associated work orders.
     */
    public function canBeDeleted(): bool
    {
        return $this->workOrders()->count() === 0;
    }
}
