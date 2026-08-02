<x-ui.page eyebrow="Producción" title="Estándares"
    subtitle="Ritmo de producción esperado (UPH) por parte y tipo de estación.">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.standards.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo estándar
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total" :value="$stats['total']" />
        <x-ui.stat label="Activos" :value="$stats['active']" tone="good" />
        <x-ui.stat label="Inactivos" :value="$stats['inactive']" />
        <x-ui.stat label="Vigentes" :value="$stats['current']" tone="info"
            help="Estándares activos cuya vigencia cubre la fecha de hoy." />
    </x-ui.stats>

    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por parte, estado o tipo de estación.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Número de parte o descripción..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="all">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Tipo de estación">
                <select wire:model.live="filterWorkstationType" class="w-full">
                    <option value="all">Todos</option>
                    @foreach ($workstationTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
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

        @if ($search || $filterStatus !== 'all' || $filterWorkstationType !== 'all')
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm"
                    wire:click="$set('search', ''); $set('filterStatus', 'all'); $set('filterWorkstationType', 'all')">
                    Limpiar filtros
                </x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th>Parte</x-ui.th>
                <x-ui.th>Configuraciones</x-ui.th>
                <x-ui.th align="right">UPH por defecto</x-ui.th>
                <x-ui.th sort="active" :field="$sortField" :direction="$sortDirection">Estado</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($standards as $standard)
            @php
                $configSummary = $this->getConfigurationSummary($standard);
                $typeMeta = [
                    'manual'         => ['Manual', 'good'],
                    'semi_automatic' => ['Semi-auto', 'warn'],
                    'machine'        => ['Máquina', 'accent'],
                ];
            @endphp
            <tr wire:key="standard-{{ $standard->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <div class="font-semibold text-slate-900 dark:text-white">{{ $standard->part->number }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ Str::limit($standard->part->description, 40) ?: '—' }}</div>
                </td>
                <td class="px-4 py-3">
                    @if ($configSummary['count'] > 0)
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-ui.badge tone="info">{{ $configSummary['count'] }} config.</x-ui.badge>
                            @foreach ($configSummary['types'] as $type => $count)
                                @php [$typeLabel, $typeTone] = $typeMeta[$type] ?? [$type, 'neutral']; @endphp
                                <x-ui.badge :tone="$typeTone">{{ $typeLabel }}: {{ $count }}</x-ui.badge>
                            @endforeach
                        </div>
                    @else
                        <x-ui.badge tone="neutral">Sin configuraciones</x-ui.badge>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ $configSummary['default_uph'] ?? '—' }}
                    <span class="text-xs font-medium text-slate-400">uph</span>
                </td>
                <td class="px-4 py-3">
                    @if ($standard->active)
                        <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="el estándar de {{ $standard->part->number }}"
                        :show="route('admin.standards.show', $standard)"
                        :edit="route('admin.standards.edit', $standard)"
                        delete="deleteStandard({{ $standard->id }})"
                        deleteConfirm="¿Eliminar el estándar de «{{ $standard->part->number }}»? Se eliminarán todas sus configuraciones.">
                        {{-- Activar/desactivar va antes que las acciones estándar. --}}
                        @if ($standard->active)
                            <x-ui.icon-btn tone="neutral" label="Desactivar este estándar"
                                wire:click="toggleActive({{ $standard->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            </x-ui.icon-btn>
                        @else
                            <x-ui.icon-btn tone="success" label="Activar este estándar"
                                wire:click="toggleActive({{ $standard->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </x-ui.icon-btn>
                        @endif
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-ui.empty icon="search" title="No se encontraron estándares"
                        hint="Ajusta los filtros o crea el estándar de una parte.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.standards.create') }}">Nuevo estándar</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($standards->hasPages())
            <x-slot:foot>{{ $standards->links() }}</x-slot:foot>
        @endif
    </x-ui.table>
</x-ui.page>
