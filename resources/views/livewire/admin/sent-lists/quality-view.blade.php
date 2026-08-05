{{--
    VISTA DE CALIDAD DENTRO DE UNA LISTA PRELIMINAR

    Se monta como pestaña del detalle de la lista, así que NO lleva
    <x-ui.page>: la cabecera la pone la pantalla contenedora.

    Calidad pesa las piezas que Producción registró y las aprueba o rechaza.
    Las rechazadas se descartan: no regresan al lote.
--}}
<div class="space-y-5">

    @unless ($this->canEditDepartment())
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

    @php
        $allLots    = $workOrders->flatMap->lots;
        $recvSum    = (int) $allLots->sum(fn ($l) => $l->weighings->whereNull('kit_id')->sum('good_pieces'));
        $goodSum    = (int) $allLots->sum(fn ($l) => $l->qualityWeighings->whereNull('kit_id')->sum('good_pieces'));
        $badSum     = (int) $allLots->sum(fn ($l) => $l->qualityWeighings->whereNull('kit_id')->sum('bad_pieces'));
        $seenSum    = $goodSum + $badSum;
        $pendSum    = max(0, $recvSum - $seenSum);
        $globalPct  = $recvSum > 0 ? min(100, round(($seenSum / $recvSum) * 100)) : 0;
        $rejectRate = $seenSum > 0 ? round(($badSum / $seenSum) * 100, 1) : 0.0;
    @endphp

    {{-- Avance de la verificación --}}
    <x-ui.section title="Avance de la verificación"
        hint="Calidad sólo puede verificar piezas que Producción ya registró.">
        <x-ui.stats cols="4">
            <x-ui.stat label="Recibidas de Producción" :value="number_format($recvSum)" unit="pz" tone="info" />
            <x-ui.stat label="Aprobadas" :value="number_format($goodSum)" unit="pz" tone="good" />
            <x-ui.stat label="Rechazadas" :value="number_format($badSum)" unit="pz"
                :tone="$badSum > 0 ? 'bad' : 'neutral'"
                help="Las piezas rechazadas se descartan: no regresan al lote." />
            <x-ui.stat label="Pendientes" :value="number_format($pendSum)" unit="pz"
                :tone="$pendSum > 0 ? 'warn' : 'good'" />
        </x-ui.stats>

        <div class="mt-4">
            <div class="mb-1.5 flex items-baseline justify-between text-xs">
                <span class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Verificado</span>
                <span class="font-bold tabular-nums text-slate-700 dark:text-slate-200">
                    {{ $globalPct }}%
                    @if ($seenSum > 0)
                        <span class="ml-2 font-normal text-slate-400">· rechazo {{ $rejectRate }}%</span>
                    @endif
                </span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                <div class="h-full rounded-full transition-all {{ $globalPct >= 100 ? 'bg-green-500' : 'bg-teal-500' }}" style="width: {{ $globalPct }}%"></div>
            </div>
        </div>
    </x-ui.section>

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
                    hint="Los lotes se crean desde el tablero de piso." />
            @else
                <div class="space-y-4">
                    @foreach ($wo->lots as $lot)
                        @php
                            $prodTotal    = (int) $lot->weighings->whereNull('kit_id')->sum('good_pieces');
                            $lotQW        = $lot->qualityWeighings->whereNull('kit_id')->values();
                            $qualityGood  = (int) $lotQW->sum('good_pieces');
                            $qualityBad   = (int) $lotQW->sum('bad_pieces');
                            $qualityTotal = $qualityGood + $qualityBad;
                            $pendingPcs   = max(0, $prodTotal - $qualityTotal);
                            $pct          = $prodTotal > 0 ? min(100, round(($qualityTotal / $prodTotal) * 100)) : 0;
                            $bar          = $pct >= 100 ? 'bg-green-500' : ($pct > 0 ? 'bg-teal-500' : 'bg-slate-300 dark:bg-slate-600');
                            $readyForQuality = $lot->status === 'completed' || $lot->weighings->isNotEmpty();
                        @endphp

                        <div wire:key="lot-{{ $lot->id }}"
                            class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">

                            {{-- Cabecera del lote --}}
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
                                            @if ($readyForQuality && $pendingPcs === 0 && $qualityTotal > 0)
                                                <x-ui.badge tone="good" dot>Verificado</x-ui.badge>
                                            @endif
                                        </div>

                                        @if ($readyForQuality)
                                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    Recibidas <strong class="tabular-nums text-sky-800 dark:text-sky-300">{{ number_format($prodTotal) }}</strong>
                                                </span>
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    Aprobadas <strong class="tabular-nums text-green-700 dark:text-green-400">{{ number_format($qualityGood) }}</strong>
                                                </span>
                                                @if ($qualityBad > 0)
                                                    <span class="text-slate-500 dark:text-slate-400">
                                                        Rechazadas <strong class="tabular-nums text-red-700 dark:text-red-400">{{ number_format($qualityBad) }}</strong>
                                                    </span>
                                                @endif
                                                @if ($pendingPcs > 0)
                                                    <span class="text-slate-500 dark:text-slate-400">
                                                        Pendientes <strong class="tabular-nums text-amber-700 dark:text-amber-400">{{ number_format($pendingPcs) }}</strong>
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    @if ($readyForQuality)
                                        <x-ui.btn variant="primary" size="sm" wire:click="openWeighingModal({{ $lot->id }})">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            Agregar pesada
                                        </x-ui.btn>
                                    @endif
                                </div>

                                @if ($readyForQuality && $prodTotal > 0)
                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div class="h-full rounded-full transition-all {{ $bar }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="shrink-0 text-xs font-semibold tabular-nums text-slate-600 dark:text-slate-300">{{ $pct }}%</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Contenido --}}
                            <div class="p-4">
                                @if (! $readyForQuality)
                                    <x-ui.note tone="muted" title="Todavía no llega a Calidad">
                                        Producción no ha registrado piezas de este lote. En cuanto lo haga podrás verificarlas.
                                    </x-ui.note>
                                @elseif ($lotQW->isEmpty())
                                    <x-ui.empty icon="doc" title="Sin pesadas de calidad"
                                        hint="Usa «Agregar pesada» para registrar cuántas piezas aprobaste y cuántas rechazaste." />
                                @else
                                    <x-ui.table>
                                        <x-slot:head>
                                            <tr>
                                                <x-ui.th class="w-44">Fecha y hora</x-ui.th>
                                                <x-ui.th class="w-28" align="right">Aprobadas</x-ui.th>
                                                <x-ui.th class="w-28" align="right">Rechazadas</x-ui.th>
                                                <x-ui.th class="w-40">Registró</x-ui.th>
                                                <x-ui.th>Comentarios</x-ui.th>
                                                <x-ui.th class="w-28" align="right">Acciones</x-ui.th>
                                            </tr>
                                        </x-slot:head>

                                        @foreach ($lotQW as $qw)
                                            <tr wire:key="qw-{{ $qw->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-700 dark:text-slate-300">
                                                    {{ \Carbon\Carbon::parse($qw->weighed_at)->format('d/m/Y H:i') }}
                                                </td>
                                                <td class="px-4 py-2.5 text-right font-bold tabular-nums text-green-700 dark:text-green-400">
                                                    {{ number_format($qw->good_pieces) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular-nums {{ $qw->bad_pieces > 0 ? 'font-bold text-red-700 dark:text-red-400' : 'text-slate-400' }}">
                                                    {{ number_format($qw->bad_pieces) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $qw->weighedBy->name ?? '—' }}</td>
                                                <td class="max-w-xs truncate px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $qw->comments ?: '—' }}</td>
                                                <td class="px-4 py-2.5">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <x-ui.icon-btn tone="primary" label="Editar esta pesada de calidad"
                                                            wire:click="editQualityWeighing({{ $lot->id }}, {{ $qw->id }})">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        </x-ui.icon-btn>
                                                        <x-ui.icon-btn tone="danger" label="Eliminar esta pesada de calidad"
                                                            wire:click="deleteWeighing({{ $qw->id }})"
                                                            wire:confirm="¿Eliminar esta pesada de calidad?">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </x-ui.icon-btn>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach

                                        <x-slot:foot>
                                            <div class="flex flex-wrap items-center justify-end gap-x-6 gap-y-1 text-xs">
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    Aprobadas <strong class="tabular-nums text-green-700 dark:text-green-400">{{ number_format($qualityGood) }}</strong>
                                                </span>
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    Rechazadas <strong class="tabular-nums {{ $qualityBad > 0 ? 'text-red-700 dark:text-red-400' : 'text-slate-500' }}">{{ number_format($qualityBad) }}</strong>
                                                </span>
                                            </div>
                                        </x-slot:foot>
                                    </x-ui.table>
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

    {{-- Cierre de la etapa --}}
    <x-ui.section title="Cerrar la verificación"
        hint="Al enviar, la lista pasa a Empaque con las piezas aprobadas.">
        @if ($pendSum > 0)
            <x-ui.note tone="warn" class="mb-4">
                Quedan <strong>{{ number_format($pendSum) }} piezas</strong> sin verificar.
                Empaque sólo podrá empacar las que ya estén aprobadas.
            </x-ui.note>
        @endif

        <div class="flex justify-end">
            <x-ui.btn variant="primary" wire:click="openSendModal">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Enviar a Empaque
            </x-ui.btn>
        </div>
    </x-ui.section>

    {{-- Registrar / editar pesada de calidad --}}
    @if ($showWeighingModal)
        @php $modalLot = $workOrders->flatMap->lots->firstWhere('id', $weighingLotId); @endphp
        <x-ui-modal wire:key="modal-quality-weighing" title="Pesada de calidad"
            :subtitle="$modalLot ? 'Lote '.$modalLot->lot_number : null"
            close="closeWeighingModal" maxWidth="2xl">

            <x-slot:context>
                <x-ui-modal.ctx label="Lote" :value="$modalLot?->lot_number ?? '—'" />
                <x-ui-modal.ctx label="Recibidas de Producción" :value="number_format($productionGoodPieces).' pz'" />
                <x-ui-modal.ctx label="Ya verificadas" :value="number_format($alreadyWeighed).' pz'" />
                <x-ui-modal.ctx label="Pendientes" :value="number_format($remainingPieces).' pz'" />
            </x-slot:context>

            <x-ui.section title="Resultado de la verificación"
                hint="Aprobadas y rechazadas se suman: el total no puede pasar de las piezas pendientes.">
                @if ($editingId)
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 dark:border-sky-800 dark:bg-sky-950/30">
                        <p class="text-sm text-sky-900 dark:text-sky-100">
                            Estás editando una pesada ya registrada, no creando una nueva.
                        </p>
                        <x-ui.btn variant="secondary" size="sm" wire:click="cancelEdit">Cancelar edición</x-ui.btn>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Piezas aprobadas" required
                        hint="Pasan a Empaque."
                        :error="$errors->first('goodPieces')">
                        <input type="number" wire:model="goodPieces" min="0" placeholder="0"
                            class="w-full text-right text-lg font-bold tabular-nums">
                    </x-ui.field>

                    <x-ui.field label="Piezas rechazadas" optional
                        hint="Se descartan: no regresan al lote."
                        :error="$errors->first('badPieces')">
                        <input type="number" wire:model="badPieces" min="0" placeholder="0"
                            class="w-full text-right text-lg font-bold tabular-nums">
                    </x-ui.field>
                </div>

                @if ($badPieces > 0)
                    <x-ui.note tone="danger" class="mt-4" title="Hay {{ number_format($badPieces) }} piezas rechazadas">
                        Documenta el motivo en los comentarios: es el registro que queda para auditoría.
                    </x-ui.note>
                @endif

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Fecha y hora" required :error="$errors->first('weighingAt')">
                        <input type="datetime-local" wire:model="weighingAt" class="w-full">
                    </x-ui.field>

                    <x-ui.field label="Comentarios" :optional="$badPieces <= 0"
                        :hint="$badPieces > 0 ? 'Explica por qué se rechazaron.' : null">
                        <textarea wire:model="weighingComments" rows="2" class="w-full"
                            placeholder="Observaciones de calidad..."></textarea>
                    </x-ui.field>
                </div>
            </x-ui.section>

            <x-slot:note>Las piezas aprobadas quedan disponibles para que Empaque las empaque.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeWeighingModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveWeighing"
                    wire:loading.attr="disabled" wire:target="saveWeighing">Guardar pesada</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Enviar a Empaque --}}
    @if ($showSendModal)
        <x-ui-modal wire:key="modal-send-packaging" title="Enviar a Empaque"
            subtitle="Revisa el resultado por lote antes de pasar la lista a la siguiente etapa."
            close="closeSendModal" maxWidth="3xl">

            <x-ui.section title="Resumen de calidad" hint="Lo que Empaque va a recibir.">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <x-ui.th>Lote</x-ui.th>
                            <x-ui.th class="w-28" align="right">Recibidas</x-ui.th>
                            <x-ui.th class="w-28" align="right">Aprobadas</x-ui.th>
                            <x-ui.th class="w-28" align="right">Rechazadas</x-ui.th>
                        </tr>
                    </x-slot:head>

                    @foreach ($workOrders as $wo)
                        @foreach ($wo->lots as $lot)
                            @php
                                $lotQW   = $lot->qualityWeighings->whereNull('kit_id');
                                $lotRecv = (int) $lot->weighings->whereNull('kit_id')->sum('good_pieces');
                                $lotGood = (int) $lotQW->sum('good_pieces');
                                $lotBad  = (int) $lotQW->sum('bad_pieces');
                            @endphp
                            <tr wire:key="send-q-{{ $lot->id }}">
                                <td class="px-4 py-2.5 font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ number_format($lotRecv) }}</td>
                                <td class="px-4 py-2.5 text-right font-bold tabular-nums text-green-700 dark:text-green-400">{{ number_format($lotGood) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $lotBad > 0 ? 'font-bold text-red-700 dark:text-red-400' : 'text-slate-400' }}">{{ number_format($lotBad) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </x-ui.table>
            </x-ui.section>

            <x-ui.section title="Notas para Empaque">
                <x-ui.field label="Notas" optional hint="Cualquier detalle que Empaque deba saber.">
                    <textarea wire:model="sendNotes" rows="3" class="w-full"
                        placeholder="Observaciones para Empaque..."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>Al enviar, la lista cambia de departamento y Empaque puede empezar a empacar.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeSendModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="sendToPackaging"
                    wire:loading.attr="disabled" wire:target="sendToPackaging">Enviar a Empaque</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</div>
