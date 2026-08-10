@php
    $horario = ($breakTime->start_break_time?->format('H:i') ?? '—') . ' – ' . ($breakTime->end_break_time?->format('H:i') ?? '—');
@endphp

<x-ui.page eyebrow="Administración" :title="$breakTime->name"
    :subtitle="'Descanso de '.$horario.($breakTime->shift ? ' · '.$breakTime->shift->name : '')"
    back="{{ route('admin.break-times.index') }}" backLabel="Volver a descansos">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.break-times.edit', $breakTime) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar descanso
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    <x-ui.stats cols="3">
        <x-ui.stat label="Inicio" :value="$breakTime->start_break_time?->format('H:i') ?? '—'" />
        <x-ui.stat label="Fin" :value="$breakTime->end_break_time?->format('H:i') ?? '—'" />
        <x-ui.stat label="Duración" :value="$this->getDuration()" tone="info"
            help="Tiempo que se descuenta del turno." />
    </x-ui.stats>

    <x-ui.section title="Información del descanso">
        <x-slot:aside>
            @if ($breakTime->active)
                <x-ui.badge tone="good" dot>Activo</x-ui.badge>
            @else
                <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
            @endif
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre" :value="$breakTime->name" />
            <x-ui.kv label="Turno">
                @if ($breakTime->shift)
                    <a href="{{ route('admin.shifts.show', $breakTime->shift) }}" wire:navigate
                        class="font-bold text-sky-700 hover:underline dark:text-sky-300">{{ $breakTime->shift->name }}</a>
                @else
                    <span class="font-normal text-slate-400 dark:text-slate-500">Sin turno</span>
                @endif
            </x-ui.kv>
            <x-ui.kv label="Horario" :value="$horario" />
            <x-ui.kv label="Comentarios" :value="$breakTime->comments ?: '—'" />
            <x-ui.kv label="Alta" :value="$breakTime->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$breakTime->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>

        <x-ui.note tone="muted" class="mt-4">
            Mientras el descanso esté activo, su duración se descuenta del tiempo productivo del turno.
        </x-ui.note>
    </x-ui.section>
</x-ui.page>
