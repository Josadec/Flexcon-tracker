@php
    $referencia = \Carbon\Carbon::parse($referenceDate ?: now());
@endphp

<x-ui.page eyebrow="Reportes" title="Reportes de partes"
    subtitle="Precios por fecha efectiva y catálogo de partes, con vista previa antes de descargar."
    back="{{ route('admin.reports.index') }}" backLabel="Volver a reportes">

    {{-- Paso 1 --}}
    <x-ui.section step="1" title="Tipo de reporte" hint="Define qué se lista y qué filtros aplican.">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-ui.choice tone="info" title="Precios"
                desc="Un renglón por precio, con su fecha efectiva y si está vigente."
                :selected="$reportType === 'prices'" wire:click="$set('reportType', 'prices')" />

            <x-ui.choice tone="info" title="Catálogo de partes"
                desc="Un renglón por parte, con su número de ítem, unidad y estado."
                :selected="$reportType === 'parts'" wire:click="$set('reportType', 'parts')" />
        </div>
    </x-ui.section>

    {{-- Paso 2 --}}
    <x-ui.section step="2" title="Filtros"
        hint="{{ $reportType === 'prices' ? 'La fecha de referencia decide qué precio está vigente, vencido o por vencer.' : 'Acota el catálogo antes de exportarlo.' }}">

        @if ($reportType === 'prices')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Estado del precio">
                    <select wire:model.live="priceStatus" class="w-full">
                        <option value="all">Todos</option>
                        <option value="active">Vigentes</option>
                        <option value="expiring">Por vencer</option>
                        <option value="expired">Vencidos</option>
                        <option value="future">Futuros</option>
                    </select>
                </x-ui.field>

                <x-ui.field label="Fecha de referencia" hint="Contra esta fecha se evalúa cada precio.">
                    <input type="date" wire:model.live="referenceDate" class="w-full">
                </x-ui.field>

                <x-ui.field label="Días para «por vencer»" hint="Ventana previa a la fecha de referencia.">
                    <input type="number" min="1" max="365" wire:model.live="expiringDays"
                        class="w-full text-right tabular-nums">
                </x-ui.field>

                <x-ui.field label="Buscar parte">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="search" wire:model.live.debounce.400ms="search"
                            placeholder="Número, ítem o descripción" class="w-full pl-10">
                    </div>
                </x-ui.field>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Estado de la parte">
                    <select wire:model.live="partsStatus" class="w-full">
                        <option value="all">Todas</option>
                        <option value="active">Activas</option>
                        <option value="inactive">Inactivas</option>
                    </select>
                </x-ui.field>

                <x-ui.field label="Buscar parte">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="search" wire:model.live.debounce.400ms="search"
                            placeholder="Número, ítem o descripción" class="w-full pl-10">
                    </div>
                </x-ui.field>
            </div>
        @endif
    </x-ui.section>

    {{-- Paso 3 --}}
    <x-ui.section step="3" title="Formato y descarga" tone="accent"
        hint="Se exporta exactamente lo que ves en la vista previa, con los filtros aplicados.">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-ui.choice tone="bad" title="PDF" desc="Listo para imprimir o archivar."
                :selected="$format === 'pdf'" wire:click="$set('format', 'pdf')" />

            <x-ui.choice tone="good" title="Excel (.xlsx)" desc="Para filtrar y hacer tus propios cálculos."
                :selected="$format === 'excel'" wire:click="$set('format', 'excel')" />
        </div>

        <div class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $rows->total() }} {{ \Illuminate\Support\Str::plural('registro', $rows->total()) }} en el reporte.
            </p>
            <x-ui.btn variant="primary" href="{{ $this->getDownloadUrl() }}" :navigate="false" target="_blank">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Descargar {{ strtoupper($format) }}
            </x-ui.btn>
        </div>
    </x-ui.section>

    {{-- Vista previa --}}
    <x-ui.table title="Vista previa"
        hint="{{ $rows->total() }} {{ \Illuminate\Support\Str::plural('registro', $rows->total()) }} con los filtros actuales.">

        @if ($reportType === 'prices')
            <x-slot:head>
                <tr>
                    <x-ui.th>Nº parte</x-ui.th>
                    <x-ui.th>Descripción</x-ui.th>
                    <x-ui.th>Tipo de estación</x-ui.th>
                    <x-ui.th align="right" class="w-36">Precio muestra</x-ui.th>
                    <x-ui.th class="w-36">Fecha efectiva</x-ui.th>
                    <x-ui.th class="w-28">Estado</x-ui.th>
                </tr>
            </x-slot:head>

            @forelse ($rows as $price)
                @php
                    $efectiva = $price->effective_date instanceof \Carbon\Carbon
                        ? $price->effective_date
                        : \Carbon\Carbon::parse($price->effective_date);

                    // Mismo criterio que usan los filtros del paso 2.
                    if (!$price->active && $efectiva->lt($referencia)) {
                        $estado = ['Vencido', 'bad'];
                    } elseif ($price->active && $efectiva->lte($referencia) && $efectiva->gte($referencia->copy()->subDays($expiringDays))) {
                        $estado = ['Por vencer', 'warn'];
                    } elseif ($price->active && $efectiva->lte($referencia)) {
                        $estado = ['Vigente', 'good'];
                    } elseif ($efectiva->gt($referencia)) {
                        $estado = ['Futuro', 'info'];
                    } else {
                        $estado = ['Inactivo', 'neutral'];
                    }
                @endphp
                <tr wire:key="price-{{ $price->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">
                        {{ $price->part->number ?? '—' }}
                    </td>
                    <td class="max-w-xs truncate px-4 py-3 text-slate-600 dark:text-slate-300"
                        title="{{ $price->part->description }}">{{ $price->part->description ?: '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                        {{ \App\Models\Price::WORKSTATION_TYPES[$price->workstation_type] ?? $price->workstation_type }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                        ${{ number_format((float) $price->sample_price, 4) }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                        {{ $efectiva->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :tone="$estado[1]">{{ $estado[0] }}</x-ui.badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <x-ui.empty icon="search" title="Sin precios con estos filtros"
                            hint="Prueba con otro estado, otra fecha de referencia o limpia la búsqueda." />
                    </td>
                </tr>
            @endforelse
        @else
            <x-slot:head>
                <tr>
                    <x-ui.th>Nº parte</x-ui.th>
                    <x-ui.th>Nº ítem</x-ui.th>
                    <x-ui.th>Descripción</x-ui.th>
                    <x-ui.th class="w-28">Unidad</x-ui.th>
                    <x-ui.th class="w-28">Estado</x-ui.th>
                </tr>
            </x-slot:head>

            @forelse ($rows as $part)
                <tr wire:key="part-{{ $part->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $part->number }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $part->item_number }}</td>
                    <td class="max-w-md truncate px-4 py-3 text-slate-600 dark:text-slate-300"
                        title="{{ $part->description }}">{{ $part->description ?: '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $part->unit_of_measure ?: '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($part->active)
                            <x-ui.badge tone="good" dot>Activa</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral" dot>Inactiva</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <x-ui.empty icon="search" title="Sin partes con estos filtros"
                            hint="Prueba con otro estado o limpia la búsqueda." />
                    </td>
                </tr>
            @endforelse
        @endif

        @if ($rows->hasPages())
            <x-slot:foot>{{ $rows->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
