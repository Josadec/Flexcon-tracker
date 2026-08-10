<x-ui.page eyebrow="Administración" title="Crear usuario"
    subtitle="Alta de una persona con acceso al sistema. El rol define qué módulos verá al entrar."
    back="{{ route('admin.users.index') }}" backLabel="Volver a usuarios">

    <form wire:submit="saveUser" class="space-y-5">
        <x-ui.section title="Identidad" hint="Como aparecerá el usuario en listados, firmas y viajeros.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" placeholder="Ej: Juan" required>
                </x-ui.field>

                <x-ui.field label="Apellido" optional :error="$errors->first('last_name')">
                    <input wire:model="last_name" type="text" class="w-full" placeholder="Ej: Pérez">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Acceso" hint="Con el correo entra al sistema; la cuenta es su clave corta interna.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Correo electrónico" required
                    hint="Debe ser único. Es el usuario con el que inicia sesión."
                    :error="$errors->first('email')">
                    <input wire:model="email" type="email" class="w-full" placeholder="Ej: juan.perez@flexcon.la"
                        autocomplete="off" required>
                </x-ui.field>

                <x-ui.field label="Cuenta" optional
                    hint="Número o clave con la que se le identifica en planta."
                    :error="$errors->first('account')">
                    <input wire:model="account" type="text" class="w-full" placeholder="Ej: jperez">
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
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Departamento" optional
                    hint="Filtra las áreas disponibles."
                    :error="$errors->first('department_id')">
                    <select wire:model.live="department_id" class="w-full">
                        <option value="">Seleccionar</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
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
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            @if ($area_id && $selected_role !== 'Supervisor')
                <x-ui.note tone="warn" class="mt-4">
                    El área sólo se asigna cuando el rol es <strong>Supervisor</strong>. Con el rol
                    <strong>{{ $selected_role ?: 'seleccionado' }}</strong> el usuario se creará sin área a su cargo.
                </x-ui.note>
            @endif
        </x-ui.section>

        <x-ui.section title="Contraseña" hint="Mínimo 8 caracteres. El usuario puede cambiarla después desde su perfil.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Contraseña" required :error="$errors->first('password')">
                    <input wire:model="password" type="password" class="w-full" autocomplete="new-password" required>
                </x-ui.field>

                <x-ui.field label="Confirmar contraseña" required :error="$errors->first('password_confirmation')">
                    <input wire:model="password_confirmation" type="password" class="w-full"
                        autocomplete="new-password" required>
                </x-ui.field>
            </div>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.users.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear usuario</x-ui.btn>
        </div>
    </form>
</x-ui.page>
