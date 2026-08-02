<x-ui.page eyebrow="Configuración" :title="$area->name"
    subtitle="Detalle del área y equipos asignados."
    back="{{ route('admin.areas.index') }}" backLabel="Volver a áreas">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.areas.edit', $area) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar
        </x-ui.btn>
    </x-slot:actions>

    {{-- Equipo asignado: lo primero que se consulta al abrir un área. --}}
    <x-ui.section title="Equipo asignado" hint="Conteo por tipo de estación, activo contra total.">
        <x-ui.stats cols="3">
            <x-ui.stat label="Máquinas" :value="$stats['total_machines']"
                :help="'Activas: '.$stats['active_machines']" />
            <x-ui.stat label="Mesas" :value="$stats['total_tables']"
                :help="'Activas: '.$stats['active_tables']" />
            <x-ui.stat label="Semi-automáticos" :value="$stats['total_semi_automatic']"
                :help="'Activos: '.$stats['active_semi_automatic']" />
            <x-ui.stat label="Máquinas activas" :value="$stats['active_machines']" tone="good" />
            <x-ui.stat label="Mesas activas" :value="$stats['active_tables']" tone="good" />
            <x-ui.stat label="Semi-autom. activos" :value="$stats['active_semi_automatic']" tone="good" />
        </x-ui.stats>
    </x-ui.section>

    {{-- Ficha del área --}}
    <x-ui.section title="Información del área">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre" :value="$area->name" />
            <x-ui.kv label="Departamento" :value="$area->department->name" />
            <x-ui.kv label="Supervisor" :value="$area->supervisor_name" />
            <x-ui.kv label="Descripción" :value="$area->description ?: 'Sin descripción'" />
            @if ($area->comments)
                <x-ui.kv label="Comentarios" :value="$area->comments" />
            @endif
        </dl>
    </x-ui.section>

    {{-- Equipos --}}
    @php $equipment = $area->getAllEquipment(); @endphp
    <x-ui.table title="Equipos en esta área"
        :hint="$equipment->count().' '.Str::plural('equipo', $equipment->count()).' registrado'.($equipment->count() === 1 ? '' : 's')">
        <x-slot:head>
            <tr>
                <x-ui.th>Tipo</x-ui.th>
                <x-ui.th>Nombre</x-ui.th>
                <x-ui.th align="right">Estado</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($equipment as $item)
            <tr wire:key="equip-{{ $item->equipment_type }}-{{ $item->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <x-ui.badge tone="neutral">
                        @switch($item->equipment_type)
                            @case('machine') Máquina @break
                            @case('table') Mesa @break
                            @case('semi_automatic') Semi-automático @break
                            @default {{ $item->equipment_type }}
                        @endswitch
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $item->name }}</td>
                <td class="px-4 py-3 text-right">
                    @if (isset($item->active) && $item->active)
                        <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3">
                    <x-ui.empty title="No hay equipos en esta área"
                        hint="Las máquinas, mesas y semi-automáticos se dan de alta desde su propio catálogo y se asignan a un área." />
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-ui.page>
