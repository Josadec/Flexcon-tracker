@php
    // Las sumas llegan en la propia consulta (withSum): aquí sólo se leen.
    $prodBueno = fn ($lot) => (int) ($lot->prod_good_sum ?? 0);
    $calAprobadas = fn ($lot) => (int) ($lot->qual_good_sum ?? 0);
    $calRechazadas = fn ($lot) => (int) ($lot->qual_bad_sum ?? 0);
    $calPendientes = fn ($lot) => max(0, $prodBueno($lot) - $calAprobadas($lot) - $calRechazadas($lot));
@endphp

<x-ui.page eyebrow="Calidad" title="Pesadas de Calidad"
    subtitle="Paso 5 del flujo: separar lo que Producción pesó en piezas aprobadas y rechazadas. Sólo las aprobadas llegan a Empaque.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.quality.index') }}">Panel de Calidad</x-ui.btn>
        <x-ui.btn variant="secondary" href="{{ route('admin.quality.inspection') }}">Inspección</x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    <x-ui.stats cols="4">
        <x-ui.stat label="Por verificar" :value="number_format($stats['pending'])"
            :tone="$stats['pending'] > 0 ? 'warn' : 'good'"
            help="Viajeros con piezas que Producción pesó y Calidad todavía no separa. Empaque no puede avanzar hasta entonces." />
        <x-ui.stat label="Verificados" :value="number_format($stats['completed'])" tone="good"
            help="Viajeros donde Calidad ya revisó todo lo que Producción registró." />
        <x-ui.stat label="Con rechazos" :value="number_format($stats['rejected'])"
            :tone="$stats['rejected'] > 0 ? 'bad' : 'neutral'"
            help="Viajeros con piezas descartadas." />
        <x-ui.stat label="Viajeros con pesadas" :value="number_format($stats['total'])" tone="info" />
    </x-ui.stats>

    {{-- Lo primero: lo que espera a Calidad --}}
    <x-ui.section title="Lo que te toca ahora"
        hint="Viajeros con piezas pesadas por Producción que siguen sin verificar.">
        <x-slot:aside>
            <x-ui.badge :tone="$stats['pending'] > 0 ? 'warn' : 'good'" dot>
                {{ number_format($stats['pending']) }}
                {{ \Illuminate\Support\Str::plural('pendiente', $stats['pending']) }}
            </x-ui.badge>
        </x-slot:aside>

        @if ($pendingLots->isNotEmpty())
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                @foreach ($pendingLots as $lot)
                    @php $po = $lot->workOrder?->purchaseOrder; @endphp
                    <div wire:key="pend-qual-{{ $lot->id }}"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-900 dark:text-white">Viajero {{ $lot->lot_number }}</span>
                                <x-ui.badge tone="warn">{{ number_format($calPendientes($lot)) }} pz por verificar</x-ui.badge>
                            </div>
                            <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">
                                WO {{ $po?->wo ?? $lot->workOrder?->wo_number ?? '—' }} · {{ $po?->part?->number ?? 'sin parte' }}
                            </p>
                        </div>
                        <x-ui.btn variant="warning" size="sm" wire:click="openDetailModal({{ $lot->id }})">
                            Verificar
                        </x-ui.btn>
                    </div>
                @endforeach
            </div>

            @if ($stats['pending'] > $pendingLots->count())
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Se muestran los {{ $pendingLots->count() }} más recientes de
                    {{ number_format($stats['pending']) }} pendientes.
                </p>
            @endif
        @else
            <x-ui.empty icon="box" title="Nada esperando a Calidad"
                hint="Cuando Producción registre pesadas de un viajero, aparecerá aquí para que las verifiques." />
        @endif
    </x-ui.section>

    {{-- Filtros --}}
    <x-ui.section title="Buscar viajeros" hint="Filtra por viajero, orden o parte, y acota por estado de verificación o tipo de flujo.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Viajero, WO o parte..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado de verificación">
                <select wire:model.live="filterQualityStatus" class="w-full">
                    <option value="">Todos los estados</option>
                    <option value="pending">Por verificar</option>
                    <option value="completed">Verificados</option>
                    <option value="rejected">Con rechazos</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Tipo de flujo">
                <select wire:model.live="filterType" class="w-full">
                    <option value="">Todos</option>
                    <option value="crimp">Con CRIMP</option>
                    <option value="standard">Sin CRIMP</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ($perPageOptions as $opcion)
                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search || $filterQualityStatus || $filterType)
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Tabla --}}
    <x-ui.table title="Viajeros con pesadas de producción"
        hint="«Por verificar» es lo que Producción pesó y Calidad todavía no separó en aprobado o rechazado.">
        <x-slot:head>
            <tr>
                <x-ui.th sort="lot_number" :field="$sortField" :direction="$sortDirection">Viajero</x-ui.th>
                <x-ui.th>Orden / parte</x-ui.th>
                <x-ui.th align="right" class="w-28" sort="quantity" :field="$sortField" :direction="$sortDirection">Cant.</x-ui.th>
                <x-ui.th align="right" class="w-32" sort="prod_good_sum" :field="$sortField" :direction="$sortDirection">Producción</x-ui.th>
                <x-ui.th align="right" class="w-32" sort="qual_good_sum" :field="$sortField" :direction="$sortDirection">Aprobadas</x-ui.th>
                <x-ui.th align="right" class="w-32" sort="qual_bad_sum" :field="$sortField" :direction="$sortDirection">Rechazadas</x-ui.th>
                <x-ui.th align="right" class="w-32">Por verificar</x-ui.th>
                <x-ui.th align="right" class="w-28">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($lots as $lot)
            @php
                $po = $lot->workOrder?->purchaseOrder;
                $pendientes = $calPendientes($lot);
                $rechazadas = $calRechazadas($lot);
            @endphp
            <tr wire:key="lot-row-{{ $lot->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</span>
                    <span class="mt-1 flex flex-wrap items-center gap-1.5">
                        <x-ui.badge :tone="$pendientes > 0 ? 'warn' : 'good'" dot>
                            {{ $pendientes > 0 ? 'Por verificar' : 'Verificado' }}
                        </x-ui.badge>
                        @if ($rechazadas > 0)
                            <x-ui.badge tone="bad">Descarte</x-ui.badge>
                        @endif
                    </span>
                </td>

                <td class="px-4 py-3">
                    <span class="block text-sm text-slate-700 dark:text-slate-200">WO {{ $po?->wo ?? '—' }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $po?->part?->number ?? 'sin parte' }}</span>
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">
                    {{ number_format($lot->quantity) }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">
                    {{ number_format($prodBueno($lot)) }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-semibold text-green-700 dark:text-green-300">
                    {{ number_format($calAprobadas($lot)) }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-semibold {{ $rechazadas > 0 ? 'text-red-700 dark:text-red-300' : 'text-slate-400 dark:text-slate-500' }}">
                    {{ number_format($rechazadas) }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-bold {{ $pendientes > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-green-700 dark:text-green-300' }}">
                    {{ number_format($pendientes) }}
                </td>

                <td class="px-4 py-3">
                    <x-ui.row-actions label="el viajero {{ $lot->lot_number }}" :show="route('admin.lots.show', $lot)">
                        <x-ui.icon-btn :tone="$pendientes > 0 ? 'success' : 'neutral'"
                            label="Ver el detalle de calidad del viajero {{ $lot->lot_number }}"
                            wire:click="openDetailModal({{ $lot->id }})">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </x-ui.icon-btn>
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    @if ($search || $filterQualityStatus || $filterType)
                        <x-ui.empty icon="search" title="Ningún viajero con esos filtros"
                            hint="Ajusta la búsqueda o límpiala para ver todos los viajeros." />
                    @else
                        <x-ui.empty icon="doc" title="No hay viajeros con pesadas de producción"
                            hint="Aparecerán aquí en cuanto Producción registre piezas." />
                    @endif
                </td>
            </tr>
        @endforelse

        @if ($lots->hasPages())
            <x-slot:foot>{{ $lots->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Detalle de calidad del viajero --}}
    @if ($showDetailModal && $this->selectedLot)
        @php
            $lotSel = $this->selectedLot;
            $poSel = $lotSel->workOrder?->purchaseOrder;
            $pendSel = $this->qualPending;
            $bloqueo = $lotSel->canBeQualityChecked() ? null : $lotSel->getProductionBlockedReason();
        @endphp

        <x-ui-modal wire:key="modal-quality-detail" badge="Paso 5" title="Verificación de calidad del viajero"
            subtitle="Compara lo que Producción pesó contra lo que ya verificaste. Lo aprobado es lo único que llega a Empaque."
            close="closeDetailModal" maxWidth="5xl">

            <x-slot:context>
                <x-ui-modal.ctx label="Viajero" :value="$lotSel->lot_number" />
                <x-ui-modal.ctx label="Orden" :value="$poSel?->wo ?? '—'" />
                <x-ui-modal.ctx label="Parte" :value="$poSel?->part?->number ?? '—'" />
                <x-ui-modal.ctx label="Cantidad" :value="number_format($lotSel->quantity).' pz'" />
            </x-slot:context>

            @if ($bloqueo)
                <x-ui.note tone="warn">{{ $bloqueo }}</x-ui.note>
            @endif

            <x-ui.section title="Cómo va el viajero" hint="Pendientes = lo bueno de Producción menos lo que ya verificaste.">
                <x-ui.stats cols="4">
                    <x-ui.stat label="Producción (buenas)" :value="number_format($this->prodGoodTotal)" unit="pz" tone="info" />
                    <x-ui.stat label="Aprobadas" :value="number_format($this->qualGoodTotal)" unit="pz" tone="good" />
                    <x-ui.stat label="Rechazadas" :value="number_format($this->qualBadTotal)" unit="pz"
                        :tone="$this->qualBadTotal > 0 ? 'bad' : 'neutral'" />
                    <x-ui.stat label="Por verificar" :value="number_format($pendSel)" unit="pz"
                        :tone="$pendSel > 0 ? 'warn' : 'good'" />
                </x-ui.stats>
            </x-ui.section>

            {{-- Pesadas de Producción --}}
            <x-ui.section title="Pesadas de Producción" :hint="count($this->productionWeighings).' registro(s). Es lo que hay disponible para verificar.'">
                @if (count($this->productionWeighings) > 0)
                    <x-ui.table>
                        <x-slot:head>
                            <tr>
                                <x-ui.th>Fecha</x-ui.th>
                                <x-ui.th align="right" class="w-32">Piezas buenas</x-ui.th>
                                <x-ui.th class="w-40">Pesó</x-ui.th>
                                <x-ui.th>Comentarios</x-ui.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($this->productionWeighings as $pw)
                            <tr wire:key="prod-w-{{ $pw['id'] }}">
                                <td class="px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200">{{ $pw['weighed_at'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ number_format($pw['good_pieces']) }}
                                </td>
                                <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">{{ $pw['weighed_by'] }}</td>
                                <td class="max-w-xs truncate px-4 py-2.5 text-xs text-slate-500 dark:text-slate-400"
                                    title="{{ $pw['comments'] }}">{{ $pw['comments'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                        <x-slot:foot>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-semibold text-slate-700 dark:text-slate-200">Total pesado por Producción</span>
                                <span class="tabular-nums font-bold text-slate-900 dark:text-white">{{ number_format($this->prodGoodTotal) }} pz</span>
                            </div>
                        </x-slot:foot>
                    </x-ui.table>
                @else
                    <x-ui.empty icon="box" title="Sin pesadas de producción"
                        hint="Producción todavía no registra piezas de este viajero." />
                @endif
            </x-ui.section>

            {{-- Pesadas de Calidad --}}
            <x-ui.section title="Pesadas de Calidad" :hint="count($this->qualityWeighings).' registro(s). Cada uno separa piezas aprobadas de rechazadas.'">
                <x-slot:aside>
                    @if ($pendSel > 0 && ! $bloqueo)
                        <x-ui.btn variant="primary" size="sm" wire:click="openWeighingModal">Nueva pesada</x-ui.btn>
                    @endif
                </x-slot:aside>

                @if (count($this->qualityWeighings) > 0)
                    <x-ui.table>
                        <x-slot:head>
                            <tr>
                                <x-ui.th>Fecha</x-ui.th>
                                <x-ui.th align="right" class="w-28">Aprobadas</x-ui.th>
                                <x-ui.th align="right" class="w-28">Rechazadas</x-ui.th>
                                <x-ui.th class="w-40">Verificó</x-ui.th>
                                <x-ui.th align="right" class="w-28">Acciones</x-ui.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($this->qualityWeighings as $qw)
                            <tr wire:key="qual-w-{{ $qw['id'] }}">
                                <td class="px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200">
                                    {{ $qw['weighed_at'] }}
                                    @if ($qw['comments'])
                                        <span class="block max-w-xs truncate text-xs text-slate-500 dark:text-slate-400"
                                            title="{{ $qw['comments'] }}">{{ $qw['comments'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-sm font-semibold text-green-700 dark:text-green-300">
                                    {{ number_format($qw['good_pieces']) }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="tabular-nums text-sm font-semibold {{ $qw['bad_pieces'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-slate-400 dark:text-slate-500' }}">
                                        {{ number_format($qw['bad_pieces']) }}
                                    </span>
                                    @if ($qw['bad_pieces'] > 0)
                                        <span class="block text-[11px] leading-4 text-red-600 dark:text-red-400">descarte</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">{{ $qw['weighed_by'] }}</td>
                                <td class="px-4 py-2.5">
                                    <x-ui.row-actions label="esta pesada de calidad"
                                        :delete="'deleteQualityWeighing('.$qw['id'].')'"
                                        deleteConfirm="¿Eliminar esta pesada de calidad? Las piezas volverán a quedar pendientes de verificar.">
                                        <x-ui.icon-btn tone="primary" label="Editar esta pesada de calidad"
                                            wire:click="editQualityWeighing({{ $qw['id'] }})">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </x-ui.icon-btn>
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                        <x-slot:foot>
                            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                                <span class="font-semibold text-slate-700 dark:text-slate-200">Total verificado</span>
                                <span class="tabular-nums">
                                    <span class="font-bold text-green-700 dark:text-green-300">{{ number_format($this->qualGoodTotal) }} aprobadas</span>
                                    <span class="mx-2 text-slate-300 dark:text-slate-600">·</span>
                                    <span class="font-bold text-red-700 dark:text-red-300">{{ number_format($this->qualBadTotal) }} rechazadas</span>
                                </span>
                            </div>
                        </x-slot:foot>
                    </x-ui.table>
                @else
                    <x-ui.empty icon="doc" title="Sin pesadas de calidad todavía"
                        hint="Registra una pesada para separar las piezas de Producción en aprobadas y rechazadas.">
                        @if ($pendSel > 0 && ! $bloqueo)
                            <x-slot:action>
                                <x-ui.btn variant="primary" wire:click="openWeighingModal">Registrar la primera</x-ui.btn>
                            </x-slot:action>
                        @endif
                    </x-ui.empty>
                @endif
            </x-ui.section>

            <x-slot:note>
                @if ($pendSel > 0)
                    Quedan {{ number_format($pendSel) }} piezas por verificar: Empaque no puede tomarlas hasta que las apruebes.
                @else
                    Todo lo que Producción pesó ya está verificado.
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeDetailModal">Cerrar</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Alta / edición de pesada de calidad --}}
    @if ($showWeighingModal && $this->selectedLot)
        @php
            $disponible = $this->qualRemainingPieces;
            $capturado = $qualGoodPieces + $qualBadPieces;
            $sinAsignar = max(0, $disponible - $capturado);
        @endphp

        <x-ui-modal wire:key="modal-quality-weighing" style="z-index:60"
            :title="$editingQualityWeighingId ? 'Editar pesada de calidad' : 'Nueva pesada de calidad'"
            :subtitle="'Viajero '.$this->selectedLot->lot_number.' — reparte las piezas de Producción entre aprobadas y rechazadas.'"
            close="closeWeighingModal" maxWidth="2xl">

            <x-ui.section step="1" title="¿Cómo salieron las piezas?" tone="accent"
                hint="La suma de aprobadas y rechazadas no puede pasar de las piezas disponibles.">

                <x-ui.stats cols="2" class="mb-4">
                    <x-ui.stat label="Disponibles para esta pesada" :value="number_format($disponible)" unit="pz" tone="info"
                        help="Piezas buenas de Producción que todavía no has verificado." />
                    <x-ui.stat label="Quedarían sin verificar" :value="number_format($sinAsignar)" unit="pz"
                        :tone="$sinAsignar > 0 ? 'warn' : 'good'" />
                </x-ui.stats>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Piezas aprobadas" required
                        hint="Las que pasan a Empaque."
                        :error="$errors->first('qualGoodPieces')">
                        <input type="number" min="0" step="1" wire:model.live.debounce.400ms="qualGoodPieces"
                            class="w-full text-right tabular-nums">
                    </x-ui.field>

                    <x-ui.field label="Piezas rechazadas" required
                        hint="Se descartan: no vuelven al flujo."
                        :error="$errors->first('qualBadPieces')">
                        <input type="number" min="0" step="1" wire:model.live.debounce.400ms="qualBadPieces"
                            class="w-full text-right tabular-nums">
                    </x-ui.field>
                </div>

                @if ($qualBadPieces > 0)
                    <x-ui.note tone="danger" class="mt-4">
                        {{ number_format($qualBadPieces) }} piezas quedarán marcadas como <strong>descarte</strong>.
                        No regresan al flujo ni cuentan para Empaque.
                    </x-ui.note>
                @endif
            </x-ui.section>

            <x-ui.section step="2" title="Cuándo y con qué observación">
                <x-ui.field label="Fecha y hora de la pesada" required hint="No puede estar en el futuro."
                    :error="$errors->first('qualWeighedAt')">
                    <input type="datetime-local" wire:model="qualWeighedAt" class="w-full">
                </x-ui.field>

                <x-ui.field label="Comentarios" optional class="mt-4" :error="$errors->first('qualComments')">
                    <textarea wire:model="qualComments" rows="2" class="w-full"
                        placeholder="Observaciones de la verificación..."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>
                Las piezas aprobadas son las únicas que Empaque podrá empacar de este viajero.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeWeighingModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveQualityWeighing">
                    {{ $editingQualityWeighingId ? 'Guardar cambios' : 'Registrar pesada' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
