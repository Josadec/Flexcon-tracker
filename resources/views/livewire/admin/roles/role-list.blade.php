<x-ui.page eyebrow="Administración" title="Roles"
    subtitle="Cada rol es un paquete de permisos. El rol de un usuario decide qué módulos ve al entrar.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.permissions.index') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            Ver permisos
        </x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.roles.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo rol
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total de roles" :value="$totalRoles" />
        <x-ui.stat label="Permisos disponibles" :value="$totalPermissions" tone="info"
            help="Permisos que se pueden repartir entre los roles." />
        <x-ui.stat label="Sin permisos" :value="$rolesWithoutPermissions"
            :tone="$rolesWithoutPermissions > 0 ? 'warn' : 'neutral'"
            help="Un rol sin permisos deja al usuario dentro del sistema pero sin acceso a nada." />
        <x-ui.stat label="Sin usuarios" :value="$rolesWithoutUsers"
            help="Roles que nadie tiene asignado. Son los únicos que se pueden eliminar." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre del rol.">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Nombre del rol..." class="w-full pl-10">
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
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Rol</x-ui.th>
                <x-ui.th class="w-40">Usuarios</x-ui.th>
                <x-ui.th class="w-40">Permisos</x-ui.th>
                <x-ui.th sort="created_at" :field="$sortField" :direction="$sortDirection" class="w-32">Alta</x-ui.th>
                <x-ui.th align="right" class="w-28">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($roles as $role)
            <tr wire:key="role-{{ $role->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3">
                    <span class="font-semibold text-slate-900 dark:text-white">{{ $role->name }}</span>
                    @if ($role->name === 'admin')
                        <x-ui.badge tone="accent" class="ml-1.5">Acceso total</x-ui.badge>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <x-ui.badge :tone="$role->users_count > 0 ? 'info' : 'neutral'">
                        {{ $role->users_count }} {{ \Illuminate\Support\Str::plural('usuario', $role->users_count) }}
                    </x-ui.badge>
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($role->permissions_count > 0)
                        <x-ui.badge tone="neutral">
                            {{ $role->permissions_count }} {{ \Illuminate\Support\Str::plural('permiso', $role->permissions_count) }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge tone="warn" dot>Sin permisos</x-ui.badge>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $role->created_at?->format('d/m/Y') ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    {{-- Eliminar sólo aparece si nadie tiene el rol: así el botón
                         visible siempre es un botón que funciona. --}}
                    <x-ui.row-actions label="el rol {{ $role->name }}"
                        :edit="route('admin.roles.edit', $role)"
                        :delete="$role->users_count === 0 ? 'deleteRole('.$role->id.')' : null"
                        deleteConfirm="¿Eliminar el rol «{{ $role->name }}»? Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-ui.empty icon="search" title="No se encontraron roles"
                        hint="Ajusta la búsqueda o crea un rol nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.roles.create') }}">Nuevo rol</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($roles->hasPages())
            <x-slot:foot>{{ $roles->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    <x-ui.note tone="muted">
        Un rol con usuarios asignados no se puede eliminar. Cambia primero el rol de esos usuarios desde
        <a href="{{ route('admin.users.index') }}" wire:navigate class="font-bold underline">Usuarios</a>.
    </x-ui.note>
</x-ui.page>
