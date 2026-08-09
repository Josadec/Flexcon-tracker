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

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

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
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>
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
</x-ui.page>
