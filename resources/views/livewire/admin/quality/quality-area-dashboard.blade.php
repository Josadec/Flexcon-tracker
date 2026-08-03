{{--
    TABLERO DE CALIDAD

    Calidad interviene dos veces y son trabajos distintos:
      1. Inspección   — aprueba el lote ANTES de que Producción lo trabaje.
      2. Verificación — pesa y aprueba las piezas DESPUÉS de producirlas.
    Por eso van en dos colas separadas y no en una sola lista revuelta.
--}}
<x-ui.page eyebrow="Área · Calidad" title="Calidad"
    subtitle="Inspección de lotes antes de producir y verificación de piezas después de producir.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.sent-lists.index') }}">Listas de envío</x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.sent-lists.display') }}">Abrir tablero de piso</x-ui.btn>
    </x-slot:actions>

    {{-- Carga del área --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Pendientes de Calidad" :value="$totalPending"
            :tone="$totalPending > 0 ? 'warn' : 'good'"
            help="Inspecciones + verificaciones por registrar." />
        <x-ui.stat label="Por inspeccionar" :value="$inspectionQueue->count()"
            :tone="$inspectionQueue->count() > 0 ? 'warn' : 'good'"
            help="Bloquean el arranque de Producción." />
        <x-ui.stat label="Por verificar" :value="$verifyQueue->count()"
            :tone="$verifyQueue->count() > 0 ? 'info' : 'good'"
            :help="$piecesPending > 0 ? number_format($piecesPending).' piezas por revisar' : 'Sin piezas pendientes'" />
        <x-ui.stat label="Tasa de rechazo" :value="$rejectRate.'%'"
            :tone="$rejectRate > 5 ? 'bad' : ($rejectRate > 0 ? 'warn' : 'good')"
            help="Piezas rechazadas sobre el total verificado, histórico." />
    </x-ui.stats>

    {{-- Lotes detenidos por inspección rechazada --}}
    @if ($rejectedLots->isNotEmpty())
        <x-ui.section title="Lotes detenidos por inspección rechazada"
            hint="No avanzan hasta que se corrija lo señalado y se vuelva a inspeccionar.">
            <ul class="divide-y divide-slate-200 rounded-lg border border-red-300 dark:divide-slate-700 dark:border-red-800">
                @foreach ($rejectedLots as $lot)
                    <li class="flex items-center justify-between gap-3 bg-red-50 px-4 py-2.5 dark:bg-red-950/30">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-red-900 dark:text-red-100">
                                {{ $lot->lot_number }}
                                <span class="font-normal text-red-700 dark:text-red-300">
                                    · {{ $lot->workOrder->purchaseOrder->part->number ?? '—' }}
                                </span>
                            </span>
                            <span class="block truncate text-xs text-red-700 dark:text-red-300">
                                {{ $lot->inspection_comments ?: 'Sin motivo capturado' }}
                            </span>
                        </span>
                        <x-ui.btn variant="danger" size="sm"
                            href="{{ route('admin.sent-lists.display.wo', $lot->work_order_id) }}">Revisar</x-ui.btn>
                    </li>
                @endforeach
            </ul>
        </x-ui.section>
    @endif

    {{-- Las dos colas, separadas --}}
    <x-ui.section step="1" title="Cola de inspección"
        hint="Lotes con material liberado esperando el visto bueno de Calidad. Producción no puede empezar sin esto.">
        <x-ui.pending-table :items="$inspectionQueue"
            emptyTitle="Nada por inspeccionar"
            emptyHint="Todos los lotes con material liberado ya fueron inspeccionados." />
    </x-ui.section>

    <x-ui.section step="2" title="Cola de verificación"
        hint="Lotes con piezas ya producidas esperando que Calidad las pese y apruebe.">
        <x-ui.pending-table :items="$verifyQueue"
            emptyTitle="Nada por verificar"
            emptyHint="Todas las piezas que Producción registró ya fueron verificadas." />
    </x-ui.section>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        {{-- Resultados --}}
        <x-ui.section title="Resultados de Calidad" hint="Acumulado histórico de inspecciones y piezas.">
            <x-ui.stats cols="2">
                <x-ui.stat label="Lotes aprobados" :value="number_format($approved)" tone="good" />
                <x-ui.stat label="Lotes rechazados" :value="number_format($rejected)"
                    :tone="$rejected > 0 ? 'bad' : 'good'" />
                <x-ui.stat label="Piezas aprobadas" :value="number_format($goodPieces)" tone="good" />
                <x-ui.stat label="Piezas rechazadas" :value="number_format($badPieces)"
                    :tone="$badPieces > 0 ? 'bad' : 'good'"
                    help="Las piezas rechazadas se descartan: no regresan al lote." />
            </x-ui.stats>

            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/40">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Actividad de hoy</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                    <strong class="text-green-700 dark:text-green-400">{{ number_format($todayGood) }}</strong> piezas aprobadas ·
                    <strong class="{{ $todayBad > 0 ? 'text-red-700 dark:text-red-400' : 'text-slate-500' }}">{{ number_format($todayBad) }}</strong> rechazadas
                </p>
            </div>
        </x-ui.section>

        {{-- Listas paradas aquí --}}
        <x-ui.section title="Listas de envío en Calidad"
            hint="Listas pendientes cuyo departamento actual es Inspección o Calidad.">
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
    </div>

    {{-- Últimas verificaciones --}}
    <x-ui.table title="Últimas verificaciones" hint="Los 8 registros más recientes.">
        <x-slot:head>
            <tr>
                <x-ui.th class="w-36">Lote</x-ui.th>
                <x-ui.th class="w-32">Parte</x-ui.th>
                <x-ui.th class="w-28" align="right">Aprobadas</x-ui.th>
                <x-ui.th class="w-28" align="right">Rechazadas</x-ui.th>
                <x-ui.th>Registró</x-ui.th>
                <x-ui.th class="w-40">Fecha</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($recentWeighings as $qw)
            <tr wire:key="qw-{{ $qw->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">
                    {{ $qw->lot->lot_number ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $qw->lot->workOrder->purchaseOrder->part->number ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-green-700 dark:text-green-400">
                    {{ number_format($qw->good_pieces) }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums {{ $qw->bad_pieces > 0 ? 'font-semibold text-red-700 dark:text-red-400' : 'text-slate-400' }}">
                    {{ number_format($qw->bad_pieces) }}
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $qw->weighedBy->name ?? '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">
                    {{ $qw->created_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty title="Sin verificaciones registradas"
                        hint="Se registran desde el tablero de piso, en la columna Cal." />
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-ui.page>
