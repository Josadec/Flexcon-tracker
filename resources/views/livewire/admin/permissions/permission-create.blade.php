<x-ui.page eyebrow="Administración" title="Crear permiso"
    subtitle="Un permiso es una acción suelta. Sólo tiene efecto cuando se lo asignas a un rol."
    back="{{ route('admin.permissions.index') }}" backLabel="Volver a permisos">

    <form wire:submit="save" class="space-y-5">
        <x-ui.section title="Identificación" hint="La convención del sistema es «grupo.accion»: el prefijo agrupa el permiso.">
            <x-ui.field label="Nombre del permiso" required
                hint="En minúsculas y sin espacios. Ejemplos: usuarios.create-users, admin.view-reports."
                :error="$errors->first('name')">
                <input wire:model="name" type="text" class="w-full font-mono"
                    placeholder="Ej: catalogos.edit-parts" required>
            </x-ui.field>

            <x-ui.note tone="info" class="mt-4">
                El prefijo antes del punto decide en qué grupo aparece al configurar un rol. Si lo escribes sin punto,
                caerá en <strong>Otros</strong>.
            </x-ui.note>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.permissions.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear permiso</x-ui.btn>
        </div>
    </form>
</x-ui.page>
