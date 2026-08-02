<x-ui.page eyebrow="Configuración" title="Editar área"
    subtitle="Los cambios afectan a los equipos y viajeros asignados a esta área."
    back="{{ route('admin.areas.index') }}" backLabel="Volver a áreas">

    <form wire:submit="updateArea" class="space-y-5">
        <x-ui.section title="Información del área" hint="El nombre y el departamento son obligatorios.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required
                    hint="Como se conoce el área en piso."
                    :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Departamento" required
                    hint="Define a qué jefatura pertenece."
                    :error="$errors->first('department_id')">
                    <select wire:model="department_id" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) $department_id === (int) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <x-ui.field label="Supervisor" optional class="mt-4" :error="$errors->first('user_id')">
                <select wire:model="user_id" class="w-full">
                    <option value="">Sin supervisor</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) $user_id === (int) $user->id)>{{ $user->full_name }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Detalles" hint="Información de apoyo. Nada de esto es obligatorio.">
            <x-ui.field label="Descripción" optional :error="$errors->first('description')">
                <textarea wire:model="description" rows="4" class="w-full"></textarea>
            </x-ui.field>

            <x-ui.field label="Comentarios" optional class="mt-4" :error="$errors->first('comments')">
                <input wire:model="comments" type="text" class="w-full">
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.areas.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
