<x-ui.page eyebrow="Administración" title="Permisos"
    subtitle="Catálogo de acciones que se pueden repartir entre los roles. Un permiso sólo sirve cuando algún rol lo tiene.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.roles.index') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            Ver roles
        </x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.permissions.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo permiso
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total de permisos" :value="$totalPermissions" />
        <x-ui.stat label="Grupos" :value="$groups->count()" tone="info"
            help="Familias según el prefijo del nombre (admin, usuarios, catalogos...)." />
        <x-ui.stat label="Roles" :value="$totalRoles" />
        <x-ui.stat label="Sin usar" :value="$orphanPermissions" :tone="$orphanPermissions > 0 ? 'warn' : 'neutral'"
            help="Permisos que ningún rol tiene asignados. Son los únicos que se pueden eliminar." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre del permiso o acota por grupo.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Ej: usuarios.edit..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Grupo">
                <select wire:model.live="filterGroup" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group }}">{{ $group }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([15, 25, 50, 100] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search || $filterGroup)
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
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Permiso</x-ui.th>
                <x-ui.th class="w-40">Grupo</x-ui.th>
                <x-ui.th class="w-36">Roles</x-ui.th>
                <x-ui.th sort="created_at" :field="$sortField" :direction="$sortDirection" class="w-32">Alta</x-ui.th>
                <x-ui.th align="right" class="w-28">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($permissions as $permission)
            @php
                $partes = explode('.', $permission->name);
                $grupo = count($partes) > 1 ? $partes[0] : null;
                $accion = count($partes) > 1 ? implode('.', array_slice($partes, 1)) : $permission->name;
            @endphp
            <tr wire:key="permission-{{ $permission->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <span class="block font-mono text-sm font-semibold text-slate-900 dark:text-white">{{ $permission->name }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $accion }}</span>
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($grupo)
                        <x-ui.badge tone="neutral">{{ $grupo }}</x-ui.badge>
                    @else
                        <span class="text-slate-400 dark:text-slate-500">Sin grupo</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($permission->roles_count > 0)
                        <x-ui.badge tone="info">
                            {{ $permission->roles_count }} {{ \Illuminate\Support\Str::plural('rol', $permission->roles_count) }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge tone="warn" dot>Sin usar</x-ui.badge>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $permission->created_at?->format('d/m/Y') ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    {{-- Eliminar sólo aparece si ningún rol lo usa: así el botón
                         visible siempre es un botón que funciona. --}}
                    <x-ui.row-actions label="el permiso {{ $permission->name }}"
                        :edit="route('admin.permissions.edit', $permission)"
                        :delete="$permission->roles_count === 0 ? 'deletePermission('.$permission->id.')' : null"
                        deleteConfirm="¿Eliminar el permiso «{{ $permission->name }}»? Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-ui.empty icon="search" title="No se encontraron permisos"
                        hint="Ajusta la búsqueda o el grupo, o crea un permiso nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.permissions.create') }}">Nuevo permiso</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($permissions->hasPages())
            <x-slot:foot>{{ $permissions->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    <x-ui.note tone="muted">
        Un permiso asignado a algún rol no se puede eliminar. Quítalo primero de esos roles desde
        <a href="{{ route('admin.roles.index') }}" wire:navigate class="font-bold underline">Roles</a>.
    </x-ui.note>
</x-ui.page>
