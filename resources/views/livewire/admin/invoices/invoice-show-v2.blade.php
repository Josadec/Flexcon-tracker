@php
    $statusTone = match ($invoice->status) {
        'draft'     => 'warn',
        'issued'    => 'good',
        'cancelled' => 'bad',
        default     => 'neutral',
    };

    $statusSummary = match ($invoice->status) {
        'draft'     => 'Se puede seguir ajustando antes de emitirlo.',
        'issued'    => 'El documento ya está emitido y listo para PDF.',
        'cancelled' => 'Este documento quedó fuera del flujo operativo.',
        default     => 'Estado del documento.',
    };
@endphp

<x-ui.page eyebrow="Facturación" title="Invoice#{{ $invoice->invoice_number }}"
    subtitle="Detalle del documento y control operativo del invoice."
    back="{{ route('admin.invoices.index') }}" backLabel="Volver a invoices">

    <x-slot:actions>
        @if ($invoice->isDraft())
            <x-ui.btn variant="success" wire:click="confirmIssue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Emitir invoice
            </x-ui.btn>
        @endif

        @if ($invoice->isPdfAvailable())
            <x-ui.btn variant="secondary" href="{{ route('admin.invoices.pdf.stream', $invoice->invoice_number) }}" :navigate="false" target="_blank">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Ver PDF
            </x-ui.btn>
            <x-ui.btn variant="secondary" href="{{ route('admin.invoices.pdf', $invoice->invoice_number) }}" :navigate="false">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Descargar PDF
            </x-ui.btn>
        @endif

        @if ($invoice->isDraft() || $invoice->isIssued())
            <x-ui.btn variant="warning" wire:click="confirmCancel">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Cancelar invoice
            </x-ui.btn>
        @endif

        @if ($invoice->isDraft() || $invoice->isCancelled())
            <x-ui.btn variant="danger" wire:click="confirmDelete">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Eliminar
            </x-ui.btn>
        @endif
    </x-slot:actions>

    {{-- Toasts para las acciones AJAX que se quedan en pantalla (editar LOT NO.,
         montos, emitir, cancelar). --}}
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

    @if (session('success'))
        <x-ui.note tone="success">{{ session('success') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Estado">
            <x-ui.badge :tone="$statusTone" dot>{{ $invoice->statusLabel }}</x-ui.badge>
        </x-ui.stat>
        <x-ui.stat label="Packing Slip">
            @if ($invoice->packingSlip)
                <a href="{{ route('admin.shipping-list.show', $invoice->packingSlip) }}" wire:navigate
                    class="font-mono text-base text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">
                    {{ $invoice->packingSlip->ps_number }}
                </a>
            @else
                <span class="text-slate-400 dark:text-slate-500">—</span>
            @endif
        </x-ui.stat>
        <x-ui.stat label="Ítems de producto" :value="$invoice->productItems->count()" tone="info" />
        <x-ui.stat label="Grand Total" :value="$invoice->formattedTotal" tone="good" />
    </x-ui.stats>

    @if ($zeroUnitCostCount > 0)
        <x-ui.note tone="warn" title="Hay {{ $zeroUnitCostCount }} ítem(s) con Unit Cost en $0.0000.">
            Puedes emitir el invoice de todas formas o ajustar precios antes de continuar.
        </x-ui.note>
    @endif

    @if ($invoice->isCancelled())
        <x-ui.note tone="danger" title="Este invoice fue cancelado y ya no participa en el flujo operativo.">
            No se puede emitir y la interfaz se mantiene en modo solo lectura.
        </x-ui.note>
    @endif

    {{-- Información general --}}
    <x-ui.section title="Información general" hint="Datos base del invoice y referencias operativas.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">

            {{-- Número de invoice (editable en draft) --}}
            @if ($invoice->isDraft())
                <div class="px-4 py-2.5"
                    x-data="{
                        editing: false,
                        original: @js($invoice->invoice_number),
                        value: @js($invoice->invoice_number),
                        submit() { this.editing = false; $wire.updateInvoiceNumber(this.value); },
                        cancel() { this.editing = false; this.value = this.original; }
                    }">
                    <div x-show="!editing" class="flex items-baseline justify-between gap-4">
                        <dt class="text-sm text-slate-600 dark:text-slate-300">Número de invoice</dt>
                        <dd class="flex shrink-0 items-center gap-2">
                            <span class="font-mono text-sm font-bold text-slate-900 dark:text-white">Invoice#<span x-text="value"></span></span>
                            <x-ui.icon-btn tone="primary" label="Editar el número de invoice"
                                x-on:click="editing = true; $nextTick(() => $refs.invoiceNumber.focus())">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </x-ui.icon-btn>
                        </dd>
                    </div>
                    <div x-show="editing" x-cloak class="space-y-2">
                        <x-ui.field label="Número de invoice" hint="Al guardar se actualiza el slug de la URL de este invoice.">
                            <div class="flex items-center gap-2">
                                <input x-ref="invoiceNumber" x-model="value" type="text" maxlength="10"
                                    class="w-full font-mono sm:max-w-xs"
                                    @keydown.enter.prevent="submit()"
                                    @keydown.escape.prevent="cancel()">
                                <x-ui.icon-btn tone="success" label="Guardar el número" x-on:click="submit()">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-ui.icon-btn>
                                <x-ui.icon-btn tone="neutral" label="Cancelar" x-on:click="cancel()">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </x-ui.icon-btn>
                            </div>
                        </x-ui.field>
                    </div>
                </div>
            @else
                <x-ui.kv label="Número de invoice" value="Invoice#{{ $invoice->invoice_number }}" />
            @endif

            <x-ui.kv label="Fecha del invoice" :value="$invoice->invoice_date?->format('d/m/Y') ?? '—'" />

            <x-ui.kv label="Creado por"
                :value="$invoice->creator?->name ?? '—'"
                :help="$invoice->created_at?->format('d/m/Y H:i')" />

            <x-ui.kv label="Packing Slip origen">
                @if ($invoice->packingSlip)
                    <a href="{{ route('admin.shipping-list.show', $invoice->packingSlip) }}" wire:navigate
                        class="font-mono font-bold text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">
                        {{ $invoice->packingSlip->ps_number }}
                    </a>
                @else
                    <span class="text-slate-400 dark:text-slate-500">—</span>
                @endif
            </x-ui.kv>

            <x-ui.kv label="Emitido por"
                :value="$invoice->issuer?->name ?? '—'"
                :help="$invoice->issued_at?->format('d/m/Y H:i') ?? 'Pendiente'" />

            {{-- LOT NO. general (editable en draft e issued) --}}
            <div class="px-4 py-2.5">
                @if ($editingLotNo)
                    <x-ui.field label="LOT NO. general"
                        hint="Formato MMDDYYxNN. Este valor se propaga a todos los ítems de producto."
                        :error="$errors->first('lotNoValue')">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input wire:model.live="lotNoValue" type="text" maxlength="20" placeholder="030926x01"
                                class="w-full font-mono sm:max-w-xs">
                            <div class="flex items-center gap-2">
                                <x-ui.btn variant="primary" size="sm" wire:click="updateLotNo">Guardar</x-ui.btn>
                                <x-ui.btn variant="secondary" size="sm" wire:click="cancelEditingLotNo">Cancelar</x-ui.btn>
                            </div>
                        </div>
                    </x-ui.field>
                @else
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-sm text-slate-600 dark:text-slate-300">
                            LOT NO. general
                            <span class="block text-[11px] leading-4 text-slate-400 dark:text-slate-500">Disponible en draft e issued. Se replica al detalle.</span>
                        </dt>
                        <dd class="flex shrink-0 items-center gap-2">
                            <span class="font-mono text-sm font-bold text-slate-900 dark:text-white">{{ $invoice->lot_no ?: '—' }}</span>
                            @if (! $invoice->isCancelled())
                                <x-ui.icon-btn tone="primary" label="Editar el LOT NO. general" wire:click="startEditingLotNo">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </x-ui.icon-btn>
                            @endif
                        </dd>
                    </div>
                @endif
            </div>
        </dl>
    </x-ui.section>

    {{-- Items de producto --}}
    <x-ui.table title="Ítems de producto"
        hint="Líneas heredadas del Packing Slip con control de LOT NO. por ítem.">
        <x-slot:aside>
            <x-ui.badge tone="neutral">{{ $invoice->productItems->count() }} ítem(s)</x-ui.badge>
        </x-slot:aside>

        <x-slot:head>
            <tr>
                <x-ui.th>Descripción</x-ui.th>
                <x-ui.th>Item No.</x-ui.th>
                <x-ui.th>LOT NO.</x-ui.th>
                <x-ui.th>P.O. No.</x-ui.th>
                <x-ui.th>W/O</x-ui.th>
                <x-ui.th align="right">Qty</x-ui.th>
                <x-ui.th align="right">Unit Cost</x-ui.th>
                <x-ui.th align="right">Total</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($invoice->productItems as $item)
            @php $zeroCost = (float) $item->unit_cost === 0.0; @endphp
            <tr wire:key="prod-item-{{ $item->id }}"
                class="hover:bg-slate-50 dark:hover:bg-slate-700/30 {{ $zeroCost ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                <td class="px-4 py-3 align-top">
                    <div class="max-w-xs font-medium text-slate-900 dark:text-white">{{ $item->description ?? '—' }}</div>
                    @if ($zeroCost)
                        <x-ui.badge tone="warn" class="mt-1.5">Sin precio</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3 align-top text-slate-600 dark:text-slate-300">{{ $item->item_number ?? '—' }}</td>
                <td class="px-4 py-3 align-top">
                    @if ($invoice->isDraft() && $editingLotItemId === $item->id)
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-1.5">
                                <input wire:model.live="editingLotItemValue" type="text" maxlength="50"
                                    class="w-40 font-mono">
                                <x-ui.icon-btn tone="success" label="Guardar LOT NO." wire:click="saveLotItem">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-ui.icon-btn>
                                <x-ui.icon-btn tone="neutral" label="Cancelar" wire:click="cancelEditingLotItem">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </x-ui.icon-btn>
                            </div>
                            @error('editingLotItemValue')
                                <p class="text-[11px] font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <div class="group flex items-center gap-2">
                            <span class="font-mono font-medium text-slate-900 dark:text-white">{{ $item->lot_number ?: '—' }}</span>
                            @if ($invoice->isDraft())
                                <x-ui.icon-btn tone="primary" label="Editar el LOT NO. de este ítem"
                                    class="opacity-0 transition-opacity group-hover:opacity-100"
                                    wire:click="startEditingLotItem({{ $item->id }})">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </x-ui.icon-btn>
                            @endif
                        </div>
                    @endif
                </td>
                <td class="px-4 py-3 align-top text-slate-600 dark:text-slate-300">{{ $item->po_number ?? '—' }}</td>
                <td class="px-4 py-3 align-top text-slate-600 dark:text-slate-300">{{ $item->wo_number ?? '—' }}</td>
                <td class="px-4 py-3 align-top text-right font-medium tabular-nums text-slate-900 dark:text-white">{{ number_format((int) $item->quantity) }}</td>
                <td class="px-4 py-3 align-top text-right font-mono tabular-nums text-slate-700 dark:text-slate-300">${{ $item->formattedUnitCost }}</td>
                <td class="px-4 py-3 align-top text-right font-semibold tabular-nums text-slate-900 dark:text-white">${{ $item->formattedTotalCost }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    <x-ui.empty icon="doc" title="Sin ítems de producto"
                        hint="Este invoice no tiene ítems de producto registrados." />
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    {{-- Cargos + Totales --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        {{-- Cargos adicionales --}}
        <x-ui.table title="Cargos adicionales" hint="Cargos fijos incluidos en el documento.">
            <x-slot:aside>
                <x-ui.badge tone="neutral">{{ $invoice->chargeItems->count() }} cargo(s)</x-ui.badge>
            </x-slot:aside>

            <x-slot:head>
                <tr>
                    <x-ui.th>Descripción</x-ui.th>
                    <x-ui.th align="right">Monto</x-ui.th>
                </tr>
            </x-slot:head>

            @forelse ($invoice->chargeItems as $charge)
                <tr wire:key="charge-item-{{ $charge->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="px-4 py-3 text-slate-900 dark:text-white">{{ $charge->description }}</td>
                    <td class="px-4 py-3 text-right">
                        @if ($invoice->canBeModified() && $editingChargeId === $charge->id)
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <input wire:model.live="editingChargeAmount" type="number" step="0.01" min="0"
                                        class="w-32 text-right tabular-nums">
                                    <x-ui.icon-btn tone="success" label="Guardar monto" wire:click="updateChargeAmount">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </x-ui.icon-btn>
                                    <x-ui.icon-btn tone="neutral" label="Cancelar" wire:click="cancelEditingCharge">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </x-ui.icon-btn>
                                </div>
                                @error('editingChargeAmount')
                                    <p class="text-right text-[11px] font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div class="group flex items-center justify-end gap-2">
                                <span class="font-semibold tabular-nums text-slate-900 dark:text-white">${{ $charge->formattedTotalCost }}</span>
                                @if ($invoice->canBeModified())
                                    <x-ui.icon-btn tone="primary" label="Editar el monto del cargo"
                                        class="opacity-0 transition-opacity group-hover:opacity-100"
                                        wire:click="startEditingCharge({{ $charge->id }})">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </x-ui.icon-btn>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">
                        <x-ui.empty icon="doc" title="Sin cargos adicionales"
                            hint="No hay cargos fijos registrados para este invoice." />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        {{-- Totales + Notas --}}
        <div class="space-y-5">
            <x-ui.section title="Totales" hint="Resumen financiero del invoice.">
                <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    <x-ui.kv label="Total piezas" :value="number_format($invoice->total_quantity)" />
                    <x-ui.kv label="Subtotal ítems" :value="'$'.number_format((float) $invoice->subtotal_items, 2)" />
                    <x-ui.kv label="Subtotal cargos" :value="'$'.number_format((float) $invoice->subtotal_charges, 2)" />
                </dl>
                <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-center dark:bg-green-900/20">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-green-700 dark:text-green-300">Grand Total</div>
                    <div class="mt-1 text-2xl font-bold tabular-nums text-green-700 dark:text-green-300">{{ $invoice->formattedTotal }}</div>
                </div>
            </x-ui.section>

            <x-ui.section title="Notas del flujo">
                <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>El número del invoice solo se puede editar mientras esté en borrador.</li>
                    <li>El LOT NO. general se propaga a todas las líneas de producto.</li>
                    <li>Los cargos fijos solo se pueden ajustar en estado borrador.</li>
                </ul>
            </x-ui.section>
        </div>
    </div>

    {{-- ============================================================= --}}
    {{-- MODALES                                                       --}}
    {{-- ============================================================= --}}

    {{-- Emitir invoice --}}
    @if ($confirmingIssue)
        <x-ui-modal wire:key="modal-issue" title="Emitir Invoice#{{ $invoice->invoice_number }}"
            subtitle="El documento cambiará a estado emitido y quedará habilitado para PDF."
            close="cancelIssue" maxWidth="lg">

            <x-ui.section title="Qué va a pasar">
                <x-ui.note tone="info">
                    Después de emitirlo ya no podrás editar precios ni cargos del documento.
                </x-ui.note>
                @if ($zeroUnitCostCount > 0)
                    <x-ui.note tone="warn" class="mt-3">
                        Hay {{ $zeroUnitCostCount }} ítem(s) con Unit Cost en $0.0000.
                    </x-ui.note>
                @endif
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelIssue">Volver</x-ui.btn>
                <x-ui.btn variant="success" wire:click="issueInvoice">Emitir invoice</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Cancelar invoice --}}
    @if ($confirmingCancel)
        <x-ui-modal wire:key="modal-cancel" title="Cancelar Invoice#{{ $invoice->invoice_number }}"
            subtitle="El documento pasará a estado cancelado y quedará en modo solo lectura."
            close="cancelCancelation" maxWidth="lg">

            <x-ui.section title="Qué va a pasar">
                @if ($invoice->isIssued())
                    <x-ui.note tone="warn">
                        Este invoice ya fue <strong>emitido</strong>. Al cancelarlo quedará en modo solo lectura.
                        Si deseas corregirlo, podrás eliminarlo y generar uno nuevo desde el Packing Slip.
                    </x-ui.note>
                @else
                    <x-ui.note tone="info">
                        Esta acción evita que el invoice siga adelante en el flujo operativo.
                    </x-ui.note>
                @endif
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelCancelation">Volver</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="cancelInvoice">Cancelar invoice</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Eliminar invoice --}}
    @if ($confirmingDelete)
        <x-ui-modal wire:key="modal-delete" title="Eliminar Invoice#{{ $invoice->invoice_number }}"
            subtitle="Esta acción es permanente y elimina el invoice junto con sus ítems."
            close="cancelDelete" maxWidth="lg">

            <x-ui.section title="Qué va a pasar">
                <x-ui.note tone="danger">
                    No se podrá deshacer después de confirmar.
                </x-ui.note>
                @if ($invoice->packingSlip)
                    <x-ui.note tone="info" class="mt-3">
                        El Packing Slip {{ $invoice->packingSlip->ps_number }} volverá a quedar disponible para un nuevo invoice.
                    </x-ui.note>
                @endif
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelDelete" wire:loading.attr="disabled" wire:target="deleteInvoice">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="deleteInvoice" wire:loading.attr="disabled" wire:target="deleteInvoice">
                    <span wire:loading.remove wire:target="deleteInvoice">Sí, eliminar</span>
                    <span wire:loading wire:target="deleteInvoice">Eliminando...</span>
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
