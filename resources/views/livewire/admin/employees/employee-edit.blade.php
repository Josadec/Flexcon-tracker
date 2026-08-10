<x-ui.page eyebrow="Administración" :title="'Editar '.$employee->full_name"
    subtitle="Datos de planta y acceso del empleado. El número de empleado no se puede modificar."
    back="{{ route('admin.employees.index') }}" backLabel="Volver a empleados">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.employees.show', $employee) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="save" class="space-y-5">
        <x-ui.section title="Identidad" hint="Como aparecerá en listados, pesajes y reportes.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Apellido" required :error="$errors->first('last_name')">
                    <input wire:model="last_name" type="text" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Número de empleado" hint="Asignado al dar de alta; no se puede modificar.">
                    <div class="flex min-h-10 w-full items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                        {{ $employee->employee_number ?: '— sin número —' }}
                    </div>
                </x-ui.field>

                <x-ui.field label="Fecha de nacimiento" optional :error="$errors->first('birth_date')">
                    <input wire:model="birth_date" type="date" class="w-full">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Acceso" hint="Con este correo entra al panel de empleado.">
            <x-ui.field label="Correo electrónico" required
                hint="Debe ser único en todo el sistema."
                :error="$errors->first('email')">
                <input wire:model="email" type="email" class="w-full" required>
            </x-ui.field>

            <x-ui.note tone="muted" class="mt-4">
                Deja la contraseña en blanco para conservar la actual. Sólo se cambia si escribes una nueva.
            </x-ui.note>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nueva contraseña" optional hint="Mínimo 8 caracteres."
                    :error="$errors->first('password')">
                    <input wire:model="password" type="password" class="w-full" autocomplete="new-password">
                </x-ui.field>

                <x-ui.field label="Confirmar contraseña" optional :error="$errors->first('password_confirmation')">
                    <input wire:model="password_confirmation" type="password" class="w-full" autocomplete="new-password">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Puesto" hint="Dónde y en qué horario trabaja. Ambos son obligatorios.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Área" required
                    hint="Área de planta en la que trabaja."
                    :error="$errors->first('area_id')">
                    <select wire:model="area_id" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((int) $area_id === (int) $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Turno" required
                    hint="Sólo aparecen los turnos activos."
                    :error="$errors->first('shift_id')">
                    <select wire:model="shift_id" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected((int) $shift_id === (int) $shift->id)>{{ $shift->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Posición o cargo" optional :error="$errors->first('position')">
                    <input wire:model="position" type="text" class="w-full">
                </x-ui.field>

                <x-ui.field label="Fecha de ingreso" optional :error="$errors->first('entry_date')">
                    <input wire:model="entry_date" type="date" class="w-full">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Estado y notas" hint="Define si el empleado sigue en operación.">
            <x-ui.check label="Empleado activo" hint="Los inactivos conservan su historial, pero no cuentan como personal en operación.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno." :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="3" class="w-full"></textarea>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Alta" :value="$employee->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$employee->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.employees.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
