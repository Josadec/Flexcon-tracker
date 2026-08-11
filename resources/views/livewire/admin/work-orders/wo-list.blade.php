<x-ui.page eyebrow="Órdenes" title="Órdenes de trabajo"
    subtitle="Cada PO aprobada abre una WO. Desde aquí se sigue su avance, su estado y sus envíos.">

    {{-- Resumen: total + una métrica por estado del catálogo (los estados son
         dinámicos, se administran desde el modal de la ficha del WO). --}}
    @php
        // Tono semántico por estado conocido; los estados nuevos caen en neutral.
        $statusTone = fn (string $name) => match (Str::lower($name)) {
            'open'        => 'info',
            'in progress' => 'warn',
            'completed'   => 'good',
            'cancelled'   => 'bad',
            default       => 'neutral',
        };
    @endphp
    <x-ui.stats :cols="min(6, 1 + $statuses->count())">
        <x-ui.stat label="Total" :value="$totalWOs" />
        @foreach ($statuses as $status)
            <x-ui.stat wire:key="stat-status-{{ $status->id }}"
                :label="$status->name" :value="$statusCounts[$status->id] ?? 0"
                :tone="$statusTone($status->name)" />
        @endforeach
    </x-ui.stats>

    @if (session('success'))
        <x-ui.note tone="success">{{ session('success') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por número de WO, de PO o de parte, por estado y por fecha de apertura.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_10rem_10rem_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="WO#, PO# o parte..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Desde" hint="Fecha de apertura.">
                <input type="date" wire:model.live="startDate" class="w-full">
            </x-ui.field>

            <x-ui.field label="Hasta" hint="Fecha de apertura.">
                <input type="date" wire:model.live="endDate" class="w-full">
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([5, 10, 25, 50] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search || $filterStatus || $startDate || $endDate)
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
                <x-ui.th sort="wo_number" :field="$sortField" :direction="$sortDirection" class="w-24">ID</x-ui.th>
                <x-ui.th>WO</x-ui.th>
                <x-ui.th>PO / Parte</x-ui.th>
                <x-ui.th sort="opened_date" :field="$sortField" :direction="$sortDirection">Fecha apertura</x-ui.th>
                <x-ui.th align="right">Cantidad</x-ui.th>
                <x-ui.th sort="status_id" :field="$sortField" :direction="$sortDirection">Estado</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($workOrders as $wo)
            <tr wire:key="wo-{{ $wo->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums text-slate-900 dark:text-white">
                    {{ str_pad(substr($wo->wo_number, strrpos($wo->wo_number, '-') + 1), 4, '0', STR_PAD_LEFT) }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-sky-700 dark:text-sky-300">
                    {{ $wo->purchaseOrder->wo ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-900 dark:text-white">{{ $wo->purchaseOrder->po_number ?? '—' }}</div>
                    @if ($wo->purchaseOrder && $wo->purchaseOrder->part)
                        <div class="max-w-xs truncate text-xs text-slate-500 dark:text-slate-400"
                            title="{{ $wo->purchaseOrder->part->description }}">
                            {{ $wo->purchaseOrder->part->number }} — {{ Str::limit($wo->purchaseOrder->part->description, 25) }}
                        </div>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <div class="text-slate-900 dark:text-white">{{ $wo->opened_date->format('d/m/Y') }}</div>
                    @if ($wo->scheduled_send_date)
                        <div class="text-xs text-slate-500 dark:text-slate-400">Envío: {{ $wo->scheduled_send_date->format('d/m/Y') }}</div>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <div class="font-semibold tabular-nums text-slate-900 dark:text-white">
                        {{ number_format($wo->sent_pieces) }} / {{ number_format($wo->original_quantity) }}
                    </div>
                    <div class="text-xs tabular-nums text-slate-500 dark:text-slate-400">Pendiente: {{ number_format($wo->pending_quantity) }}</div>
                </td>
                <td class="px-4 py-3">
                    {{-- El color del estado es dato del catálogo, no del sistema de tonos. --}}
                    <span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold text-white"
                        style="background-color: {{ $wo->status->color }}">{{ $wo->status->name }}</span>
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="la orden {{ $wo->purchaseOrder->wo ?? $wo->wo_number }}"
                        :show="route('admin.work-orders.show', $wo)"
                        :edit="route('admin.work-orders.edit', $wo)"
                        :delete="$wo->canBeDeleted() ? 'deleteWorkOrder('.$wo->id.')' : null"
                        deleteConfirm="¿Eliminar la orden {{ $wo->purchaseOrder->wo ?? $wo->wo_number }}? Se eliminarán también sus lotes, pesadas y registros relacionados. Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty icon="search" title="No se encontraron órdenes de trabajo"
                        hint="Ajusta la búsqueda, el estado o el rango de fechas. Las órdenes de trabajo se crean al aprobar una orden de compra." >
                        <x-slot:action>
                            <x-ui.btn variant="secondary" href="{{ route('admin.purchase-orders.index') }}">Ir a órdenes de compra</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($workOrders->hasPages())
            <x-slot:foot>{{ $workOrders->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
