@php
    $minutos = fn ($valor) => $valor === null
        ? '—'
        : rtrim(rtrim(number_format((float) $valor, 2), '0'), '.') . ' min';
@endphp

<x-ui.page eyebrow="Administración" :title="$machine->name"
    :subtitle="collect([$machine->brand, $machine->model])->filter()->implode(' · ') ?: 'Detalle del equipo.'"
    back="{{ route('admin.machines.index') }}" backLabel="Volver a máquinas">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.machines.edit', $machine) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar máquina
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Tiempos, que es lo que alimenta la capacidad --}}
    <x-ui.stats cols="3">
        <x-ui.stat label="Preparación" :value="$minutos($machine->setup_time)" />
        <x-ui.stat label="Mantenimiento" :value="$minutos($machine->maintenance_time)" />
        <x-ui.stat label="Empleados" :value="$machine->employees ?: '—'" tone="info"
            help="Operadores que atienden la máquina." />
    </x-ui.stats>

    {{-- Ficha --}}
    <x-ui.section title="Información de la máquina">
        <x-slot:aside>
            <div class="flex items-center gap-2">
                @if ($machine->productionStatus)
                    <x-ui.badge tone="accent">{{ $machine->productionStatus->name }}</x-ui.badge>
                @endif
                @if ($machine->active)
                    <x-ui.badge tone="good" dot>Activa</x-ui.badge>
                @else
                    <x-ui.badge tone="neutral" dot>Inactiva</x-ui.badge>
                @endif
            </div>
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre" :value="$machine->name" />
            <x-ui.kv label="Área">
                @if ($machine->area)
                    <a href="{{ route('admin.areas.show', $machine->area) }}" wire:navigate
                        class="font-bold text-sky-700 hover:underline dark:text-sky-300">{{ $machine->area->name }}</a>
                @else
                    <span class="font-normal text-slate-400 dark:text-slate-500">Sin área</span>
                @endif
            </x-ui.kv>
            <x-ui.kv label="Estado de producción" :value="$machine->productionStatus?->name ?: '—'" />
        </dl>
    </x-ui.section>

    {{-- Equipo --}}
    <x-ui.section title="Equipo" hint="Datos de inventario capturados en la ficha.">
        @if ($machine->brand || $machine->model || $machine->sn || $machine->asset_number)
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Marca" :value="$machine->brand ?: '—'" />
                <x-ui.kv label="Modelo" :value="$machine->model ?: '—'" />
                <x-ui.kv label="Número de serie" :value="$machine->sn ?: '—'" />
                <x-ui.kv label="Número de activo" :value="$machine->asset_number ?: '—'" />
            </dl>
        @else
            <x-ui.empty icon="box" title="Sin datos de equipo"
                hint="Marca, modelo, número de serie y número de activo se capturan al editar la máquina." />
        @endif
    </x-ui.section>

    {{-- Comentarios --}}
    @if ($machine->comments)
        <x-ui.section title="Comentarios" hint="Notas internas capturadas en la ficha.">
            <p class="whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $machine->comments }}</p>
        </x-ui.section>
    @endif

    {{-- Registro --}}
    <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Alta" :value="$machine->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$machine->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>
</x-ui.page>
