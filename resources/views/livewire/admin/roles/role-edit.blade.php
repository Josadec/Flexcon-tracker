<x-ui.page eyebrow="Administración" :title="'Editar rol '.$role->name"
    subtitle="Los cambios de permisos aplican a todos los usuarios con este rol en cuanto vuelvan a cargar el sistema."
    back="{{ route('admin.roles.index') }}" backLabel="Volver a roles">

    <form wire:submit="updateRole" class="space-y-5">
        @if ($role->name === 'admin')
            <x-ui.note tone="warn">
                <strong>admin</strong> es el rol con acceso total al panel. Quitarle permisos puede dejar el sistema
                sin nadie capaz de administrarlo.
            </x-ui.note>
        @endif

        <x-ui.section title="Identificación" hint="El nombre debe ser único; es el que se elige en la ficha del usuario.">
            <x-ui.field label="Nombre del rol" required
                hint="Si lo cambias, los usuarios conservan el rol: sólo cambia cómo se llama."
                :error="$errors->first('name')">
                <input wire:model="name" type="text" class="w-full" required>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Permisos" hint="Marca lo que este rol podrá hacer. Puedes marcar un grupo completo de una vez.">
            <x-slot:aside>
                <x-ui.badge tone="info">{{ count($selectedPermissions) }} de {{ $permissions->count() }}</x-ui.badge>
            </x-slot:aside>

            @error('selectedPermissions')
                <x-ui.note tone="danger" class="mb-4">{{ $message }}</x-ui.note>
            @enderror

            @if ($permissions->isNotEmpty())
                <div class="space-y-4">
                    @foreach ($groupedPermissions as $group => $groupPermissions)
                        @php $ids = $groupPermissions->pluck('id')->all(); @endphp
                        <div wire:key="group-{{ $group }}"
                            class="rounded-lg border border-slate-200 dark:border-slate-700">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-900/40">
                                <span class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ $groupLabels[$group] ?? ucfirst($group) }}
                                    <span class="ml-1 font-normal text-slate-500 dark:text-slate-400">({{ count($ids) }})</span>
                                </span>
                                <x-ui.btn variant="ghost" size="sm" wire:click="toggleGroup({{ json_encode($ids) }})">
                                    Marcar o desmarcar todo
                                </x-ui.btn>
                            </div>

                            <div class="grid grid-cols-1 gap-x-6 gap-y-1 px-4 py-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($groupPermissions as $permission)
                                    <label wire:key="perm-{{ $permission->id }}"
                                        class="flex cursor-pointer items-center gap-2 rounded px-1 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                        <input type="checkbox" wire:model.live="selectedPermissions"
                                            value="{{ (string) $permission->id }}">
                                        <span class="min-w-0 truncate text-sm text-slate-700 dark:text-slate-200"
                                            title="{{ $permission->name }}">{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="doc" title="Todavía no hay permisos"
                    hint="Sin permisos en el catálogo, este rol no puede dar acceso a nada.">
                    <x-slot:action>
                        <x-ui.btn variant="primary" href="{{ route('admin.permissions.create') }}">Crear un permiso</x-ui.btn>
                    </x-slot:action>
                </x-ui.empty>
            @endif
        </x-ui.section>

        <x-ui.section title="Registro" hint="Datos de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Usuarios con este rol" :value="$role->users()->count()"
                    help="Mientras tenga usuarios, el rol no se puede eliminar." />
                <x-ui.kv label="Alta" :value="$role->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$role->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.roles.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
