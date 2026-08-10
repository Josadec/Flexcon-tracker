<<<<<<< HEAD
<x-ui.page eyebrow="Empaque" title="Gestión de empaques"
    subtitle="Registros de empaque por lote: piezas empacadas, sobrantes y ajustes.">

    <x-slot:actions>
        @if ($lotsForCreate->isNotEmpty())
            <x-ui.btn variant="primary" wire:click="openCreateModal">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo registro
            </x-ui.btn>
        @endif
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total registros" :value="number_format($totalRecords)" />
        <x-ui.stat label="Piezas empacadas" :value="number_format($totalPackedPieces)" tone="good" />
        <x-ui.stat label="Piezas sobrantes" :value="number_format($totalSurplusPieces)" tone="warn"
            help="Piezas buenas que no entraron en la caja." />
        <x-ui.stat label="Sobrantes ajustados" :value="number_format($totalAdjustedSurplus)" tone="info"
            help="Sobrantes corregidos manualmente con una razón registrada." />
    </x-ui.stats>

=======
<x-ui.page eyebrow="Empaque" title="Gestión de empaque"
    subtitle="Tu mesa de trabajo: registrar cuántas piezas aprobadas por Calidad se empacaron de cada viajero y cuántas sobraron.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.packaging.index') }}">Panel de Empaque</x-ui.btn>
        <x-ui.btn variant="secondary" href="{{ route('admin.packaging.weighings') }}">Pesadas (CRIMP)</x-ui.btn>
        <x-ui.btn variant="primary" wire:click="openCreateModal">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo registro
        </x-ui.btn>
    </x-slot:actions>

>>>>>>> dba4729e63772ad9744b5ced48b25a0fdb214628
    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

