<x-ui.page eyebrow="Administración" title="Crear turno"
    subtitle="Alta de un horario de trabajo. Después podrás asignarle empleados y configurar sus descansos."
    back="{{ route('admin.shifts.index') }}" backLabel="Volver a turnos">

    <form wire:submit="saveShift" class="space-y-5">
        <x-ui.section title="Identificación" hint="El nombre debe ser único: es como se elige el turno al dar de alta un empleado.">
            <x-ui.field label="Nombre" required :error="$errors->first('name')">
                <input wire:model="name" type="text" class="w-full" placeholder="Ej: Primer turno" required>
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
                <textarea wire:model="comments" rows="3" class="w-full" placeholder="Notas adicionales..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.shifts.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear turno</x-ui.btn>
        </div>
    </form>
</x-ui.page>
