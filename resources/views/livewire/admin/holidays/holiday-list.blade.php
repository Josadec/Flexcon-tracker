@php $hoy = now()->startOfDay(); @endphp

<x-ui.page eyebrow="Administración" title="Días festivos"
    subtitle="Días en que no se produce. El cálculo de capacidad los descuenta de los días hábiles del período.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.holidays.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo día festivo
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total registrados" :value="$totalHolidays" />
        <x-ui.stat label="Próximos" :value="$upcomingHolidays" tone="info"
            help="De hoy en adelante. Son los que afectan la planeación." />
        <x-ui.stat label="Pasados" :value="$pastHolidays" />
        <x-ui.stat label="De {{ $hoy->year }}" :value="$thisYearHolidays" tone="accent"
            help="Festivos capturados dentro del año en curso." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    @if ($upcomingHolidays === 0 && $totalHolidays > 0)
        <x-ui.note tone="warn">
            No hay ningún día festivo de hoy en adelante. Mientras no captures los del año en curso, el cálculo de
            capacidad tomará esos días como hábiles.
        </x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre o descripción, y acota por período.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Nombre o descripción..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Período">
                <select wire:model.live="filterWhen" class="w-full">
                    <option value="all">Todos</option>
                    <option value="upcoming">Próximos</option>
                    <option value="past">Pasados</option>
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

        @if ($search || $filterWhen !== 'all')
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
                <x-ui.th sort="date" :field="$sortField" :direction="$sortDirection" class="w-44">Fecha</x-ui.th>
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Día festivo</x-ui.th>
                <x-ui.th>Descripción</x-ui.th>
                <x-ui.th class="w-32">Cuándo</x-ui.th>
                <x-ui.th align="right" class="w-32">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($holidays as $holiday)
            @php
                $esHoy = $holiday->date->isSameDay($hoy);
                $pasado = $holiday->date->lt($hoy);
            @endphp
            <tr wire:key="holiday-{{ $holiday->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="block font-semibold tabular-nums text-slate-900 dark:text-white">
                        {{ $holiday->date->format('d/m/Y') }}
                    </span>
                    <span class="block text-xs capitalize text-slate-500 dark:text-slate-400">
                        {{ $holiday->date->locale('es')->dayName }}
                    </span>
                </td>
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $holiday->name }}</td>
                <td class="max-w-md truncate px-4 py-3 text-slate-600 dark:text-slate-300"
                    title="{{ $holiday->description }}">{{ $holiday->description ?: '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($esHoy)
                        <x-ui.badge tone="warn" dot>Hoy</x-ui.badge>
                    @elseif ($pasado)
                        <x-ui.badge tone="neutral">Pasado</x-ui.badge>
                    @else
                        <x-ui.badge tone="info">En {{ $hoy->diffInDays($holiday->date) }} días</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el día festivo {{ $holiday->name }}"
                        :show="route('admin.holidays.show', $holiday)"
                        :edit="route('admin.holidays.edit', $holiday)"
                        delete="deleteHoliday({{ $holiday->id }})"
                        deleteConfirm="¿Eliminar «{{ $holiday->name }}» del {{ $holiday->date->format('d/m/Y') }}? Ese día volverá a contar como hábil." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-ui.empty icon="search" title="No se encontraron días festivos"
                        hint="Ajusta la búsqueda o el período, o da de alta un día festivo nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.holidays.create') }}">Nuevo día festivo</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($holidays->hasPages())
            <x-slot:foot>{{ $holidays->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
