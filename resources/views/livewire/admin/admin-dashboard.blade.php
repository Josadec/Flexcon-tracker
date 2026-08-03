{{--
    TABLERO DE CONTROL

    Orden de lectura, de arriba abajo:
      1. ¿Hay algo urgente?          → alertas (entregas vencidas, POs por aprobar)
      2. ¿Cómo va la operación?      → cifras del ciclo de producción
      3. ¿Quién tiene que mover qué? → pendientes por área, con liga a su pantalla
      4. ¿Dónde está el trabajo?     → flujo de 8 pasos y listas por departamento
      5. Contexto y atajos           → actividad reciente y catálogo

    Los conteos de pendientes salen del mismo cálculo que la Lista de envío,
    así que los dos tableros nunca se contradicen.
--}}
<x-ui.page eyebrow="Control de producción" title="Tablero"
    :subtitle="ucfirst(now()->translatedFormat('l, d \d\e F \d\e Y')).' · el resumen de hoy en piso.'">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.sent-lists.index') }}">Listas de envío</x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.sent-lists.display') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
            Abrir tablero de piso
        </x-ui.btn>
    </x-slot:actions>

    {{-- ══ 1. Lo que requiere atención ══════════════════════════════════ --}}
    @if ($overdueCount > 0 || $poPending > 0 || $poCorrection > 0)
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
            @if ($overdueCount > 0)
                <a href="{{ route('admin.work-orders.index') }}" wire:navigate
                    class="group flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 transition-colors hover:border-red-400 hover:bg-red-100 dark:border-red-800 dark:bg-red-950/40 dark:hover:bg-red-950/70">
                    <svg class="mt-0.5 size-5 shrink-0 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-red-900 dark:text-red-100">
                            {{ $overdueCount }} {{ Str::plural('orden', $overdueCount) }} con entrega vencida
                        </span>
                        <span class="mt-0.5 block text-xs leading-4 text-red-700 dark:text-red-300">
                            Pasó la fecha programada y todavía no se envían.
                        </span>
                    </span>
                </a>
            @endif

            @if ($poPending > 0)
                <a href="{{ route('admin.purchase-orders.index') }}" wire:navigate
                    class="group flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 transition-colors hover:border-amber-400 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:hover:bg-amber-950/70">
                    <svg class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-amber-900 dark:text-amber-100">
                            {{ $poPending }} {{ Str::plural('orden', $poPending) }} de compra por aprobar
                        </span>
                        <span class="mt-0.5 block text-xs leading-4 text-amber-700 dark:text-amber-300">
                            Hasta aprobarlas no se genera su Work Order.
                        </span>
                    </span>
                </a>
            @endif

            @if ($poCorrection > 0)
                <a href="{{ route('admin.purchase-orders.index') }}" wire:navigate
                    class="group flex items-start gap-3 rounded-lg border border-orange-300 bg-orange-50 px-4 py-3 transition-colors hover:border-orange-400 hover:bg-orange-100 dark:border-orange-800 dark:bg-orange-950/40 dark:hover:bg-orange-950/70">
                    <svg class="mt-0.5 size-5 shrink-0 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-orange-900 dark:text-orange-100">
                            {{ $poCorrection }} {{ Str::plural('orden', $poCorrection) }} en corrección
                        </span>
                        <span class="mt-0.5 block text-xs leading-4 text-orange-700 dark:text-orange-300">
                            Compras tiene que atender una observación.
                        </span>
                    </span>
                </a>
            @endif
        </div>
    @else
        <x-ui.note tone="success" title="Nada urgente en este momento">
            No hay entregas vencidas ni órdenes de compra esperando aprobación.
        </x-ui.note>
    @endif

    {{-- ══ 2. Cómo va la operación ══════════════════════════════════════ --}}
    <x-ui.section title="Estado de la operación"
        hint="Lotes y viajeros vivos en el sistema, y el trabajo que traen encima.">
        <x-ui.stats cols="4">
            <x-ui.stat label="Lotes en el sistema" :value="number_format($lotsTotal)"
                help="Cada lote o viajero recorre las 8 etapas del flujo." />
            <x-ui.stat label="Ciclos terminados" :value="number_format($lotsDone)" tone="good"
                help="Empacados y con sus sobrantes ya recibidos por Materiales." />
            <x-ui.stat label="Acciones pendientes" :value="number_format($totalPending)"
                :tone="$totalPending > 0 ? 'warn' : 'good'"
                help="Suma de todo lo que alguna área tiene que registrar." />
            <x-ui.stat label="Piezas por pesar" :value="number_format($piecesPending)"
                :tone="$piecesPending > 0 ? 'info' : 'good'"
                help="Piezas que Producción todavía no registra." />
        </x-ui.stats>
    </x-ui.section>

    {{-- ══ 3. Quién tiene que mover qué ═════════════════════════════════ --}}
    @php
        // Las fases, su verbo, su área y su ruta viven en PendingActions:
        // así la tabla no se puede desfasar del cálculo.
        $pendingRows = \App\Support\PendingActions::PHASES;

        $areaTones = [
            'Materiales' => ['dot' => 'bg-sky-500',     'num' => 'text-sky-700 dark:text-sky-300'],
            'Calidad'    => ['dot' => 'bg-teal-500',    'num' => 'text-teal-700 dark:text-teal-300'],
            'Producción' => ['dot' => 'bg-indigo-500',  'num' => 'text-indigo-700 dark:text-indigo-300'],
            'Empaque'    => ['dot' => 'bg-orange-500',  'num' => 'text-orange-700 dark:text-orange-300'],
        ];
    @endphp

    <x-ui.section title="Trabajo pendiente por área"
        hint="Cada renglón es una acción que alguien tiene que registrar para que el lote avance.">
        <x-slot:aside>
            <x-ui.btn variant="secondary" size="sm" href="{{ route('admin.sent-lists.display') }}">
                Ver en el tablero de piso
            </x-ui.btn>
        </x-slot:aside>

        @if ($totalPending === 0)
            <x-ui.note tone="success" title="Todo al día">
                Ningún lote está esperando que un área registre algo.
            </x-ui.note>
        @else
            {{-- Carga por área --}}
            <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($byArea as $area => $count)
                    @php $pct = $totalPending > 0 ? round(($count / $totalPending) * 100) : 0; @endphp
                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 shrink-0 rounded-full {{ $areaTones[$area]['dot'] }}" aria-hidden="true"></span>
                            <span class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $area }}</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span class="text-2xl font-bold tabular-nums {{ $areaTones[$area]['num'] }}">{{ $count }}</span>
                            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $pct }}% del total</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                            <div class="h-full rounded-full {{ $areaTones[$area]['dot'] }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Detalle accionable --}}
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <x-ui.th class="w-20" align="right">Lotes</x-ui.th>
                        <x-ui.th>Acción pendiente</x-ui.th>
                        <x-ui.th class="w-40">Responsable</x-ui.th>
                        <x-ui.th class="w-32" align="right">Ir</x-ui.th>
                    </tr>
                </x-slot:head>

                @foreach ($pendingRows as $key => $meta)
                    @continue(($pending[$key] ?? 0) === 0)
                    @php $area = $meta['actor']; @endphp
                    <tr wire:key="pend-{{ $key }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3 text-right text-lg font-bold tabular-nums {{ $areaTones[$area]['num'] }}">
                            {{ $pending[$key] }}
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $meta['action'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <span class="size-2 shrink-0 rounded-full {{ $areaTones[$area]['dot'] }}" aria-hidden="true"></span>
                                {{ $area }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($meta['route'] && Route::has($meta['route']))
                                <x-ui.btn variant="secondary" size="sm" href="{{ route($meta['route']) }}">Abrir</x-ui.btn>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.section>

    {{-- ══ 4. Dónde está el trabajo ═════════════════════════════════════ --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">

        {{-- Listas de envío por departamento --}}
        <x-ui.section title="Listas de envío en curso"
            hint="En qué departamento está parada cada lista pendiente.">
            <x-slot:aside>
                <x-ui.btn variant="ghost" size="sm" href="{{ route('admin.sent-lists.index') }}">Ver todas</x-ui.btn>
            </x-slot:aside>

            @php $pipelineTotal = collect($pipeline)->sum('count'); @endphp

            @if ($pipelineTotal === 0)
                <x-ui.empty icon="doc" title="Sin listas pendientes"
                    hint="Todas las listas de envío están confirmadas o cerradas." />
            @else
                <ul class="space-y-2">
                    @foreach ($pipeline as $dept => $info)
                        @php
                            $pct = $pipelineTotal > 0 ? round(($info['count'] / $pipelineTotal) * 100) : 0;
                            $bar = ['sky' => 'bg-sky-500', 'emerald' => 'bg-emerald-500', 'indigo' => 'bg-indigo-500', 'teal' => 'bg-teal-500', 'orange' => 'bg-orange-500'][$info['tone']];
                        @endphp
                        <li class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $info['label'] }}</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                <span class="block h-full rounded-full {{ $info['count'] > 0 ? $bar : '' }}" style="width: {{ $pct }}%"></span>
                            </span>
                            <span class="w-8 shrink-0 text-right text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ $info['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.section>

        {{-- Entregas --}}
        <x-ui.section title="Entregas" hint="Órdenes programadas que ya se pasaron de fecha o están por vencer.">
            <x-ui.stats cols="2" class="mb-4">
                <x-ui.stat label="Vencidas" :value="$overdueCount" :tone="$overdueCount > 0 ? 'bad' : 'good'" />
                <x-ui.stat label="Vencen en 7 días" :value="$dueSoonCount" :tone="$dueSoonCount > 0 ? 'warn' : 'good'" />
            </x-ui.stats>

            @if ($overdueWOs->isEmpty())
                <x-ui.note tone="success">Ninguna orden programada está vencida.</x-ui.note>
            @else
                <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    @foreach ($overdueWOs as $wo)
                        @php $daysLate = (int) now()->startOfDay()->diffInDays($wo->scheduled_send_date, false) * -1; @endphp
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $wo->purchaseOrder->wo ?? $wo->wo_number }}
                                    <span class="font-normal text-slate-500 dark:text-slate-400">· {{ $wo->purchaseOrder->part->number ?? '—' }}</span>
                                </span>
                                <span class="block text-xs text-slate-400 dark:text-slate-500">
                                    Programada {{ $wo->scheduled_send_date->format('d/m/Y') }}
                                </span>
                            </span>
                            <x-ui.badge tone="bad">{{ $daysLate }} {{ Str::plural('día', $daysLate) }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.section>
    </div>

    {{-- ══ 5. Actividad reciente ════════════════════════════════════════ --}}
    <x-ui.table title="Últimas órdenes abiertas" hint="Las 6 Work Orders más recientes.">
        <x-slot:aside>
            <x-ui.btn variant="ghost" size="sm" href="{{ route('admin.work-orders.index') }}">Ver todas</x-ui.btn>
        </x-slot:aside>

        <x-slot:head>
            <tr>
                <x-ui.th class="w-32">WO</x-ui.th>
                <x-ui.th class="w-40">Parte</x-ui.th>
                <x-ui.th>Descripción</x-ui.th>
                <x-ui.th class="w-28" align="right">Cantidad</x-ui.th>
                <x-ui.th class="w-32">Estado</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($recentWorkOrders as $wo)
            @php
                $statusTone = match (optional($wo->status)->name ?? '') {
                    'Abierto'    => 'info',
                    'En Proceso' => 'warn',
                    'Completado' => 'good',
                    'Cancelado'  => 'bad',
                    default      => 'neutral',
                };
            @endphp
            <tr wire:key="recent-wo-{{ $wo->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-sky-700 dark:text-sky-300">
                    {{ $wo->purchaseOrder->wo ?? $wo->wo_number }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900 dark:text-white">
                    {{ $wo->purchaseOrder->part->number ?? '—' }}
                    @if ($wo->purchaseOrder->part->is_crimp ?? false)
                        <x-ui.badge tone="accent" class="ml-1">CRIMP</x-ui.badge>
                    @endif
                </td>
                <td class="max-w-xs truncate px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $wo->purchaseOrder->part->description ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-900 dark:text-white">
                    {{ number_format($wo->purchaseOrder->quantity ?? 0) }}
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$statusTone" dot>{{ optional($wo->status)->name ?? 'Sin estado' }}</x-ui.badge>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-ui.empty title="No hay Work Orders todavía"
                        hint="Se generan al aprobar una orden de compra.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.purchase-orders.create') }}">Nueva orden de compra</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    {{-- ══ 6. Catálogo y accesos ════════════════════════════════════════ --}}
    <x-ui.section title="Catálogo y accesos"
        hint="Totales de referencia y las pantallas que más se usan.">

        <x-ui.stats cols="5" class="mb-5">
            <x-ui.stat label="Work Orders" :value="number_format($totalWO)" />
            <x-ui.stat label="Órdenes de compra" :value="number_format($totalPO)" />
            <x-ui.stat label="Partes" :value="number_format($totalParts)" />
            <x-ui.stat label="Viajeros CRIMP" :value="number_format($crimpViajeros)" tone="accent" />
            <x-ui.stat label="Lotes de CRIMP" :value="number_format($crimpLots)" tone="accent" />
        </x-ui.stats>

        @php
            $shortcuts = [
                ['admin.sent-lists.display', 'Lista de envío', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
                ['admin.materials.index',    'Materiales',     'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                ['admin.production.index',   'Producción',     'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
                ['admin.quality.index',      'Calidad',        'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ['admin.packaging.index',    'Empaque',        'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                ['admin.parts.index',        'Partes',         'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                ['admin.prices.index',       'Precios',        'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['admin.standards.index',    'Estándares',     'M13 10V3L4 14h7v7l9-11h-7z'],
            ];
        @endphp

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
            @foreach ($shortcuts as [$route, $label, $icon])
                @continue(! Route::has($route))
                <a href="{{ route($route) }}" wire:navigate
                    class="flex flex-col items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-4 text-center transition-colors hover:border-sky-500 hover:bg-sky-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-sky-600 dark:hover:bg-sky-950/40">
                    <svg class="size-6 text-slate-400 transition-colors group-hover:text-sky-600 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}"/>
                    </svg>
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </x-ui.section>
</x-ui.page>
