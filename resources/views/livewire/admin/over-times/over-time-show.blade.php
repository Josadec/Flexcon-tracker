@php
    $hoy = now()->startOfDay();
    $horario = ($overTime->start_time?->format('H:i') ?? '—') . ' – ' . ($overTime->end_time?->format('H:i') ?? '—');
@endphp

<x-ui.page eyebrow="Administración" :title="$overTime->name"
    :subtitle="$overTime->date->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY').' · '.$horario"
    back="{{ route('admin.over-times.index') }}" backLabel="Volver a tiempo extra">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.over-times.edit', $overTime) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar tiempo extra
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Lo que aporta a la capacidad --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Horas netas" :value="$overTime->net_hours" unit="h"
            help="Duración de la jornada menos el descanso." />
        <x-ui.stat label="Descanso" :value="$overTime->break_minutes" unit="min" />
        <x-ui.stat label="Empleados" :value="$overTime->users->count()" tone="info" />
        <x-ui.stat label="Horas-hombre" :value="$overTime->total_hours" unit="h" tone="accent"
            help="Horas netas × empleados. Es lo que se suma a la capacidad disponible." />
    </x-ui.stats>

    {{-- Ficha --}}
    <x-ui.section title="Información del tiempo extra">
        <x-slot:aside>
            @if ($overTime->date->isSameDay($hoy))
                <x-ui.badge tone="warn" dot>Es hoy</x-ui.badge>
            @elseif ($overTime->date->lt($hoy))
                <x-ui.badge tone="neutral">Pasado</x-ui.badge>
            @else
                <x-ui.badge tone="info">En {{ $hoy->diffInDays($overTime->date) }} días</x-ui.badge>
            @endif
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre" :value="$overTime->name" />
            <x-ui.kv label="Fecha" :value="$overTime->date->format('d/m/Y')" />
            <x-ui.kv label="Horario" :value="$horario" />
            <x-ui.kv label="Turno">
                @if ($overTime->shift)
                    <a href="{{ route('admin.shifts.show', $overTime->shift) }}" wire:navigate
                        class="font-bold text-sky-700 hover:underline dark:text-sky-300">{{ $overTime->shift->name }}</a>
                @else
                    <span class="font-normal text-slate-400 dark:text-slate-500">Sin turno</span>
                @endif
            </x-ui.kv>
            <x-ui.kv label="Comentarios" :value="$overTime->comments ?: '—'" />
            <x-ui.kv label="Alta" :value="$overTime->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$overTime->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>

    {{-- Empleados --}}
    <x-ui.section title="Empleados convocados"
        hint="Cada uno aporta las horas netas de la jornada al total de horas-hombre.">

        @if ($overTime->users->isNotEmpty())
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <x-ui.th>Empleado</x-ui.th>
                        <x-ui.th class="w-40">Número</x-ui.th>
                        <x-ui.th>Posición</x-ui.th>
                        <x-ui.th align="right" class="w-24">Acciones</x-ui.th>
                    </tr>
                </x-slot:head>

                @foreach ($overTime->users as $employee)
                    <tr wire:key="ot-user-{{ $employee->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $employee->full_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-slate-600 dark:text-slate-300">
                            {{ $employee->employee_number ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $employee->position ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <x-ui.row-actions label="a {{ $employee->full_name }}"
                                :show="route('admin.employees.show', $employee)" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty icon="box" title="Sin empleados convocados"
                hint="Sin empleados, este tiempo extra no aporta horas-hombre a la capacidad." />
        @endif
    </x-ui.section>
</x-ui.page>
