<x-ui.page eyebrow="Administración" :title="$department->name"
    :subtitle="$department->description ?: 'Detalle del departamento y de las áreas que agrupa.'"
    back="{{ route('admin.departments.index') }}" backLabel="Volver a departamentos">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.departments.edit', $department) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar departamento
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Equipo que cuelga del departamento --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Áreas" :value="$stats['total_areas']" />
        <x-ui.stat label="Máquinas" :value="$stats['total_machines']" tone="info"
            :help="$stats['active_machines'].' activas de '.$stats['total_machines'].'.'" />
        <x-ui.stat label="Mesas" :value="$stats['total_tables']" tone="info"
            :help="$stats['active_tables'].' activas de '.$stats['total_tables'].'.'" />
        <x-ui.stat label="Semi-automáticos" :value="$stats['total_semi_automatic']" tone="info"
            :help="$stats['active_semi_automatic'].' activos de '.$stats['total_semi_automatic'].'.'" />
    </x-ui.stats>

    {{-- Ficha --}}
    <x-ui.section title="Información del departamento">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre" :value="$department->name" />
            <x-ui.kv label="Descripción" :value="$department->description ?: '—'" />
            <x-ui.kv label="Comentarios" :value="$department->comments ?: '—'" />
            <x-ui.kv label="Alta" :value="$department->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$department->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>

    {{-- Áreas --}}
    <x-ui.section title="Áreas en este departamento"
        hint="Cada área tiene un responsable y agrupa las máquinas y mesas donde se produce.">

        @if ($department->areas->isNotEmpty())
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <x-ui.th>Área</x-ui.th>
                        <x-ui.th>Responsable</x-ui.th>
                        <x-ui.th align="right" class="w-24">Acciones</x-ui.th>
                    </tr>
                </x-slot:head>

                @foreach ($department->areas as $area)
                    <tr wire:key="area-{{ $area->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $area->name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                            @if ($area->user)
                                {{ $area->user->full_name }}
                            @else
                                <span class="text-slate-400 dark:text-slate-500">Sin responsable</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.row-actions label="el área {{ $area->name }}"
                                :show="route('admin.areas.show', $area)" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty icon="box" title="Sin áreas en este departamento"
                hint="Mientras no tenga áreas, no hay dónde registrar máquinas, mesas ni empleados.">
                <x-slot:action>
                    <x-ui.btn variant="primary" href="{{ route('admin.areas.create') }}">Crear un área</x-ui.btn>
                </x-slot:action>
            </x-ui.empty>
        @endif
    </x-ui.section>
</x-ui.page>
