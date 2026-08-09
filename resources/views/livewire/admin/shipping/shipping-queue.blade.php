<div class="space-y-5">

    {{-- Header: se omite cuando el componente se embebe en otro (ej. tab de PackingSlipList) --}}
    @unless ($embedded)
        <div class="border-b border-slate-200 pb-5 dark:border-slate-700">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300">Embarque</div>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">WO Listos para SL</h1>
            <p class="mt-1 max-w-2xl text-sm leading-5 text-slate-500 dark:text-slate-400">
                Work Orders con lotes disponibles para crear un Packing Slip (FPL-10).
            </p>
        </div>
    @endunless

    @if ($successMessage)
        <x-ui.note tone="success">{{ $successMessage }}</x-ui.note>
    @endif
    @if ($errorMessage)
        <x-ui.note tone="danger">{{ $errorMessage }}</x-ui.note>
    @endif

    {{-- Resumen --}}
    <x-ui.stats cols="3">
        <x-ui.stat label="Lotes en cola" :value="number_format($lotsInQueue->total())" />
        <x-ui.stat label="Seleccionados" :value="number_format(count($selectedLotIds))" tone="info" />
        <x-ui.stat label="En esta página" :value="number_format($lotsInQueue->count())" />
    </x-ui.stats>

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por lote, parte o WO externo, y por tipo de cierre.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,2fr)_14rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="searchTerm"
                        placeholder="Lote, parte o WO externo..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Tipo de cierre">
                <select wire:model.live="filterClosedByType" class="w-full">
                    @foreach ($closureTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if (!empty($selectedLotIds))
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ count($selectedLotIds) }} lote(s) seleccionado(s).</span>
                <div class="flex items-center gap-2">
                    <x-ui.btn variant="ghost" size="sm" wire:click="clearSelection">Limpiar selección</x-ui.btn>
                    @if ($canCreatePs)
                        <x-ui.btn variant="primary" size="sm" wire:click="openCreatePsModal">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Crear Packing Slip ({{ count($selectedLotIds) }})
                        </x-ui.btn>
                    @endif
                </div>
            </div>
        @endif
    </x-ui.section>

    {{-- Cola de lotes --}}
    @php
        $closureTone = fn (?string $t) => match ($t) {
            'complete_lot' => 'good', 'new_lot' => 'info', 'close_as_is' => 'warn', default => 'neutral',
        };
        $closureLabel = fn (?string $t) => match ($t) {
            'complete_lot' => 'Completo', 'new_lot' => 'Nuevo lote', 'close_as_is' => 'Tal cual', default => '—',
        };
    @endphp
    <x-ui.table>
        <x-slot:head>
            <tr>
                @if ($canCreatePs)
                    <x-ui.th class="w-10">
                        <input type="checkbox" wire:click="toggleSelectAll"
                            wire:key="select-all-{{ $allPageSelected ? 1 : 0 }}-{{ $somePageSelected ? 1 : 0 }}"
                            @checked($allPageSelected)
                            x-data x-init="$el.indeterminate = @js($somePageSelected)"
                            title="Seleccionar todos los de esta página"
                            class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600">
                        <span class="sr-only">Seleccionar todos</span>
                    </x-ui.th>
                @endif
                <x-ui.th>Viajero / Lote</x-ui.th>
                <x-ui.th>Work Order</x-ui.th>
                <x-ui.th align="right">Qty empacada</x-ui.th>
                <x-ui.th>Tipo cierre</x-ui.th>
                <x-ui.th>Fecha cierre</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($lotsInQueue as $lot)
            @php
                $hasExternalWo = $lot->workOrder?->hasExternalWoNumber();
                $isSelected    = in_array($lot->id, $selectedLotIds);
                $woCode = $hasExternalWo
                    ? 'W0' . $lot->workOrder->getEffectiveWoNumber() . str_pad((string) $lot->lot_number, 3, '0', STR_PAD_LEFT)
                    : null;
            @endphp
            <tr wire:key="qlot-{{ $lot->id }}"
                class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/30 {{ $isSelected ? 'bg-sky-50 dark:bg-sky-900/20' : '' }}"
                @if ($canCreatePs) wire:click="toggleLot({{ $lot->id }})" style="cursor: pointer;" @endif>
                @if ($canCreatePs)
                    <td class="px-4 py-3" wire:click.stop>
                        <input type="checkbox" wire:click="toggleLot({{ $lot->id }})"
                            @checked($isSelected) @disabled(!$hasExternalWo)
                            class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 disabled:opacity-40 dark:border-slate-600">
                    </td>
                @endif

                <td class="whitespace-nowrap px-4 py-3">
                    <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</span>
                </td>

                <td class="px-4 py-3">
                    @if ($woCode)
                        <div class="font-mono font-semibold text-slate-900 dark:text-white">{{ $woCode }}</div>
                        <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $lot->workOrder?->purchaseOrder?->part?->number ?? '—' }}</div>
                    @else
                        <div class="font-medium text-slate-900 dark:text-white">{{ $lot->workOrder?->wo_number ?? '—' }}</div>
                        <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $lot->workOrder?->purchaseOrder?->part?->number ?? '—' }}</div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <x-ui.badge tone="warn">
                                <svg class="size-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                Sin WO externo — no puede incluirse en PS
                            </x-ui.badge>
                            @if ($canCreatePs)
                                <x-ui.btn variant="warning" size="sm" wire:click.stop="openReturnModal({{ $lot->id }})"
                                    title="Devolver este lote a Empaque para que pueda ser re-procesado">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                    Devolver a Empaque
                                </x-ui.btn>
                            @endif
                        </div>
                    @endif
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($lot->getTotalCompletedPieces()) }}</span>
                    <span class="ml-1 text-xs text-slate-400">pzs</span>
                </td>

                <td class="px-4 py-3">
                    <x-ui.badge :tone="$closureTone($lot->closed_by_type)">{{ $closureLabel($lot->closed_by_type) }}</x-ui.badge>
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $lot->ready_for_shipping_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $canCreatePs ? 6 : 5 }}">
                    <x-ui.empty icon="box" title="No hay lotes en la cola de despacho"
                        hint="Los lotes aparecen aquí cuando Empaque cierra un lote (viajero + decisión de cierre)." />
                </td>
            </tr>
        @endforelse

        @if ($lotsInQueue->hasPages())
            <x-slot:foot>{{ $lotsInQueue->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- MODAL: Devolver lote a Empaque --}}
    @if ($showReturnModal)
        <x-ui-modal wire:key="modal-return-lot" title="Devolver lote a Empaque"
            subtitle="El lote regresará al área de Empaque para ser re-procesado." close="cancelReturnLot" maxWidth="lg">

            @if ($returningLot)
                <x-ui.section title="Información del lote">
                    <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                        <x-ui.kv label="Número de lote" :value="$returningLot->lot_number" />
                        <x-ui.kv label="WO interna" :value="$returningLot->workOrder?->wo_number ?? '—'" />
                        <x-ui.kv label="Número de parte" :value="$returningLot->workOrder?->purchaseOrder?->part?->number ?? '—'" />
                        <x-ui.kv label="Qty empacada" :value="number_format($returningLot->getTotalCompletedPieces()).' pzs'" />
                    </dl>
                </x-ui.section>
            @endif

            <x-ui.note tone="warn" title="Importante">
                Esta acción regresará el lote a Empaque. Se borrarán los datos de cierre (decisión de cierre, cantidad final
                empacada) para que Empaque pueda volver a procesarlo. Los registros de empaque existentes se conservan.
            </x-ui.note>

            <x-ui.section title="Motivo">
                <x-ui.field label="Motivo de devolución" required>
                    <textarea wire:model="returnReason" rows="3" maxlength="255"
                        placeholder="Describe el motivo por el que se devuelve este lote a Empaque..." class="w-full"></textarea>
                    <p class="mt-1 text-[11px] text-slate-400">{{ strlen($returnReason) }}/255 caracteres</p>
                </x-ui.field>
                @if ($errorMessage)
                    <x-ui.note tone="danger" class="mt-3">{{ $errorMessage }}</x-ui.note>
                @endif
            </x-ui.section>

            <x-slot:note>El lote saldrá de la cola de despacho y volverá a Empaque.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelReturnLot">Cancelar</x-ui.btn>
                <x-ui.btn variant="warning" wire:click="confirmReturnLot" wire:loading.attr="disabled" wire:target="confirmReturnLot">
                    <span wire:loading.remove wire:target="confirmReturnLot">Confirmar devolución</span>
                    <span wire:loading wire:target="confirmReturnLot">Procesando...</span>
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- MODAL: Crear Packing Slip --}}
    @if ($showCreatePsModal)
        <x-ui-modal wire:key="modal-create-ps" title="Crear Packing Slip"
            subtitle="Se creará en estado Borrador con {{ count($selectedLotIds) }} lote(s)." close="cancelCreatePs" maxWidth="2xl">

            <x-ui.section title="Lotes incluidos" hint="Estos lotes se agregarán al nuevo Packing Slip.">
                <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Viajero / Lote</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Parte</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Qty</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Label Spec</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach ($selectedLots as $lot)
                                    <tr wire:key="sel-lot-{{ $lot->id }}">
                                        <td class="px-4 py-2.5 font-mono text-xs font-medium text-slate-900 dark:text-white">{{ $lot->lot_number }}</td>
                                        <td class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $lot->workOrder?->purchaseOrder?->part?->number ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right text-xs font-medium tabular-nums text-slate-900 dark:text-white">{{ number_format($lot->getTotalCompletedPieces()) }}</td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-300">{{ $lot->workOrder?->purchaseOrder?->part?->label_spec ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-ui.section>

            <x-ui.section title="Notas">
                <x-ui.field label="Notas del Packing Slip" optional>
                    <textarea wire:model="psNotes" rows="3" placeholder="Instrucciones especiales de envío, observaciones..." class="w-full"></textarea>
                </x-ui.field>
                @if ($errorMessage)
                    <x-ui.note tone="danger" class="mt-3">{{ $errorMessage }}</x-ui.note>
                @endif
            </x-ui.section>

            <x-slot:note>El Packing Slip se creará en estado <strong>Borrador</strong>. Podrás revisarlo y confirmarlo antes de despacharlo.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelCreatePs">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="createPackingSlip" wire:loading.attr="disabled" wire:target="createPackingSlip">
                    <span wire:loading.remove wire:target="createPackingSlip">Crear Packing Slip</span>
                    <span wire:loading wire:target="createPackingSlip">Creando...</span>
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

</div>
