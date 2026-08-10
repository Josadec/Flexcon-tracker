@php
    $actual = $catalog[$department] ?? $catalog['general'];
    $rango = $startDate && $endDate
        ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') . ' – ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y')
        : 'Sin filtro de fechas';
    $dias = $startDate && $endDate
        ? \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1
        : null;
@endphp

<x-ui.page eyebrow="Reportes" title="Generador de reportes"
    subtitle="Elige un departamento, acota el período y descarga en PDF o Excel.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.reports.parts.index') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            Reportes de partes
        </x-ui.btn>
    </x-slot:actions>

    {{-- Paso 1 --}}
    <x-ui.section step="1" title="Departamento" hint="Cada reporte trae los datos de su área en el período elegido.">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($catalog as $key => $item)
                <x-ui.choice wire:key="dept-{{ $key }}"
                    :tone="$key === 'general' ? 'accent' : 'info'"
                    :title="$item['label']"
                    :desc="$item['desc']"
                    :selected="$department === $key"
                    wire:click="$set('department', '{{ $key }}')" />
            @endforeach
        </div>
    </x-ui.section>

    {{-- Paso 2 --}}
    <x-ui.section step="2" title="Período" hint="Se filtra por {{ $actual['dateField'] }}.">
        <x-slot:aside>
            <x-ui.badge :tone="$dias ? 'info' : 'neutral'">
                {{ $rango }}@if ($dias) · {{ $dias }} {{ \Illuminate\Support\Str::plural('día', $dias) }}@endif
            </x-ui.badge>
        </x-slot:aside>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            <x-ui.field label="Desde" for="report_start_date">
                <input id="report_start_date" wire:model.live="startDate" type="date" class="w-full">
            </x-ui.field>

            <x-ui.field label="Hasta" for="report_end_date">
                <input id="report_end_date" wire:model.live="endDate" type="date" class="w-full">
            </x-ui.field>

            <div class="flex items-end">
                <x-ui.btn variant="secondary" wire:click="clearDates" title="Quitar el filtro de fechas">
                    Sin filtro
                </x-ui.btn>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Atajos</span>
            <x-ui.btn variant="ghost" size="sm" wire:click="applyPreset('this-month')">Este mes</x-ui.btn>
            <x-ui.btn variant="ghost" size="sm" wire:click="applyPreset('last-month')">Mes pasado</x-ui.btn>
            <x-ui.btn variant="ghost" size="sm" wire:click="applyPreset('last-30')">Últimos 30 días</x-ui.btn>
            <x-ui.btn variant="ghost" size="sm" wire:click="applyPreset('this-year')">Este año</x-ui.btn>
        </div>

        @if (!$startDate && !$endDate)
            <x-ui.note tone="warn" class="mt-4">
                Sin filtro de fechas el reporte incluye <strong>todo el histórico</strong>. En bases grandes puede
                tardar bastante en generarse.
            </x-ui.note>
        @endif
    </x-ui.section>

    {{-- Paso 3 --}}
    <x-ui.section step="3" title="Formato y descarga" tone="accent"
        hint="El PDF sirve para imprimir o archivar; el Excel, para seguir trabajando los datos.">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-ui.choice tone="bad" title="PDF" desc="Listo para imprimir o archivar."
                :selected="$format === 'pdf'" wire:click="$set('format', 'pdf')" />

            <x-ui.choice tone="good" title="Excel (.xlsx)" :desc="$actual['excelHint'].'. Para filtrar y hacer tus propios cálculos.'"
                :selected="$format === 'excel'" wire:click="$set('format', 'excel')" />
        </div>

        <div class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Se descargará <strong class="text-slate-700 dark:text-slate-200">{{ $actual['label'] }}</strong>
                en <strong class="text-slate-700 dark:text-slate-200">{{ strtoupper($format) }}</strong>, {{ $startDate || $endDate ? 'del período seleccionado' : 'con todo el histórico' }}.
            </p>
            <x-ui.btn variant="primary" href="{{ $this->getDownloadUrl() }}" :navigate="false" target="_blank">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Descargar {{ strtoupper($format) }}
            </x-ui.btn>
        </div>
    </x-ui.section>

    {{-- Qué incluye --}}
    <x-ui.section title="Qué incluye el reporte de {{ $actual['label'] }}"
        hint="Contenido del departamento seleccionado.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            @foreach ($actual['includes'] as [$titulo, $detalle])
                <div class="flex flex-col gap-0.5 px-4 py-2.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                    <dt class="text-sm font-semibold text-slate-900 dark:text-white">{{ $titulo }}</dt>
                    <dd class="text-sm text-slate-600 sm:text-right dark:text-slate-300">{{ $detalle }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.section>
</x-ui.page>
