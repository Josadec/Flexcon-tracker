<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Autorización de escritura para las vistas de área del tablero SentList.
 *
 * Regla: solo puede escribir quien (1) pertenece por rol al departamento que
 * cubre el componente Y (2) la lista está actualmente en esa etapa y sigue
 * editable (SentList::canDepartmentEdit).
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
     */
    protected function ensureCanEditDepartment(): void
    {
        $user = Auth::user();

        abort_unless(
            $user && $user->canActOnSentListDepartment($this->guardedDepartment()),
            403,
            'No pertenece al departamento responsable de esta etapa.'
        );

        abort_unless(
            $this->sentList->canDepartmentEdit($this->guardedDepartment()),
            403,
            'Esta lista no está en la etapa de su departamento o ya fue cerrada.'
        );
    }

    /**
     * Versión no-abortante para gating de UI en Blade (botones/inputs de solo-lectura).
     */
    public function canEditDepartment(): bool
    {
        $user = Auth::user();

        return $user
            && $user->canActOnSentListDepartment($this->guardedDepartment())
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
