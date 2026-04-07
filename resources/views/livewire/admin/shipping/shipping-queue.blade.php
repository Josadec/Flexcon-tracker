<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">WO Listos para PS</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Work Orders con lotes disponibles para crear un Packing Slip (FPL-10)
            </p>
        </div>

        @if($canCreatePs && !empty($selectedLotIds))
            <button
                wire:click="openCreatePsModal"
                class="inline-flex items-center gap-2 self-start rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Crear Packing Slip ({{ count($selectedLotIds) }})
            </button>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Lotes en cola</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $lotsInQueue->total() }}</div>
        </div>
        <div class="rounded-lg border-2 border-blue-200 bg-white p-4 dark:border-blue-800 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Seleccionados</div>
            <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ count($selectedLotIds) }}</div>
        </div>
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Pagina actual</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $lotsInQueue->count() }}</div>
        </div>
    </div>

    <div class="space-y-6">

        {{-- Mensajes de estado --}}
        @if($successMessage)
            <div class="rounded-lg border-2 border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20 flex items-start gap-3">
                <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm text-green-800 dark:text-green-200">{{ $successMessage }}</p>
            </div>
        @endif

        @if($errorMessage)
            <div class="rounded-lg border-2 border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20 flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm text-red-800 dark:text-red-200">{{ $errorMessage }}</p>
            </div>
        @endif

        {{-- Filtros --}}
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col sm:flex-row gap-3">
                {{-- Busqueda --}}
                <div class="flex-1">
                    <label class="sr-only">Buscar</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            wire:model.live.debounce.300ms="searchTerm"
                            type="text"
                            placeholder="Buscar por lote, parte o WO externo..."
                            class="w-full rounded-md border-2 border-gray-200 bg-white py-2 pr-3 pl-9 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        >
                    </div>
                </div>

                {{-- Filtro por tipo de cierre --}}
                <div class="sm:w-56">
                    <select
                        wire:model.live="filterClosedByType"
                        class="w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                        @foreach($closureTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Limpiar seleccion --}}
                @if(!empty($selectedLotIds))
                    <button
                        wire:click="clearSelection"
                        class="rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                    >
                        Limpiar seleccion ({{ count($selectedLotIds) }})
                    </button>
                @endif
            </div>
        </div>

        {{-- Tabla de lotes en cola --}}
        <div class="overflow-hidden rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            @if($lotsInQueue->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">No hay lotes en la cola de despacho</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                        Los lotes aparecen aqui cuando Empaque cierra un lote (viajero + decision de cierre)
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/50">
                                @if($canCreatePs)
                                    <th class="w-10 px-4 py-3 text-left">
                                        <span class="sr-only">Seleccionar</span>
                                    </th>
                                @endif
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Lote</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Work Order</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Qty Empacada</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Tipo Cierre</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Fecha Cierre</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($lotsInQueue as $lot)
                                @php
                                    $hasExternalWo = $lot->workOrder?->hasExternalWoNumber();
                                    $isSelected    = in_array($lot->id, $selectedLotIds);
                                @endphp
                                <tr
                                    class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30 {{ $isSelected ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                                    @if($canCreatePs) wire:click="toggleLot({{ $lot->id }})" style="cursor: pointer;" @endif
                                >
                                    @if($canCreatePs)
                                        <td class="px-4 py-3" wire:click.stop>
                                            <input
                                                type="checkbox"
                                                wire:click="toggleLot({{ $lot->id }})"
                                                @checked($isSelected)
                                                @disabled(!$hasExternalWo)
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-40 dark:border-gray-600"
                                            >
                                        </td>
                                    @endif

                                    {{-- Lote --}}
                                    <td class="px-4 py-3">
                                        <span class="font-mono font-medium text-gray-900 dark:text-white">
                                            {{ $lot->lot_number }}
                                        </span>
                                    </td>

                                    {{-- Work Order (codigo FPL-10 o advertencia) --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $woCode = $hasExternalWo
                                                ? 'W0' . $lot->workOrder->getEffectiveWoNumber() . str_pad((string) $lot->lot_number, 3, '0', STR_PAD_LEFT)
                                                : null;
                                        @endphp
                                        @if($woCode)
                                            <div class="font-mono font-semibold text-gray-900 dark:text-white text-sm">
                                                {{ $woCode }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $lot->workOrder?->purchaseOrder?->part?->number ?? '—' }}
                                            </div>
                                        @else
                                            <div class="text-gray-900 dark:text-white font-medium text-sm">
                                                {{ $lot->workOrder?->wo_number ?? '—' }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $lot->workOrder?->purchaseOrder?->part?->number ?? '—' }}
                                            </div>
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full border border-orange-200 bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:border-orange-800 dark:bg-orange-900/30 dark:text-orange-400">
                                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                                Sin WO externo — no puede incluirse en PS
                                            </span>
                                            @if($canCreatePs)
                                                <button
                                                    wire:click.stop="openReturnModal({{ $lot->id }})"
                                                    class="mt-1.5 inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/40"
                                                    title="Devolver este lote a Empaque para que pueda ser re-procesado"
                                                >
                                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                                    </svg>
                                                    Devolver a Empaque
                                                </button>
                                            @endif
                                        @endif
                                    </td>

                                    {{-- Qty empacada --}}
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-semibold text-gray-900 dark:text-white">
                                            {{ number_format($lot->quantity_packed_final ?? 0) }}
                                        </span>
                                        <span class="text-xs text-gray-400 ml-1">pzs</span>
                                    </td>

                                    {{-- Tipo de cierre --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $typeLabel = match($lot->closed_by_type) {
                                                'complete_lot' => ['label' => 'Completo', 'color' => 'emerald'],
                                                'new_lot'      => ['label' => 'Nuevo lote', 'color' => 'violet'],
                                                'close_as_is'  => ['label' => 'Tal cual', 'color' => 'amber'],
                                                default        => ['label' => '—', 'color' => 'gray'],
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-{{ $typeLabel['color'] }}-100 text-{{ $typeLabel['color'] }}-800 dark:bg-{{ $typeLabel['color'] }}-900/30 dark:text-{{ $typeLabel['color'] }}-300">
                                            {{ $typeLabel['label'] }}
                                        </span>
                                    </td>

                                    {{-- Fecha de cierre --}}
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">
                                        {{ $lot->ready_for_shipping_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginacion --}}
                @if($lotsInQueue->hasPages())
                    <div class="border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50">
                        {{ $lotsInQueue->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

    {{-- ============================================================ --}}
    {{-- MODAL: Devolver lote a Empaque                                --}}
    {{-- ============================================================ --}}
    @if($showReturnModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-data
            x-init="
                document.body.style.overflow = 'hidden';
                $cleanup(() => { document.body.style.overflow = ''; });
            "
        >
            {{-- Overlay --}}
            <div
                class="absolute inset-0 bg-black/50 dark:bg-black/70"
                wire:click="cancelReturnLot"
            ></div>

            {{-- Panel --}}
            <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-lg border-2 border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">

                {{-- Header del modal --}}
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                            Devolver lote a Empaque
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            El lote regresara al area de Empaque para ser re-procesado
                        </p>
                    </div>
                    <button
                        wire:click="cancelReturnLot"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Cuerpo del modal --}}
                <div class="p-6 space-y-5">

                    {{-- Informacion del lote --}}
                    @if($returningLot)
                        <div class="bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-2">
                            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Informacion del lote</h3>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Numero de lote</p>
                                    <p class="font-mono font-semibold text-gray-900 dark:text-white">{{ $returningLot->lot_number }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">WO interna</p>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $returningLot->workOrder?->wo_number ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Numero de parte</p>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $returningLot->workOrder?->purchaseOrder?->part?->number ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Qty empacada</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($returningLot->quantity_packed_final ?? 0) }}
                                        <span class="text-xs text-gray-400 font-normal">pzs</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Aviso de impacto --}}
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3 text-xs text-amber-800 dark:text-amber-300">
                        <strong>Importante:</strong> Esta accion regresara el lote a Empaque. Se borraran los datos de cierre (decision de cierre, cantidad final empacada) para que Empaque pueda volver a procesarlo. Los registros de empaque existentes se conservan.
                    </div>

                    {{-- Campo de motivo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Motivo de devolucion
                            <span class="text-red-500 ml-0.5">*</span>
                        </label>
                        <textarea
                            wire:model="returnReason"
                            rows="3"
                            placeholder="Describe el motivo por el que se devuelve este lote a Empaque..."
                            maxlength="255"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"
                        ></textarea>
                        <p class="text-xs text-gray-400 mt-1">{{ strlen($returnReason) }}/255 caracteres</p>
                    </div>

                    {{-- Error en modal --}}
                    @if($errorMessage)
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-xs text-red-700 dark:text-red-300">
                            {{ $errorMessage }}
                        </div>
                    @endif
                </div>

                {{-- Footer del modal --}}
                <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-200 dark:border-gray-700">
                    <button
                        wire:click="cancelReturnLot"
                        class="rounded-md border-2 border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="confirmReturnLot"
                        wire:loading.attr="disabled"
                        class="flex items-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="confirmReturnLot">Confirmar devolucion</span>
                        <span wire:loading wire:target="confirmReturnLot">Procesando...</span>
                        <svg wire:loading wire:target="confirmReturnLot" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: Crear Packing Slip                                     --}}
    {{-- ============================================================ --}}
    @if($showCreatePsModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-data
            x-init="
                document.body.style.overflow = 'hidden';
                $cleanup(() => { document.body.style.overflow = ''; });
            "
        >
            {{-- Overlay --}}
            <div
                class="absolute inset-0 bg-black/50 dark:bg-black/70"
                wire:click="cancelCreatePs"
            ></div>

            {{-- Panel --}}
            <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-lg border-2 border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">

                {{-- Header del modal --}}
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Crear Packing Slip</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            Se creara en estado Borrador con {{ count($selectedLotIds) }} lote(s)
                        </p>
                    </div>
                    <button
                        wire:click="cancelCreatePs"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Cuerpo del modal --}}
                <div class="p-6 space-y-6">

                    {{-- Tabla de lotes seleccionados --}}
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Lotes incluidos</h3>
                        <div class="overflow-hidden rounded-lg border-2 border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/50">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">Lote</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">Parte</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">Qty</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">
                                            Label Spec
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                    @foreach($selectedLots as $lot)
                                        <tr>
                                            <td class="px-4 py-2.5">
                                                <span class="font-mono font-medium text-gray-900 dark:text-white text-xs">
                                                    {{ $lot->lot_number }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400 text-xs">
                                                {{ $lot->workOrder?->purchaseOrder?->part?->number ?? '—' }}
                                            </td>
                                            <td class="px-4 py-2.5 text-right text-gray-900 dark:text-white font-medium text-xs">
                                                {{ number_format($lot->quantity_packed_final ?? 0) }}
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <span class="text-xs font-mono text-gray-700 dark:text-gray-300">
                                                    {{ $lot->workOrder?->purchaseOrder?->part?->label_spec ?? '—' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Notas del PS --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Notas del Packing Slip
                            <span class="font-normal text-gray-400">(opcional)</span>
                        </label>
                        <textarea
                            wire:model="psNotes"
                            rows="3"
                            placeholder="Instrucciones especiales de envio, observaciones..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                        ></textarea>
                    </div>

                    {{-- Aviso sobre el estado draft --}}
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-xs text-blue-700 dark:text-blue-300">
                        El Packing Slip se creara en estado <strong>Borrador</strong>. Podras revisarlo y confirmarlo antes de despacharlo.
                    </div>

                    {{-- Error en modal --}}
                    @if($errorMessage)
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-xs text-red-700 dark:text-red-300">
                            {{ $errorMessage }}
                        </div>
                    @endif
                </div>

                {{-- Footer del modal --}}
                <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-200 dark:border-gray-700">
                    <button
                        wire:click="cancelCreatePs"
                        class="rounded-md border-2 border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="createPackingSlip"
                        wire:loading.attr="disabled"
                        class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="createPackingSlip">Crear Packing Slip</span>
                        <span wire:loading wire:target="createPackingSlip">Creando...</span>
                        <svg wire:loading wire:target="createPackingSlip" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
