@php
    // Un turno puede cruzar la medianoche: si la salida es menor que la entrada,
    // se le suma un día para que la duración no salga negativa.
    $duracion = function ($shift) {
        if (!$shift->start_time || !$shift->end_time) {
            return null;
        }
        $inicio = $shift->start_time->copy();
        $fin = $shift->end_time->copy();
        if ($fin->lessThanOrEqualTo($inicio)) {
            $fin->addDay();
        }
        $minutos = $inicio->diffInMinutes($fin);

        return intdiv($minutos, 60) . ' h' . ($minutos % 60 ? ' ' . ($minutos % 60) . ' min' : '');
    };
@endphp

<x-ui.page eyebrow="Administración" title="Turnos"
    subtitle="Horarios de trabajo de la planta. De aquí cuelgan los empleados, sus descansos y el tiempo extra.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.shifts.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo turno
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total de turnos" :value="$totalShifts" />
        <x-ui.stat label="Activos" :value="$activeShifts" tone="good"
            help="Sólo los turnos activos se ofrecen al dar de alta un empleado." />
        <x-ui.stat label="Inactivos" :value="$inactiveShifts" :tone="$inactiveShifts > 0 ? 'warn' : 'neutral'" />
        <x-ui.stat label="Empleados asignados" :value="$employeesAssigned" tone="info"
            help="Empleados activos que ya tienen turno." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre, horario o comentarios, y acota por estado.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Nombre, horario o comentarios..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
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

        @if ($search || $filterStatus !== '')
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
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Turno</x-ui.th>
                <x-ui.th sort="start_time" :field="$sortField" :direction="$sortDirection" class="w-52">Horario</x-ui.th>
                <x-ui.th class="w-36">Empleados</x-ui.th>
                <x-ui.th class="w-28">Descansos</x-ui.th>
                <x-ui.th sort="active" :field="$sortField" :direction="$sortDirection" class="w-28">Estado</x-ui.th>
                <x-ui.th align="right" class="w-32">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($shifts as $shift)
            @php
                // canBeDeleted() mira estas tres relaciones; con los conteos ya
                // cargados se evita una consulta por renglón.
                $enUso = $shift->all_employees_count + $shift->break_times_count + $shift->over_times_count;
            @endphp
            <tr wire:key="shift-{{ $shift->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $shift->name }}</span>
                    @if ($shift->comments)
                        <span class="block max-w-md truncate text-xs text-slate-500 dark:text-slate-400">{{ $shift->comments }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="block tabular-nums text-slate-900 dark:text-white">
                        {{ $shift->start_time?->format('H:i') ?? '—' }} – {{ $shift->end_time?->format('H:i') ?? '—' }}
                    </span>
                    @if ($d = $duracion($shift))
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $d }}</span>
                    @endif
                </td>
                {{-- El texto va completo ("3 empleados", "1 empleado"): es el
                     contrato que verifica ShiftListEmployeesTest. --}}
                <td class="whitespace-nowrap px-4 py-3">
                    <x-ui.badge :tone="$shift->employees_count > 0 ? 'info' : 'neutral'">
                        {{ $shift->employees_count }} {{ \Illuminate\Support\Str::plural('empleado', $shift->employees_count) }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3">
                    @if ($shift->break_times_count > 0)
                        <x-ui.badge tone="neutral">{{ $shift->break_times_count }}</x-ui.badge>
                    @else
                        <span class="text-slate-400 dark:text-slate-500">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if ($shift->active)
                        <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el turno {{ $shift->name }}"
                        :show="route('admin.shifts.show', $shift)"
                        :edit="route('admin.shifts.edit', $shift)"
                        :delete="$enUso === 0 ? 'deleteShift('.$shift->id.')' : null"
                        deleteConfirm="¿Eliminar el turno «{{ $shift->name }}»? Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty icon="search" title="No se encontraron turnos"
                        hint="Ajusta la búsqueda o el estado, o da de alta un turno nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.shifts.create') }}">Nuevo turno</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($shifts->hasPages())
            <x-slot:foot>{{ $shifts->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    <x-ui.note tone="muted">
        Un turno con empleados, descansos o tiempo extra no se puede eliminar; desactívalo para que deje de ofrecerse
        sin perder el histórico.
    </x-ui.note>
</x-ui.page>
