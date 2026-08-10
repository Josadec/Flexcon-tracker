<x-ui.page eyebrow="Administración" title="Crear descanso"
    subtitle="Alta de una pausa dentro de un turno. Ese tiempo deja de contar como productivo."
    back="{{ route('admin.break-times.index') }}" backLabel="Volver a descansos">

    <form wire:submit="saveBreakTime" class="space-y-5">
        <x-ui.section title="Identificación" hint="El nombre debe ser único entre todos los descansos.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" placeholder="Ej: Comida" required>
                </x-ui.field>

                <x-ui.field label="Turno" required
                    hint="A qué turno pertenece esta pausa."
                    :error="$errors->first('shift_id')">
                    <select wire:model="shift_id" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected((int) $shift_id === (int) $shift->id)>
                                {{ $shift->name }}
                                @if ($shift->start_time && $shift->end_time)
                                    ({{ $shift->start_time->format('H:i') }} – {{ $shift->end_time->format('H:i') }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Horario" hint="Debe caer dentro del horario del turno al que pertenece.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Hora de inicio" required :error="$errors->first('start_break_time')">
                    <input wire:model="start_break_time" type="time" class="w-full tabular-nums" required>
                </x-ui.field>

                <x-ui.field label="Hora de fin" required :error="$errors->first('end_break_time')">
                    <input wire:model="end_break_time" type="time" class="w-full tabular-nums" required>
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Estado y notas" hint="Define si el descanso se descuenta del tiempo productivo.">
            <x-ui.check label="Descanso activo" hint="Los inactivos se conservan pero dejan de descontarse.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno." :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="3" class="w-full" placeholder="Notas adicionales..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.break-times.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear descanso</x-ui.btn>
        </div>
    </form>
</x-ui.page>
