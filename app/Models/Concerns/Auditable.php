<?php

namespace App\Models\Concerns;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Deja rastro de lo que se crea, se cambia y se borra.
 *
 * Hasta ahora la auditoría era manual: había que acordarse de llamar al
 * servicio en cada componente. Resultado real: cubría 2 modelos de 41, y
 * ninguno de los documentos que le importan al cliente (órdenes, listas de
 * envío, packing slips, facturas, pesadas).
 *
 * Con el trait, auditar un modelo es una línea. Cada modelo declara en
 * `$auditExclude` lo que no aporta al historial (marcas de tiempo, contraseñas)
 * para que el registro se pueda leer de un vistazo.
 *
 *     class Invoice extends Model
 *     {
 *         use Auditable;
 *
 *         protected array $auditExclude = ['updated_at'];
 *     }
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAuditEntry('create', null, $model->auditableAttributes()));

        static::updated(function (Model $model) {
            $cambios = $model->auditableChanges();

            // Un guardado que no cambió nada material no es historial, es ruido.
            if ($cambios === []) {
                return;
            }

            $model->writeAuditEntry(
                'update',
                array_intersect_key($model->getOriginal(), $cambios),
                $cambios
            );
        });

        static::deleted(fn (Model $model) => $model->writeAuditEntry('delete', $model->auditableAttributes(), null));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => $model->writeAuditEntry('restore', null, $model->auditableAttributes()));
        }
    }

    /** Campos que no se guardan en el historial. */
    protected function auditExcluded(): array
    {
        return array_merge(
            ['created_at', 'updated_at', 'password', 'remember_token'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );
    }

    protected function auditableAttributes(): array
    {
        return array_diff_key($this->getAttributes(), array_flip($this->auditExcluded()));
    }

    protected function auditableChanges(): array
    {
        return array_diff_key($this->getChanges(), array_flip($this->auditExcluded()));
    }

    public function auditTrails()
    {
        return $this->morphMany(AuditTrail::class, 'auditable');
    }

    protected function writeAuditEntry(string $action, ?array $old, ?array $new): void
    {
        $actor = Auth::user();

        AuditTrail::create(array_merge([
            'user_id' => $actor?->id,
            'user_name' => $actor ? trim($actor->name.' '.($actor->last_name ?? '')) : null,
            'user_email' => $actor?->email,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ], $this->auditContext()));
    }

    /**
     * A qué orden, viajero y parte pertenece lo que se acaba de tocar.
     *
     * Se resuelve al escribir, no al consultar: es lo que permite buscar el
     * historial «por WO» o «por número de parte» sin recorrer relaciones desde
     * una tabla polimórfica. Cada modelo lo afina sobreescribiendo este método.
     */
    protected function auditContext(): array
    {
        return [];
    }
}
