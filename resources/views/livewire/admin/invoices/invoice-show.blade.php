<div class="py-12">

    {{-- Toast de notificaciones (fixed, no afecta el layout) --}}
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
        class="fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"
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
                    'bg-red-600 text-white':   toast.type === 'error' || toast.type === 'danger',
                    'bg-amber-500 text-white': toast.type === 'warning',
                    'bg-blue-600 text-white':  toast.type === 'info',
                    'bg-gray-800 text-white':  !['success','error','danger','warning','info'].includes(toast.type),
                }"
                class="pointer-events-auto flex items-center gap-3 min-w-[260px] max-w-sm px-4 py-3 rounded-lg shadow-lg text-sm font-medium"
            >
                <template x-if="toast.type === 'success'">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </template>
                <template x-if="toast.type === 'error' || toast.type === 'danger'">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
                    </svg>
                </template>
                <span x-text="toast.message" class="flex-1"></span>
                <button @click="remove(toast.id)" class="shrink-0 opacity-70 hover:opacity-100 transition-opacity">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </template>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center space-x-3">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white font-mono">
                            Invoice#{{ $invoice->invoice_number }}
                        </h1>
                        <div class="flex items-center mt-2 space-x-3">
                            @php
                                $badgeClasses = match($invoice->status) {
                                    'draft'     => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                    'issued'    => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                    'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                    default     => 'bg-gray-100 text-gray-800',
                                };
                            @endphp
                            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $badgeClasses }}">
                                {{ $invoice->statusLabel }}
                            </span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Invoice</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 sm:mt-0 flex flex-wrap gap-2 items-center">

                    {{-- Acciones según estado --}}
                    @if ($invoice->isDraft())
                        <button wire:click="confirmIssue"
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Emitir Invoice
                        </button>
                        <button wire:click="confirmCancel"
                                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancelar Invoice
                        </button>
                    @endif

                    @if ($invoice->isPdfAvailable())
                        <a href="{{ route('admin.invoices.pdf.stream', $invoice->invoice_number) }}"
                           target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            Ver PDF
                        </a>
                        <a href="{{ route('admin.invoices.pdf', $invoice->invoice_number) }}"
                           class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Descargar PDF
                        </a>
                    @endif

                    <a href="{{ route('admin.invoices.index') }}" wire:navigate
                       class="inline-flex items-center px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Volver a la lista
                    </a>

                    {{-- Separador visual: el boton Eliminar es destructivo, se separa del resto --}}
                    @if ($invoice->isDraft() || $invoice->isCancelled())
                        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 self-center mx-1"></div>
                        <button wire:click="confirmDelete"
                                class="inline-flex items-center px-4 py-2 bg-white hover:bg-red-50 text-red-600 border border-red-300 hover:border-red-400 text-sm font-medium rounded-lg shadow-sm transition-colors duration-200 dark:bg-gray-800 dark:text-red-400 dark:border-red-700 dark:hover:bg-red-900/20">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Eliminar
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Banner de advertencia: items sin precio --}}
        @if ($zeroUnitCostCount > 0)
            <div x-data="{ show: true }" x-show="show"
                 class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-xl p-4 flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                        Hay {{ $zeroUnitCostCount }} item(s) sin precio definido (Unit Cost = $0.0000)
                    </p>
                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                        Puede emitir el Invoice de todas formas o editar los precios antes de emitir.
                    </p>
                </div>
                <button @click="show = false" class="text-amber-500 hover:text-amber-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        @endif

        <!-- Info General -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Información General</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Número de Invoice</p>
                        <p class="text-base font-mono text-gray-900 dark:text-white mt-1">Invoice#{{ $invoice->invoice_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha del Invoice</p>
                        <p class="text-base text-gray-900 dark:text-white mt-1">{{ $invoice->invoice_date?->format('d/m/Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Packing Slip de origen</p>
                        @if ($invoice->packingSlip)
                            <a href="{{ route('admin.packing-slips.show', $invoice->packingSlip) }}"
                               wire:navigate
                               class="text-base font-mono text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mt-1 block">
                                {{ $invoice->packingSlip->ps_number }}
                            </a>
                        @else
                            <p class="text-base text-gray-400 mt-1">-</p>
                        @endif
                    </div>


                    @if ($invoice->isIssued())
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Emitido por</p>
                            <p class="text-base text-gray-900 dark:text-white mt-1">{{ $invoice->issuer?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de emisión</p>
                            <p class="text-base text-gray-900 dark:text-white mt-1">{{ $invoice->issued_at?->format('d/m/Y H:i') ?? '-' }}</p>
                        </div>
                    @endif

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Creado por</p>
                        <p class="text-base text-gray-900 dark:text-white mt-1">{{ $invoice->creator?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de creación</p>
                        <p class="text-base text-gray-900 dark:text-white mt-1">{{ $invoice->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de items de producto -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Items del Invoice
                    </h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $invoice->productItems->count() }} {{ $invoice->productItems->count() === 1 ? 'item' : 'items' }}
                    </span>
                </div>

                @if ($invoice->productItems->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Item No.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">LOT NO.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">P.O No.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">W/O</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Unit Cost</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                @foreach ($invoice->productItems as $item)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-100
                                        {{ (float) $item->unit_cost === 0.0 ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white max-w-xs truncate">
                                            {{ $item->description ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $item->item_number ?? '-' }}
                                        </td>
                                        {{-- LOT NO. editable por item en draft --}}
                                        <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">
                                            @if ($invoice->isDraft() && $editingLotItemId === $item->id)
                                                <div class="flex items-center gap-1">
                                                    <input
                                                        wire:model="editingLotItemValue"
                                                        type="text"
                                                        class="border border-indigo-400 rounded px-2 py-0.5 text-sm w-28 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white font-mono"
                                                        autofocus
                                                        wire:keydown.enter="saveLotItem"
                                                        wire:keydown.escape="cancelEditingLotItem"
                                                    >
                                                    <button wire:click="saveLotItem" class="text-green-600 hover:text-green-800 transition-colors" title="Guardar">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </button>
                                                    <button wire:click="cancelEditingLotItem" class="text-gray-400 hover:text-gray-600 transition-colors" title="Cancelar">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-1 group">
                                                    <span>{{ $item->lot_number ?? '-' }}</span>
                                                    @if ($invoice->isDraft())
                                                        <button wire:click="startEditingLotItem({{ $item->id }})"
                                                                class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-indigo-600 transition-all cursor-pointer"
                                                                title="Editar LOT NO.">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $item->po_number ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">
                                            {{ $item->wo_number ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">
                                            {{ number_format($item->quantity) }}
                                        </td>
                                        {{-- Unit Cost (solo lectura) --}}
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                            <span class="{{ (float) $item->unit_cost === 0.0 ? 'text-amber-600 dark:text-amber-400' : '' }}">
                                                ${{ $item->formattedUnitCost }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">
                                            ${{ $item->formattedTotalCost }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 bg-gray-50 dark:bg-gray-900 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Este Invoice no tiene items de producto.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sección de cargos fijos -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Cargos Adicionales</h2>
                @if ($invoice->chargeItems->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Descripción</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                @foreach ($invoice->chargeItems as $charge)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-100">
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $charge->description }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                            @if ($invoice->canBeModified() && $editingChargeId === $charge->id)
                                                <div class="flex items-center justify-end gap-1">
                                                    <input
                                                        wire:model="editingChargeAmount"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="border border-indigo-400 rounded px-2 py-0.5 text-sm w-28 text-right focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white"
                                                        autofocus
                                                        wire:keydown.enter="updateChargeAmount"
                                                        wire:keydown.escape="cancelEditingCharge"
                                                    >
                                                    <button wire:click="updateChargeAmount" class="text-green-600 hover:text-green-800 transition-colors" title="Guardar">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </button>
                                                    <button wire:click="cancelEditingCharge" class="text-gray-400 hover:text-gray-600 transition-colors" title="Cancelar">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @else
                                                <div class="flex items-center justify-end gap-1 group">
                                                    <span>${{ $charge->formattedTotalCost }}</span>
                                                    @if ($invoice->canBeModified())
                                                        <button wire:click="startEditingCharge({{ $charge->id }})"
                                                                class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-indigo-600 transition-all"
                                                                title="Editar monto">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-6 bg-gray-50 dark:bg-gray-900 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay cargos adicionales registrados para este Invoice.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Panel de totales -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
            <div class="p-6">
                <div class="max-w-sm ml-auto space-y-3">
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wider font-medium">Total Piezas</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ number_format($invoice->total_quantity) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wider font-medium">Subtotal Items</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            ${{ number_format((float) $invoice->subtotal_items, 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wider font-medium">Subtotal Cargos</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            ${{ number_format((float) $invoice->subtotal_charges, 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg px-3">
                        <span class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wider">Grand Total</span>
                        <span class="text-xl font-bold text-green-700 dark:text-green-400">
                            {{ $invoice->formattedTotal }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Estado cancelled: banner informativo --}}
        @if ($invoice->status === 'cancelled')
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-xl p-4 mb-6">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <p class="text-sm font-semibold text-red-800 dark:text-red-200">
                        Este Invoice fue cancelado y no puede ser modificado ni emitido.
                    </p>
                </div>
            </div>
        @endif

    </div>

    <!-- Modal confirmación: Emitir Invoice -->
    @if ($confirmingIssue)
        <div class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    Emitir Invoice#{{ $invoice->invoice_number }}
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Al emitir el Invoice su estado cambiará a <strong>Emitido</strong> y se habilitará la generación del PDF.
                                        Una vez emitido no podrá editar los precios.
                                    </p>
                                    @if ($zeroUnitCostCount > 0)
                                        <p class="mt-2 text-sm text-amber-600 dark:text-amber-400 font-medium">
                                            Advertencia: hay {{ $zeroUnitCostCount }} item(s) con Unit Cost = $0.0000
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700">
                        <button wire:click="issueInvoice" type="button"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Emitir Invoice
                        </button>
                        <button wire:click="cancelIssue" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-white dark:border-gray-600 dark:hover:bg-gray-500">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal confirmación: Cancelar Invoice -->
    @if ($confirmingCancel)
        <div class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    Cancelar Invoice#{{ $invoice->invoice_number }}
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        ¿Está seguro de que desea cancelar este Invoice? El Invoice quedará en estado
                                        <strong>Cancelado</strong> y no podrá ser editado ni emitido.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700">
                        <button wire:click="cancelInvoice" type="button"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Cancelar Invoice
                        </button>
                        <button wire:click="cancelCancelation" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-white dark:border-gray-600 dark:hover:bg-gray-500">
                            Volver
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal confirmación: Eliminar Invoice -->
    @if ($confirmingDelete)
        <div class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
                </div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/40 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    Eliminar Invoice#{{ $invoice->invoice_number }}
                                </h3>
                                <div class="mt-2 space-y-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Esta accion <strong class="text-gray-700 dark:text-gray-200">no se puede deshacer</strong>.
                                        El Invoice y todos sus items seran eliminados permanentemente.
                                    </p>
                                    @if ($invoice->packingSlip)
                                        <p class="text-sm text-blue-600 dark:text-blue-400">
                                            El Packing Slip <span class="font-mono font-semibold">{{ $invoice->packingSlip->ps_number }}</span>
                                            quedara disponible para generar un nuevo Invoice.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700">
                        <button wire:click="deleteInvoice" type="button"
                                wire:loading.attr="disabled"
                                wire:target="deleteInvoice"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-700 text-base font-medium text-white hover:bg-red-800 focus:outline-none disabled:opacity-60 disabled:cursor-not-allowed sm:ml-3 sm:w-auto sm:text-sm">
                            <span wire:loading.remove wire:target="deleteInvoice">Si, eliminar</span>
                            <span wire:loading wire:target="deleteInvoice">Eliminando...</span>
                        </button>
                        <button wire:click="cancelDelete" type="button"
                                wire:loading.attr="disabled"
                                wire:target="deleteInvoice"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none disabled:opacity-60 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-white dark:border-gray-600 dark:hover:bg-gray-500">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
