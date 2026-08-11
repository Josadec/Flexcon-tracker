<x-ui.page eyebrow="Administración" :title="'Editar ' . $department->name"
    subtitle="El cambio de nombre se refleja en todas las áreas y reportes que lo mencionan."
    back="{{ route('admin.departments.index') }}" backLabel="Volver a departamentos">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.departments.show', $department) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="updateDepartment" class="space-y-5">
        <x-ui.section title="Identificación" hint="El nombre debe ser único: es como se referencia en áreas y reportes.">
            <x-ui.field label="Nombre" required :error="$errors->first('name')">
                <input wire:model="name" type="text" class="w-full" required>
            </x-ui.field>

            <x-ui.field label="Descripción" optional class="mt-4"
                hint="Qué hace este departamento. Aparece en el listado." :error="$errors->first('description')">
                <textarea wire:model="description" rows="3" class="w-full"></textarea>
            </x-ui.field>

            <x-ui.field label="Comentarios" optional class="mt-4" hint="Uso interno. Máximo 255 caracteres."
                :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="2" class="w-full"></textarea>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Registro" hint="Datos de control, sólo lectura.">
            <dl
                class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Áreas en este departamento" :value="$department->areas()->count()"
                    help="Mientras tenga áreas, el departamento no se puede eliminar." />
                <x-ui.kv label="Alta" :value="$department->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$department->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div
            class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.departments.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
