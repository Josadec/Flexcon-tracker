<x-ui.page eyebrow="Configuración" title="Áreas"
    subtitle="Áreas de trabajo del sistema y el equipo asignado a cada una.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.areas.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva área
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="2">
        <x-ui.stat label="Total de áreas" :value="$totalAreas" />
        <x-ui.stat label="Departamentos" :value="$totalDepartments" tone="info" />
    </x-ui.stats>

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra el listado por nombre, descripción o departamento.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Nombre o descripción..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Departamento">
                <select wire:model.live="departmentFilter" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
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
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Nombre</x-ui.th>
                <x-ui.th>Departamento</x-ui.th>
                <x-ui.th>Supervisor</x-ui.th>
                <x-ui.th align="center">Equipos</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($areas as $area)
            <tr wire:key="area-{{ $area->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $area->name }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $area->department_name }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $area->supervisor_name }}</td>
                <td class="px-4 py-3 text-center">
                    @php $equipCount = $area->machines->count() + $area->tables->count() + $area->semiAutomatics->count(); @endphp
                    <x-ui.badge :tone="$equipCount > 0 ? 'info' : 'neutral'">{{ $equipCount }}</x-ui.badge>
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el área {{ $area->name }}"
                        :show="route('admin.areas.show', $area)"
                        :edit="route('admin.areas.edit', $area)"
                        delete="deleteArea({{ $area->id }})"
                        deleteConfirm="¿Eliminar el área «{{ $area->name }}»? Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-ui.empty icon="search" title="No se encontraron áreas"
                        hint="Prueba con otro texto de búsqueda o quita el filtro de departamento." />
                </td>
            </tr>
        @endforelse

        @if ($areas->hasPages())
            <x-slot:foot>{{ $areas->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
