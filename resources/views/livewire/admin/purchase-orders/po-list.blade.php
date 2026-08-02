<x-ui.page eyebrow="Compras" title="Órdenes de compra"
    subtitle="Cada PO aprobada genera la Work Order con la que arranca producción.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.purchase-orders.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva PO
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total" :value="$totalPOs" />
        <x-ui.stat label="Pendientes" :value="$pendingPOs" tone="warn"
            help="Esperan aprobación para generar su Work Order." />
        <x-ui.stat label="Aprobadas" :value="$approvedPOs" tone="good" />
        <x-ui.stat label="En corrección" :value="$pendingCorrectionPOs" tone="bad"
            help="Tienen una observación que Compras debe atender." />
    </x-ui.stats>

    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por número de PO, WO, parte o estado.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Número de PO, WO o parte..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="all">Todos</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([5, 10, 25, 50] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search || $filterStatus !== 'all')
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="$set('search', ''); $set('filterStatus', 'all')">
                    Limpiar filtros
                </x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    @php
        $statusTones = [
            'pending'            => 'warn',
            'approved'           => 'good',
            'rejected'           => 'bad',
            'pending_correction' => 'warn',
        ];
    @endphp
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th sort="po_number" :field="$sortField" :direction="$sortDirection"># PO</x-ui.th>
                <x-ui.th>WO</x-ui.th>
                <x-ui.th>Parte</x-ui.th>
                <x-ui.th sort="quantity" :field="$sortField" :direction="$sortDirection" align="right">Cantidad</x-ui.th>
                <x-ui.th sort="unit_price" :field="$sortField" :direction="$sortDirection" align="right">Precio unit.</x-ui.th>
                <x-ui.th sort="due_date" :field="$sortField" :direction="$sortDirection">Fecha entrega</x-ui.th>
                <x-ui.th sort="status" :field="$sortField" :direction="$sortDirection">Estado</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($purchaseOrders as $po)
            @php $deleteBlockReason = $po->getDeletionBlockReason(); @endphp
            <tr wire:key="po-{{ $po->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3">
                    <div class="font-semibold text-slate-900 dark:text-white">{{ $po->po_number }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $po->po_date->format('d/m/Y') }}</div>
                </td>
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-sky-700 dark:text-sky-300">{{ $po->wo ?? '—' }}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-900 dark:text-white">{{ $po->part->number }}</div>
                    <div class="max-w-xs truncate text-xs text-slate-500 dark:text-slate-400">{{ Str::limit($po->part->description, 40) ?: '—' }}</div>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-900 dark:text-white">{{ number_format($po->quantity) }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">${{ number_format($po->unit_price, 4) }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $po->due_date->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$statusTones[$po->status] ?? 'neutral'" dot>{{ $po->status_label }}</x-ui.badge>
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="la PO {{ $po->po_number }}"
                        :show="route('admin.purchase-orders.show', $po)"
                        :edit="route('admin.purchase-orders.edit', $po)"
                        :delete="$deleteBlockReason === null ? 'deletePO('.$po->id.')' : null"
                        deleteConfirm="¿Eliminar la orden de compra {{ $po->po_number }}? Se eliminará también su Work Order asociada. Esta acción no se puede deshacer.">

                        {{-- Aprobar/rechazar sólo mientras está pendiente. --}}
                        @if ($po->status === 'pending')
                            <x-ui.icon-btn tone="success" label="Aprobar la PO {{ $po->po_number }}"
                                wire:click="approve({{ $po->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-ui.icon-btn>
                            <x-ui.icon-btn tone="danger" label="Rechazar la PO {{ $po->po_number }}"
                                wire:click="reject({{ $po->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </x-ui.icon-btn>
                        @endif

                        {{-- Si no se puede borrar, se explica por qué en lugar de esconder el botón. --}}
                        @if ($deleteBlockReason !== null)
                            <span class="ui-icon-btn cursor-not-allowed text-slate-300 dark:text-slate-600"
                                title="No se puede eliminar: {{ $deleteBlockReason }}"
                                aria-label="No se puede eliminar: {{ $deleteBlockReason }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                        @endif
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    <x-ui.empty icon="search" title="No se encontraron órdenes de compra"
                        hint="Ajusta los filtros o captura una PO nueva.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.purchase-orders.create') }}">Nueva PO</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($purchaseOrders->hasPages())
            <x-slot:foot>{{ $purchaseOrders->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
