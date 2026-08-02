<x-ui.page eyebrow="Catálogo" title="Partes"
    subtitle="Catálogo de partes y productos. Desde aquí se dan de alta y se consultan sus precios.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" wire:click="downloadTemplate" title="Descargar una plantilla CSV de ejemplo">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Plantilla
        </x-ui.btn>
        <x-ui.btn variant="secondary" wire:click="openImportModal">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M16 8l-4-4m0 0L8 8m4-4v12"/></svg>
            Importar CSV
        </x-ui.btn>
        <x-ui.btn variant="secondary" wire:click="exportCsv">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M8 12l4 4m0 0l4-4m-4 4V4"/></svg>
            Exportar CSV
        </x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.parts.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva parte
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="3">
        <x-ui.stat label="Total de partes" :value="$totalParts" />
        <x-ui.stat label="Activas" :value="$activeParts" tone="good" />
        <x-ui.stat label="Con precio" :value="$withPrices" tone="info"
            help="Partes que ya tienen al menos un precio capturado." />
    </x-ui.stats>

    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por número de parte, número de ítem o descripción.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Número, ítem o descripción..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterActive" class="w-full">
                    <option value="all">Todas</option>
                    <option value="active">Activas</option>
                    <option value="inactive">Inactivas</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([5, 10, 25, 50] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search || $filterActive !== 'all')
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="$set('search', ''); $set('filterActive', 'all')">
                    Limpiar filtros
                </x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th sort="number" :field="$sortField" :direction="$sortDirection">Nº parte</x-ui.th>
                <x-ui.th sort="item_number" :field="$sortField" :direction="$sortDirection">Nº ítem</x-ui.th>
                <x-ui.th>Descripción</x-ui.th>
                <x-ui.th>Unidad</x-ui.th>
                <x-ui.th sort="active" :field="$sortField" :direction="$sortDirection">Estado</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($parts as $part)
            <tr wire:key="part-{{ $part->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">
                    {{ $part->number }}
                    @if ($part->is_crimp)
                        <x-ui.badge tone="accent" class="ml-1.5">CRIMP</x-ui.badge>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $part->item_number }}</td>
                <td class="max-w-xs truncate px-4 py-3 text-slate-600 dark:text-slate-300"
                    title="{{ $part->description }}">{{ $part->description ?: '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $part->unit_of_measure ?: '—' }}</td>
                <td class="px-4 py-3">
                    @if ($part->active)
                        <x-ui.badge tone="good" dot>Activa</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Inactiva</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="la parte {{ $part->number }}"
                        :show="route('admin.parts.show', $part)"
                        :edit="route('admin.parts.edit', $part)"
                        delete="deletePart({{ $part->id }})"
                        deleteConfirm="¿Eliminar la parte «{{ $part->number }}»? Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty icon="search" title="No se encontraron partes"
                        hint="Ajusta la búsqueda o el filtro de estado, o da de alta una parte nueva.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.parts.create') }}">Nueva parte</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($parts->hasPages())
            <x-slot:foot>{{ $parts->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Importación por CSV --}}
    @if ($showImportModal)
        <x-ui-modal wire:key="modal-import-parts" title="Importar partes desde CSV"
            subtitle="Da de alta o actualiza muchas partes de una sola vez."
            close="closeImportModal" maxWidth="3xl">

            <x-ui.section title="1. Prepara el archivo" hint="Si no estás seguro del formato, descarga la plantilla desde el listado.">
                <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    <x-ui.kv label="Columnas obligatorias" value="number, item_number" />
                    <x-ui.kv label="Columnas opcionales" value="description, unit_of_measure, label_spec, is_crimp, active, notes" />
                    <x-ui.kv label="Valores de sí/no" value="is_crimp y active usan 1 o 0" />
                </dl>
                <x-ui.note tone="info" class="mt-4">
                    <strong>number</strong> e <strong>item_number</strong> deben ser únicos. Si el número ya existe,
                    la parte se <strong>actualiza</strong> en lugar de duplicarse.
                </x-ui.note>
            </x-ui.section>

            <x-ui.section title="2. Sube el archivo">
                <x-ui.field label="Archivo CSV" required :error="$errors->first('importFile')">
                    <input type="file" wire:model="importFile" accept=".csv,text/csv"
                        class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100 dark:text-slate-300 dark:file:bg-sky-900/40 dark:file:text-sky-300">
                </x-ui.field>
                <p wire:loading wire:target="importFile" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Subiendo archivo...</p>
            </x-ui.section>

            @if (!empty($importResults))
                <x-ui.section title="3. Resultado de la importación">
                    <x-ui.stats cols="4">
                        <x-ui.stat label="Creadas" :value="$importResults['created'] ?? 0" tone="good" />
                        <x-ui.stat label="Actualizadas" :value="$importResults['updated'] ?? 0" tone="info" />
                        <x-ui.stat label="Sin cambios" :value="$importResults['skipped'] ?? 0" />
                        <x-ui.stat label="Fallaron" :value="$importResults['failed'] ?? 0"
                            :tone="($importResults['failed'] ?? 0) > 0 ? 'bad' : 'neutral'" />
                    </x-ui.stats>

                    @if (!empty($importResults['errors']))
                        <details class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                            <summary class="cursor-pointer text-sm font-semibold text-amber-900 dark:text-amber-200">
                                Ver los {{ count($importResults['errors']) }} renglones que fallaron
                            </summary>
                            <ul class="mt-3 max-h-48 list-inside list-disc space-y-1 overflow-y-auto text-xs text-amber-800 dark:text-amber-200">
                                @foreach ($importResults['errors'] as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </x-ui.section>
            @endif

            <x-slot:note>
                Las partes que ya existan se actualizan; ninguna se elimina. Revisa el resultado antes de cerrar.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeImportModal">Cerrar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="importCsv"
                    wire:loading.attr="disabled" wire:target="importCsv,importFile">
                    <span wire:loading.remove wire:target="importCsv">Procesar archivo</span>
                    <span wire:loading wire:target="importCsv">Procesando...</span>
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
