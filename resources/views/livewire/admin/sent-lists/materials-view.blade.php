{{--
    VISTA DE MATERIALES DENTRO DE UNA LISTA PRELIMINAR

    Se monta como pestaña del detalle de la lista, así que NO lleva
    <x-ui.page>: la cabecera la pone la pantalla contenedora.

    Materiales divide cada WO en lotes/viajeros, captura los lotes de CRIMP y
    libera el material para que Inspección pueda empezar.
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

    {{-- Rechazos que devolvieron la lista a Materiales --}}
    @if ($sentList->unresolvedRejections->isNotEmpty())
        <x-ui.section title="Rechazos por resolver"
            hint="Otro departamento devolvió estos lotes. La lista no avanza hasta corregirlos.">
            <ul class="divide-y divide-slate-200 rounded-lg border border-red-300 dark:divide-slate-700 dark:border-red-800">
                @foreach ($sentList->unresolvedRejections as $rejection)
                    <li class="bg-red-50 px-4 py-3 dark:bg-red-950/30">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-red-900 dark:text-red-100">
                                    @if ($rejection->lot)
                                        Lote {{ $rejection->lot->lot_number }}
                                    @else
                                        Lista completa
                                    @endif
                                    <span class="font-normal">· rechazó {{ $rejection->rejectedBy->name ?? 'sin usuario' }}</span>
                                </p>
                                <p class="mt-0.5 text-xs leading-4 text-red-800 dark:text-red-200">
                                    {{ $rejection->reason ?? $rejection->comments ?? 'Sin motivo capturado' }}
                                </p>
                            </div>
                            <span class="shrink-0 text-xs text-red-700 dark:text-red-300">
                                {{ $rejection->created_at?->format('d/m/Y H:i') }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.section>
    @endif

    @php
        $rejectedLotIds = $sentList->unresolvedRejections->pluck('lot_id')->filter()->all();
        $allLots        = $workOrders->flatMap->lots;
        $releasedCount  = $allLots->where('material_status', 'released')->count();
        $woWithoutLots  = $workOrders->filter(fn ($w) => $w->lots->isEmpty())->count();
    @endphp

    {{-- Avance del área --}}
    <x-ui.section title="Avance de Materiales"
        hint="Cada WO necesita sus lotes; cada lote, su material liberado.">
        <x-ui.stats cols="4">
            <x-ui.stat label="Work Orders" :value="$workOrders->count()" />
            <x-ui.stat label="Lotes creados" :value="$allLots->count()" tone="info" />
            <x-ui.stat label="Material liberado" :value="$releasedCount" tone="good"
                :help="'de '.$allLots->count().' lotes'" />
            <x-ui.stat label="WO sin lotes" :value="$woWithoutLots"
                :tone="$woWithoutLots > 0 ? 'bad' : 'good'"
                help="Una WO sin lotes no puede avanzar en el flujo." />
        </x-ui.stats>
    </x-ui.section>

    {{-- Work Orders --}}
    <x-ui.table title="Work Orders de la lista"
        hint="Divide cada orden en lotes o viajeros y libera su material.">
        <x-slot:head>
            <tr>
                <x-ui.th class="w-32">WO</x-ui.th>
                <x-ui.th class="w-44">Parte</x-ui.th>
                <x-ui.th class="w-28" align="right">Cant. WO</x-ui.th>
                <x-ui.th>Lotes / viajeros</x-ui.th>
                <x-ui.th>Lotes de CRIMP</x-ui.th>
                <x-ui.th class="w-36">Estado</x-ui.th>
                <x-ui.th class="w-40" align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($workOrders as $wo)
            @php
                $isCrimp      = $wo->purchaseOrder->part->is_crimp ?? false;
                $hasLots      = $wo->lots->isNotEmpty();
                $hasCrimpLots = $wo->lots->contains(fn ($l) => $l->crimpLots->isNotEmpty());
                $semaphore    = ! $hasLots ? 'red' : (($isCrimp && ! $hasCrimpLots) ? 'yellow' : 'green');
                $woRejected   = $wo->lots->pluck('id')->intersect($rejectedLotIds)->isNotEmpty();
            @endphp
            <tr wire:key="wo-{{ $wo->id }}"
                class="hover:bg-slate-50 dark:hover:bg-slate-700/30 {{ $woRejected ? 'bg-red-50 dark:bg-red-950/20' : '' }}">

                <td class="whitespace-nowrap px-4 py-3">
                    <a href="{{ route('admin.sent-lists.display.wo', $wo->id) }}" wire:navigate
                        class="font-bold text-sky-700 underline-offset-2 hover:underline dark:text-sky-300">
                        {{ $wo->purchaseOrder->wo ?? $wo->wo_number }}
                    </a>
                </td>

                <td class="px-4 py-3">
                    <span class="block font-medium text-slate-900 dark:text-white">{{ $wo->purchaseOrder->part->number ?? '—' }}</span>
                    <span class="block max-w-xs truncate text-xs text-slate-500 dark:text-slate-400">{{ $wo->purchaseOrder->part->description ?? '' }}</span>
                    @if ($isCrimp)
                        <x-ui.badge tone="accent" class="mt-1">CRIMP</x-ui.badge>
                    @endif
                </td>

                <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ number_format($wo->original_quantity) }}
                </td>

                {{-- Cada lote abre su modal de material --}}
                <td class="px-4 py-3">
                    @if (! $hasLots)
                        <span class="text-xs text-slate-400">Sin lotes creados</span>
                    @else
                        <div class="space-y-1.5">
                            @foreach ($wo->lots as $lot)
                                @php
                                    $isRejectedLot = in_array($lot->id, $rejectedLotIds);
                                    $matSt = $lot->material_status ?? 'pending';
                                    $matState = match ($matSt) {
                                        'released' => 'done',
                                        'rejected' => 'error',
                                        default    => 'pending',
                                    };
                                    $matLabel = match ($matSt) {
                                        'released' => 'Liberado',
                                        'rejected' => 'Rechazado',
                                        default    => 'Pendiente',
                                    };
                                @endphp
                                <div wire:key="lot-{{ $lot->id }}" class="flex items-center gap-2">
                                    <span class="w-28 shrink-0">
                                        <x-ui.row-action :state="$matState"
                                            :label="$lot->lot_number"
                                            :hint="'Material: '.$matLabel.' · '.number_format($lot->quantity).' pz'"
                                            wire:click="openMaterialModal({{ $lot->id }})" />
                                    </span>
                                    <span class="text-xs tabular-nums text-slate-500 dark:text-slate-400">
                                        {{ number_format($lot->quantity) }} pz
                                    </span>
                                    @if ($isRejectedLot)
                                        <x-ui.badge tone="bad">Rechazado</x-ui.badge>
                                    @endif
                                </div>
                            @endforeach

                            <p class="pt-1 text-xs text-slate-500 dark:text-slate-400">
                                Suma <strong class="tabular-nums {{ $wo->lots->sum('quantity') != $wo->original_quantity ? 'text-amber-700 dark:text-amber-400' : 'text-slate-700 dark:text-slate-200' }}">{{ number_format($wo->lots->sum('quantity')) }}</strong>
                                de {{ number_format($wo->original_quantity) }}
                            </p>
                        </div>
                    @endif
                </td>

                {{-- Lotes de CRIMP --}}
                <td class="px-4 py-3">
                    @if (! $isCrimp)
                        <span class="text-xs text-slate-400">No aplica</span>
                    @elseif (! $hasLots)
                        <span class="text-xs text-slate-400">Crea primero los viajeros</span>
                    @else
                        <div class="space-y-2">
                            @foreach ($wo->lots as $lot)
                                <div wire:key="cl-{{ $lot->id }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Viajero {{ $lot->lot_number }}</span>
                                        <x-ui.btn variant="accent" size="sm" wire:click="openCrimpLotModal({{ $lot->id }})">
                                            Gestionar
                                        </x-ui.btn>
                                    </div>
                                    @if ($lot->crimpLots->isEmpty())
                                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Sin lotes capturados</p>
                                    @else
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($lot->crimpLots as $cl)
                                                <x-ui.badge tone="accent"
                                                    title="{{ number_format($cl->quantity) }} pz{{ $cl->lote_fabricante ? ' · fabricante '.$cl->lote_fabricante : '' }}">
                                                    {{ $cl->crimp_lot_number }}
                                                </x-ui.badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </td>

                <td class="px-4 py-3">
                    @if ($semaphore === 'green')
                        <x-ui.badge tone="good" dot>Listo</x-ui.badge>
                    @elseif ($semaphore === 'yellow')
                        <x-ui.badge tone="warn" dot>Sin lotes CRIMP</x-ui.badge>
                    @else
                        <x-ui.badge tone="bad" dot>Sin lotes</x-ui.badge>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <div class="flex justify-end">
                        <x-ui.btn variant="primary" size="sm" wire:click="openLotModal({{ $wo->id }})">
                            Gestionar lotes
                        </x-ui.btn>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty title="No hay Work Orders en esta lista"
                        hint="Las órdenes se agregan a la lista desde el wizard de capacidad." />
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    {{-- Cierre de la etapa --}}
    <x-ui.section title="Cerrar Materiales"
        hint="Al enviar, la lista pasa a Inspección para que Calidad revise los lotes.">
        @if ($woWithoutLots > 0)
            <x-ui.note tone="warn" class="mb-4">
                Hay <strong>{{ $woWithoutLots }} {{ Str::plural('orden', $woWithoutLots) }}</strong> sin lotes creados.
                Esas órdenes no van a poder avanzar en el flujo.
            </x-ui.note>
        @endif

        <div class="flex justify-end">
            <x-ui.btn variant="success" wire:click="openSendModal">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Enviar a Inspección
            </x-ui.btn>
        </div>
    </x-ui.section>

    {{-- Gestión de lotes / viajeros --}}
    @if ($showLotModal)
        @php
            $lotWo = $workOrders->firstWhere('id', $selectedWorkOrderId);
            $lotsAsignado = collect($lots)->sum(fn ($r) => (int) ($r['quantity'] ?? 0));
            $lotsRestante = (int) ($lotWo->original_quantity ?? 0) - $lotsAsignado;
        @endphp
        <x-ui-modal wire:key="modal-lots" title="Lotes y viajeros"
            subtitle="Divide la orden en las partes que se van a producir por separado."
            close="closeLotModal" maxWidth="4xl">

            @if ($lotWo)
                <x-slot:context>
                    <x-ui-modal.ctx label="WO" :value="$lotWo->purchaseOrder->wo ?? $lotWo->wo_number" />
                    <x-ui-modal.ctx label="Parte" :value="$lotWo->purchaseOrder->part->number ?? '—'" />
                    <x-ui-modal.ctx label="Cantidad del WO" :value="number_format($lotWo->original_quantity).' pz'" />
                    <x-ui-modal.ctx label="Registros" :value="count($lots).' lotes'" />
                </x-slot:context>
            @endif

            <x-ui.section title="Reparto de la orden" hint="La suma de los lotes debería igualar la cantidad del WO.">
                <x-ui.stats cols="3">
                    <x-ui.stat label="Cantidad del WO" :value="number_format($lotWo->original_quantity ?? 0)" unit="pz" />
                    <x-ui.stat label="Asignado a lotes" :value="number_format($lotsAsignado)" unit="pz" tone="info" />
                    <x-ui.stat label="Restante" :value="number_format($lotsRestante)" unit="pz"
                        :tone="$lotsRestante === 0 ? 'good' : ($lotsRestante < 0 ? 'bad' : 'warn')" />
                </x-ui.stats>

                @if ($lotsRestante < 0)
                    <x-ui.note tone="danger" class="mt-4">
                        La suma de los lotes sobrepasa la cantidad del WO por <strong>{{ number_format(abs($lotsRestante)) }} pz</strong>.
                    </x-ui.note>
                @elseif ($lotsRestante === 0 && $lotsAsignado > 0)
                    <x-ui.note tone="success" class="mt-4">La suma coincide con la cantidad del WO.</x-ui.note>
                @endif
            </x-ui.section>

            <x-ui.section title="Lotes de la orden" hint="Un renglón por lote o viajero.">
                <x-slot:aside>
                    <x-ui.btn variant="success" size="sm" wire:click="addLotRow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Agregar lote
                    </x-ui.btn>
                </x-slot:aside>

                @if (empty($lots))
                    <x-ui.empty icon="box" title="Sin lotes"
                        hint="Agrega al menos uno para que la orden pueda avanzar." />
                @else
                    <div class="space-y-3">
                        @foreach ($lots as $index => $row)
                            <div wire:key="lotrow-{{ $index }}"
                                class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-start gap-3">
                                    <span class="mt-6 flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white dark:bg-slate-200 dark:text-slate-900">
                                        {{ $index + 1 }}
                                    </span>

                                    <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                                        <x-ui.field label="No. de lote / viajero" required
                                            :error="$errors->first('lots.'.$index.'.number')">
                                            <input type="text" wire:model="lots.{{ $index }}.number"
                                                placeholder="Ej: 001" class="w-full">
                                        </x-ui.field>

                                        <x-ui.field label="Cantidad" required
                                            :error="$errors->first('lots.'.$index.'.quantity')">
                                            <input type="number" min="1" wire:model="lots.{{ $index }}.quantity"
                                                placeholder="0" class="w-full text-right font-bold tabular-nums">
                                        </x-ui.field>
                                    </div>

                                    <div class="pt-6">
                                        <x-ui.icon-btn tone="danger" label="Eliminar el lote {{ $index + 1 }}"
                                            wire:click="removeLotRow({{ $index }})"
                                            wire:confirm="¿Eliminar este lote?">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </x-ui.icon-btn>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.section>

            <x-slot:note>Verifica que la suma de las cantidades corresponda a la orden antes de guardar.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeLotModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveLots"
                    wire:loading.attr="disabled" wire:target="saveLots">Guardar lotes</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Lotes de CRIMP --}}
    @if ($showCrimpLotModal)
        @php
            $asignado = collect($crimpLots)->sum(fn ($r) => (int) ($r['quantity'] ?? 0));
            $restante = (int) $crimpLotViajeroQty - $asignado;
        @endphp
        <x-ui-modal wire:key="modal-crimp-lots" title="Lotes de CRIMP"
            :subtitle="$crimpLotViajeroLabel.' · cantidad del viajero: '.number_format($crimpLotViajeroQty).' pz'"
            close="closeCrimpLotModal" maxWidth="6xl">

            <x-ui.section title="Reparto del viajero" hint="La suma de los lotes debería igualar la cantidad del viajero.">
                <x-ui.stats cols="3">
                    <x-ui.stat label="Cantidad del viajero" :value="number_format($crimpLotViajeroQty)" unit="pz" />
                    <x-ui.stat label="Asignado a lotes" :value="number_format($asignado)" unit="pz" tone="accent" />
                    <x-ui.stat label="Restante" :value="number_format($restante)" unit="pz"
                        :tone="$restante === 0 ? 'good' : ($restante < 0 ? 'bad' : 'warn')" />
                </x-ui.stats>

                @if ($restante < 0)
                    <x-ui.note tone="danger" class="mt-4">
                        La suma sobrepasa la cantidad del viajero por <strong>{{ number_format(abs($restante)) }} pz</strong>.
                    </x-ui.note>
                @elseif ($restante === 0 && $asignado > 0)
                    <x-ui.note tone="success" class="mt-4">La suma coincide con la cantidad del viajero.</x-ui.note>
                @endif
            </x-ui.section>

            <x-ui.section title="Lotes de CRIMP del viajero"
                hint="Empaque los necesita para poder registrar el Paso 5.">
                <x-slot:aside>
                    <x-ui.btn variant="accent" size="sm" wire:click="addCrimpLotRow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Agregar lote
                    </x-ui.btn>
                </x-slot:aside>

                @if (empty($crimpLots))
                    <x-ui.empty icon="box" title="Sin lotes de CRIMP"
                        hint="Sin ellos, Empaque no puede registrar el Paso 5 de este viajero." />
                @else
                    <div class="space-y-3">
                        @foreach ($crimpLots as $index => $cl)
                            <div wire:key="crimprow-{{ $index }}"
                                class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-start gap-3">
                                    <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                        <x-ui.field label="No. lote CRIMP" required
                                            :error="$errors->first('crimpLots.'.$index.'.crimp_lot_number')">
                                            <input type="text" wire:model="crimpLots.{{ $index }}.crimp_lot_number"
                                                placeholder="Ej: CL-001" class="w-full">
                                        </x-ui.field>

                                        <x-ui.field label="Lote de fabricante" optional>
                                            <input type="text" wire:model="crimpLots.{{ $index }}.lote_fabricante"
                                                placeholder="Ej: FAB-2024" class="w-full">
                                        </x-ui.field>

                                        <x-ui.field label="Cantidad" required
                                            :error="$errors->first('crimpLots.'.$index.'.quantity')">
                                            <input type="number" min="1" placeholder="0"
                                                wire:model.live.debounce.400ms="crimpLots.{{ $index }}.quantity"
                                                class="w-full text-right font-bold tabular-nums">
                                        </x-ui.field>

                                        <x-ui.field label="Comentarios" optional>
                                            <input type="text" wire:model="crimpLots.{{ $index }}.comments"
                                                placeholder="Observaciones..." class="w-full">
                                        </x-ui.field>
                                    </div>

                                    <div class="pt-6">
                                        <x-ui.icon-btn tone="danger" label="Eliminar el lote de CRIMP {{ $index + 1 }}"
                                            wire:click="removeCrimpLotRow({{ $index }})"
                                            wire:confirm="¿Eliminar este lote de CRIMP?">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </x-ui.icon-btn>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.section>

            <x-slot:note>El lote de fabricante es opcional; el número y la cantidad no.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeCrimpLotModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="accent" wire:click="saveCrimpLots"
                    wire:loading.attr="disabled" wire:target="saveCrimpLots">Guardar lotes de CRIMP</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Estado del material de un lote --}}
    @if ($showMaterialModal)
        @php $matLot = $workOrders->flatMap->lots->firstWhere('id', $materialLotId); @endphp
        <x-ui-modal wire:key="modal-material" title="Estado del material"
            :subtitle="$matLot ? 'Lote '.$matLot->lot_number.' · '.number_format($matLot->quantity).' pz' : null"
            close="closeMaterialModal" maxWidth="2xl">

            <x-ui.section title="¿El material está listo?"
                hint="Sin material liberado, Calidad no puede inspeccionar el lote.">
                <div class="grid grid-cols-1 gap-3">
                    <x-ui.choice tone="warn" title="Pendiente"
                        desc="Todavía no se revisa. El lote no avanza."
                        :selected="$materialStatus === 'pending'"
                        wire:click="$set('materialStatus', 'pending')" />
                    <x-ui.choice tone="good" title="Aprobado / liberado"
                        desc="El material está completo y correcto. Habilita la inspección."
                        :selected="$materialStatus === 'released'"
                        wire:click="$set('materialStatus', 'released')" />
                    <x-ui.choice tone="bad" title="Rechazado"
                        desc="Falta material o viene mal. El lote se detiene hasta corregirlo."
                        :selected="$materialStatus === 'rejected'"
                        wire:click="$set('materialStatus', 'rejected')" />
                </div>
            </x-ui.section>

            <x-slot:note>El estado mueve el semáforo de <strong>Material</strong> en el tablero de piso.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeMaterialModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveMaterial"
                    wire:loading.attr="disabled" wire:target="saveMaterial">Guardar estado</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Enviar a Inspección --}}
    @if ($showSendModal)
        <x-ui-modal wire:key="modal-send-inspection" title="Enviar a Inspección"
            subtitle="La lista pasa a Calidad para que revise los lotes antes de producir."
            close="closeSendModal" maxWidth="3xl">

            <x-ui.section title="Lo que se envía">
                <x-ui.stats cols="3">
                    <x-ui.stat label="Work Orders" :value="$workOrders->count()" />
                    <x-ui.stat label="Lotes" :value="$allLots->count()" tone="info" />
                    <x-ui.stat label="Con material liberado" :value="$releasedCount" tone="good" />
                </x-ui.stats>

                @if ($woWithoutLots > 0)
                    <x-ui.note tone="warn" class="mt-4">
                        <strong>{{ $woWithoutLots }} {{ Str::plural('orden', $woWithoutLots) }}</strong> sin lotes creados no podrán avanzar.
                    </x-ui.note>
                @endif
            </x-ui.section>

            <x-ui.section title="Notas para Inspección">
                <x-ui.field label="Notas de envío" optional
                    hint="Cualquier detalle que Calidad deba saber antes de inspeccionar.">
                    <textarea wire:model="sendNotes" rows="3" class="w-full"
                        placeholder="Observaciones para Inspección..."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>Al enviar, la lista cambia de departamento y Calidad puede empezar a inspeccionar.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeSendModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="success" wire:click="sendToInspection"
                    wire:loading.attr="disabled" wire:target="sendToInspection">Enviar a Inspección</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</div>
