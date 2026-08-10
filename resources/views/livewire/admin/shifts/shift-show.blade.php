@php
    $horario = ($shift->start_time?->format('H:i') ?? '—') . ' – ' . ($shift->end_time?->format('H:i') ?? '—');

    // Un turno puede cruzar la medianoche: si la salida es menor que la entrada,
    // se le suma un día para que la duración no salga negativa.
    $duracion = null;
    if ($shift->start_time && $shift->end_time) {
        $inicio = $shift->start_time->copy();
        $fin = $shift->end_time->copy();
        if ($fin->lessThanOrEqualTo($inicio)) {
            $fin->addDay();
        }
        $minutos = $inicio->diffInMinutes($fin);
        $duracion = intdiv($minutos, 60) . ' h' . ($minutos % 60 ? ' ' . ($minutos % 60) . ' min' : '');
    }
@endphp

<x-ui.page eyebrow="Administración" :title="$shift->name" :subtitle="'Horario '.$horario.($duracion ? ' · '.$duracion : '')"
    back="{{ route('admin.shifts.index') }}" backLabel="Volver a turnos">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.shifts.edit', $shift) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar turno
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Los stats vienen en null cuando no hay empleados: se muestran como «—». --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Empleados en el turno" :value="$shiftStats['total'] ?? '—'" />
        <x-ui.stat label="Activos" :value="$shiftStats['active'] ?? '—'" tone="good" />
        <x-ui.stat label="Inactivos" :value="$shiftStats['inactive'] ?? '—'"
            :tone="($shiftStats['inactive'] ?? 0) > 0 ? 'warn' : 'neutral'" />
        <x-ui.stat label="Descansos" :value="$shift->BreakTimes->count()" tone="info"
            help="Pausas configuradas dentro de este turno." />
    </x-ui.stats>

    {{-- Ficha --}}
    <x-ui.section title="Información del turno">
        <x-slot:aside>
            @if ($shift->active)
                <x-ui.badge tone="good" dot>Activo</x-ui.badge>
            @else
                <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
            @endif
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre" :value="$shift->name" />
            <x-ui.kv label="Horario" :value="$horario" />
            <x-ui.kv label="Duración" :value="$duracion ?: '—'" />
            <x-ui.kv label="Comentarios" :value="$shift->comments ?: '—'" />
            <x-ui.kv label="Alta" :value="$shift->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$shift->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>

    {{-- Descansos --}}
    <x-ui.section title="Descansos del turno" hint="Pausas que se descuentan del tiempo productivo.">
        <x-slot:aside>
            <x-ui.btn variant="secondary" size="sm" href="{{ route('admin.break-times.create') }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo descanso
            </x-ui.btn>
        </x-slot:aside>

        @if ($shift->BreakTimes->isNotEmpty())
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <x-ui.th>Descanso</x-ui.th>
                        <x-ui.th class="w-44">Horario</x-ui.th>
                        <x-ui.th class="w-28">Estado</x-ui.th>
                        <x-ui.th align="right" class="w-24">Acciones</x-ui.th>
                    </tr>
                </x-slot:head>

                @foreach ($shift->BreakTimes as $break)
                    <tr wire:key="break-{{ $break->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $break->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                            {{ $break->start_break_time?->format('H:i') ?? '—' }} – {{ $break->end_break_time?->format('H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($break->active)
                                <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.row-actions label="el descanso {{ $break->name }}"
                                :show="route('admin.break-times.show', $break)"
                                :edit="route('admin.break-times.edit', $break)" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty icon="doc" title="Sin descansos configurados"
                hint="Sin descansos, el turno cuenta como tiempo productivo completo." />
        @endif
    </x-ui.section>

    {{-- Empleados --}}
    <x-ui.section title="Empleados en este turno" hint="Personal de planta con este turno asignado.">
        @if ($shift->allEmployees->isNotEmpty())
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <x-ui.th>Empleado</x-ui.th>
                        <x-ui.th class="w-40">Número</x-ui.th>
                        <x-ui.th class="w-28">Estado</x-ui.th>
                        <x-ui.th align="right" class="w-24">Acciones</x-ui.th>
                    </tr>
                </x-slot:head>

                @foreach ($shift->allEmployees as $employee)
                    <tr wire:key="employee-{{ $employee->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $employee->full_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-slate-600 dark:text-slate-300">
                            {{ $employee->employee_number ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($employee->active)
                                <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.row-actions label="a {{ $employee->full_name }}"
                                :show="route('admin.employees.show', $employee)"
                                :edit="route('admin.employees.edit', $employee)" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty icon="box" title="Sin empleados asignados"
                hint="El turno se asigna desde la ficha de cada empleado, en el módulo Empleados." />
        @endif
    </x-ui.section>
</x-ui.page>
