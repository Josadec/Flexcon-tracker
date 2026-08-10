@php
    $tonoMaterial = fn ($estado) => match ($estado) {
        'released' => 'good',
        'rejected' => 'bad',
        default => 'warn',
    };
    $etiquetaMaterial = fn ($estado) => match ($estado) {
        'released' => 'Liberado',
        'rejected' => 'Rechazado',
        default => 'Por liberar',
    };
@endphp

<div class="space-y-5">

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Lo primero: lo que está esperando a Materiales, junto y accionable --}}
    <x-ui.section title="Lo que te toca ahora"
        hint="Viajeros esperando una acción de Materiales. Liberar material se hace aquí mismo; la decisión y los sobrantes se toman en el tablero.">
        <x-slot:aside>
            <x-ui.badge :tone="count($pendingActions) > 0 ? 'warn' : 'good'" dot>
                {{ count($pendingActions) }} {{ \Illuminate\Support\Str::plural('pendiente', count($pendingActions)) }}
            </x-ui.badge>
        </x-slot:aside>

        @if (!empty($pendingActions))
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                @foreach ($pendingActions as $accion)
                    @php $lot = $accion['lot']; $wo = $accion['wo']; @endphp
                    <div wire:key="pend-{{ $lot->id }}"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-900 dark:text-white">Viajero {{ $lot->lot_number }}</span>
                                @if ($accion['is_crimp'])
                                    <x-ui.badge tone="accent">CRIMP</x-ui.badge>
                                @else
                                    <x-ui.badge tone="neutral">Sin CRIMP</x-ui.badge>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">
                                {{ $accion['label'] }} · WO {{ $wo->purchaseOrder?->wo ?? $wo->wo_number }}
                            </p>
                        </div>

                        @if ($accion['here'])
                            <x-ui.btn variant="warning" size="sm" wire:click="openMaterialModal({{ $lot->id }})">
                                Liberar material
                            </x-ui.btn>
                        @else
                            <x-ui.btn variant="warning" size="sm" href="{{ route('admin.sent-lists.display.wo', $wo->id) }}">
                                Tomar en el tablero
                            </x-ui.btn>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty icon="box" title="Sin pendientes de Materiales"
                hint="Cuando un viajero necesite que liberes material o tomes una decisión, aparecerá aquí." />
        @endif
    </x-ui.section>

    {{-- Filtros --}}
    <x-ui.section title="Buscar órdenes" hint="Filtra por orden, parte o descripción, y acota por tipo de flujo o estado del material.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="searchTerm"
                        placeholder="Orden, parte o descripción..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Tipo de flujo" hint="CRIMP lleva ocho pasos y lotes de CRIMP.">
                <select wire:model.live="filterType" class="w-full">
                    <option value="">Todos</option>
                    <option value="crimp">Con CRIMP</option>
                    <option value="standard">Sin CRIMP</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Material">
                <select wire:model.live="filterMaterial" class="w-full">
                    <option value="">Todos</option>
                    <option value="pending">Por liberar</option>
                    <option value="released">Liberado</option>
                    <option value="rejected">Rechazado</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Estado del viajero">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="">Todos</option>
                    <option value="pending">Pendiente</option>
                    <option value="in_progress">En progreso</option>
                    <option value="completed">Completado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </x-ui.field>
        </div>

        @if ($searchTerm || $filterStatus || $filterType || $filterMaterial)
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Órdenes y sus viajeros --}}
    @forelse ($workOrders as $workOrder)
        @php
            $part = $workOrder->purchaseOrder?->part;
            $esCrimp = (bool) ($part?->is_crimp ?? false);
            $asignado = $workOrder->lots->sum('quantity');
            $restante = max(0, ($workOrder->original_quantity ?? 0) - $asignado);
        @endphp

        <x-ui.section wire:key="wo-{{ $workOrder->id }}"
            :title="'Orden '.($workOrder->purchaseOrder?->wo ?? $workOrder->wo_number)"
            :hint="($part?->number ?? 'Sin parte').' · '.($part?->description ?: 'Sin descripción')">

            <x-slot:aside>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($esCrimp)
                        <x-ui.badge tone="accent">CRIMP</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">Sin CRIMP</x-ui.badge>
                    @endif
                    <x-ui.btn variant="secondary" size="sm" wire:click="openEditWOStatusModal({{ $workOrder->id }})">
                        Estado
                    </x-ui.btn>
                    <x-ui.btn variant="primary" size="sm" wire:click="openCreateLotModal({{ $workOrder->id }})">
                        Nuevo viajero
                    </x-ui.btn>
                </div>
            </x-slot:aside>

            <x-ui.stats cols="4" class="mb-4">
                <x-ui.stat label="Cantidad de la orden" :value="number_format($workOrder->original_quantity ?? 0)" unit="pz" />
                <x-ui.stat label="Asignado a viajeros" :value="number_format($asignado)" unit="pz" tone="info" />
                <x-ui.stat label="Por asignar" :value="number_format($restante)" unit="pz"
                    :tone="$restante > 0 ? 'warn' : 'good'" />
                <x-ui.stat label="Viajeros" :value="$workOrder->lots->count()" />
            </x-ui.stats>

            @if ($workOrder->lots->isEmpty())
                <x-ui.empty icon="box" title="Esta orden no tiene viajeros"
                    hint="Sin viajeros no se puede producir nada de esta orden.">
                    <x-slot:action>
                        <x-ui.btn variant="primary" wire:click="openCreateLotModal({{ $workOrder->id }})">Crear el primero</x-ui.btn>
                    </x-slot:action>
                </x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <x-ui.th>Viajero</x-ui.th>
                            <x-ui.th align="right" class="w-28">Cantidad</x-ui.th>
                            @if ($esCrimp)
                                <x-ui.th>Lotes de CRIMP</x-ui.th>
                            @endif
                            <x-ui.th class="w-36">Material</x-ui.th>
                            <x-ui.th align="right" class="w-40">Acciones</x-ui.th>
                        </tr>
                    </x-slot:head>

                    @foreach ($workOrder->lots as $lot)
                        @php $estadoMaterial = $lot->material_status ?? 'pending'; @endphp
                        <tr wire:key="lot-{{ $lot->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3">
                                <span class="block font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ ucfirst(str_replace('_', ' ', $lot->status)) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">
                                {{ number_format($lot->quantity) }}
                            </td>

                            @if ($esCrimp)
                                <td class="px-4 py-3">
                                    @if ($lot->crimpLots->isNotEmpty())
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($lot->crimpLots as $cl)
                                                <span class="inline-flex items-center gap-1 rounded-md bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-200"
                                                    title="{{ number_format($cl->quantity ?? 0) }} pz{{ $cl->lote_fabricante ? ' · Fabricante: '.$cl->lote_fabricante : '' }}">
                                                    <span class="font-mono">{{ $cl->crimp_lot_number }}</span>
                                                    <span class="tabular-nums opacity-70">{{ number_format($cl->quantity ?? 0) }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 dark:text-slate-500">
                                            Sin lotes de CRIMP — se cargan en el tablero
                                        </span>
                                    @endif
                                </td>
                            @endif

                            <td class="px-4 py-3">
                                <x-ui.badge :tone="$tonoMaterial($estadoMaterial)" dot>{{ $etiquetaMaterial($estadoMaterial) }}</x-ui.badge>
                            </td>

                            <td class="px-4 py-3">
                                <x-ui.row-actions label="el viajero {{ $lot->lot_number }}"
                                    :show="route('admin.lots.show', $lot)"
                                    :delete="$lot->canBeDeleted() ? 'confirmDeleteLot('.$lot->id.')' : null"
                                    deleteConfirm="¿Eliminar el viajero «{{ $lot->lot_number }}»?">
                                    <x-ui.icon-btn :tone="$estadoMaterial === 'pending' ? 'success' : 'neutral'"
                                        label="Liberar o rechazar el material del viajero {{ $lot->lot_number }}"
                                        wire:click="openMaterialModal({{ $lot->id }})">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                    </x-ui.icon-btn>
                                    <x-ui.icon-btn tone="primary" label="Editar el viajero {{ $lot->lot_number }}"
                                        wire:click="openEditLotModal({{ $lot->id }})">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </x-ui.icon-btn>
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.section>
    @empty
        <x-ui.section>
            <x-ui.empty icon="search" title="No se encontraron órdenes"
                hint="Ajusta la búsqueda o los filtros. Las órdenes completadas y canceladas no se listan aquí." />
        </x-ui.section>
    @endforelse

    @if ($workOrders->hasPages())
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
            {{ $workOrders->links() }}
        </div>
    @endif

    {{-- Material: liberar o rechazar --}}
    @if ($showMaterialModal && $this->materialLot)
        @php $lotMat = $this->materialLot; @endphp
        <x-ui-modal wire:key="modal-material-{{ $lotMat->id }}" title="Material del viajero"
            subtitle="Paso 3 del flujo: liberar el material es lo que desbloquea la inspección de Calidad."
            close="closeMaterialModal" maxWidth="3xl">

            <x-slot:context>
                <x-ui-modal.ctx label="Viajero" :value="$lotMat->lot_number" />
                <x-ui-modal.ctx label="Parte" :value="$lotMat->workOrder?->purchaseOrder?->part?->number ?? '—'" />
                <x-ui-modal.ctx label="Cantidad" :value="number_format($lotMat->quantity).' pz'" />
                <x-ui-modal.ctx label="Tipo"
                    :value="($lotMat->workOrder?->purchaseOrder?->part?->is_crimp ?? false) ? 'CRIMP' : 'Sin CRIMP'" />
            </x-slot:context>

            @if ($lotMat->workOrder?->purchaseOrder?->part?->is_crimp)
                <x-ui.section title="Lotes de CRIMP del viajero" hint="Lo que se entrega físicamente a Empaque.">
                    @if ($lotMat->crimpLots->isNotEmpty())
                        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                            @foreach ($lotMat->crimpLots as $cl)
                                <x-ui.kv :label="$cl->crimp_lot_number"
                                    :value="number_format($cl->quantity ?? 0).' pz'"
                                    :help="$cl->lote_fabricante ? 'Fabricante: '.$cl->lote_fabricante : null" />
                            @endforeach
                        </dl>
                    @else
                        <x-ui.note tone="warn">
                            Este viajero no tiene lotes de CRIMP cargados. Puedes liberar el material igual, pero
                            Empaque no sabrá con qué lote trabajar.
                        </x-ui.note>
                    @endif
                </x-ui.section>
            @endif

            <x-ui.section step="1" title="¿Qué pasa con este material?" tone="accent">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-ui.choice tone="good" title="Liberado"
                        desc="El material está completo y correcto. Calidad podrá inspeccionar el viajero."
                        :selected="$materialStatus === 'released'"
                        wire:click="setMaterialStatus('released')" />

                    <x-ui.choice tone="bad" title="Rechazado"
                        desc="Hay un problema con el material. El viajero queda detenido hasta que lo corrijas."
                        :selected="$materialStatus === 'rejected'"
                        wire:click="setMaterialStatus('rejected')" />
                </div>

                @error('materialStatus')
                    <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
                @enderror
            </x-ui.section>

            <x-slot:note>
                Mientras el material no esté liberado, Calidad no puede inspeccionar y el viajero no avanza.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeMaterialModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveMaterialStatus">Guardar</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Alta de viajero --}}
    @if ($showCreateLotModal && $this->selectedWorkOrder)
        @php
            $woSel = $this->selectedWorkOrder;
            $yaAsignado = $woSel->lots->sum('quantity');
            $disponible = max(0, ($woSel->original_quantity ?? 0) - $yaAsignado);
        @endphp
        <x-ui-modal wire:key="modal-create-lot" title="Nuevo viajero"
            :subtitle="'Orden '.($woSel->purchaseOrder?->wo ?? $woSel->wo_number)"
            close="closeCreateLotModal" maxWidth="2xl">

            <x-ui.section title="Datos del viajero" hint="El número no se puede repetir dentro de la misma orden.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Número de viajero" required :error="$errors->first('newLotNumber')">
                        <input wire:model="newLotNumber" type="text" class="w-full" placeholder="Ej: 001">
                    </x-ui.field>

                    <x-ui.field label="Cantidad" required
                        :hint="'Disponible por asignar: '.number_format($disponible).' pz.'"
                        :error="$errors->first('newLotQuantity')">
                        <input wire:model="newLotQuantity" type="number" min="1" step="1"
                            class="w-full text-right tabular-nums">
                    </x-ui.field>
                </div>
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeCreateLotModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="createLot">Crear viajero</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Edición de viajero --}}
    @if ($showEditLotModal && $this->selectedLot)
        <x-ui-modal wire:key="modal-edit-lot" title="Editar viajero"
            :subtitle="$this->selectedLot->lot_number" close="closeEditLotModal" maxWidth="2xl">

            <x-ui.section title="Datos del viajero">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Cantidad" required :error="$errors->first('lotQuantity')">
                        <input wire:model="lotQuantity" type="number" min="1" step="1"
                            class="w-full text-right tabular-nums">
                    </x-ui.field>

                    <x-ui.field label="Estado" required :error="$errors->first('lotStatus')">
                        <select wire:model="lotStatus" class="w-full">
                            <option value="pending">Pendiente</option>
                            <option value="in_progress">En progreso</option>
                            <option value="completed">Completado</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </x-ui.field>
                </div>

                <x-ui.field label="Descripción" optional class="mt-4" :error="$errors->first('lotDescription')">
                    <input wire:model="lotDescription" type="text" class="w-full">
                </x-ui.field>

                <x-ui.field label="Comentarios" optional class="mt-4" :error="$errors->first('lotComments')">
                    <textarea wire:model="lotComments" rows="2" class="w-full"></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeEditLotModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="updateLot">Guardar cambios</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Borrado de viajero --}}
    @if ($showDeleteLotConfirm && $this->selectedLot)
        <x-ui-modal wire:key="modal-delete-lot" title="Eliminar viajero"
            :subtitle="$this->selectedLot->lot_number" close="cancelDeleteLot" maxWidth="lg">

            <x-ui.note tone="danger">
                Se eliminará el viajero. Si ya tiene pesadas, empaque o lotes de CRIMP, el sistema lo impedirá:
                en ese caso cámbialo a <strong>Cancelado</strong>.
            </x-ui.note>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelDeleteLot">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="deleteLot">Eliminar viajero</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Estado de la orden --}}
    @if ($showEditWOStatusModal && $this->selectedWorkOrder)
        <x-ui-modal wire:key="modal-wo-status" title="Estado de la orden"
            :subtitle="'Orden '.($this->selectedWorkOrder->purchaseOrder?->wo ?? $this->selectedWorkOrder->wo_number)"
            close="closeEditWOStatusModal" maxWidth="2xl">

            <x-ui.section title="Nuevo estado">
                <x-ui.field label="Estado" required :error="$errors->first('selectedWOStatusId')">
                    <select wire:model="selectedWOStatusId" class="w-full">
                        @foreach ($this->woStatuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeEditWOStatusModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="updateWOStatus">Guardar estado</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</div>
