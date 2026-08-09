<x-ui.page eyebrow="Facturación" title="Invoices"
    subtitle="Gestión de documentos de facturación. Cada Invoice nace de un Packing Slip despachado.">

    {{-- Toasts para las acciones AJAX que se quedan en pantalla (crear/eliminar
         desde la lista). Redirige + flash.banner cubre las que navegan; esto
         cubre las que no. --}}
    <div
        x-data="{
            toasts: [],
            add(type, message) {
                const id = Date.now();
                this.toasts.push({ id, type, message });
                setTimeout(() => this.remove(id), 7000);
            },
            remove(id) {
                this.toasts = this.toasts.filter(t => t.id !== id);
            }
        }"
        x-on:notify.window="add($event.detail[0]?.type ?? $event.detail?.type ?? 'success', $event.detail[0]?.message ?? $event.detail?.message ?? '')"
        class="pointer-events-none fixed top-5 right-5 z-50 flex flex-col gap-2"
        aria-live="polite"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-show="true"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                :class="{
                    'bg-green-600 text-white': toast.type === 'success',
                    'bg-red-600 text-white': toast.type === 'error' || toast.type === 'danger',
                    'bg-amber-500 text-white': toast.type === 'warning',
                    'bg-sky-600 text-white': toast.type === 'info',
                    'bg-slate-800 text-white': !['success', 'error', 'danger', 'warning', 'info'].includes(toast.type),
                }"
                class="pointer-events-auto flex min-w-[260px] max-w-sm items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
            >
                <span x-text="toast.message" class="flex-1"></span>
                <button type="button" @click="remove(toast.id)" class="shrink-0 opacity-70 transition-opacity hover:opacity-100" aria-label="Cerrar aviso">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total" :value="$stats['total']" />
        <x-ui.stat label="Borrador" :value="$stats['draft']" tone="warn" />
        <x-ui.stat label="Emitido" :value="$stats['issued']" tone="good" />
        <x-ui.stat label="Cancelado" :value="$stats['cancelled']" tone="bad" />
    </x-ui.stats>

    @if (session('success'))
        <x-ui.note tone="success">{{ session('success') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Packing Slips listos para facturar --}}
    @if ($pendingPackingSlips->count() > 0)
        <x-ui.table title="Packing Slips listos para Invoice"
            hint="Ya fueron despachados y aún no tienen un Invoice generado.">
            <x-slot:aside>
                <x-ui.badge tone="info" dot>{{ $pendingPackingSlips->count() }} pendiente(s)</x-ui.badge>
            </x-slot:aside>

            <x-slot:head>
                <tr>
                    <x-ui.th>PS #</x-ui.th>
                    <x-ui.th>Fecha de envío</x-ui.th>
                    <x-ui.th align="right">Ítems</x-ui.th>
                    <x-ui.th align="right">Acción</x-ui.th>
                </tr>
            </x-slot:head>

            @foreach ($pendingPackingSlips as $ps)
                <tr wire:key="pending-ps-{{ $ps->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="whitespace-nowrap px-4 py-3">
                        <a href="{{ route('admin.shipping-list.show', $ps) }}" wire:navigate
                            class="font-mono font-semibold text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">
                            {{ $ps->ps_number }}
                        </a>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                        {{ $ps->shipped_at?->format('d/m/Y H:i') ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">
                        {{ $ps->items->count() }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <x-ui.btn variant="primary" size="sm"
                            wire:click="createInvoice({{ $ps->id }})"
                            wire:confirm="Se generará un Invoice en estado borrador desde el Packing Slip {{ $ps->ps_number }}. ¿Desea continuar?">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Crear Invoice
                        </x-ui.btn>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por número de Invoice y por estado del documento.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Número de invoice..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="all">Todos</option>
                    <option value="draft">Borrador</option>
                    <option value="issued">Emitido</option>
                    <option value="cancelled">Cancelado</option>
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

        @if ($search || $filterStatus !== 'all' || $perPage !== 15)
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="$set('search', ''); $set('filterStatus', 'all'); $set('perPage', 15)">
                    Limpiar filtros
                </x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th sort="invoice_number" :field="$sortField" :direction="$sortDirection">Invoice #</x-ui.th>
                <x-ui.th sort="invoice_date" :field="$sortField" :direction="$sortDirection">Fecha</x-ui.th>
                <x-ui.th>Packing Slip</x-ui.th>
                <x-ui.th sort="grand_total" :field="$sortField" :direction="$sortDirection" align="right">Total</x-ui.th>
                <x-ui.th>Estado</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($invoices as $invoice)
            @php
                $tone = match ($invoice->status) {
                    'draft'     => 'warn',
                    'issued'    => 'good',
                    'cancelled' => 'bad',
                    default     => 'neutral',
                };
            @endphp
            <tr wire:key="invoice-{{ $invoice->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3">
                    <a href="{{ route('admin.invoices.show', $invoice->invoice_number) }}" wire:navigate
                        class="font-mono font-semibold text-slate-900 hover:text-sky-700 dark:text-white dark:hover:text-sky-300">
                        Invoice#{{ $invoice->invoice_number }}
                    </a>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $invoice->invoice_date?->format('d/m/Y') ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($invoice->packingSlip)
                        <a href="{{ route('admin.shipping-list.show', $invoice->packingSlip) }}" wire:navigate
                            class="font-mono text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">
                            {{ $invoice->packingSlip->ps_number }}
                        </a>
                    @else
                        <span class="text-slate-400 dark:text-slate-500">—</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">
                    {{ $invoice->formattedTotal }}
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$tone" dot>{{ $invoice->statusLabel }}</x-ui.badge>
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el invoice #{{ $invoice->invoice_number }}">
                        <x-ui.icon-btn tone="neutral" label="Ver el invoice #{{ $invoice->invoice_number }}"
                            href="{{ route('admin.invoices.show', $invoice->invoice_number) }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </x-ui.icon-btn>

                        @if ($invoice->isPdfAvailable())
                            <x-ui.icon-btn tone="danger" label="Ver el PDF del invoice #{{ $invoice->invoice_number }}"
                                href="{{ route('admin.invoices.pdf.stream', $invoice->invoice_number) }}"
                                :navigate="false" target="_blank">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </x-ui.icon-btn>
                        @endif

                        @if ($invoice->isDraft() || $invoice->isCancelled())
                            <x-ui.icon-btn tone="danger" label="Eliminar el invoice #{{ $invoice->invoice_number }}"
                                wire:click="deleteInvoice({{ $invoice->id }})"
                                wire:confirm="Eliminar Invoice #{{ $invoice->invoice_number }}. Esta acción no se puede deshacer. ¿Desea continuar?">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </x-ui.icon-btn>
                        @endif
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty icon="doc" title="No se encontraron invoices"
                        hint="Ajusta los filtros o crea un invoice desde un Packing Slip despachado." />
                </td>
            </tr>
        @endforelse

        @if ($invoices->hasPages())
            <x-slot:foot>{{ $invoices->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
