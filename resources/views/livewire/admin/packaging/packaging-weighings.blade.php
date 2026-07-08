<div class="space-y-6">
    {{-- Mensajes Flash --}}
    @if (session()->has('message'))
        <div class="fixed top-4 right-4 z-[70] bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white px-4 py-3 rounded-lg shadow-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm font-medium">{{ session('message') }}</span>
            </div>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="fixed top-4 right-4 z-[70] bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white px-4 py-3 rounded-lg shadow-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Pesadas de Empaque (CRIMP)</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manguitas (piezas) y CRIMP empacados por viajero</p>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Viajeros con Pesadas</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['lots']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 border-2 border-sky-200 dark:border-sky-700 rounded-lg p-4">
            <div class="text-xs text-sky-600 dark:text-sky-400 mb-1">Manguitas Empacadas</div>
            <div class="text-2xl font-semibold text-sky-700 dark:text-sky-300">{{ number_format($stats['pieces']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 border-2 border-amber-200 dark:border-amber-700 rounded-lg p-4">
            <div class="text-xs text-amber-600 dark:text-amber-400 mb-1">CRIMP Empacados</div>
            <div class="text-2xl font-semibold text-amber-700 dark:text-amber-300">{{ number_format($stats['crimp']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Registros</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['records']) }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Buscar</label>
                <input wire:model.live.debounce.300ms="search" type="text"
                    class="w-full px-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                    placeholder="Buscar por WO, viajero, parte...">
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">WO</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Viajero</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Parte</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Obj. CRIMP</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-sky-600 dark:text-sky-400 uppercase">Manguitas</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase">CRIMP</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase"># Pesadas</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($lots as $lot)
                        @php
                            $po = $lot->workOrder->purchaseOrder ?? null;
                            $part = $po->part ?? null;
                            $pieces = $lot->getPackagedPiecesTotal();
                            $crimp = $lot->getPackagedCrimpTotal();
                            $target = $lot->getCrimpTargetTotal();
                            $count = $lot->packagingPieceWeighings->count() + $lot->packagingCrimpWeighings->count();
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 text-blue-600 dark:text-blue-400 font-medium">{{ $po->wo ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">{{ $lot->lot_number }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $part->number ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">{{ number_format($target) }}</td>
                            <td class="px-6 py-4 text-right font-medium text-sky-600 dark:text-sky-400">{{ number_format($pieces) }}</td>
                            <td class="px-6 py-4 text-right font-medium text-amber-600 dark:text-amber-400">{{ number_format($crimp) }}</td>
                            <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">{{ number_format($count) }}</td>
                            <td class="px-6 py-4 text-center">
                                <button wire:click="openDetailModal({{ $lot->id }})"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-900/20 border-2 border-teal-200 dark:border-teal-800 rounded-md hover:bg-teal-100 dark:hover:bg-teal-900/40 transition-colors">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Ver Detalle
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <p class="mt-4 text-base font-medium text-gray-900 dark:text-white">No hay pesadas de Empaque registradas</p>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Las pesadas aparecerán aquí cuando Empaque confirme manguitas o CRIMP.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($lots->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                {{ $lots->links() }}
            </div>
        @endif
    </div>

    {{-- Modal de Detalle del Viajero --}}
    @if ($showDetailModal && $selectedLot)
        <div wire:key="modal-packaging-detail" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/50 transition-opacity" wire:click="closeDetailModal"></div>

                <div class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border-2 border-gray-200 dark:border-gray-700 rounded-lg">
                    {{-- Header --}}
                    <div class="px-6 py-4 border-b-2 border-gray-200 dark:border-gray-700 bg-teal-600">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Detalle de Pesadas - Viajero</h3>
                                <p class="text-sm text-teal-100 mt-1">
                                    WO: {{ $selectedLot->workOrder->purchaseOrder->wo ?? 'N/A' }} |
                                    Viajero: {{ $selectedLot->lot_number }} |
                                    Parte: {{ $selectedLot->workOrder->purchaseOrder->part->number ?? 'N/A' }}
                                </p>
                            </div>
                            <button wire:click="closeDetailModal" class="text-white hover:text-teal-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-6 max-h-[70vh] overflow-y-auto">
                        {{-- Resumen --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div class="bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 p-3 rounded-lg text-center">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Obj. CRIMP</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($crimpTarget) }}</div>
                            </div>
                            <div class="bg-sky-50 dark:bg-sky-900/20 border-2 border-sky-200 dark:border-sky-700 p-3 rounded-lg text-center">
                                <div class="text-xs text-sky-600 dark:text-sky-400">Manguitas Empacadas</div>
                                <div class="text-lg font-bold text-sky-700 dark:text-sky-300">{{ number_format($pieceTotal) }}</div>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-200 dark:border-amber-700 p-3 rounded-lg text-center">
                                <div class="text-xs text-amber-600 dark:text-amber-400">CRIMP Empacados</div>
                                <div class="text-lg font-bold text-amber-700 dark:text-amber-300">{{ number_format($crimpTotal) }}</div>
                            </div>
                        </div>

                        {{-- Pesadas de Manguitas --}}
                        <div>
                            <h4 class="text-sm font-semibold text-sky-700 dark:text-sky-300 mb-2 flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-sky-500 border-2 border-sky-300 dark:border-sky-600 inline-block"></span>
                                Pesadas de Manguitas ({{ count($pieceWeighings) }})
                            </h4>
                            @include('livewire.admin.packaging.partials.weighing-table', ['rows' => $pieceWeighings, 'type' => 'piece', 'headBg' => 'bg-sky-50 dark:bg-sky-900/20', 'accentText' => 'text-sky-600 dark:text-sky-400', 'total' => $pieceTotal])
                        </div>

                        {{-- Pesadas de CRIMP --}}
                        <div>
                            <h4 class="text-sm font-semibold text-amber-700 dark:text-amber-300 mb-2 flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-amber-500 border-2 border-amber-300 dark:border-amber-600 inline-block"></span>
                                Pesadas de CRIMP ({{ count($crimpWeighings) }})
                            </h4>
                            @include('livewire.admin.packaging.partials.weighing-table', ['rows' => $crimpWeighings, 'type' => 'crimp', 'headBg' => 'bg-amber-50 dark:bg-amber-900/20', 'accentText' => 'text-amber-600 dark:text-amber-400', 'total' => $crimpTotal])
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-end">
                        <button wire:click="closeDetailModal"
                            class="px-4 py-2 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Edición de Pesada --}}
    @if ($showEditModal)
        <div wire:key="modal-packaging-edit" class="fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 transition-opacity" wire:click="closeEditModal"></div>

                <div class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border-2 border-gray-200 dark:border-gray-700 rounded-lg">
                    <div class="px-6 py-4 border-b-2 border-gray-200 dark:border-gray-700 {{ $editType === 'crimp' ? 'bg-amber-600' : 'bg-sky-600' }}">
                        <h3 class="text-lg font-semibold text-white">
                            Editar Pesada de {{ $editType === 'crimp' ? 'CRIMP' : 'Manguitas' }}
                        </h3>
                    </div>

                    <div class="px-6 py-4 space-y-4">
                        {{-- Lote de CRIMP --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lote de CRIMP</label>
                            <select wire:model="editCrimpLotId"
                                class="w-full px-4 py-2 border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-md focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                                <option value="">— Sin lote —</option>
                                @foreach ($selectedLot?->crimpLots ?? [] as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->crimp_lot_number }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Cantidad + Peso --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cantidad *</label>
                                <input wire:model="editQuantity" type="number" min="1"
                                    class="w-full px-4 py-2 border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-md focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                    placeholder="0">
                                @error('editQuantity')
                                    <span class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Peso (kg)</label>
                                <input wire:model="editWeight" type="number" step="0.001" min="0"
                                    class="w-full px-4 py-2 border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-md focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                    placeholder="opcional">
                                @error('editWeight')
                                    <span class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        {{-- Fecha --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fecha y Hora *</label>
                            <input wire:model="editWeighedAt" type="datetime-local"
                                class="w-full px-4 py-2 border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-md focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                            @error('editWeighedAt')
                                <span class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Comentarios --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Comentarios</label>
                            <textarea wire:model="editComments" rows="2"
                                class="w-full px-4 py-2 border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-md focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                placeholder="Observaciones (opcional)..."></textarea>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex gap-3 justify-end">
                        <button wire:click="closeEditModal"
                            class="px-4 py-2 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancelar
                        </button>
                        <button wire:click="saveWeighing"
                            class="px-4 py-2 {{ $editType === 'crimp' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-sky-600 hover:bg-sky-700' }} text-white font-medium rounded-md transition-colors">
                            Actualizar Pesada
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
