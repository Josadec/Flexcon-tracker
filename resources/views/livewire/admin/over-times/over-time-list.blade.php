@php $hoy = now()->startOfDay(); @endphp

<x-ui.page eyebrow="Administración" title="Tiempo extra"
    subtitle="Jornadas extraordinarias por turno. Las horas-hombre que generan se suman a la capacidad disponible del período.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.over-times.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo tiempo extra
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Registros" :value="$stats['total']" />
        <x-ui.stat label="Programados" :value="$stats['upcoming']" tone="info"
            help="De hoy en adelante. Son los que aún afectan la planeación." />
        <x-ui.stat label="Horas-hombre" :value="$stats['total_hours']" unit="h"
            help="Horas netas × empleados, sumando todos los registros." />
        <x-ui.stat label="Horas programadas" :value="$stats['upcoming_hours']" unit="h" tone="accent"
            help="Horas-hombre de los tiempos extra que todavía no ocurren." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre o turno, y acota por período.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Nombre o turno..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Turno">
                <select wire:model.live="filterShift" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Período">
                <select wire:model.live="filterWhen" class="w-full">
                    <option value="all">Todos</option>
                    <option value="upcoming">Programados</option>
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

        @if ($search || $filterShift || $filterWhen !== 'all')
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
                <x-ui.th sort="date" :field="$sortField" :direction="$sortDirection" class="w-40">Fecha</x-ui.th>
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Tiempo extra</x-ui.th>
                <x-ui.th sort="shift_id" :field="$sortField" :direction="$sortDirection">Turno</x-ui.th>
                <x-ui.th sort="start_time" :field="$sortField" :direction="$sortDirection" class="w-44">Horario</x-ui.th>
                <x-ui.th class="w-28">Empleados</x-ui.th>
                <x-ui.th class="w-36">Horas-hombre</x-ui.th>
                <x-ui.th align="right" class="w-32">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($overTimes as $overTime)
            @php $pasado = $overTime->date->lt($hoy); @endphp
            <tr wire:key="overtime-{{ $overTime->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="block font-semibold tabular-nums text-slate-900 dark:text-white">
                        {{ $overTime->date->format('d/m/Y') }}
                    </span>
                    @if ($overTime->date->isSameDay($hoy))
                        <x-ui.badge tone="warn" dot class="mt-1">Hoy</x-ui.badge>
                    @elseif ($pasado)
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Pasado</span>
                    @else
                        <span class="block text-xs text-slate-500 dark:text-slate-400">En {{ $hoy->diffInDays($overTime->date) }} días</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $overTime->name }}</span>
                    @if ($overTime->comments)
                        <span class="block max-w-xs truncate text-xs text-slate-500 dark:text-slate-400">{{ $overTime->comments }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($overTime->shift)
                        <a href="{{ route('admin.shifts.show', $overTime->shift) }}" wire:navigate
                            class="font-medium text-sky-700 hover:underline dark:text-sky-300">{{ $overTime->shift->name }}</a>
                    @else
                        <span class="text-slate-400 dark:text-slate-500">Sin turno</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="block tabular-nums text-slate-900 dark:text-white">
                        {{ $overTime->start_time?->format('H:i') ?? '—' }} – {{ $overTime->end_time?->format('H:i') ?? '—' }}
                    </span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        {{ $overTime->net_hours }} h netas
                        @if ($overTime->break_minutes > 0)
                            · {{ $overTime->break_minutes }} min de descanso
                        @endif
                    </span>
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$overTime->users_count > 0 ? 'info' : 'warn'">
                        {{ $overTime->users_count }} {{ \Illuminate\Support\Str::plural('empleado', $overTime->users_count) }}
                    </x-ui.badge>
                </td>
                <td class="whitespace-nowrap px-4 py-3 font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ $overTime->total_hours }} h
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el tiempo extra {{ $overTime->name }}"
                        :show="route('admin.over-times.show', $overTime)"
                        :edit="route('admin.over-times.edit', $overTime)"
                        delete="deleteOverTime({{ $overTime->id }})"
                        deleteConfirm="¿Eliminar «{{ $overTime->name }}» del {{ $overTime->date->format('d/m/Y') }}? Sus horas dejarán de sumarse a la capacidad." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty icon="search" title="No se encontraron tiempos extra"
                        hint="Ajusta la búsqueda o los filtros de turno y período, o programa uno nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.over-times.create') }}">Nuevo tiempo extra</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($overTimes->hasPages())
            <x-slot:foot>{{ $overTimes->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
