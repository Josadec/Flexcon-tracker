<x-ui.page eyebrow="Administración" title="Crear semi-automático"
    subtitle="Alta de una estación semi-automática. Se identifica por su número dentro del área donde está."
    back="{{ route('admin.semi-automatics.index') }}" backLabel="Volver a semi-automáticos">

    <form wire:submit="save" class="space-y-5">
        <x-ui.section title="Identificación" hint="El número debe ser único: es como se nombra la estación en piso y en los estándares.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Número de estación" required :error="$errors->first('number')">
                    <input wire:model="number" type="text" class="w-full" placeholder="Ej: SA-01" required>
                </x-ui.field>

                <x-ui.field label="Área" required
                    hint="Área de la planta donde está físicamente la estación."
                    :error="$errors->first('area_id')">
                    <select wire:model="area_id" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Operación" hint="Con cuánta gente trabaja la estación y si está en uso.">
            <x-ui.field label="Número de empleados" optional
                hint="Cuántos operadores trabajan en ella. Mínimo 1."
                :error="$errors->first('employees')">
                <input wire:model="employees" type="number" min="1" step="1"
                    class="w-full text-right tabular-nums md:max-w-[12rem]" placeholder="Ej: 2">
            </x-ui.field>

            <x-ui.check label="Estación activa" class="mt-4"
                hint="Sólo las estaciones activas se pueden elegir al configurar un estándar.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno." :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="3" class="w-full" placeholder="Notas adicionales..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.semi-automatics.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear semi-automático</x-ui.btn>
        </div>
    </form>
</x-ui.page>
