{{--
    VISTA DE PRODUCCIÓN DENTRO DE UNA LISTA PRELIMINAR

    Se monta como pestaña del detalle de la lista, así que NO lleva
    <x-ui.page>: la cabecera la pone la pantalla contenedora.

    Producción registra las pesadas de cada lote hasta llegar a su meta.
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
        $targetSum  = (int) $allLots->sum('quantity');
        $weighedSum = (int) $allLots->sum(fn ($l) => $l->weighings->whereNull('kit_id')->sum('good_pieces'));
        $doneLots   = $allLots->filter(fn ($l) => $l->status === 'completed')->count();
        $globalPct  = $targetSum > 0 ? min(100, round(($weighedSum / $targetSum) * 100)) : 0;
    @endphp

    {{-- Avance de la producción --}}
    <x-ui.section title="Avance de la producción"
        :hint="$doneLots.' de '.$allLots->count().' '.Str::plural('lote', $allLots->count()).' completados.'">
        <x-ui.stats cols="4">
            <x-ui.stat label="Meta de la lista" :value="number_format($targetSum)" unit="pz" />
            <x-ui.stat label="Piezas pesadas" :value="number_format($weighedSum)" unit="pz" tone="good" />
            <x-ui.stat label="Faltan por pesar" :value="number_format(max(0, $targetSum - $weighedSum))" unit="pz"
                :tone="$weighedSum >= $targetSum ? 'good' : 'warn'" />
            <x-ui.stat label="Lotes completos" :value="$doneLots" tone="info" />
        </x-ui.stats>

        <div class="mt-4">
            <div class="mb-1.5 flex items-baseline justify-between text-xs">
                <span class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Avance total</span>
                <span class="font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ $globalPct }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                <div class="h-full rounded-full transition-all {{ $globalPct >= 100 ? 'bg-green-500' : 'bg-indigo-500' }}" style="width: {{ $globalPct }}%"></div>
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
                            $lotWeighings = $isCrimp ? $lot->weighings->whereNull('kit_id')->values() : $lot->weighings;
                            $totalWeighed = (int) $lot->weighings->whereNull('kit_id')->sum('good_pieces');
                            $targetQty    = (int) $lot->quantity;
                            $progressPct  = $targetQty > 0 ? min(100, round(($totalWeighed / $targetQty) * 100)) : 0;
                            $isCompleted  = $lot->status === 'completed';
                            $progressBar  = $isCompleted ? 'bg-green-500' : ($progressPct > 0 ? 'bg-indigo-500' : 'bg-slate-300 dark:bg-slate-600');
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
                                            @if ($isCompleted)
                                                <x-ui.badge tone="good" dot>Lote completo</x-ui.badge>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            Meta <strong class="tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($targetQty) }}</strong> pz ·
                                            pesadas <strong class="tabular-nums text-green-700 dark:text-green-400">{{ number_format($totalWeighed) }}</strong> pz
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($isCompleted)
                                            <x-ui.btn variant="secondary" size="sm" wire:click="reopenLot({{ $lot->id }})">
                                                Reabrir lote
                                            </x-ui.btn>
                                        @elseif (! $lot->canBeProduced())
                                            {{-- El flujo es secuencial: sin inspección aprobada no se pesa. --}}
                                            <x-ui.badge tone="neutral" dot
                                                :title="$lot->getProductionBlockedReason()">Bloqueado</x-ui.badge>
                                        @else
                                            <x-ui.btn variant="primary" size="sm" wire:click="openWeighingModal({{ $lot->id }})">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                Agregar pesada
                                            </x-ui.btn>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                        <div class="h-full rounded-full transition-all {{ $progressBar }}" style="width: {{ $progressPct }}%"></div>
                                    </div>
                                    <span class="shrink-0 text-xs font-semibold tabular-nums {{ $progressPct >= 100 ? 'text-green-700 dark:text-green-400' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $progressPct }}%
                                    </span>
                                </div>
                            </div>

                            {{-- Historial de pesadas --}}
                            <div class="p-4">
                                @if (! $lot->canBeProduced() && $lotWeighings->isEmpty())
                                    <x-ui.note tone="muted" title="Todavía no se puede pesar">
                                        {{ $lot->getProductionBlockedReason() }}
                                    </x-ui.note>
                                @elseif ($lotWeighings->isEmpty())
                                    <x-ui.empty icon="doc" title="Sin pesadas registradas"
                                        hint="Usa «Agregar pesada» para capturar las piezas producidas de este lote." />
                                @else
                                    <x-ui.table>
                                        <x-slot:head>
                                            <tr>
                                                <x-ui.th class="w-44">Fecha y hora</x-ui.th>
                                                <x-ui.th class="w-28" align="right">Piezas</x-ui.th>
                                                <x-ui.th class="w-40">Registró</x-ui.th>
                                                <x-ui.th>Comentarios</x-ui.th>
                                                <x-ui.th class="w-28" align="right">Acciones</x-ui.th>
                                            </tr>
                                        </x-slot:head>

                                        @foreach ($lotWeighings as $weighing)
                                            <tr wire:key="w-{{ $weighing->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-700 dark:text-slate-300">
                                                    {{ \Carbon\Carbon::parse($weighing->weighed_at)->format('d/m/Y H:i') }}
                                                </td>
                                                <td class="px-4 py-2.5 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                                                    {{ number_format($weighing->good_pieces) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $weighing->weighedBy->name ?? '—' }}</td>
                                                <td class="max-w-xs truncate px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $weighing->comments ?: '—' }}</td>
                                                <td class="px-4 py-2.5">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <x-ui.icon-btn tone="primary" label="Editar esta pesada"
                                                            wire:click="editWeighing({{ $weighing->id }})">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        </x-ui.icon-btn>
                                                        <x-ui.icon-btn tone="danger" label="Eliminar esta pesada"
                                                            wire:click="deleteWeighing({{ $weighing->id }})"
                                                            wire:confirm="¿Eliminar esta pesada?">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </x-ui.icon-btn>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach

                                        <x-slot:foot>
                                            <div class="flex items-center justify-end gap-2 text-xs">
                                                <span class="text-slate-500 dark:text-slate-400">Total pesado</span>
                                                <strong class="tabular-nums text-slate-900 dark:text-white">{{ number_format($lotWeighings->sum('good_pieces')) }}</strong>
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
    <x-ui.section title="Cerrar la producción"
        hint="Al enviar, la lista pasa a Calidad para que verifique las piezas producidas.">
        @if ($weighedSum < $targetSum)
            <x-ui.note tone="warn" class="mb-4">
                Faltan <strong>{{ number_format($targetSum - $weighedSum) }} piezas</strong> por pesar.
                Puedes enviar así, pero Calidad sólo podrá verificar lo que ya se registró.
            </x-ui.note>
        @endif

        <div class="flex justify-end">
            <x-ui.btn variant="primary" wire:click="openSendModal">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Enviar a Calidad
            </x-ui.btn>
        </div>
    </x-ui.section>

    {{-- Registrar / editar pesada --}}
    @if ($showWeighingModal)
        {{-- El componente sólo guarda el id; el lote y su avance se resuelven aquí. --}}
        @php
            $modalLot = $workOrders->flatMap->lots->firstWhere('id', $weighingLotId);
            $modalTotalWeighed = $modalLot
                ? (int) $modalLot->weighings->whereNull('kit_id')->sum('good_pieces')
                : 0;
        @endphp
        <x-ui-modal wire:key="modal-weighing"
            :title="$editingWeighingId ? 'Editar pesada' : 'Registrar pesada'"
            :subtitle="$modalLot ? 'Lote '.$modalLot->lot_number : null"
            close="closeWeighingModal" maxWidth="2xl">

            @if ($modalLot)
                <x-slot:context>
                    <x-ui-modal.ctx label="Lote" :value="$modalLot->lot_number" />
                    <x-ui-modal.ctx label="Meta" :value="number_format($modalLot->quantity).' pz'" />
                    <x-ui-modal.ctx label="Ya pesadas" :value="number_format($modalTotalWeighed).' pz'" />
                    <x-ui-modal.ctx label="Faltan"
                        :value="number_format(max(0, $modalLot->quantity - $modalTotalWeighed)).' pz'" />
                </x-slot:context>
            @endif

            <x-ui.section :title="$editingWeighingId ? 'Corregir la pesada' : 'Datos de la pesada'"
                hint="Queda registrada con tu usuario y la fecha que captures.">
                @if ($editingWeighingId)
                    <x-ui.note tone="info" class="mb-4">
                        Estás editando una pesada ya registrada, no creando una nueva.
                    </x-ui.note>
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Piezas pesadas" required
                        hint="Sólo piezas buenas."
                        :error="$errors->first('weighingQuantity')">
                        <input type="number" wire:model="weighingQuantity" min="1" placeholder="0"
                            class="w-full text-right text-lg font-bold tabular-nums">
                    </x-ui.field>

                    <x-ui.field label="Fecha y hora" required :error="$errors->first('weighingAt')">
                        <input type="datetime-local" wire:model="weighingAt" class="w-full">
                    </x-ui.field>
                </div>

                <x-ui.field label="Comentarios" optional class="mt-4">
                    <textarea wire:model="weighingComments" rows="2" class="w-full"
                        placeholder="Observaciones..."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>La pesada suma al avance del lote y habilita a Calidad para verificar esas piezas.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeWeighingModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveWeighing"
                    wire:loading.attr="disabled" wire:target="saveWeighing">
                    {{ $editingWeighingId ? 'Guardar cambios' : 'Registrar pesada' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Enviar a Calidad --}}
    @if ($showSendModal)
        <x-ui-modal wire:key="modal-send" title="Enviar a Calidad"
            subtitle="Revisa el avance por lote antes de pasar la lista a la siguiente etapa."
            close="closeSendModal" maxWidth="3xl">

            <x-ui.section title="Resumen de producción" hint="Lo que Calidad va a recibir para verificar.">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <x-ui.th>Lote</x-ui.th>
                            <x-ui.th class="w-28" align="right">Meta</x-ui.th>
                            <x-ui.th class="w-28" align="right">Pesadas</x-ui.th>
                            <x-ui.th class="w-24" align="right">Avance</x-ui.th>
                        </tr>
                    </x-slot:head>

                    @foreach ($workOrders as $wo)
                        @foreach ($wo->lots as $lot)
                            @php
                                $weighed = (int) $lot->weighings->whereNull('kit_id')->sum('good_pieces');
                                $pct = $lot->quantity > 0 ? min(100, round($weighed / $lot->quantity * 100)) : 0;
                            @endphp
                            <tr wire:key="send-lot-{{ $lot->id }}">
                                <td class="px-4 py-2.5 font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ number_format($lot->quantity) }}</td>
                                <td class="px-4 py-2.5 text-right font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($weighed) }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <x-ui.badge :tone="$pct >= 100 ? 'good' : 'warn'">{{ $pct }}%</x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </x-ui.table>
            </x-ui.section>

            <x-ui.section title="Notas para Calidad">
                <x-ui.field label="Notas" optional
                    hint="Cualquier detalle que Calidad deba saber antes de verificar.">
                    <textarea wire:model="sendNotes" rows="3" class="w-full"
                        placeholder="Observaciones para Calidad..."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>Al enviar, la lista cambia de departamento y Calidad puede empezar a verificar.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeSendModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="sendToQuality"
                    wire:loading.attr="disabled" wire:target="sendToQuality">Enviar a Calidad</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</div>
