<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusWO extends Model
{
    use HasFactory;

    /**
     * Formato aceptado para el color del estado.
     *
     * El color se pinta como `background-color` en las píldoras de estado
     * (WO show, listado de WOs, Lista de envío). Si se guarda cualquier cadena
     * de hasta 7 caracteres el badge puede quedar sin fondo, así que se exige
     * hexadecimal completo de 6 dígitos.
     */
    public const COLOR_REGEX = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * Color por defecto (gris) cuando no se indica otro.
     */
    public const DEFAULT_COLOR = '#6B7280';

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

    /**
     * Normaliza lo que escribe el usuario a `#RRGGBB` en mayúsculas.
     *
     * Acepta `abc`, `#abc`, `aabbcc` y `#AABBCC`. Lo que no se pueda
     * interpretar se devuelve tal cual para que la validación lo rechace con
     * un mensaje, en vez de guardarse silenciosamente transformado.
     */
    public static function normalizeColor(?string $color): string
    {
        $value = strtoupper(trim((string) $color));

        if ($value === '') {
            return self::DEFAULT_COLOR;
        }

        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        // Forma corta #ABC -> #AABBCC (así la escribe mucha gente).
        if (preg_match('/^#[0-9A-F]{3}$/', $value)) {
            $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }

        return $value;
    }

    /**
     * ¿El color es tan claro que el texto blanco de la píldora no se lee?
     *
     * Las píldoras de estado se pintan con `text-white`, así que un amarillo
     * claro deja el nombre ilegible. No se bloquea el guardado: se avisa.
     */
    public function hasLowContrastWithWhite(): bool
    {
        return self::colorIsLight($this->color);
    }

    /**
     * Luminancia relativa aproximada (ITU-R BT.601) sobre 255.
     */
    public static function colorIsLight(?string $color): bool
    {
        $value = self::normalizeColor($color);

        if (! preg_match(self::COLOR_REGEX, $value)) {
            return false;
        }

        $r = hexdec(substr($value, 1, 2));
        $g = hexdec(substr($value, 3, 2));
        $b = hexdec(substr($value, 5, 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 186;
    }
}
