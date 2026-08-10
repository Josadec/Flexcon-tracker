@php $rolesConEstePermiso = $permission->roles()->orderBy('name')->get(); @endphp

<x-ui.page eyebrow="Administración" :title="'Editar '.$permission->name"
    subtitle="Renombrar un permiso lo cambia para todos los roles que ya lo tienen asignado."
    back="{{ route('admin.permissions.index') }}" backLabel="Volver a permisos">

    <form wire:submit="save" class="space-y-5">
        @if ($rolesConEstePermiso->isNotEmpty())
            <x-ui.note tone="warn">
                Este permiso está en uso por
                {{ $rolesConEstePermiso->count() }} {{ \Illuminate\Support\Str::plural('rol', $rolesConEstePermiso->count()) }}.
                Si el código verifica el permiso por su nombre, renombrarlo puede dejar esa verificación sin efecto.
            </x-ui.note>
        @endif

        <x-ui.section title="Identificación" hint="La convención del sistema es «grupo.accion»: el prefijo agrupa el permiso.">
            <x-ui.field label="Nombre del permiso" required
                hint="En minúsculas y sin espacios. Ejemplos: usuarios.create-users, admin.view-reports."
                :error="$errors->first('name')">
                <input wire:model="name" type="text" class="w-full font-mono" required>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Roles que lo tienen" hint="Los permisos se asignan desde la ficha de cada rol.">
            @if ($rolesConEstePermiso->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($rolesConEstePermiso as $rol)
                        <a href="{{ route('admin.roles.edit', $rol) }}" wire:navigate
                            class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 hover:bg-sky-200 dark:bg-sky-900/40 dark:text-sky-300 dark:hover:bg-sky-900/70">
                            {{ $rol->name }}
                        </a>
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="doc" title="Ningún rol lo tiene asignado"
                    hint="Mientras ningún rol lo use, este permiso no da acceso a nada — y se puede eliminar desde el listado." />
            @endif
        </x-ui.section>

        <x-ui.section title="Registro" hint="Datos de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Guard" :value="$permission->guard_name" help="Contexto de autenticación al que aplica." />
                <x-ui.kv label="Alta" :value="$permission->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$permission->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.permissions.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
