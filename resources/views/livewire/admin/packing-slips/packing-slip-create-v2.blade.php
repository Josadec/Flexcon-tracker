<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            {{-- Usa wire:click para guardar en sesión el tab de retorno ('list')
                 antes de redirigir, manteniendo la URL limpia sin query strings. --}}
            <button
                wire:click="goBackToShippingList"
                class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver a Shipping List
            </button>
            <h1 class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">Nuevo Shipping List</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Crea un documento de empaque seleccionando lotes disponibles para despacho.
            </p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="text-xs text-gray-500 dark:text-gray-400">Lotes disponibles</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $availableLots->count() }}</div>
            </div>
            <div class="rounded-lg border-2 border-blue-200 bg-white p-4 dark:border-blue-800 dark:bg-gray-800">
                <div class="text-xs text-gray-500 dark:text-gray-400">Seleccionados</div>
                <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ count($selectedLotIds) }}</div>
            </div>
            <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="text-xs text-gray-500 dark:text-gray-400">Fecha documento</div>
                <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $document_date ?: 'Pendiente' }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Información del Shipping List</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Datos generales del documento antes de seleccionar lotes.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 p-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha del documento</label>
                            <input
                                type="date"
                                wire:model="document_date"
                                class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                            @error('document_date')
                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Si la dejas vacia, se puede capturar despues desde el detalle del Shipping List.
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Notas</label>
                            <textarea
                                wire:model="notes"
                                rows="4"
                                maxlength="1000"
                                placeholder="Comentarios adicionales para el documento..."
                                class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            ></textarea>
                            @error('notes')
                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 dark:border-gray-700 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Lotes disponibles para despacho</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Selecciona los lotes que deben incluirse en este Shipping List.
                            </p>
                        </div>
                        <span class="inline-flex items-center self-start rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                            {{ count($selectedLotIds) }} seleccionado(s)
                        </span>
                    </div>

                    <div class="p-4">
                        @error('selectedLotIds')
                            <div class="mb-4 rounded-lg border-2 border-red-200 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-900/20">
                                <p class="text-sm font-medium text-red-700 dark:text-red-300">{{ $message }}</p>
                            </div>
                        @enderror

                        @if ($availableLots->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Sel.</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Work Order</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">PO</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Item No.</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Descripcion</th>
                                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Cantidad</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Label Spec</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                        @foreach ($availableLots as $lot)
                                            @php
                                                $isSelected = in_array($lot->id, $selectedLotIds);
                                                $woPreview = $lot->workOrder?->buildWoCode((int) $lot->lot_number);
                                            @endphp
                                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30 {{ $isSelected ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                                <td class="px-4 py-3">
                                                    <input
                                                        type="checkbox"
                                                        wire:click="toggleLot({{ $lot->id }})"
                                                        {{ $isSelected ? 'checked' : '' }}
                                                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">
                                                    {{ $woPreview ?: 'Sin WO externo' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    {{ $lot->workOrder?->purchaseOrder?->po_number ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    {{ $lot->workOrder?->purchaseOrder?->part?->item_number ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    <div class="max-w-xs truncate">
                                                        {{ $lot->workOrder?->purchaseOrder?->part?->number ?? '-' }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ number_format($lot->quantity_packed_final ?? $lot->quantity ?? 0) }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input
                                                        type="text"
                                                        wire:model="dateSpecs.{{ $lot->id }}"
                                                        maxlength="20"
                                                        placeholder="ej: 20250512A22"
                                                        class="w-36 rounded-md border-2 border-gray-200 bg-white px-2 py-1.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">
                                                    {{ $lot->workOrder?->purchaseOrder?->part?->label_spec ?: '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900/50">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">No hay lotes disponibles para despacho</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Los lotes deben estar listos para shipping y no pertenecer a otro Shipping List activo.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Resumen</h2>
                    <div class="mt-3 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                        <p>Se recomienda verificar que cada lote tenga WO externo antes de guardar.</p>
                        <p>El campo <span class="font-medium text-gray-900 dark:text-white">Date</span> se prellena con el lote y puede ajustarse manualmente.</p>
                        <p>El documento se crea inicialmente en estado <span class="font-medium text-gray-900 dark:text-white">Borrador</span>.</p>
                    </div>
                </div>

                <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Acciones</div>
                    <div class="mt-4 flex flex-col gap-2">
                        <a
                            href="{{ route('admin.shipping-list.index') }}"
                            wire:navigate
                            class="inline-flex items-center justify-center rounded-md border-2 border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                        >
                            Cancelar
                        </a>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                            @if ($availableLots->count() === 0) disabled @endif
                        >
                            Crear Shipping List
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
