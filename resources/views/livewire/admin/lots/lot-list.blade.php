@php
    $tonoEstado = [
        \App\Models\Lot::STATUS_PENDING => 'neutral',
        \App\Models\Lot::STATUS_IN_PROGRESS => 'info',
        \App\Models\Lot::STATUS_COMPLETED => 'good',
        \App\Models\Lot::STATUS_CANCELLED => 'bad',
    ];
@endphp

<x-ui.page eyebrow="Producción" :title="$workOrder ? 'Viajeros de la WO '.($workOrder->purchaseOrder?->wo ?? $workOrder->id) : 'Viajeros'"
    subtitle="Cada viajero es una corrida de producción de una orden. De él cuelgan las pesadas, la inspección y el empaque."
    :back="$workOrder ? route('admin.work-orders.show', $workOrder) : null"
    backLabel="Volver a la orden">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.lots.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo viajero
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Viajeros" :value="number_format($stats['total'])" />
        <x-ui.stat label="En progreso" :value="number_format($stats['en_progreso'])" tone="info" />
        <x-ui.stat label="Completados" :value="number_format($stats['completados'])" tone="good" />
        <x-ui.stat label="Piezas planeadas" :value="number_format($stats['piezas'])" tone="accent"
            help="Suma de la cantidad de todos los viajeros listados." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por número de viajero, descripción u orden de trabajo.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Viajero, descripción u orden..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($statuses as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([10, 25, 50, 100] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search || $filterStatus)
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th sort="lot_number" :field="$sortField" :direction="$sortDirection">Viajero</x-ui.th>
                <x-ui.th>Orden / parte</x-ui.th>
                <x-ui.th sort="quantity" :field="$sortField" :direction="$sortDirection" class="w-28">Cantidad</x-ui.th>
                <x-ui.th class="w-44">Avance</x-ui.th>
                <x-ui.th sort="status" :field="$sortField" :direction="$sortDirection" class="w-32">Estado</x-ui.th>
                <x-ui.th align="right" class="w-40">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($lots as $lot)
            @php
                $avance = $lot->getProgressSummary();
                $esCrimp = (bool) ($lot->workOrder?->purchaseOrder?->part?->is_crimp ?? false);
            @endphp
            <tr wire:key="lot-{{ $lot->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</span>
                    @if ($esCrimp)
                        <x-ui.badge tone="accent" class="mt-1">CRIMP · {{ $lot->crimpLots->count() }} {{ \Illuminate\Support\Str::plural('lote', $lot->crimpLots->count()) }}</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="block text-slate-700 dark:text-slate-200">
                        WO {{ $lot->workOrder?->purchaseOrder?->wo ?? '—' }}
                    </span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        {{ $lot->workOrder?->purchaseOrder?->part?->number ?? 'Sin parte' }}
                    </span>
                </td>
                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-700 dark:text-slate-200">
                    {{ number_format($lot->quantity) }}
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 w-full max-w-[5rem] overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full bg-sky-500" style="width: {{ $avance['percent'] }}%"></div>
                        </div>
                        <span class="whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">{{ $avance['label'] }}</span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$tonoEstado[$lot->status] ?? 'neutral'" dot>{{ $statuses[$lot->status] ?? $lot->status }}</x-ui.badge>
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el viajero {{ $lot->lot_number }}"
                        :show="route('admin.lots.show', $lot)"
                        :edit="route('admin.lots.edit', $lot)"
                        :delete="$lot->canBeDeleted() ? 'confirmDeletion('.$lot->id.')' : null"
                        deleteConfirm="¿Eliminar el viajero «{{ $lot->lot_number }}»?">
                        <x-ui.icon-btn tone="neutral" label="Cambiar el estado del viajero {{ $lot->lot_number }}"
                            wire:click="openStatusModal({{ $lot->id }})">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </x-ui.icon-btn>
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty icon="search" title="No se encontraron viajeros"
                        hint="Ajusta la búsqueda o el estado, o da de alta un viajero nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.lots.create') }}">Nuevo viajero</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($lots->hasPages())
            <x-slot:foot>{{ $lots->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    <x-ui.note tone="muted">
        Un viajero con pesadas, empaque o lotes de CRIMP ya no se puede eliminar: borrarlo arrastraría ese historial.
        Para sacarlo de circulación, cámbialo a <strong>Cancelado</strong>.
    </x-ui.note>

    {{-- Cambio de estado --}}
    @if ($showStatusModal && $selectedLotId)
        @php
            $lotSeleccionado = \App\Models\Lot::find($selectedLotId);
            $permitidos = $lotSeleccionado ? $this->allowedTransitions($lotSeleccionado) : [];
        @endphp
        <x-ui-modal wire:key="modal-lot-status" title="Cambiar el estado del viajero"
            :subtitle="$lotSeleccionado?->lot_number"
            close="closeStatusModal" maxWidth="2xl">

            <x-ui.section title="Nuevo estado" hint="Sólo se ofrecen los cambios válidos desde el estado actual.">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($statuses as $valor => $etiqueta)
                        @php $habilitado = in_array($valor, $permitidos, true); @endphp
                        <x-ui.choice wire:key="status-{{ $valor }}"
                            :tone="$tonoEstado[$valor] ?? 'neutral'"
                            :title="$etiqueta"
                            :desc="$valor === $lotSeleccionado?->status ? 'Estado actual.' : ($habilitado ? 'Disponible desde el estado actual.' : 'No se puede pasar a este estado desde el actual.')"
                            :selected="$newStatus === $valor"
                            {{-- Igual que en lot-show: @disabled(...) rompe la etiqueta del componente. --}}
                            :disabled="!$habilitado"
                            wire:click="setNewStatus('{{ $valor }}')" />
                    @endforeach
                </div>
            </x-ui.section>

            <x-slot:note>Cambiar el estado no borra nada: las pesadas y el empaque se conservan.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeStatusModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="updateLotStatus">Guardar estado</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
