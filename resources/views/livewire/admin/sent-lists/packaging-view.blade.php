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

    {{-- Completion Banner --}}
    @if ($allLotsHavePackaging)
        <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/30 border-2 border-green-300 dark:border-green-600 rounded-lg">
            <svg class="w-6 h-6 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-semibold text-green-800 dark:text-green-300">Todos los lotes han sido empacados.</p>
                <p class="text-sm text-green-700 dark:text-green-400 mt-0.5">Puedes cerrar la lista usando el botón "Cerrar y Confirmar Lista" al pie de la página.</p>
            </div>
        </div>
    @endif

    {{-- Packaging Sections per WO --}}
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
                            $available     = (int) $lot->qualityWeighings->sum('good_pieces');
                            $packed        = (int) $lot->packagingRecords->sum('packed_pieces');
                            $surplus       = max(0, $available - $packed);
                            $hasRecords    = $lot->packagingRecords->isNotEmpty();

                            // CRIMP: empaque por pesadas separadas (piezas + CRIMP) a nivel viajero.
                            $crimpPiecesPacked  = (int) $lot->packagingPieceWeighings->sum('quantity');
                            $crimpPacked        = (int) $lot->packagingCrimpWeighings->sum('quantity');
                            $crimpTarget        = (int) $lot->crimpLots->sum('quantity');
                            $crimpPiecesSurplus = max(0, $available - $crimpPiecesPacked);
                            $crimpSurplus       = max(0, $crimpTarget - $crimpPacked);
                            if ($isCrimp) {
                                $packed     = $crimpPiecesPacked;
                                $surplus    = $crimpPiecesSurplus;
                                $hasRecords = $lot->packagingPieceWeighings->isNotEmpty() || $lot->packagingCrimpWeighings->isNotEmpty();
                            }
                            $progressPct   = $available > 0 ? min(100, round(($packed / $available) * 100)) : 0;
                            $progressColor = $progressPct >= 100 ? 'bg-green-500' : ($progressPct > 0 ? 'bg-blue-500' : 'bg-gray-300 dark:bg-gray-600');
                        @endphp

                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                            {{-- Lot Header --}}
                            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-700/40">
                                {{-- Izquierda: lot number + badges de piezas --}}
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-200">Lote {{ $lot->lot_number }}</span>
                                    @if ($lot->completion_count > 0)
                                        <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded">Completado {{ $lot->completion_count }}</span>
                                    @endif
                                    @if ($hasRecords)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Empacado
                                        </span>
                                    @endif
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded">
                                            Disponibles: {{ number_format($available) }}
                                        </span>
                                        <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded">
                                            Empacadas: {{ number_format($packed) }}
                                        </span>
                                        @if ($surplus > 0)
                                            <span class="px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 rounded">
                                                Sobrante: {{ number_format($surplus) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                {{-- Derecha: botones de acción --}}
                                <div class="flex items-center gap-2 flex-wrap">
                                    {{-- Viajero status (CRIMP: modal Paso 7 con resumen + marcar/revertir; NO-CRIMP: botón simple original) --}}
                                    @if ($isCrimp)
                                        @if ($lot->viajero_received)
                                            <button wire:click="openViajeroModal({{ $lot->id }})"
                                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors"
                                                title="Ver entrega de viajero / revertir">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Viajero &#10003; {{ \Carbon\Carbon::parse($lot->viajero_received_at)->format('d/m/Y') }}
                                            </button>
                                        @else
                                            <button wire:click="openViajeroModal({{ $lot->id }})"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-teal-600 hover:bg-teal-700 text-white rounded-lg transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                                Entregar Viajero
                                            </button>
                                        @endif
                                    @else
                                        @if ($lot->viajero_received)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Lote &#10003; {{ \Carbon\Carbon::parse($lot->viajero_received_at)->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <button wire:click="receiveViajero({{ $lot->id }})"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-teal-600 hover:bg-teal-700 text-white rounded-lg transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                                Recibir Lote
                                            </button>
                                        @endif
                                    @endif
                                    {{-- Toma de decisiones --}}
                                    @if ($lot->closure_decision)
                                        @php
                                            $decLabel = match($lot->closure_decision) {
                                                'complete_lot' => ['Completar Lote', 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'],
                                                'new_lot'      => ['Nuevo Lote', 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'],
                                                'close_as_is'  => ['Cerrado', 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300'],
                                                default        => ['Decisión', 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'],
                                            };
                                        @endphp
                                        <button wire:click="openDecisionModal({{ $lot->id }})"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium {{ $decLabel[1] }} rounded-lg transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            {{ $decLabel[0] }}
                                        </button>
                                    @else
                                        <button wire:click="openDecisionModal({{ $lot->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Toma de Decisión
                                        </button>
                                    @endif
                                    {{-- Recibí Material (surplus received) --}}
                                    @if ($surplus > 0 || $lot->surplus_received)
                                        @if ($lot->surplus_received)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Mat. Recibido
                                            </span>
                                        @else
                                            <button wire:click="markSurplusReceived({{ $lot->id }})"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                                </svg>
                                                Recibí Material
                                            </button>
                                        @endif
                                    @endif
                                    @if ($isCrimp)
                                        @if ($lot->packaging_notified_at)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg"
                                                title="Notificado el {{ \Carbon\Carbon::parse($lot->packaging_notified_at)->format('d/m/Y H:i') }}">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                Notificado
                                            </span>
                                        @endif
                                        <button wire:click="openConfirmModal({{ $lot->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                            </svg>
                                            Empacar / Confirmar
                                        </button>
                                    @else
                                        <button wire:click="openPackagingModal({{ $lot->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Registrar Empaque
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Progress Bar --}}
                            @if ($available > 0)
                                <div class="px-4 py-2 bg-white dark:bg-gray-800">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="{{ $progressColor }} h-full rounded-full transition-all duration-300" style="width: {{ $progressPct }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400 w-32 text-right">
                                            {{ number_format($packed) }} / {{ number_format($available) }} ({{ $progressPct }}%)
                                        </span>
                                    </div>
                                </div>
                            @endif

                            {{-- Packaging / Pesadas --}}
                            @if (!$isCrimp)
                            @if ($hasRecords)
                                <div class="border-t border-gray-100 dark:border-gray-700">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50/70 dark:bg-gray-900/30">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Fecha/Hora</th>
                                                <th class="px-4 py-2 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Disponibles</th>
                                                <th class="px-4 py-2 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Empacadas</th>
                                                <th class="px-4 py-2 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Sobrante</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Empacó</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Comentarios</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach ($lot->packagingRecords as $record)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                                        {{ \Carbon\Carbon::parse($record->packed_at)->format('d/m/Y H:i') }}
                                                    </td>
                                                    <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">
                                                        {{ number_format($record->available_pieces) }}
                                                    </td>
                                                    <td class="px-4 py-2 text-right font-semibold text-green-700 dark:text-green-400">
                                                        {{ number_format($record->packed_pieces) }}
                                                    </td>
                                                    <td class="px-4 py-2 text-right {{ $record->surplus_pieces > 0 ? 'text-orange-600 dark:text-orange-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                                                        {{ number_format($record->adjusted_surplus ?? $record->surplus_pieces) }}
                                                        @if ($record->adjusted_surplus !== null && $record->adjusted_surplus !== $record->surplus_pieces)
                                                            <span class="text-gray-400 dark:text-gray-500 font-normal">(ajust.)</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                        {{ $record->packedBy->name ?? 'N/A' }}
                                                    </td>
                                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                                        {{ $record->comments ?: '-' }}
                                                    </td>
                                                    <td class="px-4 py-2 text-center">
                                                        <button wire:click="deletePackaging({{ $record->id }})"
                                                            wire:confirm="¿Eliminar este registro de empaque?"
                                                            class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-gray-50 dark:bg-gray-900/30">
                                            <tr>
                                                <td class="px-4 py-2 font-semibold text-gray-700 dark:text-gray-300 text-xs uppercase">Total</td>
                                                <td class="px-4 py-2 text-right text-xs text-gray-600 dark:text-gray-400">{{ number_format($available) }}</td>
                                                <td class="px-4 py-2 text-right text-xs font-bold text-green-700 dark:text-green-400">{{ number_format($packed) }}</td>
                                                <td class="px-4 py-2 text-right text-xs font-bold {{ $surplus > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-500' }}">{{ number_format($surplus) }}</td>
                                                <td colspan="3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="px-4 py-5 text-center text-sm text-gray-400 dark:text-gray-500 italic border-t border-gray-100 dark:border-gray-700">
                                    Sin registros de empaque. Usa "Registrar Empaque" para comenzar.
                                </div>
                            @endif
                            @else
                                {{-- CRIMP: pesadas separadas (piezas + CRIMP) + resumen Empaque Terminado --}}
                                <div class="border-t border-gray-100 dark:border-gray-700 p-4 space-y-4">
                                    {{-- Resumen "Empaque Terminado" --}}
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-center">
                                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/40 p-2">
                                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">En viajero</div>
                                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ number_format($lot->quantity) }}</div>
                                        </div>
                                        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-2">
                                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas compl.</div>
                                            <div class="text-sm font-bold text-green-700 dark:text-green-300">{{ number_format($crimpPiecesPacked) }}</div>
                                        </div>
                                        <div class="rounded-lg bg-cyan-50 dark:bg-cyan-900/20 p-2">
                                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">CRIMP compl.</div>
                                            <div class="text-sm font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($crimpPacked) }}</div>
                                        </div>
                                        <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-2">
                                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Sobrante piezas</div>
                                            <div class="text-sm font-bold text-orange-700 dark:text-orange-300">{{ number_format($crimpPiecesSurplus) }}</div>
                                        </div>
                                        <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-2">
                                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Sobrante CRIMP</div>
                                            <div class="text-sm font-bold text-orange-700 dark:text-orange-300">{{ number_format($crimpSurplus) }}</div>
                                        </div>
                                    </div>

                                    {{-- Pesadas de piezas ("manguitas") --}}
                                    <div>
                                        <div class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Pesadas de piezas ("manguitas")</div>
                                        @if ($lot->packagingPieceWeighings->isNotEmpty())
                                            <table class="w-full text-xs">
                                                <thead class="bg-gray-50/70 dark:bg-gray-900/30">
                                                    <tr>
                                                        <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Fecha/Hora</th>
                                                        <th class="px-3 py-1.5 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                                                        <th class="px-3 py-1.5 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Peso</th>
                                                        <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Pesó</th>
                                                        <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Coment.</th>
                                                        <th class="px-3 py-1.5 text-center font-semibold text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                    @foreach ($lot->packagingPieceWeighings as $pw)
                                                        <tr wire:key="pw-{{ $pw->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                            <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($pw->weighed_at)->format('d/m/Y H:i') }}</td>
                                                            <td class="px-3 py-1.5 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($pw->quantity) }}</td>
                                                            <td class="px-3 py-1.5 text-right text-gray-600 dark:text-gray-400">{{ $pw->weight !== null ? number_format($pw->weight, 3) : '—' }}</td>
                                                            <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $pw->weighedBy->name ?? 'N/A' }}</td>
                                                            <td class="px-3 py-1.5 text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ $pw->comments ?: '-' }}</td>
                                                            <td class="px-3 py-1.5 text-center">
                                                                <button wire:click="deletePieceWeighing({{ $pw->id }})" wire:confirm="¿Eliminar esta pesada de piezas?" class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <p class="text-xs text-gray-400 dark:text-gray-500 italic">Sin pesadas de piezas. Usa "Pesar Piezas".</p>
                                        @endif
                                    </div>

                                    {{-- Pesadas de CRIMP --}}
                                    <div>
                                        <div class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Pesadas de CRIMP <span class="font-normal text-gray-400">(objetivo: {{ number_format($crimpTarget) }})</span></div>
                                        @if ($lot->packagingCrimpWeighings->isNotEmpty())
                                            <table class="w-full text-xs">
                                                <thead class="bg-gray-50/70 dark:bg-gray-900/30">
                                                    <tr>
                                                        <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Fecha/Hora</th>
                                                        <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Lote CRIMP</th>
                                                        <th class="px-3 py-1.5 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                                                        <th class="px-3 py-1.5 text-right font-semibold text-gray-500 dark:text-gray-400 uppercase">Peso</th>
                                                        <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Pesó</th>
                                                        <th class="px-3 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase">Coment.</th>
                                                        <th class="px-3 py-1.5 text-center font-semibold text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                    @foreach ($lot->packagingCrimpWeighings as $cw)
                                                        <tr wire:key="cw-{{ $cw->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                            <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($cw->weighed_at)->format('d/m/Y H:i') }}</td>
                                                            <td class="px-3 py-1.5 font-mono text-cyan-700 dark:text-cyan-300">{{ $cw->crimpLot?->crimp_lot_number ?? '—' }}</td>
                                                            <td class="px-3 py-1.5 text-right font-semibold text-cyan-700 dark:text-cyan-400">{{ number_format($cw->quantity) }}</td>
                                                            <td class="px-3 py-1.5 text-right text-gray-600 dark:text-gray-400">{{ $cw->weight !== null ? number_format($cw->weight, 3) : '—' }}</td>
                                                            <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $cw->weighedBy->name ?? 'N/A' }}</td>
                                                            <td class="px-3 py-1.5 text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ $cw->comments ?: '-' }}</td>
                                                            <td class="px-3 py-1.5 text-center">
                                                                <button wire:click="deleteCrimpWeighing({{ $cw->id }})" wire:confirm="¿Eliminar esta pesada de CRIMP?" class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <p class="text-xs text-gray-400 dark:text-gray-500 italic">Sin pesadas de CRIMP. Usa "Pesar CRIMP".</p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
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
    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex-wrap">
        {{-- Warning lotes sin viajero --}}
        @php
            $allLots            = $workOrders->flatMap->lots;
            $lotsWithoutViajero = $allLots->filter(fn($l) => !$l->viajero_received
                && ($l->packagingRecords->isNotEmpty()
                    || $l->packagingPieceWeighings->isNotEmpty()
                    || $l->packagingCrimpWeighings->isNotEmpty()));
        @endphp
        @if ($lotsWithoutViajero->isNotEmpty())
            <div class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 px-4 py-2 rounded-lg border border-amber-200 dark:border-amber-700 mr-auto">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                {{ $lotsWithoutViajero->count() }} lote(s) empacados sin viajero confirmado
            </div>
        @endif
        <button wire:click="openCloseModal"
            @if (!$allLotsHavePackaging) disabled @endif
            class="inline-flex items-center gap-2 px-5 py-2.5 font-semibold rounded-lg shadow transition-colors
                {{ $allLotsHavePackaging
                    ? 'bg-green-600 hover:bg-green-700 text-white'
                    : 'bg-gray-300 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            Cerrar y Confirmar Lista
            @if (!$allLotsHavePackaging)
                <span class="text-xs font-normal">(faltan lotes)</span>
            @endif
        </button>
    </div>

    {{-- Link a pantalla de Toma de Decisión --}}
    <div class="flex items-center justify-center pt-2">
        <a href="{{ route('admin.sent-lists.display') }}"
            class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            Ver pantalla de Toma de Decision
        </a>
    </div>

    {{-- ===== NOTIFY MODAL (Empaque terminado CRIMP) ===== --}}
    @if ($showNotifyModal)
        @php $nLot = $workOrders->flatMap->lots->firstWhere('id', $notifyLotId); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/70" wire:click="closeNotifyModal"></div>
            <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Confirmar empaque y notificar</h3>
                        @if ($nLot)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Viajero {{ $nLot->lot_number }}</p>
                        @endif
                    </div>
                    <button wire:click="closeNotifyModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="px-6 py-4 space-y-4">
                    @if ($nLot)
                        {{-- Resumen Empaque Terminado --}}
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-center">
                            <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-2">
                                <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas compl.</div>
                                <div class="text-sm font-bold text-green-700 dark:text-green-300">{{ number_format($nLot->getPackagedPiecesTotal()) }}</div>
                            </div>
                            <div class="rounded-lg bg-cyan-50 dark:bg-cyan-900/20 p-2">
                                <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">CRIMP compl.</div>
                                <div class="text-sm font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($nLot->getPackagedCrimpTotal()) }}</div>
                            </div>
                            <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-2">
                                <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Sobr. piezas</div>
                                <div class="text-sm font-bold text-orange-700 dark:text-orange-300">{{ number_format($nLot->getPackagedPiecesSurplus()) }}</div>
                            </div>
                            <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-2">
                                <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Sobr. CRIMP</div>
                                <div class="text-sm font-bold text-orange-700 dark:text-orange-300">{{ number_format($nLot->getPackagedCrimpSurplus()) }}</div>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No. de etiquetas <span class="text-gray-400">(opcional)</span></label>
                        <input type="number" wire:model="notifyLabelCount" min="0" placeholder="—"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-green-500">
                        @error('notifyLabelCount') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios <span class="text-gray-400">(opcional)</span></label>
                        <textarea wire:model="notifyComments" rows="2" placeholder="Observaciones para Empaque/Materiales..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Se enviará el correo "Empaque terminado CRIMP" a Empaques y Materiales.</p>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="closeNotifyModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</button>
                    <button wire:click="confirmAndNotify" class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg">Confirmar y Enviar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== PASO 7 · MODAL ENTREGA DE VIAJERO ===== --}}
    @if ($showViajeroModal)
        @php
            $vjLot = \App\Models\Lot::with(['crimpLots','workOrder.purchaseOrder.part','qualityWeighings','packagingPieceWeighings','packagingCrimpWeighings'])->find($viajeroModalLotId);
            $vjWO = $vjLot?->workOrder;
            $vjPart = $vjWO?->purchaseOrder?->part;
            $vjWoNum = $vjWO?->purchaseOrder?->wo ?? $vjWO?->wo_number ?? '—';
            $vjReceived = (bool) ($vjLot?->viajero_received);
            $vjPiezas = $vjLot ? $vjLot->getPackagedPiecesTotal() : 0;
            $vjCrimp = $vjLot ? $vjLot->getPackagedCrimpTotal() : 0;
            $vjPiezasSob = $vjLot ? $vjLot->getPackagedPiecesSurplus() : 0;
            $vjCrimpSob = $vjLot ? $vjLot->getPackagedCrimpSurplus() : 0;
            $vjDecLabel = ($vjLot && $vjLot->closure_decision) ? $vjLot->getPostQualityLifecycle()['decision']['label'] : 'Sin decisión todavía';
            $vjCrimpLots = $vjLot?->crimpLots ?? collect();
        @endphp
        <x-ui-modal badge="Paso 7" title="Entrega de Viajero" subtitle="Empaque confirma «Viajero recibido»"
            close="closeViajeroModal" maxWidth="2xl">
            <x-slot:context>
                <x-ui-modal.ctx label="Descripción" :value="$vjPart?->description ?? $vjPart?->number ?? '—'" />
                <x-ui-modal.ctx label="No. Order (WO + Viajero)" :value="$vjWoNum.($vjLot?->lot_number ?? '—')" />
                <x-ui-modal.ctx label="Lotes de CRIMP" :value="$vjCrimpLots->pluck('crimp_lot_number')->join(', ') ?: '—'" />
                <x-ui-modal.ctx label="Cantidad en viajero" :value="number_format($vjLot?->quantity ?? 0)" />
            </x-slot:context>

            {{-- Estado actual --}}
            <div class="rounded-lg p-4 border {{ $vjReceived ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700' : 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-700' }}">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 shrink-0 {{ $vjReceived ? 'text-green-600 dark:text-green-400' : 'text-amber-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if ($vjReceived)
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @endif
                    </svg>
                    <div>
                        <div class="text-sm font-semibold {{ $vjReceived ? 'text-green-800 dark:text-green-200' : 'text-amber-800 dark:text-amber-200' }}">
                            {{ $vjReceived ? 'Viajero recibido por Materiales' : 'Pendiente: Empaque debe entregar el viajero' }}
                        </div>
                        @if ($vjReceived && $vjLot?->viajero_received_at)
                            <div class="text-xs text-gray-500 dark:text-gray-400">Recibido el {{ \Carbon\Carbon::parse($vjLot->viajero_received_at)->format('d/m/Y H:i') }}{{ $vjLot->viajeroReceivedByUser?->name ? ' · por '.$vjLot->viajeroReceivedByUser->name : '' }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Resumen del empaque --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Resumen del empaque</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-center">
                        <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas «manguitas»</div>
                        <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format($vjPiezas) }}</div>
                    </div>
                    <div class="rounded-lg bg-cyan-50 dark:bg-cyan-900/20 p-3 text-center">
                        <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas CRIMP</div>
                        <div class="text-lg font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($vjCrimp) }}</div>
                    </div>
                    <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 text-center">
                        <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Sobr. piezas</div>
                        <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($vjPiezasSob) }}</div>
                    </div>
                    <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 text-center">
                        <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Sobr. CRIMP</div>
                        <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($vjCrimpSob) }}</div>
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    <span class="text-gray-500 dark:text-gray-400">Decisión de Materiales:</span> <strong>{{ $vjDecLabel }}</strong>
                </div>
            </div>

            <x-slot:footer>
                <span class="text-xs text-gray-500 dark:text-gray-400">Paso 7 · luego Paso 8 (regresar sobrantes)</span>
                <div class="flex items-center gap-2">
                    <button wire:click="closeViajeroModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cerrar</button>
                    @if ($vjReceived)
                        <button wire:click="revertViajeroReceived({{ $viajeroModalLotId }})"
                            wire:confirm="¿Revertir la entrega? El viajero quedará como NO recibido."
                            class="px-4 py-2 text-sm font-semibold text-yellow-700 dark:text-yellow-300 bg-white dark:bg-gray-800 border-2 border-yellow-400 dark:border-yellow-600 rounded-lg hover:bg-yellow-50 dark:hover:bg-yellow-900/20 inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Revertir entrega
                        </button>
                    @else
                        <button wire:click="markViajeroReceived({{ $viajeroModalLotId }})"
                            class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Marcar viajero como recibido
                        </button>
                    @endif
                </div>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- ===== PASO 5 · MODAL DE CONFIRMACIÓN DE EMPAQUE (CRIMP) ===== --}}
    @if ($showConfirmModal)
        @php
            $cfLot   = $workOrders->flatMap->lots->firstWhere('id', $confirmLotId);
            $cfWO    = $cfLot?->workOrder;
            $cfPart  = $cfWO?->purchaseOrder?->part;
            $cfWoNum = $cfWO?->purchaseOrder?->wo ?? $cfWO?->wo_number ?? '—';
            $cfCrimpLots = $cfLot?->crimpLots ?? collect();
            $cfSel   = $cfCrimpLots->firstWhere('id', $confirmCrimpLotId);
            // Pesadas del lote de CRIMP seleccionado.
            $cfPW = $cfLot ? $cfLot->packagingPieceWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
            $cfCW = $cfLot ? $cfLot->packagingCrimpWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
            $cfPiecesTotal = (int) $cfPW->sum('quantity');
            $cfCrimpTotal  = (int) $cfCW->sum('quantity');
            // Sobrantes a nivel viajero (B.4): disponibles − piezas empacadas; objetivo CRIMP − CRIMP empacado.
            $cfAvailable     = $cfLot ? (int) $cfLot->qualityWeighings->sum('good_pieces') : 0;
            $cfPiecesSurplus = $cfLot ? $cfLot->getPackagedPiecesSurplus() : 0;
            $cfCrimpSurplus  = $cfLot ? $cfLot->getPackagedCrimpSurplus() : 0;
            $cfOtherLots = $cfCrimpLots->where('id', '!=', $confirmCrimpLotId);
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-start sm:items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/70" wire:click="closeConfirmModal"></div>
                <div class="relative w-full max-w-4xl bg-white dark:bg-gray-800 rounded-2xl shadow-2xl my-8">

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <span class="inline-block px-2.5 py-0.5 text-xs font-bold bg-amber-300 text-amber-900 rounded-full">Paso 5</span>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1.5">Modal de confirmación</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Se despliega sobre la pantalla de Empaque</p>
                        </div>
                        <button wire:click="closeConfirmModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Tira de contexto --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700">
                        <div class="bg-white dark:bg-gray-800 px-4 py-2.5">
                            <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">Descripción</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $cfPart?->description ?? $cfPart?->number ?? '—' }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-2.5">
                            <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">No. Order (WO + Viajero)</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $cfWoNum }}{{ $cfLot?->lot_number }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-2.5">
                            <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">No. en etiquetas (WO + Lote)</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $cfWoNum }}{{ $cfSel?->crimp_lot_number ?? '—' }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-2.5">
                            <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">Cantidad en viajero</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ number_format($cfLot?->quantity ?? 0) }}</div>
                        </div>
                    </div>

                    <div class="px-6 py-5 space-y-6 max-h-[65vh] overflow-y-auto">
                        @if ($cfCrimpLots->isEmpty())
                            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg text-sm text-yellow-800 dark:text-yellow-300">
                                Este viajero no tiene lotes de CRIMP. Captúralos primero en <strong>Materiales</strong>.
                            </div>
                        @else
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {{-- Paso 1: Selecciona lote de CRIMP --}}
                            <section>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-bold">1</span>
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Selecciona lote de CRIMP</h4>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">De los lotes asignados al viajero</p>
                                <div class="space-y-2">
                                    @foreach ($cfCrimpLots as $cl)
                                        <button type="button" wire:click="$set('confirmCrimpLotId', {{ $cl->id }})"
                                            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg border-2 text-left transition-colors
                                                {{ $confirmCrimpLotId === $cl->id ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/40' }}">
                                            <span class="text-indigo-600 dark:text-indigo-300">{{ $confirmCrimpLotId === $cl->id ? '●' : '○' }}</span>
                                            <span class="flex-1">
                                                <span class="block text-sm font-mono font-semibold text-gray-800 dark:text-gray-100">Lote CRIMP {{ $cl->crimp_lot_number }}</span>
                                                @if ($cl->lote_fabricante)
                                                    <span class="block text-[11px] text-gray-400">Fab: {{ $cl->lote_fabricante }}</span>
                                                @endif
                                            </span>
                                            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ number_format($cl->quantity) }} pz</span>
                                        </button>
                                    @endforeach
                                </div>
                                @if ($cfOtherLots->isNotEmpty())
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                                        <strong>División del lote:</strong> este contiene {{ number_format($cfSel?->quantity ?? 0) }}
                                        y existe{{ $cfOtherLots->count() > 1 ? 'n otros lotes' : ' otro lote' }} por
                                        {{ $cfOtherLots->map(fn($l) => number_format($l->quantity))->join(', ') }}.
                                    </p>
                                @endif
                            </section>

                            {{-- Paso 2: Captura pesadas --}}
                            <section>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-bold">2</span>
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Captura pesadas</h4>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Piezas «manguitas» / Piezas CRIMP</p>

                                @error('confirmCrimpLotId') <p class="text-xs text-red-600 dark:text-red-400 mb-2">{{ $message }}</p> @enderror

                                {{-- Tabla piezas «manguitas» --}}
                                <div class="mb-4 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <div class="px-3 py-1.5 bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold text-gray-600 dark:text-gray-300">Piezas «manguitas»</div>
                                    <table class="w-full text-xs">
                                        <thead class="text-gray-400 dark:text-gray-500">
                                            <tr><th class="px-3 py-1 text-right font-medium">Piezas</th><th class="px-2 py-1"></th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @forelse ($cfPW as $w)
                                                <tr wire:key="cfpw-{{ $w->id }}">
                                                    @if ($editPieceWId === $w->id)
                                                        <td class="px-3 py-1.5">
                                                            <input type="number" min="1" wire:model="editPieceWQty"
                                                                class="w-full px-2 py-1 text-xs text-right border border-indigo-400 dark:border-indigo-500 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                            @error('editPieceWQty') <span class="block text-[10px] text-red-600 dark:text-red-400 mt-0.5">{{ $message }}</span> @enderror
                                                        </td>
                                                        <td class="px-2 py-1 text-center whitespace-nowrap">
                                                            <button wire:click="saveConfirmPieceWeighing" class="text-green-600 hover:text-green-800 font-bold" title="Guardar">✓</button>
                                                            <button wire:click="cancelEditPieceWeighing" class="text-gray-400 hover:text-gray-600 ml-1" title="Cancelar">✕</button>
                                                        </td>
                                                    @else
                                                        <td class="px-3 py-1 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($w->quantity) }}</td>
                                                        <td class="px-2 py-1 text-center whitespace-nowrap">
                                                            <button wire:click="editConfirmPieceWeighing({{ $w->id }})" class="text-indigo-500 hover:text-indigo-700" title="Editar">✎</button>
                                                            <button wire:click="deleteConfirmPieceWeighing({{ $w->id }})" class="text-red-400 hover:text-red-600 ml-1" title="Eliminar">✕</button>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @empty
                                                <tr><td colspan="2" class="px-3 py-2 text-center text-gray-400 italic">Sin pesadas</td></tr>
                                            @endforelse
                                            <tr class="bg-gray-50/60 dark:bg-gray-900/20">
                                                <td class="px-3 py-1.5">
                                                    <input type="number" min="1" wire:model="cPieceQty" placeholder="pzs"
                                                        class="w-full px-2 py-1 text-xs text-right border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button wire:click="addConfirmPieceWeighing" class="text-indigo-600 hover:text-indigo-800 font-bold">＋</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-gray-50 dark:bg-gray-900/30">
                                            <tr><td class="px-3 py-1.5 text-right text-xs font-bold text-green-700 dark:text-green-400">Total: {{ number_format($cfPiecesTotal) }}</td><td></td></tr>
                                        </tfoot>
                                    </table>
                                    @error('cPieceQty') <p class="text-xs text-red-600 dark:text-red-400 px-3 py-1">{{ $message }}</p> @enderror
                                </div>

                                {{-- Tabla piezas CRIMP --}}
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <div class="px-3 py-1.5 bg-cyan-50 dark:bg-cyan-900/20 text-xs font-semibold text-cyan-700 dark:text-cyan-300">Piezas CRIMP <span class="font-normal text-gray-400">(objetivo: {{ number_format($cfSel?->quantity ?? 0) }})</span></div>
                                    <table class="w-full text-xs">
                                        <thead class="text-gray-400 dark:text-gray-500">
                                            <tr><th class="px-3 py-1 text-right font-medium">Piezas</th><th class="px-2 py-1"></th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @forelse ($cfCW as $w)
                                                <tr wire:key="cfcw-{{ $w->id }}">
                                                    @if ($editCrimpWId === $w->id)
                                                        <td class="px-3 py-1.5">
                                                            <input type="number" min="1" wire:model="editCrimpWQty"
                                                                class="w-full px-2 py-1 text-xs text-right border border-cyan-400 dark:border-cyan-500 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                            @error('editCrimpWQty') <span class="block text-[10px] text-red-600 dark:text-red-400 mt-0.5">{{ $message }}</span> @enderror
                                                        </td>
                                                        <td class="px-2 py-1 text-center whitespace-nowrap">
                                                            <button wire:click="saveConfirmCrimpWeighing" class="text-green-600 hover:text-green-800 font-bold" title="Guardar">✓</button>
                                                            <button wire:click="cancelEditCrimpWeighing" class="text-gray-400 hover:text-gray-600 ml-1" title="Cancelar">✕</button>
                                                        </td>
                                                    @else
                                                        <td class="px-3 py-1 text-right font-semibold text-cyan-700 dark:text-cyan-400">{{ number_format($w->quantity) }}</td>
                                                        <td class="px-2 py-1 text-center whitespace-nowrap">
                                                            <button wire:click="editConfirmCrimpWeighing({{ $w->id }})" class="text-cyan-500 hover:text-cyan-700" title="Editar">✎</button>
                                                            <button wire:click="deleteConfirmCrimpWeighing({{ $w->id }})" class="text-red-400 hover:text-red-600 ml-1" title="Eliminar">✕</button>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @empty
                                                <tr><td colspan="2" class="px-3 py-2 text-center text-gray-400 italic">Sin pesadas</td></tr>
                                            @endforelse
                                            <tr class="bg-gray-50/60 dark:bg-gray-900/20">
                                                <td class="px-3 py-1.5">
                                                    <input type="number" min="1" wire:model="cCrimpQty" placeholder="pzs"
                                                        class="w-full px-2 py-1 text-xs text-right border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button wire:click="addConfirmCrimpWeighing" class="text-cyan-600 hover:text-cyan-800 font-bold">＋</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-gray-50 dark:bg-gray-900/30">
                                            <tr><td class="px-3 py-1.5 text-right text-xs font-bold text-cyan-700 dark:text-cyan-400">Total: {{ number_format($cfCrimpTotal) }}</td><td></td></tr>
                                        </tfoot>
                                    </table>
                                    @error('cCrimpQty') <p class="text-xs text-red-600 dark:text-red-400 px-3 py-1">{{ $message }}</p> @enderror
                                </div>
                            </section>
                        </div>

                        {{-- Paso 3: Confirma cantidades --}}
                        <section class="border-t border-gray-200 dark:border-gray-700 pt-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-bold">3</span>
                                <h4 class="font-semibold text-gray-800 dark:text-gray-100">Confirma cantidades</h4>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-center">
                                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas «manguitas»</div>
                                    <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format($cfPiecesTotal) }}</div>
                                </div>
                                <div class="rounded-lg bg-cyan-50 dark:bg-cyan-900/20 p-3 text-center">
                                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas CRIMP</div>
                                    <div class="text-lg font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($cfCrimpTotal) }}</div>
                                </div>
                                <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 text-center">
                                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">
                                        <span class="relative inline-flex items-center gap-1 group cursor-help">
                                            CRIMP sobrante
                                            <svg class="w-3 h-3 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 top-full mt-1 z-30 hidden group-hover:block w-56 rounded-lg bg-gray-900 text-white text-[11px] font-normal normal-case leading-snug px-3 py-2 shadow-lg text-left">CRIMP del objetivo que aún no se empacaron (quedan disponibles).</span>
                                        </span>
                                    </div>
                                    <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($cfCrimpSurplus) }}</div>
                                </div>
                                <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 text-center">
                                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">
                                        <span class="relative inline-flex items-center gap-1 group cursor-help">
                                            Piezas sobrantes
                                            <svg class="w-3 h-3 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 top-full mt-1 z-30 hidden group-hover:block w-56 rounded-lg bg-gray-900 text-white text-[11px] font-normal normal-case leading-snug px-3 py-2 shadow-lg text-left">Piezas buenas que NO se empacaron (existen físicamente). Empaque debe entregarlas.</span>
                                        </span>
                                    </div>
                                    <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($cfPiecesSurplus) }}</div>
                                </div>
                            </div>
                            <button wire:click="confirmPackaging"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg transition-colors
                                    {{ $confirmDone ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 cursor-default' : 'bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 hover:bg-gray-700' }}">
                                @if ($confirmDone)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Cantidades confirmadas
                                @else
                                    Confirmar cantidades
                                @endif
                            </button>
                        </section>

                        {{-- Empaque Terminado (al confirmar) --}}
                        @if ($confirmDone)
                            <section class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                        <span class="text-gray-400">→</span> Resumen generado — Empaque Terminado
                                    </h4>
                                    <span class="text-[11px] text-gray-400">Este documento ya no se descarga a PDF</span>
                                </div>
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700/50 text-sm font-bold text-gray-700 dark:text-gray-200">Empaque Terminado</div>
                                    <dl class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                        @php
                                            $cfRows = [
                                                ['Destinatarios', 'Empaque + Materiales'],
                                                ['Descripción', $cfPart?->description ?? $cfPart?->number ?? '—'],
                                                ['No. Order (WO + Viajero)', $cfWoNum.($cfLot?->lot_number ?? '—')],
                                                ['Número de orden en etiquetas (WO + Lote de CRIMP)', $cfWoNum.($cfSel?->crimp_lot_number ?? '—')],
                                                ['Cantidad en el viajero', number_format($cfLot?->quantity ?? 0)],
                                                ['División del lote', $cfOtherLots->isNotEmpty()
                                                    ? 'Este lote contiene '.number_format($cfSel?->quantity ?? 0).' y existe'.($cfOtherLots->count() > 1 ? 'n otros lotes' : ' otro lote').' por '.$cfOtherLots->map(fn($l) => number_format($l->quantity))->join(', ')
                                                    : 'Lote único'],
                                                ['Cantidad completa de piezas (manguitas)', number_format($cfPiecesTotal)],
                                                ['Cantidad completa de CRIMP', number_format($cfCrimpTotal)],
                                                ['CRIMP sobrante', number_format($cfCrimpSurplus)],
                                                ['Piezas / manguitas sobrantes', number_format($cfPiecesSurplus)],
                                                ['Empacado por', auth()->user()?->name ?? '—'],
                                                ['Fecha', now()->format('Y-m-d')],
                                                ['Comentarios', $confirmComments ?: '—'],
                                            ];
                                        @endphp
                                        @foreach ($cfRows as [$k, $v])
                                            <div class="flex gap-4 px-4 py-1.5">
                                                <dt class="w-1/2 text-gray-500 dark:text-gray-400">{{ $k }}</dt>
                                                <dd class="w-1/2 font-medium text-gray-800 dark:text-gray-100 text-right">{{ $v }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>

                                {{-- No. etiquetas + comentarios + notificar (M9) --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">No. de etiquetas <span class="text-gray-400">(opcional)</span></label>
                                        <input type="number" min="0" wire:model="confirmLabelCount" placeholder="—"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        @error('confirmLabelCount') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Comentarios <span class="text-gray-400">(opcional)</span></label>
                                        <input type="text" wire:model="confirmComments" placeholder="Observaciones para Empaque/Materiales..."
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    </div>
                                </div>
                            </section>
                        @endif
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 rounded-b-2xl">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Forma parte del flujo principal · continúa al Paso 6</span>
                        <div class="flex items-center gap-2">
                            <button wire:click="closeConfirmModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cerrar</button>
                            @if ($confirmDone)
                                <button wire:click="confirmAndNotifyFromModal"
                                    class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">
                                    {{ $cfLot?->packaging_notified_at ? 'Reenviar notificación' : 'Confirmar y notificar' }}
                                </button>
                            @endif
                            <button @if (!$confirmDone) disabled @endif
                                wire:click="goToDecisionFromConfirm"
                                class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors
                                    {{ $confirmDone ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}">
                                Continuar a Paso 6 ▸
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== PACKAGING MODAL ===== --}}
    @if ($showPackagingModal)
        @php
            $modalLot = $workOrders->flatMap->lots->firstWhere('id', $packagingLotId);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/70" wire:click="closePackagingModal"></div>
            <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Registrar Empaque</h3>
                        @if ($modalLot)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Lote {{ $modalLot->lot_number }}
                                &mdash; Disponibles: {{ number_format($modalAvailable) }} pzas
                            </p>
                        @endif
                    </div>
                    <button wire:click="closePackagingModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    {{-- Available pieces info --}}
                    @if ($modalAvailable === 0)
                        <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg text-sm text-yellow-800 dark:text-yellow-300">
                            No hay piezas disponibles aprobadas por calidad para este lote.
                        </div>
                    @endif

                    {{-- Packed pieces --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Piezas empacadas <span class="text-red-500">*</span>
                            @if ($modalAvailable > 0)
                                <span class="text-gray-400 dark:text-gray-500 font-normal">(máx. {{ number_format($modalAvailable) }})</span>
                            @endif
                        </label>
                        <input type="number" wire:model="packedPieces" min="0" max="{{ $modalAvailable }}" placeholder="0"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('packedPieces')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Surplus pieces --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Piezas sobrantes</label>
                        <input type="number" wire:model="surplusPieces" min="0" placeholder="0"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Puede ingresar manualmente la cantidad de sobrantes.</p>
                        @error('surplusPieces')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Date/Time --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha y hora <span class="text-red-500">*</span></label>
                        <input type="datetime-local" wire:model="packedAt"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('packedAt')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Comments --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios (opcional)</label>
                        <textarea wire:model="packagingComments" rows="2" placeholder="Observaciones de empaque..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="closePackagingModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="savePackaging"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                        Guardar Empaque
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== DECISION MODAL — Control de Materiales ===== --}}
    @if ($showDecisionModal && $selectedLotForDecision)
        @php
            $dPart = $selectedLotForDecision->workOrder->purchaseOrder->part ?? null;
            $dWoNum = $selectedLotForDecision->workOrder->purchaseOrder->wo ?? $selectedLotForDecision->workOrder->wo_number ?? '—';
            $dOrderLabel = 'No. Order (WO + '.($decIsCrimp ? 'Viajero' : 'Lote').')';
            $dFirstCrimp = $decIsCrimp ? ($selectedLotForDecision->crimpLots->first()?->crimp_lot_number ?? '—') : null;
        @endphp
        <x-ui-modal
            :badge="$decIsCrimp ? 'Paso 6' : null"
            :title="$decIsCrimp ? 'Resumen + toma de decisión' : 'Decisión – Control de Materiales'"
            :subtitle="$decIsCrimp ? '4 opciones según sobrantes / faltantes del lote de CRIMP' : 'Lote '.$selectedLotForDecision->lot_number"
            close="closeDecisionModal"
            maxWidth="2xl"
            bodyClass="px-6 py-5 space-y-5 max-h-[68vh] overflow-y-auto">
            <x-slot:context>
                <x-ui-modal.ctx label="Descripción" :value="$dPart?->description ?? $dPart?->number ?? '—'" />
                <x-ui-modal.ctx :label="$dOrderLabel" :value="$dWoNum.$selectedLotForDecision->lot_number" />
                @if ($decIsCrimp)
                    <x-ui-modal.ctx label="Lote de CRIMP" :value="$dFirstCrimp" />
                @else
                    <x-ui-modal.ctx label="Parte" :value="$dPart?->number ?? '—'" />
                @endif
                <x-ui-modal.ctx :label="$decIsCrimp ? 'Cantidad en viajero' : 'Cantidad en lote'" :value="number_format($decLotTotal)" />
            </x-slot:context>

                        {{-- Resumen --}}
                        @if ($decIsCrimp)
                            {{-- Paso 6 (diagrama 4): resumen del lote de CRIMP --}}
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-bold">∑</span>
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Resumen del lote de CRIMP</h4>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Total · Empacadas · Sobrantes · Faltantes</p>
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] uppercase text-gray-500 dark:text-gray-400">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Concepto</th>
                                                <th class="px-3 py-2 text-right">Total</th>
                                                <th class="px-3 py-2 text-right">Empacadas</th>
                                                <th class="px-3 py-2 text-right">
                                                    <span class="relative inline-flex items-center gap-1 group cursor-help justify-end">
                                                        Sobrantes
                                                        <svg class="w-3.5 h-3.5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        <span class="pointer-events-none absolute right-0 top-full mt-1 z-30 hidden group-hover:block w-60 rounded-lg bg-gray-900 text-white text-[11px] font-normal normal-case leading-snug px-3 py-2 shadow-lg text-left">Piezas/CRIMP buenos que NO se empacaron (existen físicamente). Quedan como sobrante y Empaque debe entregarlos.</span>
                                                    </span>
                                                </th>
                                                <th class="px-3 py-2 text-right">
                                                    <span class="relative inline-flex items-center gap-1 group cursor-help justify-end">
                                                        Faltantes
                                                        <svg class="w-3.5 h-3.5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        <span class="pointer-events-none absolute right-0 top-full mt-1 z-30 hidden group-hover:block w-60 rounded-lg bg-gray-900 text-white text-[11px] font-normal normal-case leading-snug px-3 py-2 shadow-lg text-left">Piezas que faltan del objetivo: se perdieron o las rechazó Calidad. Se reponen al «Completar» o se aceptan al «Cerrar».</span>
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Piezas «manguitas»</td>
                                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($decLotTotal) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($decPacked) }}</td>
                                                <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($decSurplus) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold {{ $decMissing > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">{{ number_format($decMissing) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Piezas CRIMP</td>
                                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($decCrimpTotal) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold text-cyan-700 dark:text-cyan-400">{{ number_format($decCrimpPacked) }}</td>
                                                <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($decCrimpSurplus) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold {{ $decCrimpMissing > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">{{ number_format($decCrimpMissing) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Lote</div>
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($decLotTotal) }}</div>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg text-center">
                                    <div class="text-xs text-green-600 dark:text-green-400 mb-1">Empacadas</div>
                                    <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format($decPacked) }}</div>
                                </div>
                                <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg text-center">
                                    <div class="text-xs text-orange-600 dark:text-orange-400 mb-1">Sobrantes</div>
                                    <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($decSurplus) }}</div>
                                </div>
                                <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-lg text-center">
                                    <div class="text-xs text-red-600 dark:text-red-400 mb-1">Faltantes</div>
                                    <div class="text-lg font-bold text-red-700 dark:text-red-300">{{ number_format($decMissing) }}</div>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                                Faltantes = Total Lote - Empacadas - Sobrantes
                            </p>
                        @endif

                        {{-- Decision options (only if no closure decision yet) --}}
                        @if (!$decClosureDecision)
                            @if ($decIsCrimp)
                                {{-- CRIMP · Paso 6 (diagrama 4 / wireframe): D1 Cerrar · D2 Completar (a/b/c) · D3 Nuevo lote --}}
                                <div x-data="{ sel: null, sub: null }">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-6 h-6 flex items-center justify-center rounded-full bg-amber-500 text-white text-xs font-bold">?</span>
                                        <h4 class="font-semibold text-gray-800 dark:text-gray-100">¿Qué decisión se toma?</h4>
                                    </div>

                                    {{-- 3 tarjetas de decisión --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <button type="button" x-on:click="sel='D1'; sub=null"
                                            :class="sel==='D1' ? 'border-rose-500 bg-rose-100 dark:bg-rose-900/40 ring-2 ring-rose-300 dark:ring-rose-700' : 'border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-900/15 hover:bg-rose-100 dark:hover:bg-rose-900/30'"
                                            class="p-3.5 border-2 rounded-xl text-left transition-all">
                                            <div class="text-sm font-bold text-rose-800 dark:text-rose-300">D1 · Cerrar lote</div>
                                            <div class="text-xs text-rose-600/80 dark:text-rose-400/80 mt-1">Así como está, sin crear un nuevo lote.</div>
                                        </button>
                                        <button type="button" x-on:click="sel='D2'"
                                            :class="sel==='D2' ? 'border-sky-500 bg-sky-100 dark:bg-sky-900/40 ring-2 ring-sky-300 dark:ring-sky-700' : 'border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/15 hover:bg-sky-100 dark:hover:bg-sky-900/30'"
                                            class="p-3.5 border-2 rounded-xl text-left transition-all">
                                            <div class="text-sm font-bold text-sky-800 dark:text-sky-300">D2 · Completar</div>
                                            <div class="text-xs text-sky-600/80 dark:text-sky-400/80 mt-1">Completar faltantes según el caso · 3 sub-decisiones.</div>
                                        </button>
                                        <button type="button" x-on:click="sel='D3'; sub=null"
                                            :class="sel==='D3' ? 'border-emerald-500 bg-emerald-100 dark:bg-emerald-900/40 ring-2 ring-emerald-300 dark:ring-emerald-700' : 'border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/15 hover:bg-emerald-100 dark:hover:bg-emerald-900/30'"
                                            class="p-3.5 border-2 rounded-xl text-left transition-all">
                                            <div class="text-sm font-bold text-emerald-800 dark:text-emerald-300">D3 · Nuevo viajero</div>
                                            <div class="text-xs text-emerald-600/80 dark:text-emerald-400/80 mt-1">Crear o reiniciar un nuevo viajero de CRIMP.</div>
                                        </button>
                                    </div>

                                    {{-- Detalle D1 --}}
                                    <div x-show="sel==='D1'" style="display:none" class="mt-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50/50 dark:bg-gray-900/20">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">D1 · Cerrar lote — así como está</div>
                                        <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                            <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Cerrar lote sin crear nuevo lote</span>
                                            <span class="text-gray-400">→</span>
                                            <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 1 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                            <span class="text-gray-400">→</span>
                                            <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Continúa Paso 7 · Entrega de viajero</span>
                                        </div>
                                        <button wire:click="decisionCloseAsIs" wire:confirm="¿Cerrar el viajero así como está, sin nuevo lote?"
                                            class="px-4 py-2 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 rounded-lg">Confirmar D1 y continuar a Paso 7 ▸</button>
                                    </div>

                                    {{-- Detalle D2 --}}
                                    <div x-show="sel==='D2'" style="display:none" class="mt-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50/50 dark:bg-gray-900/20">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-1">D2 · Completar — elige una sub-decisión</div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Completar CRIMP = piezas sobrantes − CRIMP sobrante = <strong>{{ number_format($decCompletarCrimp) }}</strong></p>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                            <button type="button" x-on:click="sub='a'" :class="sub==='a' ? 'border-cyan-500 bg-cyan-50 dark:bg-cyan-900/20' : 'border-gray-200 dark:border-gray-600'" class="p-2 border-2 rounded text-left transition-colors">
                                                <div class="text-xs font-bold text-gray-800 dark:text-gray-100">D2a · Solo completar CRIMP</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">Decisión de Materiales</div>
                                            </button>
                                            <button type="button" x-on:click="sub='b'" :class="sub==='b' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-600'" class="p-2 border-2 rounded text-left transition-colors">
                                                <div class="text-xs font-bold text-gray-800 dark:text-gray-100">D2b · Solo completar piezas</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">Decisión de Materiales</div>
                                            </button>
                                            <button type="button" x-on:click="sub='c'" :class="sub==='c' ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20' : 'border-gray-200 dark:border-gray-600'" class="p-2 border-2 rounded text-left transition-colors">
                                                <div class="text-xs font-bold text-gray-800 dark:text-gray-100">D2c · Piezas y CRIMP</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">Decisión de Materiales</div>
                                            </button>
                                        </div>

                                        {{-- D2a --}}
                                        <div x-show="sub==='a'" style="display:none" class="mt-3">
                                            <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Completar CRIMP = piezas sobrantes − CRIMP sobrante = {{ number_format($decCompletarCrimp) }}</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 2 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7</span>
                                            </div>
                                            <button wire:click="decisionCompleteCrimp" class="px-4 py-2 text-sm font-semibold text-white bg-cyan-600 hover:bg-cyan-700 rounded-lg">Confirmar D2a y continuar a Paso 7 ▸</button>
                                        </div>
                                        {{-- D2b --}}
                                        <div x-show="sub==='b'" style="display:none" class="mt-3">
                                            <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Materiales envía {{ number_format($decSurplus) }} pz a empaque</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Empaque completa piezas pendientes</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 3 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7</span>
                                            </div>
                                            <button wire:click="decisionCompletePieces" class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">Confirmar D2b y continuar a Paso 7 ▸</button>
                                        </div>
                                        {{-- D2c --}}
                                        <div x-show="sub==='c'" style="display:none" class="mt-3">
                                            <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Completar CRIMP = {{ number_format($decCompletarCrimp) }}</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Completar lote de CRIMP + capturar piezas CRIMP ({{ number_format($decSurplus) }} pz)</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 5 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7</span>
                                            </div>
                                            <button wire:click="decisionCompleteBoth" class="px-4 py-2 text-sm font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-lg">Confirmar D2c y continuar a Paso 7 ▸</button>
                                        </div>
                                    </div>

                                    {{-- Detalle D3 --}}
                                    <div x-show="sel==='D3'" style="display:none" class="mt-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50/50 dark:bg-gray-900/20">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-1">D3 · Nuevo viajero</div>
                                        <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">¿Reiniciar el mismo viajero o crear uno nuevo? → <strong>Nuevo viajero</strong></p>
                                        <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                            <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Sobrantes piezas (manguitas) = Nuevo viajero = {{ number_format(intdiv(max(0, $decSurplus), 100) * 100) }}</span>
                                            <span class="text-gray-400">→</span>
                                            <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 6 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                            <span class="text-gray-400">→</span>
                                            <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7</span>
                                        </div>
                                        <div class="text-[11px] text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded px-2 py-1 mb-3">Nota: redondear hacia abajo en múltiplos de 100.</div>
                                        <button wire:click="decisionNewLot" class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">Confirmar D3 — crear nuevo viajero ▸</button>
                                    </div>
                                </div>
                            @else
                            @if ($decSurplus > 0 || $decMissing > 0)
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    {{-- Opción 1: Completar Lote --}}
                                    @if ($decMissing > 0)
                                        <button wire:click="decisionCompleteLot"
                                            class="p-4 border-2 border-indigo-200 dark:border-indigo-700 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors cursor-pointer text-center">
                                            <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-indigo-100 dark:bg-indigo-800 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                            </div>
                                            <div class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">Completar Lote</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Reiniciar con {{ number_format($decMissing) }} pz faltantes</div>
                                        </button>
                                    @endif

                                    {{-- Opción 2: Nuevo Lote --}}
                                    <button wire:click="decisionNewLot"
                                        class="p-4 border-2 border-green-200 dark:border-green-700 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors cursor-pointer text-center">
                                        <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                        </div>
                                        <div class="text-sm font-semibold text-green-700 dark:text-green-300">Nuevo Lote</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ number_format(max(0, $decLotTotal - $decPacked)) }} pz en {{ $decIsCrimp ? 'viajero' : 'lote' }} nuevo</div>
                                    </button>

                                    {{-- Opción 3: Cerrar Lote como está --}}
                                    <button wire:click="decisionCloseAsIs"
                                        wire:confirm="¿Cerrar el lote aceptando {{ number_format($decMissing) }} piezas faltantes?"
                                        class="p-4 border-2 border-orange-200 dark:border-orange-700 rounded-lg hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors cursor-pointer text-center">
                                        <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-orange-100 dark:bg-orange-800 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                        <div class="text-sm font-semibold text-orange-700 dark:text-orange-300">Cerrar Lote</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Aceptar {{ number_format($decMissing) }} pz faltantes</div>
                                    </button>
                                </div>
                            @else
                                {{-- Sin faltantes: cerrar directamente --}}
                                <button wire:click="decisionCloseAsIs"
                                    wire:confirm="¿Cerrar el lote? No hay piezas faltantes."
                                    class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Cerrar Lote (Completo)
                                </button>
                            @endif
                            @endif
                        @else
                            {{-- Decisión ya tomada --}}
                            @php
                                $closureLabel = match ($decClosureDecision) {
                                    'complete_lot'    => 'Completar Lote',
                                    'new_lot'         => 'Nuevo Lote Creado',
                                    'close_as_is'     => 'Lote Cerrado (faltantes aceptados)',
                                    'complete_crimp'  => 'Completar CRIMP'.($decCompletarCrimp ? ' ('.number_format($decCompletarCrimp).')' : ''),
                                    'complete_pieces' => 'Completar piezas',
                                    'complete_both'   => 'Completar piezas y CRIMP',
                                    default           => $decClosureDecision,
                                };
                                $closureColor = match ($decClosureDecision) {
                                    'complete_lot' => 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-700 text-indigo-800 dark:text-indigo-200',
                                    'new_lot'      => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700 text-green-800 dark:text-green-200',
                                    'close_as_is'  => 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-700 text-orange-800 dark:text-orange-200',
                                    default        => 'bg-gray-50 dark:bg-gray-700/50 border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200',
                                };
                            @endphp
                            <div class="border rounded-lg p-3 {{ $closureColor }}">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm font-medium">Decisión: {{ $closureLabel }}</span>
                                </div>
                            </div>

                            @php $isCompletion = in_array($decClosureDecision, ['complete_crimp','complete_pieces','complete_both']); @endphp
                            @if ($isCompletion)
                                {{-- D2a/b/c — qué falta completar (diagrama 4) y continúa al Paso 7 --}}
                                @php
                                    $compCrimp  = (int) ($selectedLotForDecision->complete_crimp_qty ?? 0);
                                    $compPieces = (int) ($selectedLotForDecision->complete_pieces_qty ?? 0);
                                @endphp
                                <div class="bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-700 rounded-lg p-4 space-y-2">
                                    <h5 class="text-sm font-semibold text-sky-800 dark:text-sky-200">Por completar — decisión de Materiales</h5>
                                    @if ($decClosureDecision !== 'complete_pieces')
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-300">Completar CRIMP <span class="text-[11px] text-gray-400">(piezas sobrantes − CRIMP sobrante)</span></span>
                                            <span class="font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($compCrimp) }}</span>
                                        </div>
                                    @endif
                                    @if ($decClosureDecision !== 'complete_crimp')
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-300">Completar piezas «manguitas» <span class="text-[11px] text-gray-400">(Materiales envía a Empaque)</span></span>
                                            <span class="font-bold text-green-700 dark:text-green-300">{{ number_format($compPieces) }}</span>
                                        </div>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-2 text-xs pt-1">
                                        <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">{{ $decClosureDecision === 'complete_pieces' ? 'Materiales envía → Empaque completa piezas' : 'Materiales completa lo pendiente' }}</span>
                                        <span class="text-gray-400">→</span>
                                        <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                        <span class="text-gray-400">→</span>
                                        <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7 · Entrega de viajero</span>
                                    </div>
                                    @if (in_array($decClosureDecision, ['complete_pieces','complete_both']))
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 pt-1">Empaque captura las piezas faltantes con el botón <strong>“Empacar / Confirmar”</strong> del viajero (Paso 5).</p>
                                    @endif
                                </div>
                                @if (!$decSurplusReceived)
                                    <button wire:click="confirmSurplusReceived" wire:confirm="¿Marcar la completación como realizada y continuar al Paso 7?"
                                        class="w-full px-4 py-3 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Confirmar completado · Continuar a Paso 7
                                    </button>
                                @else
                                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3">
                                        <span class="text-sm font-medium text-green-800 dark:text-green-200">Completado. Continúa al Paso 7 (entrega de viajero).</span>
                                    </div>
                                @endif
                            @else
                            {{-- Estado de entrega de sobrantes --}}
                            @if ($decSurplus > 0)
                                @if (!$decSurplusDelivered)
                                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-amber-800 dark:text-amber-200">Pendiente: Empaque debe entregar {{ number_format($decSurplus) }} pz sobrantes</span>
                                        </div>
                                    </div>
                                @elseif (!$decSurplusReceived)
                                    <div class="border border-red-200 dark:border-red-700 rounded-lg p-4">
                                        <h5 class="text-sm font-semibold text-red-800 dark:text-red-200 mb-3">Pendiente: Recepción de Material Sobrante</h5>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                            Empaque entregó <strong class="text-orange-600">{{ number_format($decSurplus) }}</strong> piezas sobrantes. Confirmar recepción.
                                        </p>
                                        <button wire:click="confirmSurplusReceived"
                                            wire:confirm="¿Confirma que se recibieron {{ number_format($decSurplus) }} piezas sobrantes?"
                                            class="w-full px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                            Material Recibido
                                        </button>
                                    </div>
                                @else
                                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-sm font-medium text-green-800 dark:text-green-200">Material sobrante recibido. Lote completado.</span>
                                        </div>
                                    </div>
                                @endif
                            @endif
                            @endif

                            {{-- Reabrir Lote --}}
                            <button wire:click="reopenLot"
                                wire:confirm="¿Desea reabrir este lote y anular la decisión tomada?"
                                class="w-full px-4 py-3 border-2 border-yellow-400 dark:border-yellow-600 text-yellow-700 dark:text-yellow-300 font-semibold rounded-lg hover:bg-yellow-50 dark:hover:bg-yellow-900/20 transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Reabrir Lote
                            </button>
                        @endif
            <x-slot:footer>
                <span class="text-xs text-gray-500 dark:text-gray-400">@if($decIsCrimp)Todos los caminos continúan al Paso 7 (entrega) → Paso 8 (sobrantes)@endif</span>
                <button wire:click="closeDecisionModal"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cerrar
                </button>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- ===== CREATE LOT MODAL (from Decision) ===== --}}
    @if ($showCreateLotFormModal && $selectedLotForDecision)
        <div class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="create-lot-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/70 dark:bg-gray-900/80 transition-opacity" wire:click="closeCreateLotFormModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">

                    {{-- Header --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-indigo-600">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 id="create-lot-modal-title" class="text-xl font-bold text-gray-900 dark:text-white">
                                    Crear {{ $decIsCrimp ? 'Nuevo Viajero' : 'Nuevo Lote' }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $createLotType === 'complete' ? 'Completar lote con piezas faltantes' : 'Cerrar lote actual y crear nuevo' }}
                                </p>
                            </div>
                            <button wire:click="closeCreateLotFormModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5 space-y-4">
                        @if ($decIsCrimp)
                            <div class="bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-700 rounded-lg p-3">
                                <p class="text-xs text-cyan-700 dark:text-cyan-300">
                                    <strong>Parte con CRIMP:</strong> Se creará un nuevo viajero. Sus <strong>lotes de CRIMP</strong> se capturan en <strong>Materiales</strong> (ya no se usa Kit).
                                </p>
                            </div>
                        @endif

                        @if ($createLotType === 'new_lot')
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3">
                                <p class="text-xs text-green-700 dark:text-green-300">
                                    <strong>Nuevo Lote:</strong> El lote actual se cerrará y la lista regresará a <strong>Materiales</strong> para procesar el nuevo lote.
                                </p>
                            </div>
                        @endif

                        {{-- Lot Name --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre / Número de Lote</label>
                            <input type="text" wire:model="createLotName"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('createLotName')
                                <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Quantity --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cantidad (piezas)</label>
                            <input type="number" wire:model="createLotQuantity" min="1"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('createLotQuantity')
                                <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                            @enderror
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Piezas faltantes del Lote: {{ number_format($decMissing) }}
                            </p>
                        </div>

                        {{-- Summary --}}
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-sm">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Se creará:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    Lote "{{ $createLotName }}" — {{ number_format($createLotQuantity) }} pz
                                </span>
                            </div>
                            @if ($decIsCrimp)
                                <div class="flex justify-between text-gray-600 dark:text-gray-400 mt-1">
                                    <span>Lotes de CRIMP:</span>
                                    <span class="font-medium text-cyan-700 dark:text-cyan-300">Se capturan en Materiales</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-end gap-3">
                        <button wire:click="closeCreateLotFormModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmCreateLot"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors cursor-pointer">
                            Confirmar y Crear
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== CLOSE LIST MODAL ===== --}}
    @if ($showCloseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/70"></div>
            <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Cerrar y Confirmar Lista</h3>
                    <button wire:click="$set('showCloseModal', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <div class="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <div>
                            <p class="font-semibold text-green-800 dark:text-green-300">Confirmar cierre de lista</p>
                            <p class="text-sm text-green-700 dark:text-green-400 mt-1">
                                Esta acción marcará la lista como <strong>Confirmada</strong> y finalizará el flujo de departamentos. Esta acción no se puede deshacer.
                            </p>
                        </div>
                    </div>

                    {{-- Summary --}}
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Lote</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Disponibles</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Empacadas</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Sobrante</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($workOrders as $wo)
                                    @foreach ($wo->lots as $lot)
                                        @php
                                            $availPcs = (int) $lot->qualityWeighings->sum('good_pieces');
                                            $packedPcs = (int) $lot->packagingRecords->sum('packed_pieces');
                                            $surplusPcs = max(0, $availPcs - $packedPcs);
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-2.5 font-mono text-gray-800 dark:text-gray-200">{{ $lot->lot_number }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-400">{{ number_format($availPcs) }}</td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($packedPcs) }}</td>
                                            <td class="px-4 py-2.5 text-right {{ $surplusPcs > 0 ? 'text-orange-600 dark:text-orange-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                                                {{ number_format($surplusPcs) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="$set('showCloseModal', false)"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="closeList"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors font-semibold">
                        Confirmar y Cerrar Lista
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
