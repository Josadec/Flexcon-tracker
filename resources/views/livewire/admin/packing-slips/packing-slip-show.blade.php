@php
    $statusTone = match ($packingSlip->status) {
        'draft' => 'warn', 'pending' => 'info', 'shipped' => 'good', 'cancelled' => 'bad', default => 'neutral',
    };
@endphp

<div class="ui-screen space-y-5">

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
                    'bg-sky-600 text-white':   toast.type === 'info',
                    'bg-slate-800 text-white': !['success','error','danger','warning','info'].includes(toast.type),
                }"
                class="pointer-events-auto flex items-center gap-3 min-w-[260px] max-w-sm px-4 py-3 rounded-lg shadow-lg text-sm font-medium"
                role="alert"
            >
                <template x-if="toast.type === 'success'">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </template>
                <template x-if="toast.type === 'error' || toast.type === 'danger'">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path></svg>
                </template>
                <span x-text="toast.message" class="flex-1"></span>
                <button @click="remove(toast.id)" class="shrink-0 opacity-70 hover:opacity-100 transition-opacity">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </template>
    </div>

    {{-- Encabezado --}}
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 dark:border-slate-700">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
            <div class="min-w-0">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300">Embarque</div>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1 class="font-mono text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $packingSlip->ps_number }}</h1>
                    <x-ui.badge :tone="$statusTone" dot>{{ $packingSlip->statusLabel }}</x-ui.badge>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Detalle operativo del Shipping List.</p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($packingSlip->isShipped() || $packingSlip->isCancelled())
                    <x-ui.btn variant="danger" :navigate="false" target="_blank" href="{{ route('admin.shipping-list.pdf', $packingSlip) }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Ver PDF
                    </x-ui.btn>
                    <x-ui.btn variant="success" :navigate="false" href="{{ route('admin.shipping-list.pdf.download', $packingSlip) }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descargar PDF
                    </x-ui.btn>
                @endif
                <x-ui.btn variant="secondary" wire:click="goBackToShippingList">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Volver a Shipping List
                </x-ui.btn>
            </div>
        </div>

        {{-- Fila de acciones de estado: selector + guardar (+ editar lotes en Borrador) --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- El <form> garantiza que wire:model del <select> se sincronice en el mismo request del submit. --}}
            <form wire:submit="updateStatus" class="flex shrink-0 items-center gap-2">
                <select wire:model="selectedStatus" data-no-ts class="w-48">
                    @foreach (\App\Models\PackingSlip::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($value === $selectedStatus)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-ui.btn variant="primary" type="submit">Guardar estado</x-ui.btn>
            </form>

            @if ($packingSlip->isDraft())
                <x-ui.btn :variant="$editingLots ? 'warning' : 'secondary'" wire:click="toggleEditingLots">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    {{ $editingLots ? 'Cancelar edición' : 'Editar lotes' }}
                </x-ui.btn>
            @endif
        </div>
    </div>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Estado">
            <x-ui.badge :tone="$statusTone" dot>{{ $packingSlip->statusLabel }}</x-ui.badge>
        </x-ui.stat>
        <x-ui.stat label="Items" :value="number_format($packingSlip->items->count())" />
        <x-ui.stat label="Piezas" :value="number_format($packingSlip->items->sum('quantity_packed'))" tone="info" />
        <x-ui.stat label="Invoice">
            @if ($packingSlip->hasInvoice())
                <x-ui.badge tone="good">Generado</x-ui.badge>
            @else
                <x-ui.badge tone="neutral">Pendiente</x-ui.badge>
            @endif
        </x-ui.stat>
    </x-ui.stats>

    {{-- Información general --}}
    <x-ui.section title="Información general">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Número de PS</p>
                {{-- Editable en Borrador o Pendiente; solo lectura en Despachado o Cancelado. --}}
                @if (! $packingSlip->isShipped() && ! $packingSlip->isCancelled())
                    <div wire:key="ps-number-editor-{{ $packingSlip->id }}"
                         x-data="{ editing: false, submitting: false, value: '{{ $packingSlip->ps_number }}' }"
                         class="mt-1">
                        <span x-show="!editing" class="inline-flex items-center gap-1 font-mono text-base text-slate-900 dark:text-white">
                            <span x-text="value"></span>
                            <button type="button" @click="editing = true; submitting = false"
                                    class="rounded p-0.5 transition-colors hover:bg-slate-100 dark:hover:bg-slate-700" title="Editar PS Number">
                                <svg class="size-3.5 text-slate-400 hover:text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                        </span>
                        <span x-show="editing" class="inline-flex items-center gap-1">
                            <input x-model="value" type="text" maxlength="30"
                                   class="w-40 rounded border border-sky-400 px-2 py-0.5 font-mono text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 dark:bg-slate-700 dark:text-white"
                                   @keydown.enter.prevent="if (!submitting) { submitting = true; $wire.updatePsNumber(value) }"
                                   @keydown.escape="editing = false; submitting = false; value = '{{ $packingSlip->ps_number }}'"
                                   x-effect="if (editing) $el.focus()">
                            <button type="button" @click="if (!submitting) { submitting = true; $wire.updatePsNumber(value) }"
                                    class="rounded p-0.5 transition-colors hover:bg-green-100 dark:hover:bg-green-900" title="Guardar PS Number">
                                <svg class="size-3.5 text-green-500 hover:text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </button>
                            <button type="button" @click="editing = false; submitting = false; value = '{{ $packingSlip->ps_number }}'"
                                    class="rounded p-0.5 transition-colors hover:bg-red-100 dark:hover:bg-red-900" title="Cancelar">
                                <svg class="size-3.5 text-red-400 hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </span>
                    </div>
                @else
                    <p class="mt-1 font-mono text-base text-slate-900 dark:text-white">{{ $packingSlip->ps_number }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Creado por</p>
                <p class="mt-1 text-base text-slate-900 dark:text-white">{{ $packingSlip->creator?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Fecha de creación</p>
                <p class="mt-1 text-base text-slate-900 dark:text-white">{{ $packingSlip->created_at->format('d/m/Y H:i') }}</p>
            </div>

            @if ($packingSlip->isShipped())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Despachado por</p>
                    <p class="mt-1 text-base text-slate-900 dark:text-white">{{ $packingSlip->shipper?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Fecha de despacho</p>
                    <p class="mt-1 text-base text-slate-900 dark:text-white">{{ $packingSlip->shipped_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
            @endif

            <div class="{{ $packingSlip->isShipped() ? '' : 'md:col-span-3' }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Notas</p>
                @if ($packingSlip->isDraft() || $packingSlip->isPending())
                    <div class="mt-1">
                        <textarea wire:model="notesValue" rows="3" placeholder="Agregar notas..." class="w-full"></textarea>
                        <div class="mt-2">
                            <x-ui.btn variant="primary" size="sm" wire:click="updateNotes">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Guardar
                            </x-ui.btn>
                        </div>
                    </div>
                @else
                    <p class="mt-1 text-base text-slate-900 dark:text-white">{{ $packingSlip->notes ?: '—' }}</p>
                @endif
            </div>
        </div>
    </x-ui.section>

    {{-- Panel de edición de lotes (solo Borrador + editingLots) --}}
    @if ($packingSlip->isDraft() && $editingLots)
        <x-ui.section step="✎" tone="accent" title="Editar lotes del Shipping List"
            hint="Selecciona o deselecciona lotes. Los cambios se aplican al guardar." id="lot-editing-panel">
            <x-slot:aside>
                <x-ui.badge tone="warn">{{ count($selectedLotIds) }} lote(s)</x-ui.badge>
            </x-slot:aside>

            @error('selectedLotIds')
                <x-ui.note tone="danger" class="mb-4">{{ $message }}</x-ui.note>
            @enderror

            @if ($availableLots->count() > 0)
                <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                                <tr>
                                    <th class="w-10 px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Sel.</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Work Order</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">PO</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Item No</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Description</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Quantity</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Date</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Label Spec</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach ($availableLots as $lot)
                                    @php $isSelected = in_array($lot->id, $selectedLotIds); @endphp
                                    <tr wire:key="edit-lot-{{ $lot->id }}"
                                        class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/30 {{ $isSelected ? 'bg-sky-50 dark:bg-sky-900/20' : '' }}">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" wire:click="toggleLot({{ $lot->id }})" @checked($isSelected)
                                                class="size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600">
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 font-mono text-slate-900 dark:text-white">
                                            @php $woPreview = $lot->workOrder?->buildWoCode((int) $lot->lot_number); @endphp
                                            @if ($woPreview)
                                                {{ $woPreview }}
                                            @else
                                                <span class="font-sans text-xs text-orange-600 dark:text-orange-400">Sin WO externo</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $lot->workOrder?->purchaseOrder?->po_number ?? '—' }}</td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $lot->workOrder?->purchaseOrder?->part?->item_number ?? '—' }}</td>
                                        <td class="max-w-xs truncate px-4 py-3 text-slate-500 dark:text-slate-400">{{ $lot->workOrder?->purchaseOrder?->part?->description ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-slate-900 dark:text-white">{{ number_format($lot->quantity_packed_final ?? $lot->quantity ?? 0) }}</td>
                                        <td class="px-4 py-3">
                                            <input type="text" wire:model="dateSpecs.{{ $lot->id }}" maxlength="20" placeholder="ej: 20250512A22" class="w-36">
                                        </td>
                                        <td class="px-4 py-3 font-mono text-slate-700 dark:text-slate-300">{{ $lot->workOrder?->purchaseOrder?->part?->label_spec ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <x-ui.empty icon="box" title="No hay lotes disponibles para agregar." />
            @endif

            <div class="mt-4 flex justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                <x-ui.btn variant="secondary" wire:click="toggleEditingLots">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="updateLots">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Guardar cambios de lotes
                </x-ui.btn>
            </div>
        </x-ui.section>
    @endif

    {{-- Items del Shipping List --}}
    <x-ui.section title="Items del Shipping List">
        <x-slot:aside>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $packingSlip->items->count() }} {{ $packingSlip->items->count() === 1 ? 'item' : 'items' }}</span>
        </x-slot:aside>

        @if ($packingSlip->items->count() > 0)
            <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Work Order</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300"># PO</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Item No</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Description</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Quantity</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Date</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Label Spec</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($itemsGroupedByPo as $poNumber => $poItems)
                                @foreach ($poItems as $item)
                                    @php
                                        // CRIMP (FPL-10): una fila por lote de CRIMP, formato idéntico al PDF del cliente.
                                        // Solo partes is_crimp; NO-CRIMP queda idéntico al comportamiento previo.
                                        $part      = $item->lot?->workOrder?->purchaseOrder?->part;
                                        $psIsCrimp = (bool) ($part?->is_crimp ?? false);
                                        $crimpLots = $psIsCrimp ? ($item->lot?->crimpLots ?? collect()) : collect();
                                        $poNum     = $item->lot?->workOrder?->purchaseOrder?->po_number ?? '—';
                                        $itemLabel = $item->label_spec ?: ($part?->label_spec ?: '—');
                                    @endphp

                                    @if ($psIsCrimp && $crimpLots->isNotEmpty())
                                        {{-- CRIMP (FPL-10): cada lote de CRIMP es UNA fila. Sin rótulo "Viajero". --}}
                                        @foreach ($crimpLots as $crimp)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                                <td class="px-4 py-3 font-mono text-slate-900 dark:text-white">{{ $item->wo_number_ps ?? '—' }}</td>
                                                <td class="px-4 py-3 text-slate-900 dark:text-white">{{ $poNum }}</td>
                                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $part?->item_number ?? '—' }}</td>
                                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $part?->description ?? '—' }}</td>
                                                <td class="px-4 py-3 text-right font-medium tabular-nums text-slate-900 dark:text-white">{{ number_format($crimp->quantity) }}</td>
                                                {{-- Date: editable inline por lote de CRIMP (date_code manual, formato FPL-10). --}}
                                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400"
                                                    x-data="{ editing: false, value: '{{ $crimp->date_code ?? '' }}' }">
                                                    @if ($packingSlip->isShipped() || $packingSlip->isCancelled())
                                                        <span class="inline-block min-w-[80px]">{{ $crimp->date_code ?: '—' }}</span>
                                                    @else
                                                        <span x-show="!editing" @click="editing = true"
                                                              class="inline-block min-w-[80px] cursor-pointer hover:text-sky-600 hover:underline"
                                                              x-text="value || '—'"></span>
                                                        <input x-show="editing" x-model="value" type="text" maxlength="20" placeholder="ej: 250512A22"
                                                               class="w-36 rounded border border-sky-400 px-2 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-sky-500"
                                                               @blur="editing = false; $wire.updateCrimpLotDate({{ $crimp->id }}, value)"
                                                               @keydown.enter="editing = false; $wire.updateCrimpLotDate({{ $crimp->id }}, value)"
                                                               @keydown.escape="editing = false"
                                                               x-effect="if (editing) $el.focus()">
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 font-mono text-slate-700 dark:text-slate-300">{{ $itemLabel }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                            <td class="px-4 py-3 font-mono text-slate-900 dark:text-white">{{ $item->wo_number_ps ?? '—' }}</td>
                                            <td class="px-4 py-3 text-slate-900 dark:text-white">{{ $poNum }}</td>
                                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $part?->item_number ?? '—' }}</td>
                                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $part?->description ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right font-medium tabular-nums text-slate-900 dark:text-white">{{ number_format($item->quantity_packed) }}</td>
                                            {{-- Celda Date: editable solo en Borrador/Pendiente --}}
                                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400"
                                                x-data="{ editing: false, value: '{{ $item->lot_date_code ?? '' }}' }">
                                                @if ($packingSlip->isShipped() || $packingSlip->isCancelled())
                                                    <span class="inline-block min-w-[80px]">{{ $item->lot_date_code ?: '—' }}</span>
                                                @else
                                                    <span x-show="!editing" @click="editing = true"
                                                          class="inline-block min-w-[80px] cursor-pointer hover:text-sky-600 hover:underline"
                                                          x-text="value || '—'"></span>
                                                    <input x-show="editing" x-model="value" type="text" maxlength="20" placeholder="ej: 250512A22"
                                                           class="w-36 rounded border border-sky-400 px-2 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-sky-500"
                                                           @blur="editing = false; $wire.updateItemDate({{ $item->id }}, value)"
                                                           @keydown.enter="editing = false; $wire.updateItemDate({{ $item->id }}, value)"
                                                           @keydown.escape="editing = false"
                                                           x-effect="if (editing) $el.focus()">
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 font-mono text-slate-700 dark:text-slate-300">{{ $itemLabel }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                {{-- Subtotal por PO (formato FPL-10) --}}
                                <tr class="border-t-2 border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-900/20">
                                    <td colspan="3" class="px-4 py-2"></td>
                                    <td class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">Total PO {{ $poNumber }}:</td>
                                    <td class="px-4 py-2 text-right text-sm font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($poItems->sum('quantity_packed')) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Total de piezas:</td>
                                <td class="px-4 py-3 text-right text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($packingSlip->items->sum('quantity_packed')) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @else
            <x-ui.empty icon="box" title="Este Shipping List no tiene items." />
        @endif
    </x-ui.section>

    {{-- Panel Invoice (solo Despachado) --}}
    @if ($packingSlip->isShipped())
        <x-ui.section title="Invoice">
            @if (! $packingSlip->hasInvoice())
                <x-ui.note tone="info" title="Listo para Invoice">
                    Este Shipping List fue despachado y está disponible para que el departamento de Órdenes genere el Invoice correspondiente.
                </x-ui.note>
            @else
                @php $inv = $packingSlip->invoice; @endphp
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Invoice #</dt>
                        <dd class="mt-0.5 font-mono text-base font-semibold text-slate-900 dark:text-white">Invoice#{{ $inv->invoice_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Estado</dt>
                        <dd class="mt-0.5">
                            @php
                                $invTone = match ($inv->status) {
                                    'draft' => 'warn', 'issued' => 'good', 'cancelled' => 'bad', default => 'neutral',
                                };
                            @endphp
                            <x-ui.badge :tone="$invTone">{{ $inv->statusLabel }}</x-ui.badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $inv->isIssued() ? 'Fecha emisión' : 'Fecha creación' }}</dt>
                        <dd class="mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ ($inv->issued_at ?? $inv->created_at)?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            @endif
        </x-ui.section>
    @endif

    {{-- Metadatos --}}
    <x-ui.section title="Metadatos">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Creado" :value="$packingSlip->created_at->format('d/m/Y H:i')" />
            <x-ui.kv label="Última actualización" :value="$packingSlip->updated_at->format('d/m/Y H:i')" />
        </dl>
    </x-ui.section>

</div>
