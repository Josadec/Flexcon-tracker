<<<<<<< HEAD
<x-ui.page eyebrow="Catálogo" back="{{ route('admin.departments.index') }}" backLabel="Departamentos"
    title="Crear departamento" subtitle="Completa la información para dar de alta un nuevo departamento.">

    <form wire:submit="saveDepartment" class="space-y-5">
        <x-ui.section title="Información del departamento">
            <div class="space-y-4">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input type="text" wire:model="name" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Descripción" optional :error="$errors->first('description')">
                    <textarea wire:model="description" rows="4" class="w-full"></textarea>
                </x-ui.field>

                <x-ui.field label="Comentarios" optional :error="$errors->first('comments')">
                    <input type="text" wire:model="comments" class="w-full">
                </x-ui.field>
            </div>
        </x-ui.section>

        <div class="flex justify-end gap-2">
=======
<x-ui.page eyebrow="Administración" title="Crear departamento"
    subtitle="Alta de un departamento. Sus áreas se dan de alta después, desde el módulo de Áreas."
    back="{{ route('admin.departments.index') }}" backLabel="Volver a departamentos">

    <form wire:submit="saveDepartment" class="space-y-5">
        <x-ui.section title="Identificación" hint="El nombre debe ser único: es como se referencia en áreas y reportes.">
            <x-ui.field label="Nombre" required :error="$errors->first('name')">
                <input wire:model="name" type="text" class="w-full" placeholder="Ej: Producción" required>
            </x-ui.field>

            <x-ui.field label="Descripción" optional class="mt-4"
                hint="Qué hace este departamento. Aparece en el listado."
                :error="$errors->first('description')">
                <textarea wire:model="description" rows="3" class="w-full"
                    placeholder="Descripción del departamento..."></textarea>
            </x-ui.field>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno. Máximo 255 caracteres." :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="2" class="w-full" placeholder="Notas adicionales..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
>>>>>>> dba4729e63772ad9744b5ced48b25a0fdb214628
            <x-ui.btn variant="secondary" href="{{ route('admin.departments.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear departamento</x-ui.btn>
        </div>
    </form>
</x-ui.page>
