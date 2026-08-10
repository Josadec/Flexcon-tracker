<x-ui.page eyebrow="Administración" title="Mesas"
    subtitle="Mesas de trabajo de la planta: a qué área pertenecen, cuánta gente las opera y si están en uso.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" wire:click="$dispatch('open-production-statuses')"
            title="Ver y administrar el catálogo de estados de producción">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
            Estados de producción
        </x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.tables.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva mesa
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total de mesas" :value="$stats['total']" />
        <x-ui.stat label="Activas" :value="$stats['active']" tone="good"
            help="Sólo las mesas activas se pueden elegir al configurar un estándar." />
        <x-ui.stat label="Inactivas" :value="$stats['inactive']" :tone="$stats['inactive'] > 0 ? 'warn' : 'neutral'" />
        <x-ui.stat label="Empleados por mesa" :value="$stats['avg_employees']" tone="info"
            help="Promedio de operadores capturado en las mesas." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por número, nombre o número de activo, y acota por área o estado.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-ui.field label="Texto a buscar" class="lg:col-span-2">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Número, nombre o activo..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Área">
                <select wire:model.live="filterArea" class="w-full">
                    <option value="">Todas</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="">Todos</option>
                    <option value="1">Activas</option>
                    <option value="0">Inactivas</option>
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

        @if ($search || $filterArea || $filterStatus !== '')
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
                <x-ui.th sort="number" :field="$sortField" :direction="$sortDirection">Mesa</x-ui.th>
                <x-ui.th sort="area_id" :field="$sortField" :direction="$sortDirection">Área</x-ui.th>
                <x-ui.th sort="employees" :field="$sortField" :direction="$sortDirection">Empleados</x-ui.th>
                <x-ui.th>Estado de producción</x-ui.th>
                <x-ui.th sort="active" :field="$sortField" :direction="$sortDirection">Estado</x-ui.th>
                <x-ui.th align="right" class="w-32">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($tables as $table)
            <tr wire:key="table-{{ $table->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $table->number }}</span>
                    @if ($table->name)
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $table->name }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $table->area?->name ?: '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                    {{ $table->employees ?: '—' }}
                </td>
                <td class="px-4 py-3">
                    @if ($table->productionStatus)
                        <x-ui.badge tone="accent">{{ $table->productionStatus->name }}</x-ui.badge>
                    @else
                        <span class="text-slate-400 dark:text-slate-500">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if ($table->active)
                        <x-ui.badge tone="good" dot>Activa</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Inactiva</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="la mesa {{ $table->number }}"
                        :show="route('admin.tables.show', $table)"
                        :edit="route('admin.tables.edit', $table)"
                        delete="deleteTable({{ $table->id }})"
                        deleteConfirm="¿Eliminar la mesa «{{ $table->number }}»? Dejará de aparecer en el sistema y no se podrá elegir en estándares nuevos." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty icon="search" title="No se encontraron mesas"
                        hint="Ajusta la búsqueda o los filtros de área y estado, o da de alta una mesa nueva.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.tables.create') }}">Nueva mesa</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($tables->hasPages())
            <x-slot:foot>{{ $tables->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Catálogo compartido de estados: se abre con el botón del encabezado. --}}
    <livewire:admin.production-statuses.production-status-manager />
</x-ui.page>
