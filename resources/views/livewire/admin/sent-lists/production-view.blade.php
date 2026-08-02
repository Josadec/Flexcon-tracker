<div class="space-y-6">

    {{-- Aviso de solo lectura: el backend rechaza toda edición fuera de etapa/rol --}}
    @unless ($this->canEditDepartment())
        <div class="flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg text-amber-800 dark:text-amber-300">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium">Modo solo lectura: esta lista no está en la etapa de tu departamento o ya fue cerrada. No puedes modificar sus datos.</span>
        </div>
    @endunless

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg text-green-800 dark:text-green-300">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg text-red-800 dark:text-red-300">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Production Sections per WO --}}
    @forelse ($workOrders as $wo)
        @php $isCrimp = $wo->purchaseOrder->part->is_crimp ?? false; @endphp

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            {{-- WO Header --}}
            <div class="flex items-center gap-4 px-5 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ route('admin.sent-lists.display.wo', $wo->id) }}"
                    wire:navigate
                    class="font-mono font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline"
                    title="Ver este WO en la Lista de envío">{{ $wo->purchaseOrder->wo ?? $wo->wo_number }}</a>
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $wo->purchaseOrder->part->number ?? '-' }}</span>
                <span class="text-gray-500 dark:text-gray-400 text-sm truncate flex-1">{{ $wo->purchaseOrder->part->description ?? '' }}</span>
                @if ($isCrimp)
                    <span class="px-2 py-0.5 text-xs font-medium bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-300 rounded">CRIMP</span>
                @endif
            </div>

            {{-- Lots --}}
            @if ($wo->lots->isNotEmpty())
                <div class="p-4 space-y-4">
                    @foreach ($wo->lots as $lot)
                        @php
                            $totalWeighed  = $lot->weighings->whereNull('kit_id')->sum('good_pieces');
                            $targetQty     = $lot->quantity;
                            $progressPct   = $targetQty > 0 ? min(100, round(($totalWeighed / $targetQty) * 100)) : 0;
                            $isCompleted   = $lot->status === 'completed';
                            $progressColor = $isCompleted ? 'bg-green-500' : ($progressPct > 0 ? 'bg-blue-500' : 'bg-gray-300 dark:bg-gray-600');
                        @endphp

                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                            {{-- Lot Header --}}
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/40">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-200">Lote {{ $lot->lot_number }}</span>
                                    @if ($lot->completion_count > 0)
                                        <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded">Completado {{ $lot->completion_count }}</span>
                                    @endif
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Meta: {{ number_format($targetQty) }} pzas
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium {{ $isCompleted ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400' }}">
                                        {{ number_format($totalWeighed) }} / {{ number_format($targetQty) }}
                                    </span>
                                    @if ($isCompleted)
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Lote Completo
                                        </span>
                                        <button wire:click="reopenLot({{ $lot->id }})"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg transition-colors">
                                            Reabrir
                                        </button>
                                    @else
                                        <button wire:click="openWeighingModal({{ $lot->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Agregar Pesada
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Progress Bar --}}
                            <div class="px-4 py-2 bg-white dark:bg-gray-800">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="{{ $progressColor }} h-full rounded-full transition-all duration-300" style="width: {{ $progressPct }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium {{ $progressPct >= 100 ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400' }} w-10 text-right">
                                        {{ $progressPct }}%
                                    </span>
                                </div>
                            </div>

                            {{-- Weighings History --}}
                            @php
                                $lotOnlyWeighings = $isCrimp
                                    ? $lot->weighings->whereNull('kit_id')->values()
                                    : $lot->weighings;
                                $lotOnlyTotal = $lotOnlyWeighings->sum('quantity');
                            @endphp
                            @if ($lotOnlyWeighings->isNotEmpty())
                                <div class="border-t border-gray-100 dark:border-gray-700">
                                    @if ($isCrimp)
                                        <div class="px-4 py-1.5 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-800">
                                            <span class="text-xs font-semibold text-blue-700 dark:text-blue-300 uppercase tracking-wider">Pesadas de Lote</span>
                                        </div>
                                    @endif
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50/70 dark:bg-gray-900/30">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fecha/Hora</th>
                                                <th class="px-4 py-2 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Piezas</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usuario</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Comentarios</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach ($lotOnlyWeighings as $weighing)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                                        {{ \Carbon\Carbon::parse($weighing->weighed_at)->format('d/m/Y H:i') }}
                                                    </td>
                                                    <td class="px-4 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                                                        {{ number_format($weighing->good_pieces) }}
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                        {{ $weighing->weighedBy->name ?? 'N/A' }}
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                                        {{ $weighing->comments ?: '-' }}
                                                    </td>
                                                    <td class="px-4 py-2 text-center">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <button wire:click="editWeighing({{ $weighing->id }})"
                                                                class="p-1 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition-colors" title="Editar">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                                </svg>
                                                            </button>
                                                            <button wire:click="deleteWeighing({{ $weighing->id }})"
                                                                wire:confirm="¿Eliminar esta pesada?"
                                                                class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors" title="Eliminar">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-gray-50 dark:bg-gray-900/30">
                                            <tr>
                                                <td class="px-4 py-2 font-semibold text-gray-700 dark:text-gray-300 text-xs uppercase">Total</td>
                                                <td class="px-4 py-2 text-right font-bold text-gray-900 dark:text-white text-xs">{{ number_format($lotOnlyWeighings->sum('good_pieces')) }}</td>
                                                <td colspan="3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="px-4 py-5 text-center text-sm text-gray-400 dark:text-gray-500 italic border-t border-gray-100 dark:border-gray-700">
                                    @if ($isCrimp)
                                        Sin pesadas de lote. Usa "Agregar Pesada" para registrar piezas del viajero.
                                    @else
                                        Sin pesadas registradas. Usa "Agregar Pesada" para comenzar.
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Pesadas por Kit: eliminado en el reajuste CRIMP — las pesadas de CRIMP
                     se registran a nivel viajero (igual que NO-CRIMP), sin selección de kit. --}}

            @else
                <div class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500 italic">
                    Este WO no tiene lotes asignados.
                </div>
            @endif
        </div>
    @empty
        <div class="text-center py-10 text-gray-400 dark:text-gray-500">No hay Work Orders en esta lista.</div>
    @endforelse

    {{-- Footer Actions --}}
    <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
        <button wire:click="openSendModal"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
            </svg>
            Enviar a Calidad
        </button>
    </div>

    {{-- ===== ADD WEIGHING MODAL ===== --}}
    @if ($showWeighingModal)
        @php
            $modalLot       = $workOrders->flatMap->lots->firstWhere('id', $weighingLotId);
            $modalWo        = $modalLot ? $workOrders->firstWhere('id', $modalLot->work_order_id) : null;
            $modalIsCrimp   = $modalWo ? ($modalWo->purchaseOrder->part->is_crimp ?? false) : false;
            $modalTotalWeighed = $modalLot ? (int) $modalLot->weighings->whereNull('kit_id')->sum('good_pieces') : 0;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/70" wire:click="closeWeighingModal"></div>
            <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Registrar Pesada</h3>
                        @if ($modalLot)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Lote {{ $modalLot->lot_number }}
                                &mdash; Pesadas: {{ number_format($modalTotalWeighed) }} / {{ number_format($modalLot->quantity) }} pzas
                            </p>
                        @endif
                    </div>
                    <button wire:click="closeWeighingModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    {{-- Editing indicator --}}
                    @if ($editingWeighingId)
                        <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <span class="text-sm text-blue-700 dark:text-blue-300 font-medium">Editando pesada existente</span>
                            <button wire:click="closeWeighingModal" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">Cancelar</button>
                        </div>
                    @endif

                    {{-- Quantity --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Piezas pesadas <span class="text-red-500">*</span>
                        </label>
                        <input type="number" wire:model="weighingQuantity" min="1" placeholder="0"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('weighingQuantity')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Date/Time --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha y hora <span class="text-red-500">*</span></label>
                        <input type="datetime-local" wire:model="weighingAt"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('weighingAt')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Comments --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios (opcional)</label>
                        <textarea wire:model="weighingComments" rows="2" placeholder="Observaciones..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="closeWeighingModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="saveWeighing"
                        class="px-4 py-2 text-sm font-medium text-white {{ $editingWeighingId ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700' }} rounded-lg transition-colors">
                        {{ $editingWeighingId ? 'Actualizar Pesada' : 'Guardar Pesada' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== SEND TO QUALITY MODAL ===== --}}
    @if ($showSendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/70" wire:click="closeSendModal"></div>
            <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Enviar a Calidad</h3>
                    <button wire:click="closeSendModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Resumen de piezas producidas por lote (viajero):</p>

                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Lote</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Meta</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Pesadas</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($workOrders as $wo)
                                    @php $woIsCrimp = $wo->purchaseOrder->part->is_crimp ?? false; @endphp
                                    @foreach ($wo->lots as $lot)
                                        @php $weighed = $lot->weighings->whereNull('kit_id')->sum('good_pieces'); @endphp
                                        <tr>
                                            <td class="px-4 py-2.5 font-mono text-gray-800 dark:text-gray-200">{{ $lot->lot_number }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-400">{{ number_format($lot->quantity) }}</td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-gray-900 dark:text-white">{{ number_format($weighed) }}</td>
                                            <td class="px-4 py-2.5 text-right">
                                                @php $pct = $lot->quantity > 0 ? min(100, round($weighed / $lot->quantity * 100)) : 0; @endphp
                                                <span class="{{ $pct >= 100 ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }} font-medium">{{ $pct }}%</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas (opcional)</label>
                        <textarea wire:model="sendNotes" rows="2" placeholder="Observaciones para Calidad..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="closeSendModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="sendToQuality"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                        Confirmar Envío
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
