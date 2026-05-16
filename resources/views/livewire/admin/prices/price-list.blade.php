<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Precios</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Administra los precios por parte con niveles de volumen</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button wire:click="downloadTemplate"
                class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 transition-colors"
                title="Descargar plantilla CSV">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Plantilla
            </button>
            <button wire:click="openImportModal"
                class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M16 8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Importar CSV
            </button>
            <button wire:click="exportCsv"
                class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M8 12l4 4m0 0l4-4m-4 4V4"/>
                </svg>
                Exportar CSV
            </button>
            <a href="{{ route('admin.prices.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Nuevo precio
            </a>
        </div>
    </div>

    {{-- Modal Import CSV --}}
    @if($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click.self="closeImportModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Importar Precios desde CSV</h3>
                    <button wire:click="closeImportModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-4 overflow-y-auto space-y-4">
                    <div class="rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 text-sm text-blue-800 dark:text-blue-200">
                        <p class="font-medium">Formato (una fila por tier):</p>
                        <p class="mt-1">Obligatorios: <code class="font-mono text-xs">part_number, workstation_type, effective_date, sample_price</code></p>
                        <p class="mt-1">Tier (opcional): <code class="font-mono text-xs">min_quantity, max_quantity, tier_price</code></p>
                        <p class="mt-1"><strong>workstation_type</strong>: <code>table</code>, <code>machine</code> o <code>semi_automatic</code>.</p>
                        <p class="mt-1"><strong>Importante:</strong> Para un precio con N tiers, usa N filas con la misma combinación <code>part_number + workstation_type + effective_date</code>. Para un precio sin tiers, deja las columnas de tier vacías. Descarga la plantilla para ver el ejemplo.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Archivo CSV</label>
                        <input type="file" wire:model="importFile" accept=".csv,text/csv" class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                        @error('importFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="importFile" class="mt-2 text-xs text-gray-500">Subiendo archivo...</div>
                    </div>

                    @if(!empty($importResults))
                        <div class="rounded-md border p-3 text-sm
                            {{ ($importResults['failed'] ?? 0) > 0 ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200' : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200' }}">
                            <p class="font-medium">Resultado:</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                                <div><span class="font-semibold">{{ $importResults['created'] ?? 0 }}</span> creados</div>
                                <div><span class="font-semibold">{{ $importResults['updated'] ?? 0 }}</span> actualizados</div>
                                <div><span class="font-semibold">{{ $importResults['skipped'] ?? 0 }}</span> sin cambios</div>
                                <div><span class="font-semibold">{{ $importResults['failed'] ?? 0 }}</span> fallaron</div>
                            </div>
                            @if(!empty($importResults['errors']))
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs font-medium">Ver errores ({{ count($importResults['errors']) }})</summary>
                                    <ul class="mt-2 list-disc list-inside text-xs space-y-0.5 max-h-40 overflow-y-auto">
                                        @foreach($importResults['errors'] as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    <button wire:click="closeImportModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cerrar
                    </button>
                    <button wire:click="importCsv" wire:loading.attr="disabled" wire:target="importCsv,importFile" class="px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-md disabled:opacity-50">
                        <span wire:loading.remove wire:target="importCsv">Procesar</span>
                        <span wire:loading wire:target="importCsv">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalPrices }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Activos</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $activePrices }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Partes con precio</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $partsWithPrice }}</div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Buscar</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Número de parte o descripción..."
                        class="block w-full pl-10 pr-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Estado</label>
                <select wire:model.live="filterActive" class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="all">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Parte</label>
                <select wire:model.live="filterPart" class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="all">Todas</option>
                    @foreach($parts as $part)
                        <option value="{{ $part->id }}">{{ $part->number }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Por página</label>
                <select wire:model.live="perPage" class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        @if(session('error'))
            <div class="mt-4 p-3 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                <p class="text-sm text-red-700 dark:text-red-300">{{ session('error') }}</p>
            </div>
        @endif
        @if($search || $filterActive !== 'all' || $filterPart !== 'all')
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Resultados filtrados</span>
                <button wire:click="$set('search', ''); $set('filterActive', 'all'); $set('filterPart', 'all')" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 font-medium">Limpiar filtros</button>
            </div>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Parte</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            <button wire:click="sortBy('sample_price')" class="flex items-center gap-2 hover:text-gray-900 dark:hover:text-white transition-colors">
                                Precio muestra
                                @if($sortField === 'sample_price')
                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Tipo estación</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Niveles</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            <button wire:click="sortBy('effective_date')" class="flex items-center gap-2 hover:text-gray-900 dark:hover:text-white transition-colors">
                                Fecha efectiva
                                @if($sortField === 'effective_date')
                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($prices as $price)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $price->part->number ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($price->part->description ?? '', 30) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">${{ number_format($price->sample_price, 4) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-medium rounded-full border-2
                                    @if($price->workstation_type === 'table') border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300
                                    @elseif($price->workstation_type === 'machine') border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300
                                    @else border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 @endif">
                                    {{ $price->workstation_type_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @forelse($price->tiers as $tier)
                                    <div>{{ $tier->label }}: ${{ number_format($tier->tier_price, 4) }}</div>
                                @empty
                                    <span class="text-gray-400">Sin niveles</span>
                                @endforelse
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $price->effective_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($price->active)
                                    <span class="px-3 py-1 text-xs font-medium rounded-full border-2 border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">Activo</span>
                                @else
                                    <span class="px-3 py-1 text-xs font-medium rounded-full border-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.prices.edit', $price) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 border-2 border-transparent hover:border-blue-300 rounded-md transition-colors" title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    <button wire:click="deletePrice({{ $price->id }})" wire:confirm="¿Estás seguro de eliminar este precio?" class="inline-flex items-center justify-center w-8 h-8 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 border-2 border-transparent hover:border-red-300 rounded-md transition-colors" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center text-sm text-gray-500 dark:text-gray-400">No se encontraron precios</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($prices->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">{{ $prices->links() }}</div>
        @endif
    </div>
</div>
