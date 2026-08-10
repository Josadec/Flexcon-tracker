<x-ui.page eyebrow="Administración" :title="'Editar '.$user->full_name"
    subtitle="Los cambios de rol y área aplican en cuanto el usuario vuelve a cargar el sistema."
    back="{{ route('admin.users.index') }}" backLabel="Volver a usuarios">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.users.show', $user) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="updateUser" class="space-y-5">
        <x-ui.section title="Identidad" hint="Como aparecerá el usuario en listados, firmas y viajeros.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Apellido" optional :error="$errors->first('last_name')">
                    <input wire:model="last_name" type="text" class="w-full">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Acceso" hint="Con el correo entra al sistema; la cuenta es su clave corta interna.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Correo electrónico" required
                    hint="Debe ser único. Es el usuario con el que inicia sesión."
                    :error="$errors->first('email')">
                    <input wire:model="email" type="email" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Cuenta" optional
                    hint="Número o clave con la que se le identifica en planta."
                    :error="$errors->first('account')">
                    <input wire:model="account" type="text" class="w-full">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Rol y asignación" hint="El rol decide los permisos; el área sólo se usa para quien supervisa.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-ui.field label="Rol" required
                    hint="Sin rol, el usuario entra pero no ve ningún módulo."
                    :error="$errors->first('selected_role')">
                    <select wire:model.live="selected_role" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected($selected_role === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Departamento" optional
                    hint="Filtra las áreas disponibles."
                    :error="$errors->first('department_id')">
                    <select wire:model.live="department_id" class="w-full">
                        <option value="">Seleccionar</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) $department_id === (int) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Área" :required="$selected_role === 'Supervisor'"
                    :optional="$selected_role !== 'Supervisor'"
                    hint="Primero elige un departamento."
                    :error="$errors->first('area_id')">
                    <select wire:model="area_id" class="w-full" @disabled(!$department_id)>
                        <option value="">Seleccionar</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((int) $area_id === (int) $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            @if ($area_id && $selected_role !== 'Supervisor')
                <x-ui.note tone="warn" class="mt-4">
                    El área sólo se asigna cuando el rol es <strong>Supervisor</strong>. Al guardar con el rol
                    <strong>{{ $selected_role ?: 'seleccionado' }}</strong>, este usuario quedará sin área a su cargo.
                </x-ui.note>
            @endif
        </x-ui.section>

        <x-ui.section title="Contraseña" hint="Sólo se cambia si lo pides expresamente; si no, se conserva la actual.">
            <x-ui.check label="Cambiar contraseña" hint="Marca la casilla para capturar una contraseña nueva.">
                <input wire:model.live="changePassword" type="checkbox">
            </x-ui.check>

            @if ($changePassword)
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ui.field label="Nueva contraseña" required hint="Mínimo 8 caracteres."
                        :error="$errors->first('password')">
                        <input wire:model="password" type="password" class="w-full" autocomplete="new-password">
                    </x-ui.field>

                    <x-ui.field label="Confirmar contraseña" required :error="$errors->first('password_confirmation')">
                        <input wire:model="password_confirmation" type="password" class="w-full" autocomplete="new-password">
                    </x-ui.field>
                </div>
            @endif
        </x-ui.section>

        <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Alta" :value="$user->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$user->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.users.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
