<div class="min-h-screen bg-zinc-100 dark:bg-zinc-950">

    {{-- Header --}}
    <div class="border-b border-zinc-200 bg-white px-4 py-5 dark:border-zinc-800 dark:bg-zinc-900 sm:px-6 xl:px-8">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 sm:text-2xl">Reportes de Partes</h1>
                    <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">Precios por fecha efectiva y catálogo de partes</p>
                </div>
            </div>
            <a href="{{ route('admin.reports.index') }}"
               class="inline-flex items-center gap-2 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver a reportes
            </a>
        </div>
    </div>

    <div class="px-4 py-6 sm:px-6 xl:px-8 space-y-5">

        {{-- Tipo de reporte --}}
        <div>
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Paso 1 — Tipo de reporte</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="$set('reportType','prices')"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors
                    {{ $reportType === 'prices'
                        ? 'border-blue-900 bg-blue-900 text-white'
                        : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Partes por fecha efectiva (precios)
                </button>
                <button type="button" wire:click="$set('reportType','parts')"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors
                    {{ $reportType === 'parts'
                        ? 'border-emerald-800 bg-emerald-800 text-white'
                        : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    Partes activas / inactivas
                </button>
            </div>
        </div>

        {{-- Filtros del reporte --}}
        <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Paso 2 — Filtros</p>

            @if($reportType === 'prices')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1.5">Estado del precio</label>
                        <select wire:model.live="priceStatus"
                            class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="all">Todos</option>
                            <option value="active">Activos vigentes</option>
                            <option value="expiring">Por vencer</option>
                            <option value="expired">Vencidos</option>
                            <option value="future">Futuros (aún no vigentes)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1.5">Fecha de referencia</label>
                        <input type="date" wire:model.live="referenceDate"
                            class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1.5">Días para "por vencer"</label>
                        <input type="number" min="1" max="365" wire:model.live="expiringDays"
                            class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1.5">Buscar parte</label>
                        <input type="search" placeholder="Número, ítem o descripción" wire:model.live.debounce.400ms="search"
                            class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1.5">Estado de la parte</label>
                        <select wire:model.live="partsStatus"
                            class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="all">Todas</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1.5">Buscar</label>
                        <input type="search" placeholder="Número, ítem o descripción" wire:model.live.debounce.400ms="search"
                            class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                    </div>
                </div>
            @endif
        </div>

        {{-- Formato y descarga --}}
        <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Paso 3 — Formato y descarga</p>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <button type="button" wire:click="$set('format','pdf')"
                    class="flex items-center gap-2.5 rounded-md px-4 py-3 text-sm font-medium transition-colors
                    {{ $format === 'pdf'
                        ? 'border-rose-700 bg-rose-700 text-white'
                        : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <div class="text-left">
                        <div class="font-semibold leading-tight">PDF</div>
                        <div class="text-xs opacity-75 leading-tight">Listo para imprimir</div>
                    </div>
                </button>

                <button type="button" wire:click="$set('format','excel')"
                    class="flex items-center gap-2.5 rounded-md px-4 py-3 text-sm font-medium transition-colors
                    {{ $format === 'excel'
                        ? 'border-emerald-700 bg-emerald-700 text-white'
                        : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    <div class="text-left">
                        <div class="font-semibold leading-tight">Excel (.xlsx)</div>
                        <div class="text-xs opacity-75 leading-tight">Datos crudos</div>
                    </div>
                </button>

                @php $url = $this->getDownloadUrl(); @endphp
                <a href="{{ $url }}" target="_blank"
                    class="sm:ml-auto inline-flex items-center gap-2 rounded-md px-5 py-3 text-sm font-semibold text-white shadow-sm transition-colors
                    {{ $format === 'pdf' ? 'bg-rose-700 hover:bg-rose-800' : 'bg-emerald-700 hover:bg-emerald-800' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar {{ strtoupper($format) }}
                </a>
            </div>
        </div>

        {{-- Vista previa --}}
        <div class="rounded-md border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Vista previa</h3>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $rows->total() }} {{ Str::plural('registro', $rows->total()) }}</span>
            </div>

            <div class="overflow-x-auto">
                @if($reportType === 'prices')
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">N° parte</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Descripción</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Estación</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Precio muestra</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Fecha efectiva</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse($rows as $price)
                                @php
                                    $ref = \Carbon\Carbon::parse($referenceDate);
                                    $eff = $price->effective_date instanceof \Carbon\Carbon ? $price->effective_date : \Carbon\Carbon::parse($price->effective_date);
                                    $diff = $ref->diffInDays($eff, false);
                                    if (!$price->active && $eff->lt($ref)) {
                                        $badge = ['Vencido', 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300'];
                                    } elseif ($price->active && $eff->lte($ref) && $eff->gte($ref->copy()->subDays($expiringDays))) {
                                        $badge = ['Por vencer', 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'];
                                    } elseif ($price->active && $eff->lte($ref)) {
                                        $badge = ['Vigente', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'];
                                    } elseif ($eff->gt($ref)) {
                                        $badge = ['Futuro', 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'];
                                    } else {
                                        $badge = ['Inactivo', 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'];
                                    }
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $price->part->number ?? '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">{{ $price->part->description ?? '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">{{ \App\Models\Price::WORKSTATION_TYPES[$price->workstation_type] ?? $price->workstation_type }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-zinc-900 dark:text-zinc-100">${{ number_format((float) $price->sample_price, 4) }}</td>
                                    <td class="px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">{{ $eff->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[1] }}">{{ $badge[0] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">Sin resultados</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">N° parte</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">N° ítem</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Descripción</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Unidad</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse($rows as $part)
                                <tr>
                                    <td class="px-4 py-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $part->number }}</td>
                                    <td class="px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">{{ $part->item_number }}</td>
                                    <td class="px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">{{ $part->description }}</td>
                                    <td class="px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">{{ $part->unit_of_measure ?? '—' }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if($part->active)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Activa</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-zinc-200 px-2.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">Inactiva</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">Sin resultados</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="border-t border-zinc-200 px-6 py-3 dark:border-zinc-700">
                {{ $rows->links() }}
            </div>
        </div>

    </div>
</div>
