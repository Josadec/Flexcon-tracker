{{--
    TABLERO DE PRODUCCIÓN

    Producción tiene un solo trabajo en el flujo: pesar las piezas de los lotes
    que Calidad ya aprobó. Por eso el tablero abre con la cola de pesada y sigue
    con la productividad del turno. Los pendientes salen de
    App\Support\PendingActions, igual que en el tablero de piso.
--}}
<x-ui.page eyebrow="Área · Producción" title="Producción"
    subtitle="Lotes por pesar y rendimiento de la línea.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.sent-lists.index') }}">Listas de envío</x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.sent-lists.display') }}">Abrir tablero de piso</x-ui.btn>
    </x-slot:actions>

    {{-- Carga del área --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Lotes por pesar" :value="$totalPending"
            :tone="$totalPending > 0 ? 'warn' : 'good'"
            help="Lotes con inspección aprobada a los que les faltan piezas por registrar." />
        <x-ui.stat label="Piezas por pesar" :value="number_format($piecesPending)"
            :tone="$piecesPending > 0 ? 'info' : 'good'"
            help="Suma de lo que falta en todos los lotes pendientes." />
        <x-ui.stat label="Piezas hoy" :value="number_format($todayPieces)" tone="good"
            :help="$todayWeighings.' '.Str::plural('pesada', $todayWeighings).' registradas hoy'" />
        <x-ui.stat label="Piezas esta semana" :value="number_format($weekPieces)" tone="info" />
    </x-ui.stats>

    {{-- Cola de trabajo --}}
    <x-ui.section title="Cola de pesada"
        hint="Lotes listos para producir. Si un lote no aparece aquí, es porque Calidad todavía no aprueba su inspección.">
        <x-ui.pending-table :items="$mine"
            emptyTitle="Producción está al día"
            emptyHint="No hay lotes con inspección aprobada esperando pesada." />
    </x-ui.section>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        {{-- Tendencia de la semana --}}
        <x-ui.section title="Piezas por día" hint="Últimos 7 días, sólo piezas buenas.">
            @if ($dailySeries->sum('pieces') === 0)
                <x-ui.empty icon="doc" title="Sin pesadas esta semana"
                    hint="En cuanto se registre la primera pesada aparecerá aquí la tendencia." />
            @else
                <div class="flex items-end justify-between gap-2" style="height: 10rem;">
                    @foreach ($dailySeries as $day)
                        @php $h = $maxDaily > 0 ? max(4, round(($day['pieces'] / $maxDaily) * 100)) : 4; @endphp
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <span class="text-xs font-bold tabular-nums text-slate-700 dark:text-slate-200">
                                {{ $day['pieces'] > 0 ? number_format($day['pieces']) : '' }}
                            </span>
                            <div class="w-full rounded-t {{ $day['pieces'] > 0 ? 'bg-indigo-500' : 'bg-slate-200 dark:bg-slate-700' }}"
                                style="height: {{ $h }}%"
                                title="{{ $day['date'] }}: {{ number_format($day['pieces']) }} piezas"></div>
                            <span class="text-[11px] font-semibold uppercase text-slate-500 dark:text-slate-400">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
                    Total acumulado histórico: <strong class="text-slate-700 dark:text-slate-200">{{ number_format($totalPieces) }}</strong> piezas
                </p>
            @endif
        </x-ui.section>

        {{-- Operadores --}}
        <x-ui.section title="Quién está pesando" hint="Últimos 30 días, ordenado por piezas buenas.">
            @if ($topOperators->isEmpty())
                <x-ui.empty icon="doc" title="Sin actividad en 30 días"
                    hint="No hay pesadas registradas en el último mes." />
            @else
                <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    @foreach ($topOperators as $i => $op)
                        <li class="flex items-center gap-3 px-4 py-2.5">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white dark:bg-slate-200 dark:text-slate-900">
                                {{ $i + 1 }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $op->weighedBy->name ?? 'Sin usuario' }}
                                </span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    {{ number_format($op->total_weighings) }} {{ Str::plural('pesada', $op->total_weighings) }}
                                </span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-sm font-bold tabular-nums text-indigo-700 dark:text-indigo-300">
                                    {{ number_format($op->total_good) }}
                                </span>
                                <span class="block text-[11px] text-slate-400">piezas</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.section>
    </div>

    {{-- Últimas pesadas --}}
    <x-ui.table title="Últimas pesadas registradas" hint="Los 8 registros más recientes.">
        <x-slot:head>
            <tr>
                <x-ui.th class="w-36">Lote</x-ui.th>
                <x-ui.th class="w-32">Parte</x-ui.th>
                <x-ui.th class="w-28" align="right">Buenas</x-ui.th>
                <x-ui.th class="w-28" align="right">Malas</x-ui.th>
                <x-ui.th>Registró</x-ui.th>
                <x-ui.th class="w-40">Fecha</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($recentWeighings as $w)
            <tr wire:key="w-{{ $w->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">
                    {{ $w->lot->lot_number ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $w->lot->workOrder->purchaseOrder->part->number ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-green-700 dark:text-green-400">
                    {{ number_format($w->good_pieces) }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums {{ $w->bad_pieces > 0 ? 'font-semibold text-red-700 dark:text-red-400' : 'text-slate-400' }}">
                    {{ number_format($w->bad_pieces) }}
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $w->weighedBy->name ?? '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">
                    {{ $w->weighed_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty title="Sin pesadas registradas"
                        hint="Las pesadas se registran desde el tablero de piso, en la columna Prod." />
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    {{-- Listas paradas aquí --}}
    <x-ui.section title="Listas de envío en Producción"
        hint="Listas pendientes cuyo departamento actual es Producción.">
        @if ($sentListsHere->isEmpty())
            <x-ui.empty icon="doc" title="Ninguna lista parada aquí"
                hint="Todas las listas pendientes están en otro departamento." />
        @else
            <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                @foreach ($sentListsHere as $sl)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-white">Lista #{{ $sl->id }}</span>
                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $sl->workOrders->count() }} {{ Str::plural('orden', $sl->workOrders->count()) }} ·
                                {{ $sl->created_at->format('d/m/Y') }}
                            </span>
                        </span>
                        <x-ui.btn variant="secondary" size="sm"
                            href="{{ route('admin.sent-lists.show', $sl->id) }}">Ver</x-ui.btn>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.section>
</x-ui.page>
