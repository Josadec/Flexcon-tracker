<div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
    @php
        $estadoMap = [
            'completado'  => ['Completado', 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700', 'bg-emerald-500'],
            'en_proceso'  => ['En proceso', 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700', 'bg-amber-500'],
            'no_iniciado' => ['No iniciado', 'bg-slate-100 dark:bg-slate-700/40 text-slate-500 dark:text-slate-400 border-slate-300 dark:border-slate-600', 'bg-slate-400'],
        ];
        $pct = fn ($emp, $tot) => $tot > 0 ? min(100, (int) round($emp / $tot * 100)) : 0;
    @endphp

    {{-- ===== Encabezado ===== --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200">Resumen de WO</span>
                @if ($isCrimp)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300">CRIMP</span>
                @endif
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $woNum }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $part?->number ?? '—' }}</span>
                @if ($part?->description) <span class="text-slate-400">·</span> {{ $part->description }} @endif
            </p>
        </div>
        <a href="{{ route('admin.sent-lists.display.wo', $workOrder->id) }}" wire:navigate
            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Volver al tablero
        </a>
    </div>

    {{-- ===== Barra de totales del WO ===== --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $isCrimp ? 'Viajeros' : 'Lotes' }}</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($totals['viajeros']) }}</div>
            <div class="mt-1 flex flex-wrap gap-1 text-[10px] font-semibold">
                <span class="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">{{ $totals['completados'] }} ✓</span>
                <span class="px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">{{ $totals['en_proceso'] }} ⏳</span>
                <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/40 text-slate-500 dark:text-slate-400">{{ $totals['no_iniciado'] }} ○</span>
            </div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Total</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($totals['total']) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Empacadas</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($totals['empacadas']) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Sobrantes</div>
            <div class="text-2xl font-bold {{ $totals['sobrantes'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400 dark:text-slate-500' }}">{{ number_format($totals['sobrantes']) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Faltantes</div>
            <div class="text-2xl font-bold {{ $totals['faltantes'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500' }}">{{ number_format($totals['faltantes']) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 shadow-sm flex flex-col justify-center">
            <div class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Avance</div>
            @php $woPct = $pct($totals['empacadas'], $totals['total']); @endphp
            <div class="flex items-center gap-2">
                <div class="flex-1 h-2.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                    <div class="h-full rounded-full bg-cyan-500" style="width: {{ $woPct }}%"></div>
                </div>
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $woPct }}%</span>
            </div>
        </div>
    </div>

    @if (empty($viajeros))
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center text-slate-500 dark:text-slate-400">
            Este WO no tiene {{ $isCrimp ? 'viajeros' : 'lotes' }} abiertos.
        </div>
    @endif

    {{-- ===== Viajeros ===== --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        @foreach ($viajeros as $v)
            @php
                [$vEstadoLabel, $vEstadoClass, $vDot] = $estadoMap[$v['estado']];
                $vPct = $pct($v['empacadas'], $v['total']);
            @endphp
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
                {{-- Cabecera --}}
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="inline-flex items-center justify-center min-w-[3rem] h-11 px-2 rounded-xl bg-cyan-600 text-white text-sm font-extrabold tracking-tight">{{ $v['number'] }}</span>
                        <div class="min-w-0">
                            <div class="text-base font-bold text-slate-900 dark:text-white truncate">{{ $isCrimp ? 'Viajero' : 'Lote' }} {{ $v['number'] }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">Cantidad: <span class="font-semibold">{{ number_format($v['total']) }}</span> pz</div>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $vEstadoClass }} shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full {{ $vDot }}"></span>{{ $vEstadoLabel }}
                    </span>
                </div>

                {{-- Avance --}}
                <div class="px-5 pt-4">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full {{ $vPct >= 100 ? 'bg-emerald-500' : 'bg-cyan-500' }}" style="width: {{ $vPct }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300 w-9 text-right">{{ $vPct }}%</span>
                    </div>
                </div>

                {{-- Métricas --}}
                <div class="grid grid-cols-4 gap-2 px-5 py-4">
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 p-2.5 text-center">
                        <div class="text-[10px] uppercase text-slate-500 dark:text-slate-400">Total</div>
                        <div class="text-base font-bold text-slate-800 dark:text-slate-100">{{ number_format($v['total']) }}</div>
                    </div>
                    <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 p-2.5 text-center">
                        <div class="text-[10px] uppercase text-slate-500 dark:text-slate-400">Empacadas</div>
                        <div class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($v['empacadas']) }}</div>
                    </div>
                    <div class="rounded-lg {{ $v['sobrantes'] > 0 ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-slate-50 dark:bg-slate-900/40' }} p-2.5 text-center">
                        <div class="text-[10px] uppercase text-slate-500 dark:text-slate-400">Sobrantes</div>
                        <div class="text-base font-bold {{ $v['sobrantes'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400 dark:text-slate-500' }}">{{ number_format($v['sobrantes']) }}</div>
                    </div>
                    <div class="rounded-lg {{ $v['faltantes'] > 0 ? 'bg-red-50 dark:bg-red-900/20' : 'bg-slate-50 dark:bg-slate-900/40' }} p-2.5 text-center">
                        <div class="text-[10px] uppercase text-slate-500 dark:text-slate-400">Faltantes</div>
                        <div class="text-base font-bold {{ $v['faltantes'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500' }}">{{ number_format($v['faltantes']) }}</div>
                    </div>
                </div>

                {{-- Lotes de CRIMP --}}
                @if ($isCrimp)
                    <div class="px-5 pb-5 mt-auto">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[11px] font-bold text-cyan-700 dark:text-cyan-300 uppercase tracking-wide">Lotes de CRIMP</span>
                            <span class="text-[11px] text-slate-400">·</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">objetivo {{ number_format($v['crimp_obj']) }} · empacados {{ number_format($v['crimp_emp']) }}</span>
                        </div>
                        @if (empty($v['crimp_lots']))
                            <div class="text-xs text-slate-400 dark:text-slate-500 italic bg-slate-50 dark:bg-slate-900/40 rounded-lg px-3 py-3 text-center">Sin lotes de CRIMP capturados (se registran en Materiales).</div>
                        @else
                            <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl">
                                <table class="w-full text-sm whitespace-nowrap">
                                    <thead class="bg-cyan-50 dark:bg-cyan-900/20 text-[10px] uppercase text-cyan-700 dark:text-cyan-300">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold">Lote CRIMP</th>
                                            <th class="px-3 py-2 text-right font-semibold">Total</th>
                                            <th class="px-3 py-2 text-right font-semibold">Empac.</th>
                                            <th class="px-3 py-2 text-right font-semibold">CRIMP</th>
                                            <th class="px-3 py-2 text-right font-semibold">Sobr.</th>
                                            <th class="px-3 py-2 text-right font-semibold">Falt.</th>
                                            <th class="px-3 py-2 text-center font-semibold">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        @foreach ($v['crimp_lots'] as $cl)
                                            @php [$clLabel, $clClass, $clDot] = $estadoMap[$cl['estado']]; @endphp
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                                <td class="px-3 py-2">
                                                    <div class="font-mono font-bold text-slate-800 dark:text-slate-100">{{ $cl['number'] }}</div>
                                                    @if ($cl['fab'])
                                                        <div class="text-[11px] text-slate-400">Fab: {{ $cl['fab'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-right text-slate-700 dark:text-slate-300">{{ number_format($cl['total']) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($cl['empacadas']) }}</td>
                                                <td class="px-3 py-2 text-right text-cyan-700 dark:text-cyan-400">{{ number_format($cl['crimp_emp']) }}</td>
                                                <td class="px-3 py-2 text-right {{ $cl['sobrantes'] > 0 ? 'text-orange-600 dark:text-orange-400 font-semibold' : 'text-slate-400 dark:text-slate-500' }}">{{ number_format($cl['sobrantes']) }}</td>
                                                <td class="px-3 py-2 text-right {{ $cl['faltantes'] > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-400 dark:text-slate-500' }}">{{ number_format($cl['faltantes']) }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $clClass }}">
                                                        <span class="w-1.5 h-1.5 rounded-full {{ $clDot }}"></span>{{ $clLabel }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
