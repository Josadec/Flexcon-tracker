<x-ui.page eyebrow="Embarque" title="Shipping List"
    subtitle="Gestión de documentos de empaque, seguimiento operativo y salida a embarque.">

    <x-slot:actions>
        @if ($activeTab === 'list')
            <x-ui.btn variant="primary" href="{{ route('admin.shipping-list.create') }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo Shipping List
            </x-ui.btn>
        @endif
    </x-slot:actions>

    {{-- Navegación por tabs --}}
    <div class="-mt-1 border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex gap-1" aria-label="Secciones de Shipping List">
            <button type="button" wire:click="setTab('queue')"
                aria-current="{{ $activeTab === 'queue' ? 'page' : 'false' }}"
                class="flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors
                    {{ $activeTab === 'queue'
                        ? 'border-sky-600 text-sky-700 dark:border-sky-400 dark:text-sky-300'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h8"/></svg>
                WO Listos para SL
            </button>
            <button type="button" wire:click="setTab('list')"
                aria-current="{{ $activeTab === 'list' ? 'page' : 'false' }}"
                class="flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors
                    {{ $activeTab === 'list'
                        ? 'border-sky-600 text-sky-700 dark:border-sky-400 dark:text-sky-300'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Shipping List
            </button>
        </nav>
    </div>

    {{-- TAB: WO listos para SL (cola embebida) --}}
    @if ($activeTab === 'queue')
        @livewire('admin.shipping.shipping-queue', ['embedded' => true], key('shipping-queue-tab'))
    @endif

    {{-- TAB: Shipping List --}}
    @if ($activeTab === 'list')

        <x-ui.stats cols="5">
            <x-ui.stat label="Total" :value="number_format($stats['total'])" />
            <x-ui.stat label="Borrador" :value="number_format($stats['draft'])" tone="warn" />
            <x-ui.stat label="Pendiente" :value="number_format($stats['pending'])" tone="info" />
            <x-ui.stat label="Despachado" :value="number_format($stats['shipped'])" tone="good" />
            <x-ui.stat label="Cancelado" :value="number_format($stats['cancelled'])" tone="bad" />
        </x-ui.stats>

        @if (session('error'))
            <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
        @endif

        {{-- Filtros --}}
        <x-ui.section title="Buscar" hint="Filtra por número de PS, por estado y ajusta cuántos ver por página.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
                <x-ui.field label="Texto a buscar">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="text" wire:model.live.debounce.300ms="search"
                            placeholder="Número de PS..." class="w-full pl-10">
                    </div>
                </x-ui.field>

                <x-ui.field label="Estado">
                    <select wire:model.live="filterStatus" class="w-full">
                        <option value="all">Todos</option>
                        <option value="draft">Borrador</option>
                        <option value="pending">Pendiente</option>
                        <option value="shipped">Despachado</option>
                        <option value="cancelled">Cancelado</option>
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

            @if ($search !== '' || $filterStatus !== 'all' || $perPage !== 10)
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                    <x-ui.btn variant="ghost" size="sm"
                        wire:click="$set('search', ''); $set('filterStatus', 'all'); $set('perPage', 10)">
                        Limpiar filtros
                    </x-ui.btn>
                </div>
            @endif
        </x-ui.section>

        {{-- Listado --}}
        @php
            $statusTone = fn (string $s) => match ($s) {
                'draft' => 'warn', 'pending' => 'info', 'shipped' => 'good', 'cancelled' => 'bad', default => 'neutral',
            };
        @endphp
        <x-ui.table>
            <x-slot:head>
                <tr>
                    <x-ui.th sort="ps_number" :field="$sortField" :direction="$sortDirection">PS Number</x-ui.th>
                    <x-ui.th>Estado</x-ui.th>
                    <x-ui.th align="right">Items</x-ui.th>
                    <x-ui.th align="right">Piezas</x-ui.th>
                    <x-ui.th>Creado por</x-ui.th>
                    <x-ui.th sort="created_at" :field="$sortField" :direction="$sortDirection">Fecha creación</x-ui.th>
                    <x-ui.th>Despacho</x-ui.th>
                    <x-ui.th>Invoice</x-ui.th>
                    <x-ui.th align="right">Acciones</x-ui.th>
                </tr>
            </x-slot:head>

            @forelse ($packingSlips as $ps)
                <tr wire:key="ps-{{ $ps->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="whitespace-nowrap px-4 py-3">
                        <a href="{{ route('admin.shipping-list.show', $ps) }}" wire:navigate
                            class="font-mono font-semibold text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">
                            {{ $ps->ps_number }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :tone="$statusTone($ps->status)" dot>{{ $ps->statusLabel }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-900 dark:text-white">{{ number_format($ps->items->count()) }}</td>
                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($ps->total_quantity) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $ps->creator?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $ps->created_at->format('d/m/Y H:i') }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $ps->shipped_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($ps->hasInvoice())
                            <x-ui.badge tone="good">Generado</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral">Pendiente</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.row-actions>
                            @if ($ps->status === 'shipped' || $ps->status === 'cancelled')
                                <x-ui.icon-btn tone="danger" :navigate="false" target="_blank"
                                    href="{{ route('admin.shipping-list.pdf', $ps) }}" label="Ver PDF del {{ $ps->ps_number }}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6M9 17h4"/></svg>
                                </x-ui.icon-btn>
                            @endif
                            <x-ui.icon-btn tone="primary" href="{{ route('admin.shipping-list.show', $ps) }}"
                                label="Ver el {{ $ps->ps_number }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </x-ui.icon-btn>
                            <x-ui.icon-btn tone="danger" wire:click="confirmDeletion({{ $ps->id }})"
                                label="Eliminar el {{ $ps->ps_number }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </x-ui.icon-btn>
                        </x-ui.row-actions>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        <x-ui.empty icon="doc" title="No se encontraron Shipping Lists"
                            hint="Ajusta los filtros o crea un nuevo documento de empaque.">
                            <x-slot:action>
                                <x-ui.btn variant="primary" href="{{ route('admin.shipping-list.create') }}">Nuevo Shipping List</x-ui.btn>
                            </x-slot:action>
                        </x-ui.empty>
                    </td>
                </tr>
            @endforelse

            @if ($packingSlips->hasPages())
                <x-slot:foot>{{ $packingSlips->links() }}</x-slot:foot>
            @endif
        </x-ui.table>

        {{-- Confirmación de borrado --}}
        @if ($confirmingDeletion)
            <x-ui-modal wire:key="modal-ps-delete" title="Eliminar Shipping List"
                subtitle="Esta acción no se puede deshacer." close="cancelDeletion" maxWidth="lg">
                <x-ui.note tone="danger" title="Se eliminará el documento de empaque">
                    Los lotes asociados volverán a quedar disponibles en la cola de despacho.
                </x-ui.note>
                <x-slot:footer>
                    <x-ui.btn variant="secondary" wire:click="cancelDeletion">Cancelar</x-ui.btn>
                    <x-ui.btn variant="danger" wire:click="delete">Eliminar</x-ui.btn>
                </x-slot:footer>
            </x-ui-modal>
        @endif

    @endif
</x-ui.page>
