<x-ui.page eyebrow="Administración" title="Departamentos"
    subtitle="Agrupan las áreas de la planta. Un departamento sólo se puede eliminar cuando ya no tiene áreas.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.departments.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo departamento
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="3">
        <x-ui.stat label="Total de departamentos" :value="$totalDepartments" />
        <x-ui.stat label="Áreas registradas" :value="$totalAreas" tone="info"
            help="Suma de todas las áreas, de todos los departamentos." />
        <x-ui.stat label="Sin áreas" :value="$emptyDepartments" :tone="$emptyDepartments > 0 ? 'warn' : 'neutral'"
            help="Departamentos que todavía no tienen ninguna área dada de alta." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre o descripción del departamento.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Nombre o descripción..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([5, 10, 25, 50] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search)
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
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Departamento</x-ui.th>
                <x-ui.th>Descripción</x-ui.th>
                <x-ui.th class="w-28">Áreas</x-ui.th>
                <x-ui.th align="right" class="w-32">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($departments as $department)
            <tr wire:key="department-{{ $department->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">
                    {{ $department->name }}
                </td>
                <td class="max-w-md truncate px-4 py-3 text-slate-600 dark:text-slate-300"
                    title="{{ $department->description }}">{{ $department->description ?: '—' }}</td>
                <td class="px-4 py-3">
                    @if ($department->areas_count > 0)
                        <x-ui.badge tone="info">{{ $department->areas_count }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="warn" dot>Sin áreas</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el departamento {{ $department->name }}"
                        :show="route('admin.departments.show', $department)"
                        :edit="route('admin.departments.edit', $department)"
                        :delete="$department->areas_count > 0 ? null : 'deleteDepartment('.$department->id.')'"
                        deleteConfirm="¿Eliminar el departamento «{{ $department->name }}»? Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">
                    <x-ui.empty icon="search" title="No se encontraron departamentos"
                        hint="Ajusta la búsqueda o da de alta un departamento nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.departments.create') }}">Nuevo departamento</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($departments->hasPages())
            <x-slot:foot>{{ $departments->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
