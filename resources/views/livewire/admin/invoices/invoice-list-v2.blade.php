<div class="space-y-6">
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
                    'bg-blue-600 text-white': toast.type === 'info',
                    'bg-gray-800 text-white': !['success', 'error', 'danger', 'warning', 'info'].includes(toast.type),
                }"
                class="pointer-events-auto flex min-w-[260px] max-w-sm items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
            >
                <template x-if="toast.type === 'success'">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </template>
                <template x-if="toast.type === 'error' || toast.type === 'danger'">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                </template>
                <span x-text="toast.message" class="flex-1"></span>
                <button type="button" @click="remove(toast.id)" class="shrink-0 opacity-70 transition-opacity hover:opacity-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Invoices</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gestion de documentos de facturacion</p>
        </div>

        @if ($pendingPackingSlips->count() > 0)
            <div class="inline-flex items-center gap-2 self-start rounded-full border-2 border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                <span class="h-2 w-2 rounded-full bg-blue-500 dark:bg-blue-400"></span>
                {{ $pendingPackingSlips->count() }} pendiente(s) por facturar
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Total</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['total'] }}</div>
        </div>
        <div class="rounded-lg border-2 border-yellow-200 bg-white p-4 dark:border-yellow-800 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Borrador</div>
            <div class="mt-1 text-2xl font-semibold text-yellow-600 dark:text-yellow-400">{{ $stats['draft'] }}</div>
        </div>
        <div class="rounded-lg border-2 border-green-200 bg-white p-4 dark:border-green-800 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Emitido</div>
            <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $stats['issued'] }}</div>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border-2 border-green-200 bg-green-50 px-4 py-3 dark:border-green-800 dark:bg-green-900/20" role="alert">
            <p class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border-2 border-red-200 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-900/20" role="alert">
            <p class="text-sm text-red-700 dark:text-red-300">{{ session('error') }}</p>
        </div>
    @endif

    @if ($pendingPackingSlips->count() > 0)
        <div class="overflow-hidden rounded-lg border-2 border-blue-200 bg-white dark:border-blue-800 dark:bg-gray-800">
            <div class="flex flex-col gap-3 border-b border-blue-200 bg-blue-50 px-4 py-4 dark:border-blue-800 dark:bg-blue-900/20 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-200">Packing Slips listos para Invoice</h2>
                    <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        Estos Packing Slips ya fueron despachados y aun no tienen un Invoice generado.
                    </p>
                </div>
                <span class="inline-flex items-center self-start rounded-full border border-blue-200 bg-white px-2.5 py-1 text-xs font-medium text-blue-700 dark:border-blue-700 dark:bg-blue-950/40 dark:text-blue-200">
                    {{ $pendingPackingSlips->count() }} pendiente(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">PS #</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Fecha de envio</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Items</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Accion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                        @foreach ($pendingPackingSlips as $ps)
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a
                                        href="{{ route('admin.packing-slips.show', $ps) }}"
                                        wire:navigate
                                        class="font-mono text-sm font-medium text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        {{ $ps->ps_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                    {{ $ps->shipped_at?->format('d/m/Y H:i') ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $ps->items->count() }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <button
                                        wire:click="createInvoice({{ $ps->id }})"
                                        wire:confirm="Se generara un Invoice en estado borrador desde el Packing Slip {{ $ps->ps_number }}. Desea continuar?"
                                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Crear Invoice
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @php
        $hasActiveFilters = $search !== '' || $filterStatus !== 'all' || $perPage !== 15;
    @endphp

    <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Buscar</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Buscar por numero de invoice..."
                        class="block w-full rounded-md border-2 border-gray-200 bg-white py-2 pr-4 pl-10 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                </div>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                <select
                    wire:model.live="filterStatus"
                    class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    <option value="all">Todos</option>
                    <option value="draft">Borrador</option>
                    <option value="issued">Emitido</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Por pagina</label>
                <select
                    wire:model.live="perPage"
                    class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>

        @if ($hasActiveFilters)
            <div class="mt-4 flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                <span class="text-sm text-gray-600 dark:text-gray-400">Resultados filtrados</span>
                <button
                    wire:click="$set('search', ''); $set('filterStatus', 'all'); $set('perPage', 15)"
                    class="text-sm font-medium text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                >
                    Limpiar filtros
                </button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            <button wire:click="sortBy('invoice_number')" class="flex items-center gap-2 transition-colors hover:text-gray-900 dark:hover:text-white">
                                Invoice #
                                @if ($sortField === 'invoice_number')
                                    <svg class="h-4 w-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            <button wire:click="sortBy('invoice_date')" class="flex items-center gap-2 transition-colors hover:text-gray-900 dark:hover:text-white">
                                Fecha
                                @if ($sortField === 'invoice_date')
                                    <svg class="h-4 w-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Packing Slip</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            <button wire:click="sortBy('grand_total')" class="ml-auto flex items-center gap-2 transition-colors hover:text-gray-900 dark:hover:text-white">
                                Total
                                @if ($sortField === 'grand_total')
                                    <svg class="h-4 w-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                    @forelse ($invoices as $invoice)
                        @php
                            $badgeClasses = match ($invoice->status) {
                                'draft' => 'border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                'issued' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300',
                                'cancelled' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300',
                                default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300',
                            };
                        @endphp

                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a
                                    href="{{ route('admin.invoices.show', $invoice->invoice_number) }}"
                                    wire:navigate
                                    class="font-mono text-sm font-semibold text-gray-900 transition-colors hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                >
                                    Invoice#{{ $invoice->invoice_number }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                {{ $invoice->invoice_date?->format('d/m/Y') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($invoice->packingSlip)
                                    <a
                                        href="{{ route('admin.packing-slips.show', $invoice->packingSlip) }}"
                                        wire:navigate
                                        class="font-mono text-sm text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        {{ $invoice->packingSlip->ps_number }}
                                    </a>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $invoice->formattedTotal }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex rounded-full border-2 px-3 py-1 text-xs font-medium {{ $badgeClasses }}">
                                    {{ $invoice->statusLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        href="{{ route('admin.invoices.show', $invoice->invoice_number) }}"
                                        wire:navigate
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-blue-600 transition-colors hover:border-blue-300 hover:bg-blue-50 dark:text-blue-400 dark:hover:border-blue-700 dark:hover:bg-blue-900/20"
                                        title="Ver invoice"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    @if ($invoice->isPdfAvailable())
                                        <a
                                            href="{{ route('admin.invoices.pdf.stream', $invoice->invoice_number) }}"
                                            target="_blank"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 dark:text-red-400 dark:hover:border-red-700 dark:hover:bg-red-900/20"
                                            title="Ver PDF"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </a>
                                    @endif

                                    @if ($invoice->isDraft() || $invoice->isCancelled())
                                        <button
                                            wire:click="deleteInvoice({{ $invoice->id }})"
                                            wire:confirm="Eliminar Invoice #{{ $invoice->invoice_number }}. Esta accion no se puede deshacer. Desea continuar?"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 dark:text-red-400 dark:hover:border-red-700 dark:hover:bg-red-900/20"
                                            title="Eliminar invoice"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                                        <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-medium text-gray-900 dark:text-white">No se encontraron invoices</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajusta los filtros o crea un invoice desde un Packing Slip enviado.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/50">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
