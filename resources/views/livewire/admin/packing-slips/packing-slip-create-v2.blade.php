<div class="ui-screen space-y-5">

    {{-- Encabezado con enlace de regreso (wire:click para conservar el tab de retorno) --}}
    <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between lg:gap-6 dark:border-slate-700">
        <div class="min-w-0">
            <button type="button" wire:click="goBackToShippingList"
                class="group -ml-1 mb-1 inline-flex items-center gap-1 rounded px-1 py-0.5 text-xs font-bold uppercase tracking-[0.12em] text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">
                <svg class="size-3.5 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Volver a Shipping List
            </button>
            <h1 class="truncate text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Nuevo Shipping List</h1>
            <p class="mt-1 max-w-2xl text-sm leading-5 text-slate-500 dark:text-slate-400">
                Crea un documento de empaque seleccionando lotes disponibles para despacho.
            </p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-5">
        <x-ui.stats cols="3">
            <x-ui.stat label="Lotes disponibles" :value="number_format($availableLots->count())" />
            <x-ui.stat label="Seleccionados" :value="number_format(count($selectedLotIds))" tone="info" />
            <x-ui.stat label="Fecha documento" :value="$document_date ?: 'Pendiente'" />
        </x-ui.stats>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-5">
                <x-ui.section title="Información del Shipping List" hint="Datos generales del documento antes de seleccionar lotes.">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-ui.field label="Fecha del documento" :error="$errors->first('document_date')"
                            hint="Si la dejas vacía, se puede capturar después desde el detalle del Shipping List.">
                            <input type="date" wire:model="document_date" class="w-full">
                        </x-ui.field>

                        <x-ui.field label="Notas" optional :error="$errors->first('notes')">
                            <textarea wire:model="notes" rows="4" maxlength="1000"
                                placeholder="Comentarios adicionales para el documento..." class="w-full"></textarea>
                        </x-ui.field>
                    </div>
                </x-ui.section>

                <x-ui.section title="Lotes disponibles para despacho" hint="Selecciona los lotes que deben incluirse en este Shipping List.">
                    <x-slot:aside>
                        <x-ui.badge tone="info">{{ count($selectedLotIds) }} seleccionado(s)</x-ui.badge>
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
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Item No.</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Parte</th>
                                            <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Cantidad</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Date</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Label Spec</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                        @foreach ($availableLots as $lot)
                                            @php
                                                $isSelected = in_array($lot->id, $selectedLotIds);
                                                $woPreview = $lot->workOrder?->buildWoCode((int) $lot->lot_number);
                                            @endphp
                                            <tr wire:key="avl-{{ $lot->id }}"
                                                class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/30 {{ $isSelected ? 'bg-sky-50 dark:bg-sky-900/20' : '' }}">
                                                <td class="px-4 py-3">
                                                    <input type="checkbox" wire:click="toggleLot({{ $lot->id }})"
                                                        @checked($isSelected)
                                                        class="size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600">
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 font-mono {{ $woPreview ? 'text-slate-900 dark:text-white' : 'text-orange-600 dark:text-orange-400' }}">
                                                    {{ $woPreview ?: 'Sin WO externo' }}
                                                </td>
                                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $lot->workOrder?->purchaseOrder?->po_number ?? '—' }}</td>
                                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $lot->workOrder?->purchaseOrder?->part?->item_number ?? '—' }}</td>
                                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                                    <div class="max-w-xs truncate">{{ $lot->workOrder?->purchaseOrder?->part?->number ?? '—' }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-right font-medium tabular-nums text-slate-900 dark:text-white">
                                                    {{ number_format($lot->quantity_packed_final ?? $lot->quantity ?? 0) }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="text" wire:model="dateSpecs.{{ $lot->id }}" maxlength="20"
                                                        placeholder="ej: 20250512A22" class="w-36">
                                                </td>
                                                <td class="px-4 py-3 font-mono text-slate-700 dark:text-slate-300">{{ $lot->workOrder?->purchaseOrder?->part?->label_spec ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <x-ui.empty icon="box" title="No hay lotes disponibles para despacho"
                            hint="Los lotes deben estar listos para shipping y no pertenecer a otro Shipping List activo." />
                    @endif
                </x-ui.section>
            </div>

            <div class="space-y-5">
                <x-ui.section title="Resumen">
                    <ul class="space-y-2.5 text-sm text-slate-600 dark:text-slate-300">
                        <li>Se recomienda verificar que cada lote tenga WO externo antes de guardar.</li>
                        <li>El campo <span class="font-semibold text-slate-900 dark:text-white">Date</span> se prellena con el lote y puede ajustarse manualmente.</li>
                        <li>El documento se crea inicialmente en estado <span class="font-semibold text-slate-900 dark:text-white">Borrador</span>.</li>
                    </ul>
                </x-ui.section>

                <x-ui.section title="Acciones">
                    <div class="flex flex-col gap-2">
                        <x-ui.btn variant="secondary" block href="{{ route('admin.shipping-list.index') }}">Cancelar</x-ui.btn>
                        <x-ui.btn variant="primary" block type="submit" :disabled="$availableLots->count() === 0">
                            Crear Shipping List
                        </x-ui.btn>
                    </div>
                </x-ui.section>
            </div>
        </div>
    </form>
</div>
