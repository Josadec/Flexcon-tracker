{{--
    LISTADO DE LISTAS PRELIMINARES

    Búsqueda, filtros, orden y cambio de estado ocurren sin recargar. El cambio
    de estado va en un modal para no sacar al usuario del listado.
--}}
<x-ui.page eyebrow="Control de producción" title="Listas preliminares"
    subtitle="Listas generadas desde el wizard de capacidad. Cada una agrupa las órdenes de una semana.">

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Totales sobre toda la tabla, no sobre la página actual --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total de listas" :value="number_format($totalAll)" />
        <x-ui.stat label="Pendientes" :value="number_format($totalPend)"
            :tone="$totalPend > 0 ? 'warn' : 'good'"
            help="Sólo las pendientes se pueden editar o eliminar." />
        <x-ui.stat label="Confirmadas" :value="number_format($totalConf)" tone="good" />
        <x-ui.stat label="Canceladas" :value="number_format($totalCanc)"
            :tone="$totalCanc > 0 ? 'bad' : 'neutral'" />
    </x-ui.stats>

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por número de lista, PO, parte, estado o departamento.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="# de lista, PO, parte..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="all">Todos</option>
                    <option value="pending">Pendientes</option>
                    <option value="confirmed">Confirmadas</option>
                    <option value="canceled">Canceladas</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Departamento actual">
                <select wire:model.live="filterDepartment" class="w-full">
                    <option value="all">Todos</option>
                    @foreach ($departments as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([10, 15, 25, 50] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search !== '' || $filterStatus !== 'all' || $filterDepartment !== 'all')
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Mostrando {{ $sentLists->total() }} {{ Str::plural('resultado', $sentLists->total()) }} con los filtros aplicados.
                </span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th class="w-24" sort="id" :field="$sortField" :direction="$sortDirection">Lista</x-ui.th>
                <x-ui.th class="w-40">Órdenes de compra</x-ui.th>
                <x-ui.th class="w-40">Partes</x-ui.th>
                <x-ui.th class="w-44" sort="start_date" :field="$sortField" :direction="$sortDirection">Período</x-ui.th>
                <x-ui.th class="w-24" sort="num_persons" :field="$sortField" :direction="$sortDirection" align="right">Personas</x-ui.th>
                <x-ui.th class="w-56">Capacidad</x-ui.th>
                <x-ui.th class="w-32">Departamento</x-ui.th>
                <x-ui.th class="w-28" sort="status" :field="$sortField" :direction="$sortDirection">Estado</x-ui.th>
                <x-ui.th class="w-44" align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($sentLists as $sl)
            @php
                $statusTone = match ($sl->status) {
                    'confirmed' => 'good',
                    'pending'   => 'warn',
                    'canceled'  => 'bad',
                    default     => 'neutral',
                };
                $util = $sl->capacity_utilization;
                $utilBar = $util >= 100 ? 'bg-red-500' : ($util >= 80 ? 'bg-amber-500' : 'bg-green-500');
                $parts = $sl->purchaseOrders->pluck('part.number')->unique()->filter()->values();
            @endphp
            <tr wire:key="sl-{{ $sl->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3">
                    <a href="{{ route('admin.sent-lists.show', $sl) }}" wire:navigate
                        class="font-bold text-sky-700 underline-offset-2 hover:underline dark:text-sky-300">#{{ $sl->id }}</a>
                    <span class="block text-xs text-slate-400 dark:text-slate-500">{{ $sl->created_at->format('d/m/Y') }}</span>
                </td>

                <td class="px-4 py-3">
                    @if ($sl->purchaseOrders->isEmpty())
                        <span class="text-slate-400">—</span>
                    @else
                        <span class="block text-sm text-slate-700 dark:text-slate-200">{{ $sl->purchaseOrders->first()->po_number }}</span>
                        @if ($sl->purchaseOrders->count() > 1)
                            <span class="text-xs text-slate-500 dark:text-slate-400">+{{ $sl->purchaseOrders->count() - 1 }} más</span>
                        @endif
                    @endif
                </td>

                <td class="px-4 py-3">
                    @if ($parts->isEmpty())
                        <span class="text-slate-400">—</span>
                    @else
                        <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $parts->first() }}</span>
                        @if ($parts->count() > 1)
                            <span class="text-xs text-slate-500 dark:text-slate-400">+{{ $parts->count() - 1 }} más</span>
                        @endif
                    @endif
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                    @if ($sl->start_date && $sl->end_date)
                        <span class="block font-medium text-slate-900 dark:text-white">Semana {{ $sl->start_date->weekOfYear }}</span>
                        <span class="block text-xs">{{ $sl->start_date->format('d/m') }} – {{ $sl->end_date->format('d/m/Y') }}</span>
                    @else
                        <span class="text-slate-400">Sin período</span>
                    @endif
                </td>

                <td class="px-4 py-3 text-right tabular-nums text-slate-900 dark:text-white">{{ $sl->num_persons }}</td>

                <td class="px-4 py-3">
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ number_format($sl->used_hours, 1) }} / {{ number_format($sl->total_available_hours, 1) }} h
                        </span>
                        <span class="font-bold tabular-nums {{ $util >= 100 ? 'text-red-700 dark:text-red-400' : 'text-slate-700 dark:text-slate-200' }}">
                            {{ number_format($util, 0) }}%
                        </span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-full rounded-full {{ $utilBar }}" style="width: {{ min($util, 100) }}%"></div>
                    </div>
                    @if ($sl->remaining_hours < 0)
                        <span class="mt-1 block text-[11px] font-semibold text-red-700 dark:text-red-400">
                            Sobrepasada por {{ number_format(abs($sl->remaining_hours), 1) }} h
                        </span>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <x-ui.badge tone="info">{{ $sl->department_label }}</x-ui.badge>
                </td>

                <td class="px-4 py-3">
                    {{-- El estado se puede cambiar en cualquier dirección, también
                         para regresar una lista confirmada o cancelada a pendiente. --}}
                    <button type="button" wire:click="openStatusModal({{ $sl->id }})"
                        class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
                        title="Cambiar el estado de la lista #{{ $sl->id }}">
                        <x-ui.badge :tone="$statusTone" dot>{{ $sl->status_label }}</x-ui.badge>
                    </button>
                </td>

                <td class="px-4 py-3">
                    <x-ui.row-actions label="la lista #{{ $sl->id }}"
                        :show="route('admin.sent-lists.show', $sl)"
                        :edit="$sl->isPending() ? route('admin.sent-lists.edit', $sl) : null"
                        :delete="$sl->canBeDeleted() ? 'deleteSentList('.$sl->id.')' : null"
                        deleteConfirm="¿Eliminar la lista preliminar #{{ $sl->id }}? Esta acción no se puede deshacer.">

                        <x-ui.icon-btn tone="success" label="Abrir la lista #{{ $sl->id }} en el tablero de piso"
                            :href="route('admin.sent-lists.display.sl', $sl->id)">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M5 5h6a2 2 0 012 2v2"/></svg>
                        </x-ui.icon-btn>

                        {{-- El PDF es una descarga: sin wire:navigate. --}}
                        <x-ui.icon-btn tone="danger" label="Descargar la lista #{{ $sl->id }} en PDF"
                            :href="route('admin.sent-lists.export-pdf', $sl->id)" :navigate="false">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </x-ui.icon-btn>
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9">
                    @if ($search !== '' || $filterStatus !== 'all' || $filterDepartment !== 'all')
                        <x-ui.empty icon="search" title="No hay listas con esos filtros"
                            hint="Prueba con otro texto o quita los filtros.">
                            <x-slot:action>
                                <x-ui.btn variant="secondary" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
                            </x-slot:action>
                        </x-ui.empty>
                    @else
                        <x-ui.empty title="Todavía no hay listas preliminares"
                            hint="Se crean desde el wizard de capacidad, planeando las horas de una semana.">
                            <x-slot:action>
                                <x-ui.btn variant="primary" href="{{ route('admin.capacity.wizard') }}">
                                    Ir al wizard de capacidad
                                </x-ui.btn>
                            </x-slot:action>
                        </x-ui.empty>
                    @endif
                </td>
            </tr>
        @endforelse

        @if ($sentLists->hasPages())
            <x-slot:foot>{{ $sentLists->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Cambio de estado sin salir del listado --}}
    @if ($statusModalId && $modalList)
        <x-ui-modal wire:key="modal-status-{{ $statusModalId }}"
            title="Cambiar estado de la lista #{{ $modalList->id }}"
            subtitle="El estado define si la lista sigue en planeación o ya se cerró."
            close="closeStatusModal" maxWidth="2xl">

            <x-slot:context>
                <x-ui-modal.ctx label="Lista" :value="'#'.$modalList->id" />
                <x-ui-modal.ctx label="Departamento" :value="$modalList->department_label" />
                <x-ui-modal.ctx label="Órdenes"
                    :value="$modalList->getEffectiveWorkOrders()->count().' work orders'" />
                <x-ui-modal.ctx label="Estado actual" :value="$modalList->status_label" />
            </x-slot:context>

            @php $runningWOs = $modalList->getRunningWorkOrders(); @endphp

            <x-ui.section title="Nuevo estado" hint="Puedes moverla en cualquier dirección, incluido regresarla a Pendiente.">
                <div class="grid grid-cols-1 gap-3">
                    <x-ui.choice tone="warn" title="Pendiente"
                        desc="Sigue en planeación. Es el único estado que permite editarla."
                        :selected="$newStatus === 'pending'"
                        wire:click="$set('newStatus', 'pending')" />
                    <x-ui.choice tone="good" title="Confirmada"
                        desc="La lista queda cerrada. Puedes regresarla a Pendiente cuando quieras."
                        :selected="$newStatus === 'confirmed'"
                        wire:click="$set('newStatus', 'confirmed')" />
                    <x-ui.choice tone="bad" title="Cancelada"
                        :desc="$runningWOs->isEmpty()
                            ? 'La lista se descarta Y SE ELIMINA: ninguna de sus órdenes ha empezado.'
                            : 'La lista se descarta pero NO se elimina: ya hay órdenes corriendo.'"
                        :selected="$newStatus === 'canceled'"
                        wire:click="$set('newStatus', 'canceled')" />
                </div>

                @error('newStatus')
                    <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
                @enderror

                @if ($newStatus === 'canceled')
                    @if ($runningWOs->isEmpty())
                        <x-ui.note tone="danger" class="mt-4" title="Esta lista se va a ELIMINAR">
                            Ninguna de sus órdenes tiene trabajo registrado, así que al cancelarla se borra.
                            Las Work Orders y sus lotes <strong>no</strong> se borran.
                            <strong>Esta acción no se puede deshacer.</strong>
                        </x-ui.note>
                    @else
                        <x-ui.note tone="warn" class="mt-4"
                            title="La lista se conserva: {{ $runningWOs->count() }} {{ Str::plural('orden', $runningWOs->count()) }} ya {{ $runningWOs->count() === 1 ? 'está' : 'están' }} corriendo">
                            <span class="mb-2 block">Se marcará como cancelada, pero no se elimina porque ya hay trabajo registrado en:</span>
                            <span class="flex flex-wrap gap-1.5">
                                @foreach ($runningWOs->take(6) as $rwo)
                                    <x-ui.badge tone="warn">{{ $rwo->purchaseOrder->wo ?? $rwo->wo_number }}</x-ui.badge>
                                @endforeach
                                @if ($runningWOs->count() > 6)
                                    <x-ui.badge tone="neutral">+{{ $runningWOs->count() - 6 }} más</x-ui.badge>
                                @endif
                            </span>
                        </x-ui.note>
                    @endif
                @endif
            </x-ui.section>

            <x-slot:note>
                Cancelar elimina la lista sólo si ninguna de sus órdenes empezó a trabajarse.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeStatusModal">Cancelar</x-ui.btn>

                @if ($newStatus === 'canceled' && $runningWOs->isEmpty())
                    <x-ui.btn variant="danger" wire:click="saveStatus"
                        wire:confirm="Se va a ELIMINAR la lista #{{ $modalList->id }}. Esta acción no se puede deshacer. ¿Continuar?"
                        wire:loading.attr="disabled" wire:target="saveStatus">Cancelar y eliminar lista</x-ui.btn>
                @else
                    <x-ui.btn variant="primary" wire:click="saveStatus"
                        wire:loading.attr="disabled" wire:target="saveStatus">Guardar estado</x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
