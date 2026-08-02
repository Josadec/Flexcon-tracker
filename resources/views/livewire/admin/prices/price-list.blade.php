<x-ui.page eyebrow="Catálogo" title="Precios"
    subtitle="Precio por parte y tipo de estación, con niveles por volumen.">

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
        <x-ui.btn variant="primary" href="{{ route('admin.prices.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo precio
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="3">
        <x-ui.stat label="Total de precios" :value="$totalPrices" />
        <x-ui.stat label="Activos" :value="$activePrices" tone="good" />
        <x-ui.stat label="Partes con precio" :value="$partsWithPrice" tone="info" />
    </x-ui.stats>

    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por parte, estado o texto libre.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Número de parte o descripción..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterActive" class="w-full">
                    <option value="all">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Parte">
                <select wire:model.live="filterPart" class="w-full">
                    <option value="all">Todas</option>
                    @foreach ($parts as $part)
                        <option value="{{ $part->id }}">{{ $part->number }}</option>
                    @endforeach
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

        @if ($search || $filterActive !== 'all' || $filterPart !== 'all')
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm"
                    wire:click="$set('search', ''); $set('filterActive', 'all'); $set('filterPart', 'all')">
                    Limpiar filtros
                </x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th>Parte</x-ui.th>
                <x-ui.th sort="sample_price" :field="$sortField" :direction="$sortDirection" align="right">Precio muestra</x-ui.th>
                <x-ui.th>Tipo de estación</x-ui.th>
                <x-ui.th>Niveles por volumen</x-ui.th>
                <x-ui.th sort="effective_date" :field="$sortField" :direction="$sortDirection">Fecha efectiva</x-ui.th>
                <x-ui.th>Estado</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($prices as $price)
            @php
                $wsTone = match ($price->workstation_type) {
                    'table'   => 'good',
                    'machine' => 'info',
                    default   => 'accent',
                };
            @endphp
            <tr wire:key="price-{{ $price->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <div class="font-semibold text-slate-900 dark:text-white">{{ $price->part->number ?? 'N/A' }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ Str::limit($price->part->description ?? '', 40) ?: '—' }}</div>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                    ${{ number_format($price->sample_price, 4) }}
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$wsTone">{{ $price->workstation_type_label }}</x-ui.badge>
                </td>
                <td class="px-4 py-3">
                    @forelse ($price->tiers as $tier)
                        <div class="flex items-baseline gap-2 text-xs">
                            <span class="text-slate-600 dark:text-slate-300">{{ $tier->label }}</span>
                            <span class="font-bold tabular-nums text-slate-900 dark:text-white">${{ number_format($tier->tier_price, 4) }}</span>
                        </div>
                    @empty
                        <span class="text-xs text-slate-400">Sin niveles</span>
                    @endforelse
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $price->effective_date?->format('d/m/Y') ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    @if ($price->active)
                        <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="este precio"
                        :edit="route('admin.prices.edit', $price)"
                        delete="deletePrice({{ $price->id }})"
                        deleteConfirm="¿Eliminar este precio de la parte «{{ $price->part->number ?? 'N/A' }}»? Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty icon="search" title="No se encontraron precios"
                        hint="Ajusta los filtros o captura un precio nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.prices.create') }}">Nuevo precio</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($prices->hasPages())
            <x-slot:foot>{{ $prices->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Importación por CSV --}}
    @if ($showImportModal)
        <x-ui-modal wire:key="modal-import-prices" title="Importar precios desde CSV"
            subtitle="Captura muchos precios y sus niveles de una sola vez."
            close="closeImportModal" maxWidth="3xl">

            <x-ui.section title="1. Prepara el archivo" hint="Una fila por nivel de precio.">
                <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    <x-ui.kv label="Columnas obligatorias" value="part_number, workstation_type, effective_date, sample_price" />
                    <x-ui.kv label="Columnas de nivel (opcionales)" value="min_quantity, max_quantity, tier_price" />
                    <x-ui.kv label="Tipos de estación válidos" value="table · machine · semi_automatic" />
                </dl>
                <x-ui.note tone="warn" class="mt-4" title="Cómo se agrupan los niveles">
                    Para un precio con varios niveles, usa <strong>una fila por nivel</strong> repitiendo la misma combinación de
                    <strong>part_number + workstation_type + effective_date</strong>. Si el precio no tiene niveles, deja vacías
                    las tres columnas de nivel.
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
                        <x-ui.stat label="Creados" :value="$importResults['created'] ?? 0" tone="good" />
                        <x-ui.stat label="Actualizados" :value="$importResults['updated'] ?? 0" tone="info" />
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
                Los precios que ya existan se actualizan; ninguno se elimina. Revisa el resultado antes de cerrar.
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
