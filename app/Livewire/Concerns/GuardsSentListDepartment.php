<?php

namespace App\Livewire\Concerns;

use App\Services\ReopeningService;
use Illuminate\Support\Facades\Auth;

/**
 * Autorización de escritura para las vistas de área del tablero SentList.
 *
 * Regla: solo puede escribir quien (1) pertenece por rol al departamento que
 * cubre el componente Y (2) la lista está actualmente en esa etapa y sigue
 * editable (SentList::canDepartmentEdit).
 *
 * Excepción: Administración. Corregir lo que ya se cerró es justo su trabajo,
 * así que atraviesa las dos condiciones. El tablero de piso ya lo hacía
 * (ShippingListDisplay::canAccessDepartment) y aquí no, de modo que el mismo
 * usuario podía actuar desde una pantalla y recibía un 403 desde la otra.
 *
 * El componente que use este trait DEBE:
 *  - exponer la propiedad pública `$sentList` (App\Models\SentList).
 *  - implementar `guardedDepartment()` devolviendo una constante SentList::DEPT_*.
 */
trait GuardsSentListDepartment
{
    /**
     * Constante SentList::DEPT_* que cubre este componente.
     */
    abstract protected function guardedDepartment(): string;

    /**
     * Aborta con 403 si el usuario no puede editar en la etapa actual.
     * Llamar como PRIMERA línea de cada método de escritura.
     *
     * Es una red de seguridad contra llamadas Livewire directas, no el gate de
     * la interfaz: las vistas esconden sus acciones con canEditDepartment(), de
     * modo que un usuario normal nunca debería llegar hasta aquí.
     */
    protected function ensureCanEditDepartment(): void
    {
        $user = Auth::user();

        // Administración pasa de largo: ver la nota de cabecera del trait.
        if ($user && $user->can(ReopeningService::PERMISSION)) {
            return;
        }

        abort_unless(
            $user && $user->canActOnSentListDepartment($this->guardedDepartment()),
            403,
            'No pertenece al departamento responsable de esta etapa.'
        );

        // El motivo va en el mensaje: la versión anterior decía sólo «no está en
        // la etapa de su departamento o ya fue cerrada», y se leía como un
        // problema de permisos aunque el rol fuese correcto.
        abort_unless(
            $this->sentList->canDepartmentEdit($this->guardedDepartment()),
            403,
            'No es un problema de permisos: la lista #'.$this->sentList->id
                .' está en la etapa «'.$this->sentList->current_department.'» con estado «'
                .$this->sentList->status.'», y esta pantalla sólo edita en «'
                .$this->guardedDepartment().'».'
        );
    }

    /**
     * Versión no-abortante para gating de UI en Blade (botones/inputs de solo-lectura).
     */
    public function canEditDepartment(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->can(ReopeningService::PERMISSION)) {
            return true;
        }

        return $user->canActOnSentListDepartment($this->guardedDepartment())
            && $this->sentList->canDepartmentEdit($this->guardedDepartment());
    }

    /**
     * IDs de las WorkOrders que pertenecen a ESTA lista (flujo directo + pivot legacy).
     * Misma resolución que SentListController@exportPdf.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    protected function sentListWorkOrderIds(): \Illuminate\Support\Collection
    {
        return \App\Models\WorkOrder::where('sent_list_id', $this->sentList->id)
            ->orWhereHas('purchaseOrder.sentLists', fn ($q) => $q->where('sent_lists.id', $this->sentList->id))
            ->pluck('id');
    }

    /**
     * IDs de los lotes que pertenecen a ESTA lista. Úsalo para acotar los
     * findOrFail de registros hijos (pesadas, empaques, crimp) y evitar IDOR.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    protected function sentListLotIds(): \Illuminate\Support\Collection
    {
        return \App\Models\Lot::whereIn('work_order_id', $this->sentListWorkOrderIds())->pluck('id');
    }
}
