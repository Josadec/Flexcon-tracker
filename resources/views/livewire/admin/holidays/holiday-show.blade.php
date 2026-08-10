@php
    $hoy = now()->startOfDay();
    $esHoy = $holiday->date->isSameDay($hoy);
    $pasado = $holiday->date->lt($hoy);
@endphp

<x-ui.page eyebrow="Administración" :title="$holiday->name"
    :subtitle="$holiday->date->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')"
    back="{{ route('admin.holidays.index') }}" backLabel="Volver a días festivos">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.holidays.edit', $holiday) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar día festivo
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    <x-ui.section title="Información del día festivo">
        <x-slot:aside>
            @if ($esHoy)
                <x-ui.badge tone="warn" dot>Es hoy</x-ui.badge>
            @elseif ($pasado)
                <x-ui.badge tone="neutral">Pasado</x-ui.badge>
            @else
                <x-ui.badge tone="info">En {{ $hoy->diffInDays($holiday->date) }} días</x-ui.badge>
            @endif
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre" :value="$holiday->name" />
            <x-ui.kv label="Fecha" :value="$holiday->date->format('d/m/Y')" />
            <x-ui.kv label="Día de la semana" :value="ucfirst($holiday->date->locale('es')->dayName)" />
            <x-ui.kv label="Descripción" :value="$holiday->description ?: '—'" />
            <x-ui.kv label="Alta" :value="$holiday->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$holiday->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>

        <x-ui.note tone="muted" class="mt-4">
            El cálculo de capacidad descuenta este día de los días hábiles del período en que cae.
        </x-ui.note>
    </x-ui.section>
</x-ui.page>
