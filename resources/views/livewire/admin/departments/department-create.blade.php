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
            <x-ui.btn variant="secondary" href="{{ route('admin.departments.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear departamento</x-ui.btn>
        </div>
    </form>
</x-ui.page>
