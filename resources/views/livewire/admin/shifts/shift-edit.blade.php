<x-ui.page eyebrow="Administración" :title="'Editar '.$shift->name"
    subtitle="Cambiar el horario afecta a los empleados que ya tiene asignados."
    back="{{ route('admin.shifts.index') }}" backLabel="Volver a turnos">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.shifts.show', $shift) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="updateShift" class="space-y-5">
        <x-ui.section title="Identificación" hint="El nombre debe ser único: es como se elige el turno al dar de alta un empleado.">
            <x-ui.field label="Nombre" required :error="$errors->first('name')">
                <input wire:model="name" type="text" class="w-full" required>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Horario" hint="Si el turno cruza la medianoche, captura la salida del día siguiente igual (ej. 22:00 a 06:00).">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Hora de entrada" required :error="$errors->first('start_time')">
                    <input wire:model="start_time" type="time" class="w-full tabular-nums" required>
                </x-ui.field>

                <x-ui.field label="Hora de salida" required :error="$errors->first('end_time')">
                    <input wire:model="end_time" type="time" class="w-full tabular-nums" required>
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Estado y notas" hint="Define si el turno se puede elegir al asignar empleados.">
            <x-ui.check label="Turno activo" hint="Los inactivos no aparecen al dar de alta o editar un empleado.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno." :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="3" class="w-full"></textarea>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Registro" hint="Datos de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Empleados en el turno" :value="$shift->allEmployees()->count()"
                    help="Mientras tenga empleados, descansos o tiempo extra, el turno no se puede eliminar." />
                <x-ui.kv label="Descansos configurados" :value="$shift->BreakTimes()->count()" />
                <x-ui.kv label="Alta" :value="$shift->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$shift->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.shifts.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
