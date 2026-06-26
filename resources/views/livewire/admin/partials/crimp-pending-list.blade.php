{{--
    Lista reutilizable de viajeros CRIMP pendientes de acción de un rol.
    Vars: $titulo (string), $pendientes (Collection de ['lot','action','kind']),
          $badges (array kind => clases tailwind), $empty (string opcional).
--}}
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ $titulo }}</h3>
        <span class="px-2 py-0.5 text-xs font-semibold bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-300 rounded-full">{{ $pendientes->count() }}</span>
    </div>
    @if ($pendientes->isNotEmpty())
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ($pendientes as $row)
                @php $vj = $row['lot']; $wo = $vj->workOrder; $b = $badges[$row['kind']] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'; @endphp
                <div class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-100">Viajero {{ $vj->lot_number }}</span>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $b }}">{{ $row['action'] }}</span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            WO {{ $wo->purchaseOrder->wo ?? $wo->wo_number }} · {{ $wo->purchaseOrder->part->number ?? '' }} · {{ $wo->purchaseOrder->part->description ?? '' }}
                        </div>
                    </div>
                    <a href="{{ route('admin.sent-lists.display.wo', $wo->id) }}" wire:navigate
                        class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-cyan-600 hover:bg-cyan-700 rounded-lg transition-colors">
                        Ir al tablero
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <div class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
            {{ $empty ?? 'Sin pendientes CRIMP. 🎉' }}
        </div>
    @endif
</div>
