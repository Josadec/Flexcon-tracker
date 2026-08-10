<x-ui.page eyebrow="Administración" title="Crear empleado"
    subtitle="Alta de personal de planta. Se crea con el rol «employee», con acceso al panel de empleado."
    back="{{ route('admin.employees.index') }}" backLabel="Volver a empleados">

    <form wire:submit="save" class="space-y-5">
        <x-ui.section title="Identidad" hint="Como aparecerá en listados, pesajes y reportes.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" placeholder="Ej: Juan" required>
                </x-ui.field>

                <x-ui.field label="Apellido" required :error="$errors->first('last_name')">
                    <input wire:model="last_name" type="text" class="w-full" placeholder="Ej: Pérez" required>
                </x-ui.field>

                <x-ui.field label="Número de empleado" optional
                    hint="Déjalo vacío si aún no lo tienes. Ojo: después no se puede cambiar."
                    :error="$errors->first('employee_number')">
                    <input wire:model="employee_number" type="text" class="w-full font-mono" placeholder="Ej: EMP260001">
                </x-ui.field>

                <x-ui.field label="Fecha de nacimiento" optional :error="$errors->first('birth_date')">
                    <input wire:model="birth_date" type="date" class="w-full">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Acceso" hint="Con este correo y contraseña entra al panel de empleado.">
            <x-ui.field label="Correo electrónico" required
                hint="Debe ser único en todo el sistema."
                :error="$errors->first('email')">
                <input wire:model="email" type="email" class="w-full" placeholder="Ej: juan.perez@flexcon.la"
                    autocomplete="off" required>
            </x-ui.field>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Contraseña" required hint="Mínimo 8 caracteres." :error="$errors->first('password')">
                    <input wire:model="password" type="password" class="w-full" autocomplete="new-password" required>
                </x-ui.field>

                <x-ui.field label="Confirmar contraseña" required :error="$errors->first('password_confirmation')">
                    <input wire:model="password_confirmation" type="password" class="w-full"
                        autocomplete="new-password" required>
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
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Turno" required
                    hint="Sólo aparecen los turnos activos."
                    :error="$errors->first('shift_id')">
                    <select wire:model="shift_id" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Posición o cargo" optional :error="$errors->first('position')">
                    <input wire:model="position" type="text" class="w-full" placeholder="Ej: Operador">
                </x-ui.field>

                <x-ui.field label="Fecha de ingreso" optional :error="$errors->first('entry_date')">
                    <input wire:model="entry_date" type="date" class="w-full">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Estado y notas" hint="Define si el empleado ya está en operación.">
            <x-ui.check label="Empleado activo" hint="Los inactivos conservan su historial, pero no cuentan como personal en operación.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno." :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="3" class="w-full" placeholder="Notas adicionales..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.employees.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear empleado</x-ui.btn>
        </div>
    </form>
</x-ui.page>
