<x-ui.page eyebrow="Producción" title="Pesadas de producción"
    subtitle="Registro de lo que se fabricó por viajero. De aquí sale lo que Calidad verifica después.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.production.index') }}">Panel de Producción</x-ui.btn>
        <x-ui.btn variant="primary" wire:click="openCreateModal">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Registrar pesada
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Pesadas registradas" :value="number_format($stats['total'])" />
        <x-ui.stat label="Piezas acumuladas" :value="number_format($stats['piezas'])" tone="info" />
        <x-ui.stat label="Pesadas de hoy" :value="number_format($stats['hoy'])" tone="accent" />
        <x-ui.stat label="Piezas de hoy" :value="number_format($stats['piezas_hoy'])" tone="good" />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por viajero, orden o número de parte, y acota por fecha de pesada.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Viajero, orden o parte..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Pesadas desde">
                <input type="date" wire:model.live="filterFrom" class="w-full">
            </x-ui.field>

            <x-ui.field label="Hasta">
                <input type="date" wire:model.live="filterTo" class="w-full">
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([15, 25, 50, 100] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search || $filterFrom || $filterTo)
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
                <x-ui.th sort="weighed_at" :field="$sortField" :direction="$sortDirection" class="w-44">Fecha</x-ui.th>
                <x-ui.th>Viajero</x-ui.th>
                <x-ui.th>Parte</x-ui.th>
                <x-ui.th sort="good_pieces" :field="$sortField" :direction="$sortDirection" class="w-32">Piezas</x-ui.th>
                <x-ui.th class="w-44">Avance del viajero</x-ui.th>
                <x-ui.th>Pesó</x-ui.th>
                <x-ui.th align="right" class="w-32">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($weighings as $weighing)
            @php
                $lot = $weighing->lot;
                $total = (int) ($lot?->quantity ?? 0);
                $pesado = (int) ($lot?->getProductionTotalWeighed() ?? 0);
                $porcentaje = $total > 0 ? min(100, (int) round($pesado / $total * 100)) : 0;
            @endphp
            <tr wire:key="weighing-{{ $weighing->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                    {{ $weighing->weighed_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $lot?->lot_number ?? '—' }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        WO {{ $lot?->workOrder?->purchaseOrder?->wo ?? '—' }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span class="block text-slate-700 dark:text-slate-200">{{ $lot?->workOrder?->purchaseOrder?->part?->number ?? '—' }}</span>
                    @if ($lot?->workOrder?->purchaseOrder?->part?->is_crimp)
                        <x-ui.badge tone="accent" class="mt-1">CRIMP</x-ui.badge>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ number_format($weighing->good_pieces) }}
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 w-full max-w-[6rem] overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full {{ $porcentaje >= 100 ? 'bg-green-500' : 'bg-sky-500' }}"
                                style="width: {{ $porcentaje }}%"></div>
                        </div>
                        <span class="whitespace-nowrap text-xs tabular-nums text-slate-500 dark:text-slate-400">
                            {{ number_format($pesado) }} / {{ number_format($total) }}
                        </span>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $weighing->weighedBy?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="esta pesada"
                        delete="confirmDeletion({{ $weighing->id }})"
                        deleteConfirm="¿Eliminar esta pesada de {{ number_format($weighing->good_pieces) }} piezas?">
                        <x-ui.icon-btn tone="neutral" label="Ver detalle de la pesada"
                            wire:click="openDetailModal({{ $weighing->id }})">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </x-ui.icon-btn>
                        <x-ui.icon-btn tone="primary" label="Editar esta pesada"
                            wire:click="openEditModal({{ $weighing->id }})">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </x-ui.icon-btn>
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty icon="search" title="No se encontraron pesadas"
                        hint="Ajusta la búsqueda o el rango de fechas, o registra una pesada nueva.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" wire:click="openCreateModal">Registrar pesada</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($weighings->hasPages())
            <x-slot:foot>{{ $weighings->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Alta / edición --}}
    @if ($showFormModal)
        <x-ui-modal wire:key="modal-weighing-form"
            :title="$editingWeighingId ? 'Editar pesada' : 'Registrar pesada'"
            subtitle="La pesada se guarda contra el viajero completo."
            close="closeFormModal" maxWidth="2xl">

            <x-ui.section title="Viajero" hint="Sólo aparecen los viajeros que Calidad ya aprobó para producción.">
                <x-ui.field label="Viajero" required :error="$errors->first('selectedLotId')">
                    <select wire:model.live="selectedLotId" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($selectableLots as $lot)
                            <option value="{{ $lot->id }}">
                                {{ $lot->lot_number }} · {{ $lot->workOrder?->purchaseOrder?->part?->number ?? 'Sin parte' }}
                                ({{ number_format($lot->quantity) }} pz)
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                @if ($selectableLots->isEmpty())
                    <x-ui.note tone="warn" class="mt-3">
                        No hay viajeros aprobados por Calidad. Producción sólo puede pesar después de que la
                        inspección se libere.
                    </x-ui.note>
                @endif
            </x-ui.section>

            <x-ui.section title="Pesada">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Piezas pesadas" required hint="Sólo piezas buenas."
                        :error="$errors->first('formWeighedPieces')">
                        <input wire:model="formWeighedPieces" type="number" min="1" step="1"
                            class="w-full text-right tabular-nums" required>
                    </x-ui.field>

                    <x-ui.field label="Fecha y hora" required :error="$errors->first('formWeighedAt')">
                        <input wire:model="formWeighedAt" type="datetime-local" class="w-full" required>
                    </x-ui.field>
                </div>

                @if ($formQuantity > 0)
                    <x-ui.note tone="muted" class="mt-4">
                        Cantidad del viajero: <strong>{{ number_format($formQuantity) }}</strong> piezas.
                    </x-ui.note>
                @endif

                <x-ui.field label="Comentarios" optional class="mt-4" :error="$errors->first('formComments')">
                    <textarea wire:model="formComments" rows="2" class="w-full"></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>
                Si Calidad ya verificó piezas de este viajero, no se puede bajar la cantidad por debajo de lo verificado.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeFormModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                    {{ $editingWeighingId ? 'Guardar cambios' : 'Registrar pesada' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Detalle --}}
    @if ($showDetailModal && $detailWeighing)
        <x-ui-modal wire:key="modal-weighing-detail" title="Detalle de la pesada"
            subtitle="Registro de producción a nivel viajero."
            close="closeDetailModal" maxWidth="2xl">

            <x-ui.section title="Información">
                <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    <x-ui.kv label="Viajero" :value="$detailWeighing->lot?->lot_number ?? '—'" />
                    <x-ui.kv label="Orden de trabajo" :value="$detailWeighing->lot?->workOrder?->purchaseOrder?->wo ?? '—'" />
                    <x-ui.kv label="Parte" :value="$detailWeighing->lot?->workOrder?->purchaseOrder?->part?->number ?? '—'" />
                    <x-ui.kv label="Piezas pesadas" :value="number_format($detailWeighing->good_pieces)" tone="good" />
                    <x-ui.kv label="Cantidad del viajero" :value="number_format($detailWeighing->quantity)" />
                    <x-ui.kv label="Fecha y hora" :value="$detailWeighing->weighed_at?->format('d/m/Y H:i') ?? '—'" />
                    <x-ui.kv label="Registró" :value="$detailWeighing->weighedBy?->name ?? '—'" />
                    <x-ui.kv label="Comentarios" :value="$detailWeighing->comments ?: '—'" />
                </dl>
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeDetailModal">Cerrar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="openEditModal({{ $detailWeighing->id }})">Editar</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Confirmación de borrado --}}
    @if ($confirmingDeletion)
        <x-ui-modal wire:key="modal-weighing-delete" title="Eliminar pesada"
            subtitle="Esta acción no se puede deshacer."
            close="cancelDeletion" maxWidth="lg">

            <x-ui.note tone="danger">
                Se eliminará el registro de producción. Si Calidad ya verificó piezas de este viajero, el sistema
                lo impedirá y te dirá cuántas hay verificadas.
            </x-ui.note>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelDeletion">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="delete">Eliminar pesada</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