<<<<<<< HEAD
    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por lote, WO, parte o comentario, o acota por WO o lote.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="searchTerm"
                        placeholder="Lote, WO, parte o comentario..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Orden de trabajo">
                <select wire:model.live="filterWorkOrderId" class="w-full">
                    <option value="">Todas las WO</option>
                    @foreach ($workOrdersForFilter as $wo)
                        <option value="{{ $wo->id }}">{{ $wo->purchaseOrder->wo ?? 'N/A' }} — {{ $wo->purchaseOrder->part->number ?? '' }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Lote">
                <select wire:model.live="filterLotId" class="w-full">
                    <option value="">Todos los lotes</option>
                    @foreach ($lotsForFilter as $lot)
                        <option value="{{ $lot->id }}">{{ $lot->lot_number }} — {{ $lot->workOrder->purchaseOrder->part->number ?? '' }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($searchTerm || $filterLotId || $filterWorkOrderId)
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th class="w-16">ID</x-ui.th>
                <x-ui.th>Lote</x-ui.th>
                <x-ui.th>WO</x-ui.th>
                <x-ui.th>Parte</x-ui.th>
                <x-ui.th align="right">Disponibles</x-ui.th>
                <x-ui.th align="right">Empacadas</x-ui.th>
                <x-ui.th align="right">Sobrantes</x-ui.th>
                <x-ui.th align="right">Ajustado</x-ui.th>
                <x-ui.th>Empacó</x-ui.th>
                <x-ui.th>Fecha</x-ui.th>
                <x-ui.th>Comentarios</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <tr wire:key="pr-{{ $record->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-500 dark:text-slate-400">{{ $record->id }}</td>
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">
                    {{ $record->lot->lot_number ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-sky-700 dark:text-sky-300">
                    {{ $record->lot->workOrder->purchaseOrder->wo ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $record->lot->workOrder->purchaseOrder->part->number ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums text-slate-600 dark:text-slate-300">
                    {{ number_format($record->available_pieces) }}
                </td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-green-700 dark:text-green-400">
                    {{ number_format($record->packed_pieces) }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums {{ $record->surplus_pieces > 0 ? 'font-semibold text-orange-700 dark:text-orange-400' : 'text-slate-400' }}">
                    {{ number_format($record->surplus_pieces) }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums">
                    @if ($record->adjusted_surplus !== null)
                        <span class="font-semibold text-amber-700 dark:text-amber-400">{{ number_format($record->adjusted_surplus) }}</span>
                        @if ($record->adjustment_reason)
                            <span class="block text-xs text-slate-400 dark:text-slate-500" title="{{ $record->adjustment_reason }}">{{ Str::limit($record->adjustment_reason, 20) }}</span>
                        @endif
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $record->packedBy->name ?? '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">
                    {{ $record->packed_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
                <td class="max-w-xs truncate px-4 py-3 text-slate-500 dark:text-slate-400" title="{{ $record->comments }}">
                    {{ $record->comments ?: '—' }}
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el registro #{{ $record->id }}"
                        delete="deleteRecord({{ $record->id }})"
                        deleteConfirm="¿Eliminar este registro de empaque? Esta acción no se puede deshacer.">
                        <x-ui.icon-btn tone="primary" label="Editar el registro #{{ $record->id }}"
                            wire:click="openEditModal({{ $record->id }})">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </x-ui.icon-btn>
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="12">
                    <x-ui.empty icon="box" title="No hay registros de empaque"
                        hint="{{ ($searchTerm || $filterLotId || $filterWorkOrderId) ? 'No se encontraron registros con los filtros aplicados.' : 'Los registros de empaque se crean desde la lista de envío o aquí.' }}" />
                </td>
            </tr>
        @endforelse

        @if ($records->hasPages())
            <x-slot:foot>{{ $records->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Alta / Edición de un registro --}}
    @if ($showModal)
        <x-ui-modal wire:key="modal-packaging-form"
            :title="$editingId ? 'Editar registro de empaque' : 'Nuevo registro de empaque'"
            subtitle="Captura las piezas empacadas y los sobrantes del lote."
            close="closeModal" maxWidth="2xl">

            <x-ui.section title="Lote" hint="El lote determina cuántas piezas hay disponibles para empacar.">
                @if ($editingId || $formLotId)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900/40">
                        <span class="font-semibold text-slate-900 dark:text-white">Lote {{ $modalLotNumber }}</span>
                        <span class="ml-2 text-slate-500 dark:text-slate-400">WO: {{ $modalWo }} — {{ $modalPartNumber }}</span>
                        <span class="ml-2 text-slate-500 dark:text-slate-400">({{ number_format($modalAvailable) }} pz disponibles)</span>
                    </div>
                    @error('formLotId') <p class="mt-1.5 text-[11px] font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @else
                    <x-ui.field label="Selecciona un lote" required :error="$errors->first('formLotId')">
                        <select wire:model.live="formLotId" class="w-full">
                            <option value="">Seleccionar lote...</option>
                            @foreach ($lotsForCreate as $lot)
                                <option value="{{ $lot->id }}">
                                    Lote {{ $lot->lot_number }} — {{ $lot->workOrder->purchaseOrder->wo ?? 'N/A' }} — {{ $lot->workOrder->purchaseOrder->part->number ?? '' }}
=======
    {{-- Lo que le importa a Empaque, no conteos generales del sistema --}}
    <x-ui.stats cols="5">
        <x-ui.stat label="Listos sin empacar" :value="number_format($stats['listos_sin_empacar'])"
            :tone="$stats['listos_sin_empacar'] > 0 ? 'warn' : 'good'"
            help="Viajeros con piezas aprobadas por Calidad y ningún registro de empaque. Los CRIMP no cuentan: se registran en Pesadas." />
        <x-ui.stat label="Piezas empacadas" :value="number_format($stats['empacadas'])" unit="pz" tone="good" />
        <x-ui.stat label="Sobrante declarado" :value="number_format($stats['sobrante'])" unit="pz"
            :tone="$stats['sobrante'] > 0 ? 'warn' : 'neutral'"
            help="Suma del sobrante de cada registro; cuando hay corrección, cuenta el valor corregido." />
        <x-ui.stat label="Registros corregidos" :value="number_format($stats['ajustados'])" tone="info"
            help="Registros donde se corrigió el sobrante contado. Cada uno lleva su motivo." />
        <x-ui.stat label="Registros" :value="number_format($stats['registros'])" />
    </x-ui.stats>

    {{-- Lo primero: viajeros esperando a Empaque, junto y accionable --}}
    <x-ui.section title="Lo que te toca ahora"
        hint="Viajeros con piezas aprobadas por Calidad que todavía no tienen ningún registro de empaque.">
        <x-slot:aside>
            <x-ui.badge :tone="$stats['listos_sin_empacar'] > 0 ? 'warn' : 'good'" dot>
                {{ number_format($stats['listos_sin_empacar']) }}
                {{ \Illuminate\Support\Str::plural('pendiente', $stats['listos_sin_empacar']) }}
            </x-ui.badge>
        </x-slot:aside>

        @if ($pendingLots->isNotEmpty())
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                @foreach ($pendingLots as $lot)
                    @php
                        $po = $lot->workOrder?->purchaseOrder;
                        $aprobadas = $lot->getPackagingAvailablePieces();
                    @endphp
                    <div wire:key="pend-{{ $lot->id }}"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-900 dark:text-white">Viajero {{ $lot->lot_number }}</span>
                                <x-ui.badge tone="good">{{ number_format($aprobadas) }} pz aprobadas</x-ui.badge>
                            </div>
                            <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">
                                WO {{ $po?->wo ?? $lot->workOrder?->wo_number ?? '—' }} · Parte {{ $po?->part?->number ?? '—' }}
                            </p>
                        </div>
                        <x-ui.btn variant="warning" size="sm" wire:click="openCreateForLot({{ $lot->id }})">
                            Registrar empaque
                        </x-ui.btn>
                    </div>
                @endforeach
            </div>

            @if ($stats['listos_sin_empacar'] > $pendingLots->count())
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Se muestran los {{ $pendingLots->count() }} más recientes de
                    {{ number_format($stats['listos_sin_empacar']) }} pendientes.
                </p>
            @endif
        @else
            <x-ui.empty icon="box" title="Nada esperando a Empaque"
                hint="Cuando Calidad apruebe piezas de un viajero, aparecerá aquí para que registres su empaque." />
        @endif
    </x-ui.section>

    {{-- Filtros --}}
    <x-ui.section title="Buscar registros" hint="Filtra por viajero, orden, parte, quien empacó o el comentario del registro.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="searchTerm"
                        placeholder="Viajero, WO, parte, quien empacó..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Orden de trabajo">
                <select wire:model.live="filterWorkOrderId" class="w-full">
                    <option value="">Todas las órdenes</option>
                    @foreach ($workOrdersForFilter as $wo)
                        <option value="{{ $wo->id }}">
                            {{ $wo->purchaseOrder?->wo ?? $wo->wo_number }} — {{ $wo->purchaseOrder?->part?->number ?? 'sin parte' }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Viajero" hint="Sólo los viajeros de la orden elegida.">
                <select wire:model.live="filterLotId" class="w-full">
                    <option value="">Todos los viajeros</option>
                    @foreach ($lotsForFilter as $lot)
                        <option value="{{ $lot->id }}">
                            {{ $lot->lot_number }} — {{ $lot->workOrder?->purchaseOrder?->part?->number ?? 'sin parte' }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Sobrante" hint="Registros donde se corrigió el sobrante contado.">
                <select wire:model.live="filterAdjusted" class="w-full">
                    <option value="">Todos</option>
                    <option value="yes">Sólo corregidos</option>
                    <option value="no">Sin corrección</option>
                </select>
            </x-ui.field>
        </div>

        @if ($searchTerm || $filterWorkOrderId || $filterLotId || $filterAdjusted)
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Registros --}}
    <x-ui.table title="Registros de empaque"
        hint="Cada renglón es una entrega empacada de un viajero. Las cantidades alimentan la decisión de cierre.">
        <x-slot:head>
            <tr>
                <x-ui.th>Viajero</x-ui.th>
                <x-ui.th align="right" class="w-32" sort="available_pieces" :field="$sortField" :direction="$sortDirection">Aprobadas</x-ui.th>
                <x-ui.th align="right" class="w-32" sort="packed_pieces" :field="$sortField" :direction="$sortDirection">Empacadas</x-ui.th>
                <x-ui.th align="right" class="w-40" sort="surplus_pieces" :field="$sortField" :direction="$sortDirection">Sobrante</x-ui.th>
                <x-ui.th class="w-48" sort="packed_at" :field="$sortField" :direction="$sortDirection">Empacó</x-ui.th>
                <x-ui.th>Comentario</x-ui.th>
                <x-ui.th align="right" class="w-28">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            @php
                $lot = $record->lot;
                $po = $lot?->workOrder?->purchaseOrder;
                $corregido = $record->adjusted_surplus !== null;
                $sobrante = $record->effective_surplus;
                $bloqueo = $this->deleteBlockReason($record);
            @endphp
            <tr wire:key="rec-{{ $record->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">
                        {{ $lot?->lot_number ?? 'Viajero eliminado' }}
                    </span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        WO {{ $po?->wo ?? '—' }} · {{ $po?->part?->number ?? 'sin parte' }}
                    </span>
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">
                    {{ number_format((int) $record->available_pieces) }}
                </td>

                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-semibold text-green-700 dark:text-green-300">
                    {{ number_format((int) $record->packed_pieces) }}
                </td>

                <td class="px-4 py-3 text-right">
                    <span class="block tabular-nums font-semibold {{ $sobrante > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-slate-500 dark:text-slate-400' }}">
                        {{ number_format($sobrante) }}
                    </span>
                    @if ($corregido)
                        <span class="mt-0.5 block text-[11px] leading-4 text-amber-700 dark:text-amber-300"
                            title="Contado: {{ number_format((int) $record->surplus_pieces) }} pz. Motivo: {{ $record->adjustment_reason }}">
                            Corregido desde {{ number_format((int) $record->surplus_pieces) }}
                        </span>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <span class="block text-sm text-slate-700 dark:text-slate-200">{{ $record->packedBy?->name ?? '—' }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        {{ $record->packed_at?->format('d/m/Y H:i') ?? 'sin fecha' }}
                    </span>
                </td>

                <td class="px-4 py-3">
                    <span class="block max-w-xs truncate text-xs text-slate-500 dark:text-slate-400"
                        title="{{ $record->comments }}">{{ $record->comments ?: '—' }}</span>
                </td>

                <td class="px-4 py-3">
                    <x-ui.row-actions label="el registro del viajero {{ $lot?->lot_number ?? '' }}">
                        <x-ui.icon-btn tone="primary" label="Editar el registro del viajero {{ $lot?->lot_number ?? '' }}"
                            wire:click="openEditModal({{ $record->id }})">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </x-ui.icon-btn>

                        @if ($bloqueo)
                            <span class="ui-icon-btn cursor-not-allowed bg-slate-100 text-slate-300 dark:bg-slate-700 dark:text-slate-500"
                                title="{{ $bloqueo }}" aria-label="{{ $bloqueo }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                        @else
                            <x-ui.icon-btn tone="danger" label="Eliminar el registro del viajero {{ $lot?->lot_number ?? '' }}"
                                wire:click="confirmDelete({{ $record->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </x-ui.icon-btn>
                        @endif
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    @if ($searchTerm || $filterWorkOrderId || $filterLotId || $filterAdjusted)
                        <x-ui.empty icon="search" title="Ningún registro con esos filtros"
                            hint="Ajusta la búsqueda o límpiala para ver todos los registros de empaque." />
                    @else
                        <x-ui.empty icon="doc" title="Todavía no hay registros de empaque"
                            hint="Se crean desde el tablero de la lista de envío o aquí mismo, con «Nuevo registro»." />
                    @endif
                </td>
            </tr>
        @endforelse

        @if ($records->hasPages())
            <x-slot:foot>{{ $records->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Alta / edición de registro --}}
    @if ($showModal)
        @php
            $lotSel = $this->formLot;
            $poSel = $lotSel?->workOrder?->purchaseOrder;
            $restante = $this->modalRemaining;
            $aprobadasSel = $lotSel?->getPackagingAvailablePieces() ?? 0;
            $cerrado = $lotSel && ($lotSel->hasClosureDecision() || $lotSel->isViajeroReceived());
        @endphp

        <x-ui-modal wire:key="modal-packaging-{{ $editingId ?? 'new' }}"
            :title="$editingId ? 'Editar registro de empaque' : 'Nuevo registro de empaque'"
            subtitle="Lo que registres aquí es lo que Materiales usará para cerrar el viajero y recibir el sobrante."
            close="closeModal" maxWidth="3xl">

            @if ($lotSel)
                <x-slot:context>
                    <x-ui-modal.ctx label="Viajero" :value="$lotSel->lot_number" />
                    <x-ui-modal.ctx label="Orden" :value="$poSel?->wo ?? '—'" />
                    <x-ui-modal.ctx label="Parte" :value="$poSel?->part?->number ?? '—'" />
                    <x-ui-modal.ctx label="Disponible" :value="number_format($restante).' pz'" />
                </x-slot:context>
            @endif

            {{-- Paso 1: el viajero --}}
            <x-ui.section step="1" title="¿De qué viajero es este empaque?"
                hint="Sólo aparecen los viajeros con piezas aprobadas por Calidad: antes de eso no hay nada que empacar.">

                @if ($lotSel)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                        <div class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900 dark:text-white">Viajero {{ $lotSel->lot_number }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                WO {{ $poSel?->wo ?? '—' }} · Parte {{ $poSel?->part?->number ?? '—' }}
                            </span>
                        </div>
                        @unless ($editingId)
                            <x-ui.btn variant="ghost" size="sm" wire:click="clearLotSelection">Cambiar viajero</x-ui.btn>
                        @endunless
                    </div>

                    @if ($poSel?->part?->is_crimp)
                        <x-ui.note tone="warn" class="mt-3">
                            Este viajero es <strong>CRIMP</strong>: su empaque normalmente se registra con doble pesada en
                            «Pesadas de Empaque». Usa esta pantalla sólo si sabes que le corresponde un registro simple.
                        </x-ui.note>
                    @endif

                    @if ($cerrado)
                        <x-ui.note tone="warn" class="mt-3">
                            El viajero ya fue cerrado o recibido por Materiales. Puedes corregir las cantidades, pero la
                            decisión que ya se tomó <strong>no se recalcula sola</strong>: avisa a Materiales si cambian.
                        </x-ui.note>
                    @endif
                @else
                    <x-ui.field label="Viajero" required :error="$errors->first('formLotId')">
                        <select wire:model.live="formLotId" class="w-full">
                            <option value="">Elige el viajero...</option>
                            @foreach ($lotsForCreate as $lot)
                                <option value="{{ $lot->id }}">
                                    {{ $lot->lot_number }} — WO {{ $lot->workOrder?->purchaseOrder?->wo ?? '—' }} — {{ $lot->workOrder?->purchaseOrder?->part?->number ?? 'sin parte' }}
>>>>>>> dba4729e63772ad9744b5ced48b25a0fdb214628
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>
<<<<<<< HEAD
                @endif
            </x-ui.section>

            <x-ui.section title="Cantidades" hint="Piezas empacadas y las que quedaron como sobrante.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Piezas empacadas" required :error="$errors->first('formPackedPieces')">
                        <input type="number" wire:model="formPackedPieces" min="0" class="w-full">
                    </x-ui.field>

                    <x-ui.field label="Piezas sobrantes" required :error="$errors->first('formSurplusPieces')">
                        <input type="number" wire:model="formSurplusPieces" min="0" class="w-full">
                    </x-ui.field>

                    <x-ui.field label="Sobrante ajustado" optional :error="$errors->first('formAdjustedSurplus')"
                        hint="Déjalo vacío si no aplica.">
                        <input type="number" wire:model.live="formAdjustedSurplus" min="0" class="w-full" placeholder="—">
                    </x-ui.field>

                    @if ($formAdjustedSurplus !== null && $formAdjustedSurplus !== '')
                        <x-ui.field label="Razón del ajuste" required :error="$errors->first('formAdjustmentReason')">
                            <textarea wire:model="formAdjustmentReason" rows="2" class="w-full"
                                placeholder="Indica la razón del ajuste..."></textarea>
                        </x-ui.field>
                    @endif
                </div>
            </x-ui.section>

            <x-ui.section title="Registro" hint="Fecha del empaque y observaciones.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Fecha y hora de empaque" required :error="$errors->first('formPackedAt')">
                        <input type="datetime-local" wire:model="formPackedAt" class="w-full">
                    </x-ui.field>

                    <x-ui.field label="Comentarios" optional :error="$errors->first('formComments')">
                        <textarea wire:model="formComments" rows="2" class="w-full" placeholder="Observaciones..."></textarea>
                    </x-ui.field>
                </div>
            </x-ui.section>

            <x-slot:note>
                {{ $editingId ? 'Se actualizará el registro de empaque del lote.' : 'Se creará un nuevo registro de empaque para el lote seleccionado.' }}
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="save">{{ $editingId ? 'Actualizar' : 'Crear' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
=======

                    @if ($lotsForCreate->isEmpty())
                        <x-ui.note tone="info" class="mt-3">
                            No hay viajeros listos para empacar. Calidad tiene que aprobar piezas antes de que Empaque
                            pueda registrar algo.
                        </x-ui.note>
                    @endif
                @endif

                @error('formLotId')
                    @if ($lotSel)
                        <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
                    @endif
                @enderror
            </x-ui.section>

            {{-- Paso 2: las cantidades --}}
            @if ($lotSel)
                <x-ui.section step="2" title="¿Cuántas piezas empacaste?" tone="accent"
                    hint="Empacadas + sobrante no pueden pasar de lo que queda del viajero.">

                    <x-ui.stats cols="3" class="mb-4">
                        <x-ui.stat label="Aprobadas por Calidad" :value="number_format($aprobadasSel)" unit="pz" tone="info" />
                        <x-ui.stat label="Queda por registrar" :value="number_format($restante)" unit="pz"
                            :tone="$restante > 0 ? 'warn' : 'good'"
                            help="Aprobadas menos lo que ya está empacado o declarado como sobrante en otros registros." />
                        <x-ui.stat label="Quedaría sin registrar" :value="number_format($this->modalLeftover)" unit="pz"
                            :tone="$this->modalLeftover > 0 ? 'warn' : 'good'"
                            help="Lo que faltaría por capturar si guardas con estos números." />
                    </x-ui.stats>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.field label="Piezas empacadas" required
                            :hint="'Máximo disponible: '.number_format($restante).' pz.'"
                            :error="$errors->first('formPackedPieces')">
                            <input type="number" min="1" step="1" wire:model.live.debounce.400ms="formPackedPieces"
                                class="w-full text-right tabular-nums">
                        </x-ui.field>

                        <x-ui.field label="Piezas sobrantes" optional
                            hint="Piezas buenas que no entraron en el empaque y regresan a Materiales."
                            :error="$errors->first('formSurplusPieces')">
                            <input type="number" min="0" step="1" wire:model.live.debounce.400ms="formSurplusPieces"
                                class="w-full text-right tabular-nums">
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Fecha y hora del empaque" required class="mt-4"
                        hint="No puede estar en el futuro."
                        :error="$errors->first('formPackedAt')">
                        <input type="datetime-local" wire:model="formPackedAt" class="w-full">
                    </x-ui.field>
                </x-ui.section>

                {{-- Paso 3: correcciones y notas --}}
                <x-ui.section step="3" title="Corrección del sobrante y notas"
                    hint="Sólo si el conteo físico no coincidió con lo declarado. Todo lo demás es opcional.">

                    <x-ui.field label="Sobrante corregido" optional
                        hint="Déjalo vacío si el sobrante contado es correcto. Si lo llenas, reemplaza al sobrante en todos los cálculos."
                        :error="$errors->first('formAdjustedSurplus')">
                        <input type="number" min="0" step="1" wire:model.live.debounce.400ms="formAdjustedSurplus"
                            placeholder="Vacío = sin corrección" class="w-full text-right tabular-nums">
                    </x-ui.field>

                    @if ($formAdjustedSurplus !== null)
                        <x-ui.field label="¿Por qué se corrige?" required class="mt-4"
                            hint="Este texto queda en el historial del viajero: escribe qué pasó, no sólo «ajuste»."
                            :error="$errors->first('formAdjustmentReason')">
                            <textarea wire:model="formAdjustmentReason" rows="2" class="w-full"
                                placeholder="Ej: al recontar, 12 piezas estaban dañadas y no regresan a Materiales."></textarea>
                        </x-ui.field>
                    @endif

                    <x-ui.field label="Comentarios" optional class="mt-4" :error="$errors->first('formComments')">
                        <textarea wire:model="formComments" rows="2" class="w-full"
                            placeholder="Observaciones del empaque..."></textarea>
                    </x-ui.field>
                </x-ui.section>
            @endif

            <x-slot:note>
                @if ($lotSel)
                    Al guardar, estas piezas dejan de estar disponibles para otro registro del mismo viajero.
                @else
                    Elige primero el viajero: las cantidades se validan contra lo que Calidad aprobó.
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="save" :disabled="! $lotSel">
                    {{ $editingId ? 'Guardar cambios' : 'Registrar empaque' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Borrado de registro --}}
    @if ($showDeleteModal && $this->deletingRecord)
        @php $recDel = $this->deletingRecord; @endphp
        <x-ui-modal wire:key="modal-delete-packaging" title="Eliminar registro de empaque"
            :subtitle="'Viajero '.($recDel->lot?->lot_number ?? '—')" close="cancelDelete" maxWidth="lg">

            <x-ui.note tone="danger">
                Se eliminarán <strong>{{ number_format((int) $recDel->packed_pieces) }} piezas empacadas</strong>
                @if ($recDel->effective_surplus > 0)
                    y <strong>{{ number_format($recDel->effective_surplus) }} de sobrante</strong>
                @endif
                del historial del viajero. Esas piezas volverán a contar como disponibles.
            </x-ui.note>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelDelete">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="deleteRecord">Eliminar registro</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
>>>>>>> dba4729e63772ad9744b5ced48b25a0fdb214628
</x-ui.page>
