@php
    $statuses = \App\Models\Lot::getStatuses();
    $tonoEstado = [
        \App\Models\Lot::STATUS_PENDING => 'neutral',
        \App\Models\Lot::STATUS_IN_PROGRESS => 'info',
        \App\Models\Lot::STATUS_COMPLETED => 'good',
        \App\Models\Lot::STATUS_CANCELLED => 'bad',
    ];
    $parte = $lot->workOrder?->purchaseOrder?->part;
    $esCrimp = (bool) ($parte?->is_crimp ?? false);
    $avance = $lot->getProgressSummary();
    $producido = $lot->getProductionTotalWeighed();
    $verificado = $lot->getQualityVerifiedPieces();
    $empacado = $lot->getPackagingPackedPieces();
@endphp

<x-ui.page eyebrow="Producción" :title="'Viajero '.$lot->lot_number"
    :subtitle="$lot->description ?: 'Detalle del viajero y su avance por área.'"
    back="{{ route('admin.lots.index') }}" backLabel="Volver a viajeros">

    <x-slot:actions>
        <x-ui.btn variant="secondary" wire:click="openStatusModal">Cambiar estado</x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.lots.edit', $lot) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar viajero
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Avance por área: es lo que se viene a ver aquí --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Cantidad" :value="number_format($lot->quantity)" unit="pz" />
        <x-ui.stat label="Producido" :value="number_format($producido)" unit="pz" tone="info"
            help="Suma de las pesadas de producción." />
        <x-ui.stat label="Verificado por calidad" :value="number_format($verificado)" unit="pz" tone="good"
            help="Piezas aprobadas más rechazadas." />
        <x-ui.stat label="Empacado" :value="number_format($empacado)" unit="pz" tone="accent" />
    </x-ui.stats>

    {{-- Ficha --}}
    <x-ui.section title="Información del viajero">
        <x-slot:aside>
            <div class="flex items-center gap-2">
                @if ($esCrimp)
                    <x-ui.badge tone="accent">CRIMP</x-ui.badge>
                @endif
                <x-ui.badge :tone="$tonoEstado[$lot->status] ?? 'neutral'" dot>{{ $statuses[$lot->status] ?? $lot->status }}</x-ui.badge>
            </div>
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Número de viajero" :value="$lot->lot_number" />
            <x-ui.kv label="Orden de trabajo">
                @if ($lot->workOrder)
                    <a href="{{ route('admin.work-orders.show', $lot->workOrder) }}" wire:navigate
                        class="font-bold text-sky-700 hover:underline dark:text-sky-300">
                        WO {{ $lot->workOrder->purchaseOrder?->wo ?? $lot->workOrder->id }}
                    </a>
                @else
                    <span class="font-normal text-slate-400 dark:text-slate-500">—</span>
                @endif
            </x-ui.kv>
            <x-ui.kv label="Parte" :value="$parte?->number ?: '—'" />
            <x-ui.kv label="Descripción" :value="$lot->description ?: '—'" />
            <x-ui.kv label="Etapa del flujo" :value="$avance['label']" />
            <x-ui.kv label="Material" :value="$lot->material_status === 'released' ? 'Liberado' : 'Pendiente de liberar'"
                :tone="$lot->material_status === 'released' ? 'good' : 'warn'" />
            <x-ui.kv label="Inspección" :value="$statuses[$lot->inspection_status] ?? ucfirst($lot->inspection_status ?? 'pendiente')" />
            <x-ui.kv label="Alta" :value="$lot->created_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>

    {{-- Lotes de CRIMP --}}
    @if ($esCrimp)
        <x-ui.section title="Lotes de CRIMP" hint="El material de CRIMP que Materiales asignó a este viajero.">
            @if ($lot->crimpLots->isNotEmpty())
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <x-ui.th>Lote de CRIMP</x-ui.th>
                            <x-ui.th>Lote de fabricante</x-ui.th>
                            <x-ui.th align="right" class="w-32">Cantidad</x-ui.th>
                            <x-ui.th>Comentarios</x-ui.th>
                        </tr>
                    </x-slot:head>

                    @foreach ($lot->crimpLots as $crimpLot)
                        <tr wire:key="crimp-{{ $crimpLot->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $crimpLot->crimp_lot_number ?: $crimpLot->id }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $crimpLot->lote_fabricante ?: '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-900 dark:text-white">{{ number_format($crimpLot->quantity ?? 0) }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $crimpLot->comments ?: '—' }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @else
                <x-ui.empty icon="box" title="Sin lotes de CRIMP asignados"
                    hint="Materiales los carga desde el tablero, en la celda de Material del viajero." />
            @endif
        </x-ui.section>
    @endif

    {{-- Acciones de estado --}}
    <x-ui.section title="Estado del viajero" hint="Sólo aparecen los cambios válidos desde el estado actual.">
        <div class="flex flex-wrap gap-2">
            @if ($lot->canBeStarted())
                <x-ui.btn variant="primary" wire:click="startLot">Iniciar viajero</x-ui.btn>
            @endif
            @if ($lot->canBeCompleted())
                <x-ui.btn variant="success" wire:click="completeLot">Marcar como completado</x-ui.btn>
            @endif
            @if ($lot->canBeCancelled())
                <x-ui.btn variant="danger" wire:click="cancelLot"
                    wire:confirm="¿Cancelar el viajero {{ $lot->lot_number }}?">Cancelar viajero</x-ui.btn>
            @endif
            @if (!$lot->canBeStarted() && !$lot->canBeCompleted() && !$lot->canBeCancelled())
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Este viajero está <strong>{{ strtolower($statuses[$lot->status] ?? $lot->status) }}</strong>: no hay cambios de estado disponibles.
                </p>
            @endif
        </div>

        {{-- Reapertura: la única salida cuando el viajero ya se cerró. Vive aquí
             y no sólo en el tablero porque un viajero terminado puede sacar a su
             orden del tablero, y con ella al botón que permitía deshacerlo. --}}
        @if ($lot->isFinished())
            @php $bloqueoReapertura = $lot->getReopenBlockReason(); @endphp

            <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                @if ($bloqueoReapertura)
                    <x-ui.note tone="warn" title="No se puede reabrir">{{ $bloqueoReapertura }}</x-ui.note>
                @elseif ($this->puedeReabrir())
                    <x-ui.btn variant="secondary" wire:click="openReopenModal">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Reabrir viajero
                    </x-ui.btn>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Lo devuelve al flujo y vuelve a aparecer en la lista de envío.
                    </p>
                @else
                    <x-ui.note tone="muted">
                        Este viajero ya está cerrado. Sólo Administración puede reabrirlo:
                        pídelo indicando el viajero y qué hay que corregir.
                    </x-ui.note>
                @endif
            </div>
        @endif
    </x-ui.section>

    {{-- Cambio de estado --}}
    @if ($showStatusModal)
        @php $permitidos = $this->allowedTransitions(); @endphp
        <x-ui-modal wire:key="modal-lot-status" title="Cambiar el estado del viajero"
            :subtitle="$lot->lot_number" close="closeStatusModal" maxWidth="2xl">

            <x-ui.section title="Nuevo estado" hint="Sólo se ofrecen los cambios válidos desde el estado actual.">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($statuses as $valor => $etiqueta)
                        @php $habilitado = in_array($valor, $permitidos, true); @endphp
                        <x-ui.choice wire:key="status-{{ $valor }}"
                            :tone="$tonoEstado[$valor] ?? 'neutral'"
                            :title="$etiqueta"
                            :desc="$valor === $lot->status ? 'Estado actual.' : ($habilitado ? 'Disponible desde el estado actual.' : 'No se puede pasar a este estado desde el actual.')"
                            :selected="$newStatus === $valor"
                            {{-- Ojo: aquí NO se puede usar @disabled(...). Es una directiva y deja
                                 PHP crudo dentro de la etiqueta <x-...>, con lo que Blade ya no
                                 reconoce el componente y lo escupe tal cual (invisible). --}}
                            :disabled="!$habilitado"
                            wire:click="setNewStatus('{{ $valor }}')" />
                    @endforeach
                </div>
            </x-ui.section>

            <x-slot:note>Cambiar el estado no borra nada: las pesadas y el empaque se conservan.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeStatusModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="updateLotStatus">Guardar estado</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Reapertura: qué se va a deshacer, y por qué --}}
    @if ($showReopenModal)
        <x-ui-modal wire:key="modal-reabrir-{{ $lot->id }}"
            title="Reabrir el viajero {{ $lot->lot_number }}"
            subtitle="Lo devuelve al flujo para volver a trabajarlo y reaparece en la lista de envío."
            close="closeReopenModal" maxWidth="2xl">

            @if (count($reopenCascade) > 0)
                <x-ui.note tone="warn" title="Esto no afecta sólo al viajero">
                    <p>Para reabrirlo hay que deshacer también:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($reopenCascade as $paso)
                            <li>{{ $paso }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2">Se hace todo junto o no se hace nada.</p>
                </x-ui.note>
            @else
                <x-ui.note tone="info">
                    El viajero volverá a estar abierto en Empaque. Todavía no está en ningún
                    packing slip, así que no hay nada más que deshacer.
                </x-ui.note>
            @endif

            <x-ui.section step="1" title="¿Por qué se reabre?" tone="accent"
                hint="Queda guardado en el historial junto a tu nombre. Es lo que explicará este cambio dentro de un año.">
                <x-ui.field label="Motivo" required
                    hint="Mínimo 10 caracteres. Sé concreto: qué estaba mal y quién lo detectó."
                    :error="$errors->first('reopenReason')">
                    <textarea wire:model="reopenReason" rows="3" class="w-full"
                        placeholder="Ej: el cliente reportó 20 piezas menos de las facturadas en este viajero."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>Todo lo que se deshaga queda registrado con tu nombre y la fecha.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeReopenModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="reopenLot">Reabrir</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Qué se ha hecho sobre este viajero. Mismo componente que la pantalla de
         Historial, en modo ficha: una sola implementación para las dos cosas. --}}
    <livewire:admin.history.history-explorer
        :entity-type="\App\Models\Lot::class"
        :entity-id="$lot->id"
        :key="'historial-lote-'.$lot->id" />
</x-ui.page>
