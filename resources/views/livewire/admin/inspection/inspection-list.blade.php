@php
    $tonoInspeccion = fn ($estado) => match ($estado) {
        'approved' => 'good',
        'rejected' => 'bad',
        default => 'warn',
    };
@endphp

<x-ui.page eyebrow="Calidad" title="Inspección de viajeros"
    subtitle="Paso 4 del flujo: revisar lo que Materiales liberó y decidir si entra a Producción o se detiene.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.quality.index') }}">Panel de Calidad</x-ui.btn>
        <x-ui.btn variant="secondary" href="{{ route('admin.quality.weighings') }}">Pesadas de Calidad</x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    <x-ui.stats cols="4">
        <x-ui.stat label="Por inspeccionar" :value="number_format($stats['por_inspeccionar'])"
            :tone="$stats['por_inspeccionar'] > 0 ? 'warn' : 'good'"
            help="Viajeros con material liberado y sin decisión. Producción no puede pesarlos hasta que decidas." />
        <x-ui.stat label="Aprobados" :value="number_format($stats['aprobados'])" tone="good" />
        <x-ui.stat label="Rechazados" :value="number_format($stats['rechazados'])"
            :tone="$stats['rechazados'] > 0 ? 'bad' : 'neutral'"
            help="Viajeros detenidos: siguen frenados hasta que Materiales corrija y vuelvas a revisarlos." />
        <x-ui.stat label="Esperando material" :value="number_format($stats['esperando_material'])" tone="info"
            help="Todavía en Materiales. Aparecerán aquí en cuanto liberen su material." />
    </x-ui.stats>

    {{-- Lo primero: lo que espera una decisión --}}
    <x-ui.section title="Lo que te toca ahora"
        hint="Viajeros liberados por Materiales que siguen sin decisión de inspección.">
        <x-slot:aside>
            <x-ui.badge :tone="$stats['por_inspeccionar'] > 0 ? 'warn' : 'good'" dot>
                {{ number_format($stats['por_inspeccionar']) }}
                {{ \Illuminate\Support\Str::plural('pendiente', $stats['por_inspeccionar']) }}
            </x-ui.badge>
        </x-slot:aside>

        @if ($pendingLots->isNotEmpty())
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                @foreach ($pendingLots as $lot)
                    @php
                        $po = $lot->workOrder?->purchaseOrder;
                        $esCrimp = (bool) ($po?->part?->is_crimp ?? false);
                    @endphp
                    <div wire:key="pend-insp-{{ $lot->id }}"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-900 dark:text-white">Viajero {{ $lot->lot_number }}</span>
                                <x-ui.badge :tone="$esCrimp ? 'accent' : 'neutral'">{{ $esCrimp ? 'CRIMP' : 'Sin CRIMP' }}</x-ui.badge>
                            </div>
                            <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">
                                WO {{ $po?->wo ?? $lot->workOrder?->wo_number ?? '—' }} ·
                                {{ $po?->part?->number ?? 'sin parte' }} ·
                                {{ number_format($lot->quantity) }} pz
                            </p>
                        </div>
                        <x-ui.btn variant="warning" size="sm" wire:click="openInspectionModal({{ $lot->id }})">
                            Inspeccionar
                        </x-ui.btn>
                    </div>
                @endforeach
            </div>

            @if ($stats['por_inspeccionar'] > $pendingLots->count())
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Se muestran los {{ $pendingLots->count() }} más recientes de
                    {{ number_format($stats['por_inspeccionar']) }} pendientes.
                </p>
            @endif
        @else
            <x-ui.empty icon="box" title="Nada esperando inspección"
                hint="Cuando Materiales libere el material de un viajero, aparecerá aquí para que lo decidas." />
        @endif
    </x-ui.section>

    {{-- Filtros --}}
    <x-ui.section title="Buscar viajeros" hint="Filtra por viajero, orden, parte o lote de CRIMP, y acota por estado o tipo de flujo.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Viajero, WO, parte, lote de CRIMP..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado de inspección">
                <select wire:model.live="filterInspectionStatus" class="w-full">
                    <option value="">Todos los estados</option>
                    @foreach ($inspectionStatuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Tipo de flujo" hint="CRIMP lleva ocho pasos y lotes de CRIMP.">
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

        @if ($search || $filterInspectionStatus || $filterType)
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Tabla --}}
    <x-ui.table title="Viajeros" hint="Los liberados esperan decisión; los ya inspeccionados quedan como historial y se pueden corregir.">
        <x-slot:head>
            <tr>
                <x-ui.th sort="lot_number" :field="$sortField" :direction="$sortDirection">Viajero</x-ui.th>
                <x-ui.th>Orden / parte</x-ui.th>
                <x-ui.th>Lotes de CRIMP</x-ui.th>
                <x-ui.th align="right" class="w-28" sort="quantity" :field="$sortField" :direction="$sortDirection">Cantidad</x-ui.th>
                <x-ui.th class="w-44" sort="inspection_status" :field="$sortField" :direction="$sortDirection">Inspección</x-ui.th>
                <x-ui.th align="right" class="w-28">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($lots as $lot)
            @php
                $po = $lot->workOrder?->purchaseOrder;
                $estado = $lot->inspection_status ?? 'pending';
                $puede = $lot->canBeInspected();
            @endphp
            <tr wire:key="lot-{{ $lot->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        {{ ($po?->part?->is_crimp ?? false) ? 'CRIMP' : 'Sin CRIMP' }}
                    </span>
                </td>

                <td class="px-4 py-3">
                    <span class="block text-sm text-slate-700 dark:text-slate-200">WO {{ $po?->wo ?? '—' }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $po?->part?->number ?? 'sin parte' }}</span>
                </td>

                <td class="px-4 py-3">
                    @if ($lot->crimpLots->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($lot->crimpLots as $cl)
                                <span class="inline-flex items-center rounded-md bg-cyan-50 px-2 py-1 font-mono text-xs font-medium text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-200"
                                    title="{{ number_format($cl->quantity ?? 0) }} pz">{{ $cl->crimp_lot_number }}</span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                    @endif
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">
                    {{ number_format($lot->quantity) }}
                </td>

                <td class="px-4 py-3">
                    <x-ui.badge :tone="$tonoInspeccion($estado)" dot>{{ $lot->inspection_status_label }}</x-ui.badge>
                    @if ($lot->inspection_completed_at)
                        <span class="mt-1 block text-[11px] leading-4 text-slate-500 dark:text-slate-400">
                            {{ $lot->inspector?->name ?? 'Sin nombre' }} · {{ $lot->inspection_completed_at->format('d/m/Y H:i') }}
                        </span>
                    @endif
                    @if ($estado === 'rejected' && $lot->inspection_comments)
                        <span class="mt-0.5 block max-w-xs truncate text-[11px] leading-4 text-red-600 dark:text-red-400"
                            title="{{ $lot->inspection_comments }}">{{ $lot->inspection_comments }}</span>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <x-ui.row-actions label="el viajero {{ $lot->lot_number }}" :show="route('admin.lots.show', $lot)">
                        @if ($puede)
                            <x-ui.icon-btn :tone="$estado === 'pending' ? 'success' : 'neutral'"
                                :label="($estado === 'pending' ? 'Inspeccionar' : 'Revisar la decisión de').' el viajero '.$lot->lot_number"
                                wire:click="openInspectionModal({{ $lot->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </x-ui.icon-btn>
                        @else
                            <span class="ui-icon-btn cursor-not-allowed bg-slate-100 text-slate-300 dark:bg-slate-700 dark:text-slate-500"
                                title="{{ $lot->getInspectionBlockedReason() }}" aria-label="{{ $lot->getInspectionBlockedReason() }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                        @endif
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    @if ($search || $filterInspectionStatus || $filterType)
                        <x-ui.empty icon="search" title="Ningún viajero con esos filtros"
                            hint="Ajusta la búsqueda o límpiala para ver todos los viajeros." />
                    @else
                        <x-ui.empty icon="doc" title="No hay viajeros para inspeccionar"
                            hint="Aparecerán aquí en cuanto Materiales libere su material." />
                    @endif
                </td>
            </tr>
        @endforelse

        @if ($lots->hasPages())
            <x-slot:foot>{{ $lots->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Decisión de inspección --}}
    @if ($showInspectionModal && $this->selectedLot)
        @php
            $lotSel = $this->selectedLot;
            $poSel = $lotSel->workOrder?->purchaseOrder;
            $yaDecidido = in_array($lotSel->inspection_status, ['approved', 'rejected'], true);
            $yaProducido = $lotSel->getProductionTotalWeighed();
        @endphp

        <x-ui-modal wire:key="modal-inspeccion-{{ $lotSel->id }}" badge="Paso 4"
            :title="$yaDecidido ? 'Revisar la inspección' : 'Inspección del viajero'"
            subtitle="Tu decisión abre o cierra el paso siguiente: Producción no puede pesar sin inspección aprobada."
            close="closeInspectionModal" maxWidth="3xl">

            <x-slot:context>
                <x-ui-modal.ctx label="Viajero" :value="$lotSel->lot_number" />
                <x-ui-modal.ctx label="Orden" :value="$poSel?->wo ?? '—'" />
                <x-ui-modal.ctx label="Parte" :value="$poSel?->part?->number ?? '—'" />
                <x-ui-modal.ctx label="Cantidad" :value="number_format($lotSel->quantity).' pz'" />
            </x-slot:context>

            @if ($yaDecidido)
                <x-ui.note tone="info">
                    Este viajero ya está <strong>{{ $lotSel->inspection_status_label }}</strong>
                    @if ($lotSel->inspection_completed_at)
                        desde el {{ $lotSel->inspection_completed_at->format('d/m/Y H:i') }}
                        @if ($lotSel->inspector) por {{ $lotSel->inspector->name }} @endif
                    @endif.
                    Puedes corregir la decisión; se guardará con tu nombre y la fecha de hoy.
                </x-ui.note>
            @endif

            @if ($poSel?->part?->is_crimp && $lotSel->crimpLots->isNotEmpty())
                <x-ui.section title="Lotes de CRIMP del viajero" hint="Lo que Materiales cargó para este viajero.">
                    <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                        @foreach ($lotSel->crimpLots as $cl)
                            <x-ui.kv :label="$cl->crimp_lot_number" :value="number_format($cl->quantity ?? 0).' pz'"
                                :help="$cl->lote_fabricante ? 'Fabricante: '.$cl->lote_fabricante : null" />
                        @endforeach
                    </dl>
                </x-ui.section>
            @endif

            <x-ui.section step="1" title="¿El viajero pasa la inspección?" tone="accent">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-ui.choice tone="good" title="Aprobado"
                        desc="El viajero está correcto. Producción podrá empezar a pesarlo."
                        :selected="$inspectionAction === 'approved'"
                        wire:click="setInspectionAction('approved')" />

                    <x-ui.choice tone="bad" title="Rechazado"
                        desc="Hay un problema. El viajero se detiene hasta que Materiales lo corrija."
                        :selected="$inspectionAction === 'rejected'"
                        wire:click="setInspectionAction('rejected')" />
                </div>

                @error('inspectionAction')
                    <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
                @enderror

                @if ($yaProducido > 0)
                    <x-ui.note tone="warn" class="mt-3">
                        Producción ya registró <strong>{{ number_format($yaProducido) }} piezas</strong> de este viajero.
                        Por eso ya no se puede rechazar aquí: si hay un problema, levanta un rechazo desde la lista de envío.
                    </x-ui.note>
                @endif
            </x-ui.section>

            <x-ui.section step="2"
                :title="$inspectionAction === 'rejected' ? '¿Por qué se rechaza?' : 'Observaciones'"
                :hint="$inspectionAction === 'rejected'
                    ? 'Este texto es lo que Materiales va a leer para corregir. Sé concreto.'
                    : 'Opcional: cualquier detalle que convenga dejar registrado.'">
                <x-ui.field :label="$inspectionAction === 'rejected' ? 'Motivo del rechazo' : 'Comentarios'"
                    :required="$inspectionAction === 'rejected'"
                    :optional="$inspectionAction !== 'rejected'"
                    :error="$errors->first('inspectionComments')">
                    <textarea wire:model="inspectionComments" rows="3" class="w-full"
                        placeholder="{{ $inspectionAction === 'rejected' ? 'Ej: el lote de CRIMP CL-14 llegó con 40 piezas menos de las declaradas.' : 'Observaciones de la inspección...' }}"></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>
                @if ($inspectionAction === 'rejected')
                    Al guardar, el viajero queda detenido y no avanza hasta que Materiales corrija.
                @else
                    Al guardar, Producción podrá registrar pesadas de este viajero.
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeInspectionModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="submitInspectionDecision">Guardar decisión</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
