<div class="space-y-6">

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

    {{-- Quality Sections per WO --}}
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
                    <span class="px-2 py-0.5 text-xs font-medium bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 rounded">CRIMP</span>
                @endif
            </div>

            {{-- Lots --}}
            @if ($wo->lots->isNotEmpty())
                <div class="p-4 space-y-4">
                    @foreach ($wo->lots as $lot)
                        @php
                            $prodTotal              = (int) $lot->weighings->whereNull('kit_id')->sum('good_pieces');
                            $lotOnlyQualWeighings   = $lot->qualityWeighings->whereNull('kit_id')->values();
                            $qualityGood            = (int) $lotOnlyQualWeighings->sum('good_pieces');
                            $qualityBad             = (int) $lotOnlyQualWeighings->sum('bad_pieces');
                            $qualityTotal           = $qualityGood + $qualityBad;
                            $pendingPieces          = max(0, $prodTotal - $qualityTotal);
                            $progressPct            = $prodTotal > 0 ? min(100, round(($qualityTotal / $prodTotal) * 100)) : 0;
                            $progressColor          = $progressPct >= 100 ? 'bg-green-500' : ($progressPct > 0 ? 'bg-yellow-500' : 'bg-gray-300 dark:bg-gray-600');
                            $lotIsReadyForQuality   = $lot->status === 'completed' || $lot->weighings->isNotEmpty();
                            $lotProductionInProgress = $lot->weighings->isNotEmpty() && $lot->status !== 'completed';
                        @endphp

                        @if (!$lotIsReadyForQuality)
                            {{-- Lot not yet in production --}}
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-700/40">
                                    <span class="font-mono text-sm font-semibold text-gray-500 dark:text-gray-400">Lote {{ $lot->lot_number }}</span>
                                    <span class="px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded">
                                        Pendiente de producción
                                    </span>
                                </div>
                                <div class="px-4 py-4 text-sm text-gray-400 dark:text-gray-500 italic text-center border-t border-gray-100 dark:border-gray-700">
                                    Este lote aún no tiene pesadas de producción registradas.
                                </div>
                            </div>
                        @else

                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                            {{-- Warning: production still in progress --}}
                            @if ($lotProductionInProgress)
                                <div class="flex items-center gap-2 px-4 py-2 bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-200 dark:border-yellow-700 text-yellow-700 dark:text-yellow-300 text-xs">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                    Producción aún en progreso — datos parciales ({{ number_format($prodTotal) }} pzas pesadas)
                                </div>
                            @endif
                            {{-- Lot Header --}}
                            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-700/40">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-200">Lote {{ $lot->lot_number }}</span>
                                    @if ($lot->completion_count > 0)
                                        <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded">Completado {{ $lot->completion_count }}</span>
                                    @endif
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded">
                                            Recibidas: {{ number_format($prodTotal) }}
                                        </span>
                                        @if ($qualityGood > 0)
                                            <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded">
                                                Buenas: {{ number_format($qualityGood) }}
                                            </span>
                                        @endif
                                        @if ($qualityBad > 0)
                                            <span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded">
                                                Malas: {{ number_format($qualityBad) }}
                                            </span>
                                        @endif
                                        @if ($pendingPieces > 0)
                                            <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 rounded">
                                                Pendientes: {{ number_format($pendingPieces) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <button wire:click="openWeighingModal({{ $lot->id }})"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Agregar Pesada
                                </button>
                            </div>

                            {{-- Progress Bar --}}
                            @if ($prodTotal > 0)
                                <div class="px-4 py-2 bg-white dark:bg-gray-800">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="{{ $progressColor }} h-full rounded-full transition-all duration-300" style="width: {{ $progressPct }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400 w-32 text-right">
                                            {{ number_format($qualityTotal) }} / {{ number_format($prodTotal) }} ({{ $progressPct }}%)
                                        </span>
                                    </div>
                                </div>
                            @endif

                            {{-- Lot-level Quality Weighings History (no kit) --}}
                            @if ($isCrimp && $lotOnlyQualWeighings->isNotEmpty())
                                <div class="px-4 py-1.5 bg-yellow-50 dark:bg-yellow-900/20 border-t border-yellow-100 dark:border-yellow-800">
                                    <span class="text-xs font-semibold text-yellow-700 dark:text-yellow-300 uppercase tracking-wider">Pesadas de Lote</span>
                                </div>
                            @endif
                            @if ($lotOnlyQualWeighings->isNotEmpty())
                                <div class="border-t border-gray-100 dark:border-gray-700">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50/70 dark:bg-gray-900/30">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Fecha/Hora</th>
                                                <th class="px-4 py-2 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Buenas</th>
                                                <th class="px-4 py-2 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Malas</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Comentarios</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach ($lotOnlyQualWeighings as $qw)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                                        {{ \Carbon\Carbon::parse($qw->weighed_at)->format('d/m/Y H:i') }}
                                                    </td>
                                                    <td class="px-4 py-2 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($qw->good_pieces) }}</td>
                                                    <td class="px-4 py-2 text-right font-semibold {{ $qw->bad_pieces > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                                                        {{ number_format($qw->bad_pieces) }}
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                        {{ $qw->weighedBy->name ?? 'N/A' }}
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                                        {{ $qw->comments ?: '-' }}
                                                    </td>
                                                    <td class="px-4 py-2 text-center">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <button wire:click="editQualityWeighing({{ $lot->id }}, {{ $qw->id }})"
                                                                class="p-1 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition-colors" title="Editar">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                                </svg>
                                                            </button>
                                                            <button wire:click="deleteWeighing({{ $qw->id }})"
                                                                wire:confirm="¿Eliminar esta pesada de calidad?"
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
                                                <td class="px-4 py-2 text-right text-xs font-bold text-green-700 dark:text-green-400">{{ number_format($qualityGood) }}</td>
                                                <td class="px-4 py-2 text-right text-xs font-bold {{ $qualityBad > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500' }}">{{ number_format($qualityBad) }}</td>
                                                <td colspan="3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="px-4 py-5 text-center text-sm text-gray-400 dark:text-gray-500 italic border-t border-gray-100 dark:border-gray-700">
                                    @if ($isCrimp)
                                        Sin pesadas de calidad de lote. Usa "Agregar Pesada" para registrar piezas sin kit.
                                    @else
                                        Sin pesadas de calidad. Usa "Agregar Pesada" para comenzar.
                                    @endif
                                </div>
                            @endif
                        </div>
                        @endif {{-- end $lotIsReadyForQuality --}}
                    @endforeach
                </div>

                {{-- Pesadas por Kit para CRIMP --}}
                @if ($isCrimp && $wo->kits->isNotEmpty())
                    <div class="mt-2 border border-purple-200 dark:border-purple-700 rounded-lg overflow-hidden">
                        <div class="px-4 py-2.5 bg-purple-50 dark:bg-purple-900/20 border-b border-purple-200 dark:border-purple-700">
                            <h4 class="text-sm font-semibold text-purple-800 dark:text-purple-300">Pesadas de Calidad por Kit (CRIMP)</h4>
                        </div>
                        <div class="divide-y divide-purple-100 dark:divide-purple-800">
                            @foreach ($wo->kits as $kit)
                                @php
                                    $kitProdWeighed = $wo->lots->flatMap->weighings->where('kit_id', $kit->id)->sum('good_pieces');
                                    $kitQualWeighings = $wo->lots->flatMap->qualityWeighings->where('kit_id', $kit->id)->values();
                                    $kitQualGood = (int) $kitQualWeighings->sum('good_pieces');
                                    $kitQualBad  = (int) $kitQualWeighings->sum('bad_pieces');
                                    $kitQualTotal = $kitQualGood + $kitQualBad;
                                    $kitPct = $kitProdWeighed > 0 ? min(100, round(($kitQualTotal / $kitProdWeighed) * 100)) : 0;
                                    $kitBarColor = $kitPct >= 100 ? 'bg-green-500' : ($kitPct > 0 ? 'bg-purple-500' : 'bg-gray-300 dark:bg-gray-600');
                                    $kitFirstLot = $wo->lots->first();
                                @endphp
                                <div class="px-4 py-3">
                                    {{-- Kit Header --}}
                                    <div class="flex items-center justify-between gap-3 mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm font-semibold text-purple-700 dark:text-purple-300">{{ $kit->kit_number }}</span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Producción: {{ number_format($kitProdWeighed) }} pzas</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium {{ $kitPct >= 100 ? 'text-green-600 dark:text-green-400' : 'text-purple-600 dark:text-purple-400' }}">
                                                {{ number_format($kitQualTotal) }} / {{ number_format($kitProdWeighed) }} ({{ $kitPct }}%)
                                            </span>
                                            @if ($kitFirstLot)
                                                <button wire:click="openKitWeighingModal({{ $kitFirstLot->id }}, {{ $kit->id }})"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                    Agregar Pesada
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    {{-- Progress bar --}}
                                    <div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mb-2">
                                        <div class="{{ $kitBarColor }} h-full rounded-full transition-all duration-300" style="width: {{ $kitPct }}%"></div>
                                    </div>
                                    {{-- Kit quality weighings table --}}
                                    @if ($kitQualWeighings->isNotEmpty())
                                        <table class="w-full text-xs mt-1">
                                            <thead class="bg-gray-50/70 dark:bg-gray-900/30">
                                                <tr>
                                                    <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Fecha/Hora</th>
                                                    <th class="px-3 py-1.5 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Buenas</th>
                                                    <th class="px-3 py-1.5 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Malas</th>
                                                    <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                                                    <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Comentarios</th>
                                                    <th class="px-3 py-1.5 text-center font-semibold text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach ($kitQualWeighings as $kqw)
                                                    <tr class="hover:bg-purple-50 dark:hover:bg-purple-900/10 transition-colors">
                                                        <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                                            {{ \Carbon\Carbon::parse($kqw->weighed_at)->format('d/m/Y H:i') }}
                                                        </td>
                                                        <td class="px-3 py-1.5 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($kqw->good_pieces) }}</td>
                                                        <td class="px-3 py-1.5 text-right font-semibold {{ $kqw->bad_pieces > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                                                            {{ number_format($kqw->bad_pieces) }}
                                                        </td>
                                                        <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">
                                                            {{ $kqw->weighedBy->name ?? 'N/A' }}
                                                        </td>
                                                        <td class="px-3 py-1.5 text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                                            {{ $kqw->comments ?: '-' }}
                                                        </td>
                                                        <td class="px-3 py-1.5 text-center">
                                                            <div class="flex items-center justify-center gap-1">
                                                                <button wire:click="editQualityWeighing({{ $kitFirstLot->id }}, {{ $kqw->id }})"
                                                                    class="p-1 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition-colors" title="Editar">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                                    </svg>
                                                                </button>
                                                                <button wire:click="deleteWeighing({{ $kqw->id }})"
                                                                    wire:confirm="¿Eliminar esta pesada de calidad de kit?"
                                                                    class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors" title="Eliminar">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <p class="text-xs text-gray-400 dark:text-gray-500 italic">Sin pesadas de calidad para este kit aún.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-yellow-600 hover:bg-yellow-700 text-white font-semibold rounded-lg shadow transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
            </svg>
            Enviar a Empaque
        </button>
    </div>

    {{-- ===== QUALITY WEIGHING MODAL ===== --}}
    @if ($showWeighingModal)
        @php
            $modalLot      = $workOrders->flatMap->lots->firstWhere('id', $weighingLotId);
            $modalWo       = $modalLot ? $workOrders->firstWhere('id', $modalLot->work_order_id) : null;
            $modalIsCrimp  = $modalWo ? ($modalWo->purchaseOrder->part->is_crimp ?? false) : false;
            $modalKit      = $weighingKitId ? ($modalWo ? $modalWo->kits->firstWhere('id', $weighingKitId) : null) : null;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" wire:click="closeWeighingModal"></div>
            <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 {{ $modalKit ? 'bg-purple-600 dark:bg-purple-700' : 'bg-yellow-600 dark:bg-yellow-700' }}">
                    <div>
                        <h3 class="text-lg font-bold text-white">Pesada de Calidad {{ $modalKit ? '(Kit)' : '' }}</h3>
                        @if ($modalLot)
                            <p class="text-sm {{ $modalKit ? 'text-purple-100' : 'text-yellow-100' }} mt-0.5">
                                @if ($modalKit)
                                    Kit {{ $modalKit->kit_number }} &mdash; Producción: {{ number_format($productionGoodPieces) }} pzas | Pendientes: {{ number_format($remainingPieces) }}
                                @else
                                    Lote {{ $modalLot->lot_number }}
                                    &mdash; Producción: {{ number_format($productionGoodPieces) }} pzas
                                    | Pendientes: {{ number_format($remainingPieces) }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <button wire:click="closeWeighingModal" class="text-white/80 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    {{-- Editing indicator --}}
                    @if ($editingId)
                        <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <span class="text-sm text-blue-700 dark:text-blue-300 font-medium">Editando pesada existente</span>
                            <button wire:click="cancelEdit" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">Cancelar edición</button>
                        </div>
                    @endif

                    {{-- Summary stats --}}
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded">
                            <div class="font-bold text-blue-700 dark:text-blue-300">{{ number_format($productionGoodPieces) }}</div>
                            <div class="text-gray-500">Producción</div>
                        </div>
                        <div class="p-2 bg-green-50 dark:bg-green-900/20 rounded">
                            <div class="font-bold text-green-700 dark:text-green-300">{{ number_format($alreadyWeighed) }}</div>
                            <div class="text-gray-500">Verificadas</div>
                        </div>
                        <div class="p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded">
                            <div class="font-bold text-yellow-700 dark:text-yellow-300">{{ number_format($remainingPieces) }}</div>
                            <div class="text-gray-500">Pendientes</div>
                        </div>
                    </div>

                    {{-- Good pieces --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Piezas aprobadas <span class="text-red-500">*</span></label>
                        <input type="number" wire:model="goodPieces" min="0" placeholder="0"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        @error('goodPieces')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Bad pieces --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Piezas rechazadas</label>
                        <input type="number" wire:model="badPieces" min="0" placeholder="0"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        @error('badPieces')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Comments shown prominently if bad pieces > 0 --}}
                    @if ($badPieces > 0)
                        <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg text-sm text-red-700 dark:text-red-300">
                            Hay {{ $badPieces }} pieza(s) rechazada(s). Documente el motivo en los comentarios.
                        </div>
                    @endif

                    {{-- Date/Time --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha y hora <span class="text-red-500">*</span></label>
                        <input type="datetime-local" wire:model="weighingAt"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        @error('weighingAt')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Comments --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios{{ $badPieces > 0 ? ' (recomendado)' : ' (opcional)' }}</label>
                        <textarea wire:model="weighingComments" rows="2" placeholder="Observaciones de calidad..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-yellow-500 resize-none"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="closeWeighingModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="saveWeighing"
                        class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 rounded-lg transition-colors">
                        {{ $editingId ? 'Actualizar Pesada' : 'Guardar Pesada' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== SEND TO PACKAGING MODAL ===== --}}
    @if ($showSendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" wire:click="closeSendModal"></div>
            <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 bg-yellow-600 dark:bg-yellow-700">
                    <h3 class="text-lg font-bold text-white">Enviar a Empaque</h3>
                    <button wire:click="closeSendModal" class="text-white/80 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Resumen de piezas aprobadas por calidad{{ $workOrders->contains(fn($wo) => $wo->purchaseOrder->part->is_crimp ?? false) ? ' (lote y kit)' : '' }}:</p>

                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Lote / Kit</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Recibidas</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Buenas</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Rechazadas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($workOrders as $wo)
                                    @php $sendWoIsCrimp = $wo->purchaseOrder->part->is_crimp ?? false; @endphp
                                    @foreach ($wo->lots as $lot)
                                        @php
                                            $lotOnlyQW = $lot->qualityWeighings->whereNull('kit_id');
                                            $lotRecv = (int) $lot->weighings->whereNull('kit_id')->sum('good_pieces');
                                            $lotGood = (int) $lotOnlyQW->sum('good_pieces');
                                            $lotBad  = (int) $lotOnlyQW->sum('bad_pieces');
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-2.5 font-mono text-gray-800 dark:text-gray-200">{{ $lot->lot_number }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-400">{{ number_format($lotRecv) }}</td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($lotGood) }}</td>
                                            <td class="px-4 py-2.5 text-right {{ $lotBad > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                                                {{ number_format($lotBad) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    {{-- Kit rows for CRIMP --}}
                                    @if ($sendWoIsCrimp && $wo->kits->isNotEmpty())
                                        @foreach ($wo->kits as $kit)
                                            @php
                                                $kitRecv = (int) $wo->lots->flatMap->weighings->where('kit_id', $kit->id)->sum('good_pieces');
                                                $kitQW = $wo->lots->flatMap->qualityWeighings->where('kit_id', $kit->id);
                                                $kitGood = (int) $kitQW->sum('good_pieces');
                                                $kitBad  = (int) $kitQW->sum('bad_pieces');
                                            @endphp
                                            <tr class="bg-purple-50/50 dark:bg-purple-900/10">
                                                <td class="px-4 py-2 font-mono text-purple-700 dark:text-purple-300 text-xs">
                                                    <span class="inline-flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                                        {{ $kit->kit_number }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-right text-xs text-purple-600 dark:text-purple-400">{{ number_format($kitRecv) }}</td>
                                                <td class="px-4 py-2 text-right text-xs font-semibold text-green-700 dark:text-green-400">{{ number_format($kitGood) }}</td>
                                                <td class="px-4 py-2 text-right text-xs {{ $kitBad > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                                                    {{ number_format($kitBad) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas (opcional)</label>
                        <textarea wire:model="sendNotes" rows="2" placeholder="Observaciones para Empaque..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-yellow-500 resize-none"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="closeSendModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="sendToPackaging"
                        class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 rounded-lg transition-colors">
                        Confirmar Envío
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
