<x-ui.page eyebrow="Administración" :title="'Editar '.$semiAutomatic->number"
    subtitle="Ubicación y operación de la estación. El número la identifica en los estándares."
    back="{{ route('admin.semi-automatics.index') }}" backLabel="Volver a semi-automáticos">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.semi-automatics.show', $semiAutomatic) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="update" class="space-y-5">
        <x-ui.section title="Identificación" hint="El número debe ser único: es como se nombra la estación en piso y en los estándares.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Número de estación" required :error="$errors->first('number')">
                    <input wire:model="number" type="text" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Área" required
                    hint="Área de la planta donde está físicamente la estación."
                    :error="$errors->first('area_id')">
                    <select wire:model="area_id" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((int) $area_id === (int) $area->id)>{{ $area->name }}</option>
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
                    class="w-full text-right tabular-nums md:max-w-[12rem]">
            </x-ui.field>

            <x-ui.check label="Estación activa" class="mt-4"
                hint="Sólo las estaciones activas se pueden elegir al configurar un estándar.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno." :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="3" class="w-full"></textarea>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Alta" :value="$semiAutomatic->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$semiAutomatic->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.semi-automatics.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
