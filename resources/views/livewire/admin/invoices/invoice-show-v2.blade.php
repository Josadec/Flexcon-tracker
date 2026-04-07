@php
    $statusClasses = match ($invoice->status) {
        'draft' => 'border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        'issued' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300',
        'cancelled' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300',
        default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };

    $statusSummary = match ($invoice->status) {
        'draft' => 'Se puede seguir ajustando antes de emitirlo.',
        'issued' => 'El documento ya esta emitido y listo para PDF.',
        'cancelled' => 'Este documento quedo fuera del flujo operativo.',
        default => 'Estado del documento.',
    };
@endphp

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
                this.toasts = this.toasts.filter(toast => toast.id !== id);
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

    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('admin.invoices.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-md border-2 border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver a invoices
                </a>
                <span class="inline-flex rounded-full border-2 px-3 py-1 text-xs font-medium {{ $statusClasses }}">
                    {{ $invoice->statusLabel }}
                </span>
                @if ($invoice->packingSlip)
                    <a
                        href="{{ route('admin.packing-slips.show', $invoice->packingSlip) }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 transition-colors hover:border-blue-300 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:border-blue-700 dark:hover:bg-blue-900/30"
                    >
                        PS {{ $invoice->packingSlip->ps_number }}
                    </a>
                @endif
            </div>

            @if ($invoice->isDraft())
                <div
                    x-data="{
                        editing: false,
                        original: @js($invoice->invoice_number),
                        value: @js($invoice->invoice_number),
                        submit() {
                            this.editing = false;
                            $wire.updateInvoiceNumber(this.value);
                        },
                        cancel() {
                            this.editing = false;
                            this.value = this.original;
                        }
                    }"
                    class="space-y-2"
                >
                    <div x-show="!editing" class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            Invoice#<span x-text="value" class="font-mono"></span>
                        </h1>
                        <button
                            type="button"
                            @click="editing = true; $nextTick(() => $refs.invoiceNumber.focus())"
                            class="inline-flex items-center gap-2 rounded-md border-2 border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:border-blue-300 hover:text-blue-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-blue-700 dark:hover:text-blue-400"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Editar numero
                        </button>
                    </div>

                    <div x-show="editing" class="flex flex-col gap-3 rounded-lg border-2 border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <label class="text-sm font-medium text-blue-900 dark:text-blue-200">Invoice#</label>
                            <input
                                x-ref="invoiceNumber"
                                x-model="value"
                                type="text"
                                maxlength="10"
                                class="w-full rounded-md border-2 border-blue-200 bg-white px-3 py-2 text-sm font-mono text-gray-900 focus:border-blue-500 focus:outline-none dark:border-blue-700 dark:bg-gray-800 dark:text-white sm:max-w-xs"
                                @keydown.enter.prevent="submit()"
                                @keydown.escape.prevent="cancel()"
                                @blur="editing && submit()"
                            >
                        </div>
                        <p class="text-xs text-blue-700 dark:text-blue-300">
                            Al guardar se actualiza el slug de la URL de este invoice.
                        </p>
                    </div>
                </div>
            @else
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        Invoice#<span class="font-mono">{{ $invoice->invoice_number }}</span>
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Detalle del documento y control operativo del invoice.
                    </p>
                </div>
            @endif

        </div>

        <div class="flex flex-wrap gap-2 xl:max-w-xl xl:justify-end">
            @if ($invoice->isDraft())
                <button
                    wire:click="confirmIssue"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Emitir invoice
                </button>
                <button
                    wire:click="confirmCancel"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Cancelar invoice
                </button>
            @endif

            @if ($invoice->isPdfAvailable())
                <a
                    href="{{ route('admin.invoices.pdf.stream', $invoice->invoice_number) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-md border-2 border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 dark:border-red-800 dark:bg-gray-800 dark:text-red-400 dark:hover:border-red-700 dark:hover:bg-red-900/20"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    Ver PDF
                </a>
                <a
                    href="{{ route('admin.invoices.pdf', $invoice->invoice_number) }}"
                    class="inline-flex items-center gap-2 rounded-md border-2 border-emerald-200 bg-white px-4 py-2 text-sm font-medium text-emerald-600 transition-colors hover:border-emerald-300 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-gray-800 dark:text-emerald-400 dark:hover:border-emerald-700 dark:hover:bg-emerald-900/20"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar PDF
                </a>
            @endif

            @if ($invoice->isDraft() || $invoice->isCancelled())
                <button
                    wire:click="confirmDelete"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border-2 border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 dark:border-red-800 dark:bg-gray-800 dark:text-red-400 dark:hover:border-red-700 dark:hover:bg-red-900/20"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Eliminar
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Estado</div>
            <div class="mt-2 inline-flex rounded-full border-2 px-3 py-1 text-xs font-medium {{ $statusClasses }}">
                {{ $invoice->statusLabel }}
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $statusSummary }}</p>
        </div>

        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Packing Slip</div>
            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                @if ($invoice->packingSlip)
                    <a
                        href="{{ route('admin.packing-slips.show', $invoice->packingSlip) }}"
                        wire:navigate
                        class="font-mono text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        {{ $invoice->packingSlip->ps_number }}
                    </a>
                @else
                    <span class="text-gray-400 dark:text-gray-500">-</span>
                @endif
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                Fecha del invoice: {{ $invoice->invoice_date?->format('d/m/Y') ?? '-' }}
            </p>
        </div>

        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Contenido</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $invoice->productItems->count() }}</div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                item(s) de producto y {{ $invoice->chargeItems->count() }} cargo(s) fijos
            </p>
        </div>

        <div class="rounded-lg border-2 border-green-200 bg-white p-4 dark:border-green-800 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Grand Total</div>
            <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $invoice->formattedTotal }}</div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                {{ number_format($invoice->total_quantity) }} pieza(s) en total
            </p>
        </div>
    </div>

    @if ($zeroUnitCostCount > 0)
        <div class="rounded-lg border-2 border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                        Hay {{ $zeroUnitCostCount }} item(s) con Unit Cost en $0.0000.
                    </p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        Puedes emitir el invoice de todas formas o ajustar precios antes de continuar.
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if ($invoice->isCancelled())
        <div class="rounded-lg border-2 border-red-200 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-900/20">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <div>
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                        Este invoice fue cancelado y ya no participa en el flujo operativo.
                    </p>
                    <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                        No se puede emitir y la UI se mantiene en modo solo lectura.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Informacion general</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Datos base del invoice y referencias operativas.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 p-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Numero de invoice</div>
                <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">Invoice#{{ $invoice->invoice_number }}</div>
            </div>

            <div>
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Fecha del invoice</div>
                <div class="mt-2 text-sm text-gray-900 dark:text-white">{{ $invoice->invoice_date?->format('d/m/Y') ?? '-' }}</div>
            </div>

            <div>
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Creado por</div>
                <div class="mt-2 text-sm text-gray-900 dark:text-white">{{ $invoice->creator?->name ?? '-' }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $invoice->created_at->format('d/m/Y H:i') }}</div>
            </div>

            <div>
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Packing Slip origen</div>
                <div class="mt-2">
                    @if ($invoice->packingSlip)
                        <a
                            href="{{ route('admin.packing-slips.show', $invoice->packingSlip) }}"
                            wire:navigate
                            class="font-mono text-sm font-medium text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                        >
                            {{ $invoice->packingSlip->ps_number }}
                        </a>
                    @else
                        <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                    @endif
                </div>
            </div>

            <div>
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Emitido por</div>
                <div class="mt-2 text-sm text-gray-900 dark:text-white">{{ $invoice->issuer?->name ?? '-' }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $invoice->issued_at?->format('d/m/Y H:i') ?? 'Pendiente' }}
                </div>
            </div>

            <div>
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">LOT NO. general</div>

                @if ($editingLotNo)
                    <div class="mt-2 space-y-2 rounded-lg border-2 border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input
                                wire:model.live="lotNoValue"
                                type="text"
                                maxlength="20"
                                placeholder="030926x01"
                                class="w-full rounded-md border-2 border-blue-200 bg-white px-3 py-2 text-sm font-mono text-gray-900 focus:border-blue-500 focus:outline-none dark:border-blue-700 dark:bg-gray-800 dark:text-white"
                            >
                            <div class="flex items-center gap-2">
                                <button
                                    wire:click="updateLotNo"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                                >
                                    Guardar
                                </button>
                                <button
                                    wire:click="cancelEditingLotNo"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-blue-700 dark:text-blue-300">
                            Formato esperado: MMDDYYxNN. Este valor se propaga a todos los items de producto.
                        </p>
                        @error('lotNoValue')
                            <p class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div class="mt-2 flex items-center gap-2">
                        <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $invoice->lot_no ?: '-' }}
                        </span>
                        @if (! $invoice->isCancelled())
                            <button
                                wire:click="startEditingLotNo"
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-blue-600 transition-colors hover:border-blue-300 hover:bg-blue-50 dark:text-blue-400 dark:hover:border-blue-700 dark:hover:bg-blue-900/20"
                                title="Editar LOT NO."
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Disponible en draft e issued. Se replica al detalle del invoice.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 dark:border-gray-700 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Items de producto</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Lineas heredadas del Packing Slip con control de LOT NO. por item.
                </p>
            </div>
            <span class="inline-flex items-center self-start rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                {{ $invoice->productItems->count() }} item(s)
            </span>
        </div>

        @if ($invoice->productItems->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Descripcion</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Item No.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">LOT NO.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">P.O. No.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">W/O</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Unit Cost</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                        @foreach ($invoice->productItems as $item)
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30 {{ (float) $item->unit_cost === 0.0 ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                                <td class="px-4 py-3 align-top">
                                    <div class="max-w-xs text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $item->description ?? '-' }}
                                    </div>
                                    @if ((float) $item->unit_cost === 0.0)
                                        <span class="mt-2 inline-flex rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                            Sin precio
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-300">
                                    {{ $item->item_number ?? '-' }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @if ($invoice->isDraft() && $editingLotItemId === $item->id)
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <input
                                                    wire:model.live="editingLotItemValue"
                                                    type="text"
                                                    maxlength="50"
                                                    class="w-40 rounded-md border-2 border-blue-200 bg-white px-3 py-2 text-sm font-mono text-gray-900 focus:border-blue-500 focus:outline-none dark:border-blue-700 dark:bg-gray-900 dark:text-white"
                                                >
                                                <button
                                                    wire:click="saveLotItem"
                                                    type="button"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-green-600 text-white transition-colors hover:bg-green-700"
                                                    title="Guardar LOT NO."
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    wire:click="cancelEditingLotItem"
                                                    type="button"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border-2 border-gray-200 bg-white text-gray-500 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-800"
                                                    title="Cancelar"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                            @error('editingLotItemValue')
                                                <p class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @else
                                        <div class="group flex items-center gap-2">
                                            <span class="font-mono text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $item->lot_number ?: '-' }}
                                            </span>
                                            @if ($invoice->isDraft())
                                                <button
                                                    wire:click="startEditingLotItem({{ $item->id }})"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-blue-600 opacity-0 transition-all group-hover:opacity-100 hover:border-blue-300 hover:bg-blue-50 dark:text-blue-400 dark:hover:border-blue-700 dark:hover:bg-blue-900/20"
                                                    title="Editar LOT NO."
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-300">{{ $item->po_number ?? '-' }}</td>
                                <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-300">{{ $item->wo_number ?? '-' }}</td>
                                <td class="px-4 py-3 align-top text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $item->quantity) }}</td>
                                <td class="px-4 py-3 align-top text-right text-sm font-mono text-gray-700 dark:text-gray-300">${{ $item->formattedUnitCost }}</td>
                                <td class="px-4 py-3 align-top text-right text-sm font-semibold text-gray-900 dark:text-white">${{ $item->formattedTotalCost }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-4 py-10">
                <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-900/50">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Este invoice no tiene items de producto registrados.</p>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 dark:border-gray-700 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Cargos adicionales</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Cargos fijos incluidos en el documento.
                    </p>
                </div>
                <span class="inline-flex items-center self-start rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                    {{ $invoice->chargeItems->count() }} cargo(s)
                </span>
            </div>

            @if ($invoice->chargeItems->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Descripcion</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach ($invoice->chargeItems as $charge)
                                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        {{ $charge->description }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($invoice->canBeModified() && $editingChargeId === $charge->id)
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-end gap-2">
                                                    <input
                                                        wire:model.live="editingChargeAmount"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="w-32 rounded-md border-2 border-blue-200 bg-white px-3 py-2 text-right text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-blue-700 dark:bg-gray-900 dark:text-white"
                                                    >
                                                    <button
                                                        wire:click="updateChargeAmount"
                                                        type="button"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-green-600 text-white transition-colors hover:bg-green-700"
                                                        title="Guardar monto"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        wire:click="cancelEditingCharge"
                                                        type="button"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border-2 border-gray-200 bg-white text-gray-500 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-800"
                                                        title="Cancelar"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                @error('editingChargeAmount')
                                                    <p class="text-right text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @else
                                            <div class="group flex items-center justify-end gap-2">
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    ${{ $charge->formattedTotalCost }}
                                                </span>
                                                @if ($invoice->canBeModified())
                                                    <button
                                                        wire:click="startEditingCharge({{ $charge->id }})"
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-blue-600 opacity-0 transition-all group-hover:opacity-100 hover:border-blue-300 hover:bg-blue-50 dark:text-blue-400 dark:hover:border-blue-700 dark:hover:bg-blue-900/20"
                                                        title="Editar monto"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
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
                <div class="px-4 py-10">
                    <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-900/50">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay cargos adicionales registrados para este invoice.</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Totales</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Resumen financiero del invoice.
                    </p>
                </div>

                <div class="space-y-4 p-4">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total piezas</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($invoice->total_quantity) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Subtotal items</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">${{ number_format((float) $invoice->subtotal_items, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Subtotal cargos</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">${{ number_format((float) $invoice->subtotal_charges, 2) }}</span>
                    </div>
                    <div class="rounded-lg border-2 border-green-200 bg-green-50 px-4 py-3 dark:border-green-800 dark:bg-green-900/20">
                        <div class="text-xs font-medium uppercase tracking-wider text-green-700 dark:text-green-300">Grand Total</div>
                        <div class="mt-1 text-2xl font-semibold text-green-700 dark:text-green-400">{{ $invoice->formattedTotal }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Notas del flujo</h2>
                </div>
                <div class="space-y-3 p-4 text-sm text-gray-600 dark:text-gray-300">
                    <p>El numero del invoice solo se puede editar mientras este en borrador.</p>
                    <p>El LOT NO. general se propaga a todas las lineas de producto.</p>
                    <p>Los cargos fijos solo se pueden ajustar en estado draft.</p>
                </div>
            </div>
        </div>
    </div>

    @if ($confirmingIssue)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 p-4">
            <div class="w-full max-w-lg rounded-lg border-2 border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Emitir Invoice#{{ $invoice->invoice_number }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                El documento cambiara a estado emitido y quedara habilitado para PDF.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                    <p>Despues de emitirlo ya no podras editar precios ni cargos del documento.</p>
                    @if ($zeroUnitCostCount > 0)
                        <div class="rounded-lg border-2 border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                            Hay {{ $zeroUnitCostCount }} item(s) con Unit Cost en $0.0000.
                        </div>
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                    <button
                        wire:click="cancelIssue"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border-2 border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                    >
                        Volver
                    </button>
                    <button
                        wire:click="issueInvoice"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700"
                    >
                        Emitir invoice
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmingCancel)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 p-4">
            <div class="w-full max-w-lg rounded-lg border-2 border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cancelar Invoice#{{ $invoice->invoice_number }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                El documento pasara a estado cancelado y quedara en modo solo lectura.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                    <p>Esta accion evita que el invoice siga adelante en el flujo operativo.</p>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                    <button
                        wire:click="cancelCancelation"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border-2 border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                    >
                        Volver
                    </button>
                    <button
                        wire:click="cancelInvoice"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700"
                    >
                        Cancelar invoice
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmingDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 p-4">
            <div class="w-full max-w-lg rounded-lg border-2 border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Eliminar Invoice#{{ $invoice->invoice_number }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Esta accion es permanente y elimina el invoice junto con sus items.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                    <p>No se podra deshacer despues de confirmar.</p>
                    @if ($invoice->packingSlip)
                        <div class="rounded-lg border-2 border-blue-200 bg-blue-50 px-4 py-3 text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-200">
                            El Packing Slip {{ $invoice->packingSlip->ps_number }} volvera a quedar disponible para un nuevo invoice.
                        </div>
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                    <button
                        wire:click="cancelDelete"
                        wire:loading.attr="disabled"
                        wire:target="deleteInvoice"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border-2 border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="deleteInvoice"
                        wire:loading.attr="disabled"
                        wire:target="deleteInvoice"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-800 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="deleteInvoice">Si, eliminar</span>
                        <span wire:loading wire:target="deleteInvoice">Eliminando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
