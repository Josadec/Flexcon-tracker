<x-ui.page eyebrow="Catálogo" back="{{ route('admin.departments.index') }}" backLabel="Departamentos"
    :title="$department->name" subtitle="Detalles del departamento y sus áreas.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.departments.edit', $department) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total áreas" :value="number_format($stats['total_areas'])" tone="info" />
        <x-ui.stat label="Total máquinas" :value="number_format($stats['total_machines'])" />
        <x-ui.stat label="Total mesas" :value="number_format($stats['total_tables'])" />
        <x-ui.stat label="Semi-automáticos" :value="number_format($stats['total_semi_automatic'])" />
    </x-ui.stats>

    {{-- Información general --}}
    <x-ui.section title="Información del departamento">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Nombre</p>
                <p class="mt-1 text-base text-slate-900 dark:text-white">{{ $department->name }}</p>
            </div>
            <div class="md:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Descripción</p>
                <p class="mt-1 text-base text-slate-900 dark:text-white">{{ $department->description ?: '—' }}</p>
            </div>
            @if ($department->comments)
                <div class="md:col-span-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Comentarios</p>
                    <p class="mt-1 text-base text-slate-900 dark:text-white">{{ $department->comments }}</p>
                </div>
            @endif
        </div>
    </x-ui.section>

    {{-- Áreas del departamento --}}
    <x-ui.table title="Áreas en este departamento" hint="Áreas agrupadas bajo este departamento.">
        <x-slot:aside>
            <x-ui.badge tone="neutral">{{ number_format($department->areas->count()) }} {{ Str::plural('área', $department->areas->count()) }}</x-ui.badge>
        </x-slot:aside>
        <x-slot:head>
            <tr>
                <x-ui.th>Nombre</x-ui.th>
                <x-ui.th>Supervisor</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($department->areas as $area)
            <tr wire:key="area-{{ $area->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $area->name }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $area->supervisor_name ?: '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el área {{ $area->name }}" :show="route('admin.areas.show', $area)" />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3">
                    <x-ui.empty icon="box" title="No hay áreas asociadas a este departamento"
                        hint="Las áreas se administran desde el catálogo de áreas." />
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-ui.page>
