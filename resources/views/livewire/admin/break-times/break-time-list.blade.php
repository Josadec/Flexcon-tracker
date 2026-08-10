@php
    // Un descanso puede cruzar la medianoche en turnos nocturnos.
    $duracion = function ($break) {
        if (!$break->start_break_time || !$break->end_break_time) {
            return null;
        }
        $inicio = $break->start_break_time->copy();
        $fin = $break->end_break_time->copy();
        if ($fin->lessThanOrEqualTo($inicio)) {
            $fin->addDay();
        }
        $minutos = $inicio->diffInMinutes($fin);

        return $minutos >= 60
            ? intdiv($minutos, 60) . ' h' . ($minutos % 60 ? ' ' . ($minutos % 60) . ' min' : '')
            : $minutos . ' min';
    };
@endphp

<x-ui.page eyebrow="Administración" title="Descansos"
    subtitle="Pausas dentro de cada turno. El tiempo de descanso no cuenta como tiempo productivo.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.break-times.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo descanso
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total de descansos" :value="$totalBreakTimes" />
        <x-ui.stat label="Activos" :value="$activeBreakTimes" tone="good"
            help="Sólo los activos se descuentan del tiempo productivo." />
        <x-ui.stat label="Inactivos" :value="$inactiveBreakTimes" :tone="$inactiveBreakTimes > 0 ? 'warn' : 'neutral'" />
        <x-ui.stat label="Turnos sin descanso" :value="$shiftsWithoutBreaks"
            :tone="$shiftsWithoutBreaks > 0 ? 'warn' : 'neutral'"
            help="Turnos activos sin ninguna pausa configurada: se cuentan como productivos de principio a fin." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre, horario o comentarios, y acota por turno o estado.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Nombre, horario o comentarios..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Turno">
                <select wire:model.live="filterShift" class="w-full">
                    <option value="all">Todos</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterActive" class="w-full">
                    <option value="all">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
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

        @if ($search || $filterActive !== 'all' || $filterShift !== 'all')
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
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Descanso</x-ui.th>
                <x-ui.th sort="shift_id" :field="$sortField" :direction="$sortDirection">Turno</x-ui.th>
                <x-ui.th sort="start_break_time" :field="$sortField" :direction="$sortDirection" class="w-52">Horario</x-ui.th>
                <x-ui.th sort="active" :field="$sortField" :direction="$sortDirection" class="w-28">Estado</x-ui.th>
                <x-ui.th align="right" class="w-32">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($breakTimes as $breakTime)
            <tr wire:key="break-{{ $breakTime->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $breakTime->name }}</span>
                    @if ($breakTime->comments)
                        <span class="block max-w-md truncate text-xs text-slate-500 dark:text-slate-400">{{ $breakTime->comments }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($breakTime->shift)
                        <a href="{{ route('admin.shifts.show', $breakTime->shift) }}" wire:navigate
                            class="font-medium text-sky-700 hover:underline dark:text-sky-300">{{ $breakTime->shift->name }}</a>
                    @else
                        <span class="text-slate-400 dark:text-slate-500">Sin turno</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="block tabular-nums text-slate-900 dark:text-white">
                        {{ $breakTime->start_break_time?->format('H:i') ?? '—' }} – {{ $breakTime->end_break_time?->format('H:i') ?? '—' }}
                    </span>
                    @if ($d = $duracion($breakTime))
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $d }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if ($breakTime->active)
                        <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el descanso {{ $breakTime->name }}"
                        :show="route('admin.break-times.show', $breakTime)"
                        :edit="route('admin.break-times.edit', $breakTime)"
                        delete="deleteBreakTime({{ $breakTime->id }})"
                        deleteConfirm="¿Eliminar el descanso «{{ $breakTime->name }}»? Ese tiempo volverá a contar como productivo." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-ui.empty icon="search" title="No se encontraron descansos"
                        hint="Ajusta la búsqueda o los filtros de turno y estado, o da de alta un descanso nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.break-times.create') }}">Nuevo descanso</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($breakTimes->hasPages())
            <x-slot:foot>{{ $breakTimes->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
