{{--
    VISTA DE EMPAQUE DENTRO DE UNA LISTA PRELIMINAR

    Se monta como pestaña en el detalle de la lista, así que NO lleva
    <x-ui.page>: la cabecera la pone la pantalla contenedora.

    Un bloque por Work Order, y dentro un bloque por lote/viajero con su avance
    y sus acciones. Los modales de Paso 5 y Paso 7 son partials compartidos con
    el tablero de piso.
--}}
<div class="space-y-5">

    {{--
        Sólo lectura: además del aviso, las acciones de escritura NO se pintan.
        Antes el aviso era lo único que cambiaba y los botones seguían vivos, así
        que el primer clic se llevaba una página de error 403 en vez de un «no
        puedes todavía».
    --}}
    @php $puedeEditar = $this->canEditDepartment(); @endphp

    @unless ($puedeEditar)
        <x-ui.note tone="warn" title="Modo sólo lectura">
            Esta lista no está en la etapa de tu departamento o ya fue cerrada. Puedes consultarla, pero no modificarla.
        </x-ui.note>
    @endunless

    @if (session()->has('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session()->has('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    @if ($allLotsHavePackaging)
        <x-ui.note tone="success" title="Todos los lotes están empacados">
            Ya puedes cerrar la lista con el botón <strong>Cerrar y confirmar lista</strong> al pie de la página.
        </x-ui.note>
    @endif

    {{-- Un bloque por Work Order --}}
    @forelse ($workOrders as $wo)
        @php $isCrimp = $wo->purchaseOrder->part->is_crimp ?? false; @endphp

        <x-ui.section wire:key="wo-{{ $wo->id }}"
            :title="($wo->purchaseOrder->wo ?? $wo->wo_number).' · '.($wo->purchaseOrder->part->number ?? '—')"
            :hint="$wo->purchaseOrder->part->description ?? null">

            <x-slot:aside>
                <div class="flex items-center gap-2">
                    @if ($isCrimp)
                        <x-ui.badge tone="accent">CRIMP</x-ui.badge>
                    @endif
                    <x-ui.btn variant="secondary" size="sm" href="{{ route('admin.sent-lists.display.wo', $wo->id) }}">
                        Ver en el tablero
                    </x-ui.btn>
                </div>
            </x-slot:aside>

            @if ($wo->lots->isEmpty())
                <x-ui.empty icon="box" title="Este WO no tiene lotes asignados"
                    hint="Los lotes se crean desde el tablero de piso, en la columna WO." />
            @else
                <div class="space-y-4">
                    @foreach ($wo->lots as $lot)
                        @php
                            $available  = (int) $lot->qualityWeighings->sum('good_pieces');
                            $packed     = (int) $lot->packagingRecords->sum('packed_pieces');
                            $surplus    = max(0, $available - $packed);
                            $hasRecords = $lot->packagingRecords->isNotEmpty();

                            // CRIMP: el empaque se mide por pesadas separadas (piezas + CRIMP).
                            $crimpPiecesPacked  = (int) $lot->packagingPieceWeighings->sum('quantity');
                            $crimpPacked        = (int) $lot->packagingCrimpWeighings->sum('quantity');
                            $crimpTarget        = (int) $lot->crimpLots->sum('quantity');
                            $crimpPiecesSurplus = max(0, $available - $crimpPiecesPacked);
                            $crimpSurplus       = max(0, $crimpTarget - $crimpPacked);

                            if ($isCrimp) {
                                $packed     = $crimpPiecesPacked;
                                $surplus    = $crimpPiecesSurplus;
                                $hasRecords = $lot->packagingPieceWeighings->isNotEmpty()
                                    || $lot->packagingCrimpWeighings->isNotEmpty();
                            }

                            $progressPct = $available > 0 ? min(100, round(($packed / $available) * 100)) : 0;
                            $progressBar = $progressPct >= 100 ? 'bg-green-500' : ($progressPct > 0 ? 'bg-sky-500' : 'bg-slate-300 dark:bg-slate-600');
                        @endphp

                        <div wire:key="lot-{{ $lot->id }}"
                            class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">

                            {{-- Cabecera del lote: identificación + cifras + acciones --}}
                            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/40">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-bold text-slate-900 dark:text-white">
                                                {{ $isCrimp ? 'Viajero' : 'Lote' }} {{ $lot->lot_number }}
                                            </span>
                                            @if ($lot->completion_count > 0)
                                                <x-ui.badge tone="warn">Completado {{ $lot->completion_count }}</x-ui.badge>
                                            @endif
                                            @if ($hasRecords)
                                                <x-ui.badge tone="good" dot>Empacado</x-ui.badge>
                                            @endif
                                            @if ($isCrimp && $lot->packaging_notified_at)
                                                <x-ui.badge tone="good"
                                                    title="Notificado el {{ \Carbon\Carbon::parse($lot->packaging_notified_at)->format('d/m/Y H:i') }}">
                                                    Notificado
                                                </x-ui.badge>
                                            @endif
                                        </div>

                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                            <span class="text-slate-500 dark:text-slate-400">
                                                Disponibles <strong class="tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($available) }}</strong>
                                            </span>
                                            <span class="text-slate-500 dark:text-slate-400">
                                                Empacadas <strong class="tabular-nums text-green-700 dark:text-green-400">{{ number_format($packed) }}</strong>
                                            </span>
                                            @if ($surplus > 0)
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    Sobrante <strong class="tabular-nums text-orange-700 dark:text-orange-400">{{ number_format($surplus) }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Acciones del lote --}}
                                    <div class="flex flex-wrap items-center gap-2">
                                        {{-- Paso 7 · entrega del viajero --}}
                                        @if ($isCrimp)
                                            @if ($puedeEditar)
                                                <x-ui.btn size="sm" :variant="$lot->viajero_received ? 'secondary' : 'primary'"
                                                    wire:click="openViajeroModal({{ $lot->id }})">
                                                    @if ($lot->viajero_received)
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        Viajero entregado
                                                    @else
                                                        Entregar viajero
                                                    @endif
                                                </x-ui.btn>
                                            @elseif ($lot->viajero_received)
                                                <x-ui.badge tone="good" dot>Viajero entregado</x-ui.badge>
                                            @endif
                                        @elseif ($lot->viajero_received)
                                            <x-ui.badge tone="good" dot>
                                                Lote recibido {{ \Carbon\Carbon::parse($lot->viajero_received_at)->format('d/m/Y') }}
                                            </x-ui.badge>
                                        @elseif ($puedeEditar)
                                            <x-ui.btn variant="primary" size="sm" wire:click="receiveViajero({{ $lot->id }})">
                                                Recibir lote
                                            </x-ui.btn>
                                        @endif

                                        {{-- Paso 6 · decisión --}}
                                        @php
                                            $decMeta = match ($lot->closure_decision) {
                                                'complete_lot' => ['Completar lote', 'info'],
                                                'new_lot'      => ['Nuevo lote', 'good'],
                                                'close_as_is'  => ['Cerrado', 'warn'],
                                                null           => null,
                                                default        => ['Decisión tomada', 'neutral'],
                                            };
                                        @endphp
                                        {{-- El modal de decisión sólo lee, así que se puede abrir siempre:
                                             es donde está el desglose del Paso 6. Sus botones de acción sí
                                             desaparecen en sólo lectura, más abajo. --}}
                                        @if ($decMeta)
                                            <x-ui.btn variant="secondary" size="sm" wire:click="openDecisionModal({{ $lot->id }})">
                                                {{ $decMeta[0] }}
                                            </x-ui.btn>
                                        @elseif ($puedeEditar)
                                            <x-ui.btn variant="warning" size="sm" wire:click="openDecisionModal({{ $lot->id }})">
                                                Tomar decisión
                                            </x-ui.btn>
                                        @else
                                            <x-ui.badge tone="neutral">Sin decisión</x-ui.badge>
                                        @endif

                                        {{-- Paso 8 · sobrantes --}}
                                        @if ($surplus > 0 || $lot->surplus_received)
                                            @if ($lot->surplus_received)
                                                <x-ui.badge tone="neutral" dot>Material recibido</x-ui.badge>
                                            @elseif ($puedeEditar)
                                                <x-ui.btn variant="warning" size="sm" wire:click="markSurplusReceived({{ $lot->id }})">
                                                    Recibí material
                                                </x-ui.btn>
                                            @endif
                                        @endif

                                        {{-- Paso 5 · empaque --}}
                                        @if ($puedeEditar)
                                            <x-ui.btn variant="accent" size="sm"
                                                wire:click="{{ $isCrimp ? 'openConfirmModal' : 'openPackagingModal' }}({{ $lot->id }})">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                                {{ $isCrimp ? 'Empacar / confirmar' : 'Registrar empaque' }}
                                            </x-ui.btn>
                                        @endif
                                    </div>
                                </div>

                                {{-- Avance del empaque --}}
                                @if ($available > 0)
                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div class="h-full rounded-full transition-all {{ $progressBar }}" style="width: {{ $progressPct }}%"></div>
                                        </div>
                                        <span class="shrink-0 text-xs font-semibold tabular-nums text-slate-600 dark:text-slate-300">
                                            {{ number_format($packed) }} / {{ number_format($available) }} ({{ $progressPct }}%)
                                        </span>
                                    </div>
                                @endif
                            </div>

                            {{-- Detalle: pesadas del lote --}}
                            <div class="p-4">
                                @if ($isCrimp)
                                    {{-- CRIMP: piezas «manguitas» y CRIMP se registran por separado --}}
                                    <x-ui.stats cols="5" class="mb-4">
                                        <x-ui.stat label="En viajero" :value="number_format($lot->quantity)" />
                                        <x-ui.stat label="Piezas empacadas" :value="number_format($crimpPiecesPacked)" tone="good" />
                                        <x-ui.stat label="CRIMP empacado" :value="number_format($crimpPacked)" tone="accent" />
                                        <x-ui.stat label="Sobrante de piezas" :value="number_format($crimpPiecesSurplus)" tone="warn" />
                                        <x-ui.stat label="Sobrante de CRIMP" :value="number_format($crimpSurplus)" tone="warn" />
                                    </x-ui.stats>

                                    {{-- Pesadas de piezas --}}
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                        Pesadas de piezas «manguitas»
                                    </p>
                                    @if ($lot->packagingPieceWeighings->isEmpty())
                                        <x-ui.note tone="muted" class="mb-4">
                                            Sin pesadas de piezas. Se capturan en <strong>Empacar / confirmar</strong>.
                                        </x-ui.note>
                                    @else
                                        <x-ui.table class="mb-4">
                                            <x-slot:head>
                                                <tr>
                                                    <x-ui.th class="w-44">Fecha y hora</x-ui.th>
                                                    <x-ui.th class="w-28" align="right">Cantidad</x-ui.th>
                                                    <x-ui.th class="w-28" align="right">Peso</x-ui.th>
                                                    <x-ui.th class="w-40">Pesó</x-ui.th>
                                                    <x-ui.th>Comentarios</x-ui.th>
                                                    <x-ui.th class="w-20" align="right">Acción</x-ui.th>
                                                </tr>
                                            </x-slot:head>
                                            @foreach ($lot->packagingPieceWeighings as $pw)
                                                <tr wire:key="pw-{{ $pw->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                                    <td class="whitespace-nowrap px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ \Carbon\Carbon::parse($pw->weighed_at)->format('d/m/Y H:i') }}</td>
                                                    <td class="px-4 py-2.5 text-right font-bold tabular-nums text-green-700 dark:text-green-400">{{ number_format($pw->quantity) }}</td>
                                                    <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ $pw->weight !== null ? number_format($pw->weight, 3) : '—' }}</td>
                                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $pw->weighedBy->name ?? '—' }}</td>
                                                    <td class="max-w-xs truncate px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $pw->comments ?: '—' }}</td>
                                                    <td class="px-4 py-2.5">
                                                        <div class="flex justify-end">
                                                            @if ($puedeEditar)
                                                                <x-ui.icon-btn tone="danger" label="Eliminar esta pesada de piezas"
                                                                    wire:click="deletePieceWeighing({{ $pw->id }})"
                                                                    wire:confirm="¿Eliminar esta pesada de piezas?">
                                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                </x-ui.icon-btn>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </x-ui.table>
                                    @endif

                                    {{-- Pesadas de CRIMP --}}
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                        Pesadas de CRIMP <span class="font-normal normal-case text-slate-400">· objetivo {{ number_format($crimpTarget) }}</span>
                                    </p>
                                    @if ($lot->packagingCrimpWeighings->isEmpty())
                                        <x-ui.note tone="muted">
                                            Sin pesadas de CRIMP. Se capturan en <strong>Empacar / confirmar</strong>.
                                        </x-ui.note>
                                    @else
                                        <x-ui.table>
                                            <x-slot:head>
                                                <tr>
                                                    <x-ui.th class="w-44">Fecha y hora</x-ui.th>
                                                    <x-ui.th class="w-32">Lote CRIMP</x-ui.th>
                                                    <x-ui.th class="w-28" align="right">Cantidad</x-ui.th>
                                                    <x-ui.th class="w-28" align="right">Peso</x-ui.th>
                                                    <x-ui.th class="w-40">Pesó</x-ui.th>
                                                    <x-ui.th>Comentarios</x-ui.th>
                                                    <x-ui.th class="w-20" align="right">Acción</x-ui.th>
                                                </tr>
                                            </x-slot:head>
                                            @foreach ($lot->packagingCrimpWeighings as $cw)
                                                <tr wire:key="cw-{{ $cw->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                                    <td class="whitespace-nowrap px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ \Carbon\Carbon::parse($cw->weighed_at)->format('d/m/Y H:i') }}</td>
                                                    <td class="px-4 py-2.5 font-semibold text-cyan-700 dark:text-cyan-300">{{ $cw->crimpLot?->crimp_lot_number ?? '—' }}</td>
                                                    <td class="px-4 py-2.5 text-right font-bold tabular-nums text-cyan-700 dark:text-cyan-400">{{ number_format($cw->quantity) }}</td>
                                                    <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ $cw->weight !== null ? number_format($cw->weight, 3) : '—' }}</td>
                                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $cw->weighedBy->name ?? '—' }}</td>
                                                    <td class="max-w-xs truncate px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $cw->comments ?: '—' }}</td>
                                                    <td class="px-4 py-2.5">
                                                        <div class="flex justify-end">
                                                            @if ($puedeEditar)
                                                                <x-ui.icon-btn tone="danger" label="Eliminar esta pesada de CRIMP"
                                                                    wire:click="deleteCrimpWeighing({{ $cw->id }})"
                                                                    wire:confirm="¿Eliminar esta pesada de CRIMP?">
                                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                </x-ui.icon-btn>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </x-ui.table>
                                    @endif

                                @elseif ($hasRecords)
                                    {{-- NO-CRIMP: registros de empaque --}}
                                    <x-ui.table>
                                        <x-slot:head>
                                            <tr>
                                                <x-ui.th class="w-44">Fecha y hora</x-ui.th>
                                                <x-ui.th class="w-28" align="right">Disponibles</x-ui.th>
                                                <x-ui.th class="w-28" align="right">Empacadas</x-ui.th>
                                                <x-ui.th class="w-28" align="right">Sobrante</x-ui.th>
                                                <x-ui.th class="w-40">Empacó</x-ui.th>
                                                <x-ui.th>Comentarios</x-ui.th>
                                                <x-ui.th class="w-20" align="right">Acción</x-ui.th>
                                            </tr>
                                        </x-slot:head>

                                        @foreach ($lot->packagingRecords as $record)
                                            <tr wire:key="pr-{{ $record->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ \Carbon\Carbon::parse($record->packed_at)->format('d/m/Y H:i') }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ number_format($record->available_pieces) }}</td>
                                                <td class="px-4 py-2.5 text-right font-bold tabular-nums text-green-700 dark:text-green-400">{{ number_format($record->packed_pieces) }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums {{ $record->surplus_pieces > 0 ? 'font-semibold text-orange-700 dark:text-orange-400' : 'text-slate-400' }}">
                                                    {{ number_format($record->adjusted_surplus ?? $record->surplus_pieces) }}
                                                    @if ($record->adjusted_surplus !== null && $record->adjusted_surplus !== $record->surplus_pieces)
                                                        <span class="text-[11px] font-normal text-slate-400">ajustado</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $record->packedBy->name ?? '—' }}</td>
                                                <td class="max-w-xs truncate px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $record->comments ?: '—' }}</td>
                                                <td class="px-4 py-2.5">
                                                    <div class="flex justify-end">
                                                        @if ($puedeEditar)
                                                            <x-ui.icon-btn tone="danger" label="Eliminar este registro de empaque"
                                                                wire:click="deletePackaging({{ $record->id }})"
                                                                wire:confirm="¿Eliminar este registro de empaque?">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </x-ui.icon-btn>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach

                                        <x-slot:foot>
                                            <div class="flex flex-wrap items-center justify-end gap-x-6 gap-y-1 text-xs">
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    Disponibles <strong class="tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($available) }}</strong>
                                                </span>
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    Empacadas <strong class="tabular-nums text-green-700 dark:text-green-400">{{ number_format($packed) }}</strong>
                                                </span>
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    Sobrante <strong class="tabular-nums {{ $surplus > 0 ? 'text-orange-700 dark:text-orange-400' : 'text-slate-500' }}">{{ number_format($surplus) }}</strong>
                                                </span>
                                            </div>
                                        </x-slot:foot>
                                    </x-ui.table>

                                @else
                                    <x-ui.empty icon="box" title="Sin registros de empaque"
                                        hint="Usa «Registrar empaque» para capturar las piezas empacadas de este lote." />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.section>
    @empty
        <x-ui.section>
            <x-ui.empty title="No hay Work Orders en esta lista"
                hint="Las órdenes se agregan a la lista desde el wizard de capacidad." />
        </x-ui.section>
    @endforelse

    {{-- Cierre de la lista --}}
    @php
        $allLots            = $workOrders->flatMap->lots;
        $lotsWithoutViajero = $allLots->filter(fn ($l) => ! $l->viajero_received
            && ($l->packagingRecords->isNotEmpty()
                || $l->packagingPieceWeighings->isNotEmpty()
                || $l->packagingCrimpWeighings->isNotEmpty()));
    @endphp

    <x-ui.section title="Cerrar la lista"
        hint="Sólo se puede cerrar cuando todos los lotes tienen empaque registrado.">
        @if ($lotsWithoutViajero->isNotEmpty())
            <x-ui.note tone="warn" class="mb-4"
                title="{{ $lotsWithoutViajero->count() }} {{ Str::plural('lote', $lotsWithoutViajero->count()) }} empacado{{ $lotsWithoutViajero->count() === 1 ? '' : 's' }} sin viajero confirmado">
                Se registró su empaque pero todavía no se confirma la entrega del viajero.
            </x-ui.note>
        @endif

        @unless ($allLotsHavePackaging)
            <x-ui.note tone="muted" class="mb-4">
                Faltan lotes por empacar. El botón de cierre se habilita cuando todos tengan su registro.
            </x-ui.note>
        @endunless

        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <x-ui.btn variant="secondary" href="{{ route('admin.sent-lists.display') }}">
                Ver el tablero de piso
            </x-ui.btn>
            @if ($puedeEditar)
                <x-ui.btn variant="success" wire:click="openCloseModal" :disabled="! $allLotsHavePackaging"
                    :title="$allLotsHavePackaging ? null : 'Faltan lotes por empacar'">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Cerrar y confirmar lista
                </x-ui.btn>
            @endif
        </div>
    </x-ui.section>

    {{-- Paso 5 · Registro de empaque (NO-CRIMP) --}}
    @if ($showPackagingModal)
        @php
            $pkLot   = $workOrders->flatMap->lots->firstWhere('id', $packagingLotId);
            $pkWO    = $pkLot?->workOrder;
            $pkPart  = $pkWO?->purchaseOrder?->part;
            $pkWoNum = $pkWO?->purchaseOrder?->wo ?? $pkWO?->wo_number ?? '—';
            $pkPacked = $pkLot ? $pkLot->getPackagingPackedPieces() : 0;
        @endphp
        <x-ui-modal wire:key="modal-packaging-{{ $packagingLotId }}" title="Registrar empaque"
            subtitle="Captura las piezas que Empaque acaba de empacar de este lote."
            close="closePackagingModal" maxWidth="3xl">
            <x-slot:context>
                <x-ui-modal.ctx label="Descripción" :value="$pkPart?->description ?? $pkPart?->number ?? '—'" />
                <x-ui-modal.ctx label="No. Order (WO + Lote)" :value="$pkWoNum.($pkLot?->lot_number ?? '—')" />
                <x-ui-modal.ctx label="Cantidad en lote" :value="number_format($pkLot?->quantity ?? 0)" />
                <x-ui-modal.ctx label="Disponibles (Calidad)" :value="number_format($modalAvailable)" />
            </x-slot:context>

            @if ($modalAvailable === 0)
                <x-ui.section>
                    <x-ui.note tone="warn" title="Calidad todavía no aprueba piezas de este lote">
                        Empaque no puede registrar piezas hasta que <strong>Calidad</strong> verifique al menos una pieza buena.
                    </x-ui.note>
                </x-ui.section>
            @else
                <x-ui.section title="Cantidades empacadas"
                    hint="Empacadas = las que se van con el cliente. Sobrantes = piezas buenas que NO se empacaron y regresan a Materiales.">
                    <x-ui.stats cols="3" class="mb-4">
                        <x-ui.stat label="Disponibles" :value="number_format($modalAvailable)" />
                        <x-ui.stat label="Ya empacadas" :value="number_format($pkPacked)" tone="good" />
                        <x-ui.stat label="Por empacar" :value="number_format(max(0, $modalAvailable - $pkPacked))" tone="warn" />
                    </x-ui.stats>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.field label="Piezas empacadas" required
                            :hint="'Máximo '.number_format($modalAvailable).' piezas.'"
                            :error="$errors->first('packedPieces')">
                            <input type="number" wire:model="packedPieces" min="1" max="{{ $modalAvailable }}"
                                placeholder="0" class="w-full text-right font-bold tabular-nums">
                        </x-ui.field>

                        <x-ui.field label="Piezas sobrantes" optional
                            hint="Se capturan a mano; quedan pendientes de entregar a Materiales."
                            :error="$errors->first('surplusPieces')">
                            <input type="number" wire:model="surplusPieces" min="0"
                                placeholder="0" class="w-full text-right font-bold tabular-nums">
                        </x-ui.field>

                        <x-ui.field label="Fecha y hora" required :error="$errors->first('packedAt')">
                            <input type="datetime-local" wire:model="packedAt" class="w-full">
                        </x-ui.field>

                        <x-ui.field label="Comentarios" optional
                            hint="Observaciones del empaque de este lote.">
                            <textarea wire:model="packagingComments" rows="2" class="w-full"
                                placeholder="Observaciones de empaque..."></textarea>
                        </x-ui.field>
                    </div>
                </x-ui.section>
            @endif

            <x-slot:note>
                Al guardar se crea el registro de empaque del lote y avanza el semáforo de <strong>Empaque</strong>.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closePackagingModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="savePackaging"
                    wire:loading.attr="disabled" wire:target="savePackaging"
                    :disabled="$modalAvailable === 0">
                    Guardar empaque
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Notificación de empaque terminado (CRIMP) --}}
    @if ($showNotifyModal)
        @php $nLot = $workOrders->flatMap->lots->firstWhere('id', $notifyLotId); @endphp
        <x-ui-modal wire:key="modal-notify-{{ $notifyLotId }}"
            title="Confirmar empaque y notificar"
            :subtitle="$nLot ? 'Viajero '.$nLot->lot_number : null"
            close="closeNotifyModal" maxWidth="3xl">

            @if ($nLot)
                <x-ui.section title="Resumen del empaque" hint="Estas cifras se incluyen en el correo.">
                    <x-ui.stats cols="4">
                        <x-ui.stat label="Piezas empacadas" :value="number_format($nLot->getPackagedPiecesTotal())" tone="good" />
                        <x-ui.stat label="CRIMP empacado" :value="number_format($nLot->getPackagedCrimpTotal())" tone="accent" />
                        <x-ui.stat label="Sobrante de piezas" :value="number_format($nLot->getPackagedPiecesSurplus())" tone="warn" />
                        <x-ui.stat label="Sobrante de CRIMP" :value="number_format($nLot->getPackagedCrimpSurplus())" tone="warn" />
                    </x-ui.stats>
                </x-ui.section>
            @endif

            <x-ui.section title="Datos del envío">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="No. de etiquetas" optional
                        hint="Cuántas etiquetas se imprimieron."
                        :error="$errors->first('notifyLabelCount')">
                        <input type="number" wire:model="notifyLabelCount" min="0" placeholder="—"
                            class="w-full text-right font-bold tabular-nums">
                    </x-ui.field>

                    <x-ui.field label="Comentarios" optional
                        hint="Se incluyen en el correo.">
                        <textarea wire:model="notifyComments" rows="2" class="w-full"
                            placeholder="Observaciones para Empaque/Materiales..."></textarea>
                    </x-ui.field>
                </div>
            </x-ui.section>

            <x-slot:note>
                Se enviará el correo <strong>«Empaque terminado CRIMP»</strong> a Empaques y a Materiales.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeNotifyModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="success" wire:click="confirmAndNotify"
                    wire:loading.attr="disabled" wire:target="confirmAndNotify">Confirmar y enviar</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Paso 5 y Paso 7: markup compartido con el tablero de piso. --}}
    @include('livewire.admin.sent-lists.partials.modal-viajero')

    @include('livewire.admin.sent-lists.partials.modal-confirm-empaque')

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
                        @if (! $decClosureDecision && ! $puedeEditar)
                            <x-ui.note tone="muted">
                                El viajero todavía no tiene decisión de cierre, pero esta lista no está en la
                                etapa de tu departamento: aquí sólo puedes consultar el desglose.
                            </x-ui.note>
                        @elseif (!$decClosureDecision)
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
                                @if (! $decSurplusReceived && $puedeEditar)
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
                                        @if ($puedeEditar)
                                            <button wire:click="confirmSurplusReceived"
                                                wire:confirm="¿Confirma que se recibieron {{ number_format($decSurplus) }} piezas sobrantes?"
                                                class="w-full px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                </svg>
                                                Material Recibido
                                            </button>
                                        @endif
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

                            {{-- Reabrir Lote: sólo Administración, con motivo y dejando rastro --}}
                            @if (auth()->user()?->can(\App\Services\ReopeningService::PERMISSION))
                                <x-ui.section title="Corregir la decisión"
                                    hint="Anula el cierre y devuelve el viajero al flujo. Queda registrado con tu nombre.">
                                    <x-ui.field label="¿Por qué se reabre?" required
                                        hint="Mínimo 10 caracteres."
                                        :error="$errors->first('reopenReason')">
                                        <textarea wire:model="reopenReason" rows="2" class="w-full"
                                            placeholder="Ej: el cliente reportó una diferencia de cantidad."></textarea>
                                    </x-ui.field>

                                    <x-ui.btn variant="danger" block class="mt-3" wire:click="reopenLot">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Reabrir lote y anular la decisión
                                    </x-ui.btn>
                                </x-ui.section>
                            @else
                                <x-ui.note tone="muted">
                                    La decisión ya está tomada. Sólo Administración puede reabrirla.
                                </x-ui.note>
                            @endif
                        @endif
            <x-slot:note>
                @if ($decIsCrimp)
                    Elijas la opción que elijas, el viajero continúa al <strong>Paso 7</strong> (entrega) y luego al <strong>Paso 8</strong> (sobrantes).
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeDecisionModal">Cerrar</x-ui.btn>
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
