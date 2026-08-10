<x-ui.page eyebrow="Administración" title="Usuarios"
    subtitle="Cuentas de acceso al sistema: alta, rol y área a su cargo. Los empleados de planta se administran en Empleados.">

    {{-- Sólo el alta es acción principal; importar, exportar y la plantilla son
         tareas ocasionales y competían con ella al mismo peso visual. --}}
    <x-slot:actions>
        <x-ui.menu label="Más acciones de usuarios">
            <x-ui.menu.item wire:click="openImportModal">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M16 8l-4-4m0 0L8 8m4-4v12"/></svg>
                Importar CSV
            </x-ui.menu.item>
            <x-ui.menu.item wire:click="exportCsv">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M8 12l4 4m0 0l4-4m-4 4V4"/></svg>
                Exportar CSV
            </x-ui.menu.item>
            <x-ui.menu.item wire:click="downloadTemplate">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Descargar plantilla CSV
            </x-ui.menu.item>
        </x-ui.menu>

        <x-ui.btn variant="primary" href="{{ route('admin.users.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo usuario
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen. Las tres primeras describen esta pantalla (sin empleados de
         planta); la última existe para dejar claro dónde vive el resto. --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Usuarios del sistema" :value="$totalUsers"
            help="No incluye a los empleados de planta, que se administran en el módulo Empleados." />
        <x-ui.stat label="Con rol" :value="$usersWithRole" tone="good"
            help="Usuarios que ya tienen un rol asignado y por lo tanto pueden entrar a su módulo." />
        <x-ui.stat label="Sin rol" :value="$usersWithoutRole" :tone="$usersWithoutRole > 0 ? 'warn' : 'neutral'"
            help="Sin rol, el usuario entra al sistema pero no ve ningún módulo." />
        <x-ui.stat label="Empleados de planta" :value="$employeeCount" tone="info"
            help="Se dan de alta y se editan en el módulo Empleados. Aquí sólo aparecen si cambias el ámbito." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre, correo o cuenta, y acota por ámbito, rol o departamento.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <x-ui.field label="Texto a buscar" class="lg:col-span-2">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Nombre, correo o cuenta..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Ámbito" hint="Los empleados viven en su módulo.">
                <select wire:model.live="typeFilter" class="w-full">
                    <option value="staff">Del sistema</option>
                    <option value="employee">Empleados de planta</option>
                    <option value="all">Todos</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Rol">
                <select wire:model.live="roleFilter" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }} ({{ $role->users_count }})</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Departamento" hint="Por el área que supervisa.">
                <select wire:model.live="departmentFilter" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([5, 10, 25, 50] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        @if ($search || $roleFilter || $departmentFilter || $typeFilter !== 'staff')
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    @if ($typeFilter !== 'staff')
        <x-ui.note tone="info">
            Estás viendo empleados de planta. Su número de empleado, turno, área de trabajo y estado se editan en
            <a href="{{ route('admin.employees.index') }}" wire:navigate class="font-bold underline">Empleados</a>;
            aquí sólo puedes cambiar sus datos de acceso y su rol.
        </x-ui.note>
    @endif

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Usuario</x-ui.th>
                <x-ui.th sort="email" :field="$sortField" :direction="$sortDirection">Correo</x-ui.th>
                <x-ui.th>Rol</x-ui.th>
                <x-ui.th>Área</x-ui.th>
                <x-ui.th sort="created_at" :field="$sortField" :direction="$sortDirection">Alta</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($users as $user)
            <tr wire:key="user-{{ $user->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
                            aria-hidden="true">{{ $user->initials }}</span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-900 dark:text-white">
                                {{ $user->full_name }}
                                @if ($user->id === auth()->id())
                                    <x-ui.badge tone="info" class="ml-1.5">Tú</x-ui.badge>
                                @endif
                            </span>
                            @if ($user->account)
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $user->account }}</span>
                            @endif
                        </span>
                    </div>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $user->email }}</td>
                <td class="px-4 py-3">
                    @if ($user->roles->isNotEmpty())
                        @php
                            $roleName = $user->roles->first()->name;
                            $roleTone = match ($roleName) {
                                'admin' => 'accent',
                                'employee' => 'neutral',
                                default => 'info',
                            };
                        @endphp
                        <x-ui.badge :tone="$roleTone">{{ $roleName }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="warn" dot>Sin rol</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if ($user->areas->isNotEmpty())
                        @foreach ($user->areas as $area)
                            <div class="font-medium text-slate-900 dark:text-white">{{ $area->name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $area->department?->name ?? '—' }}</div>
                        @endforeach
                    @else
                        <span class="text-slate-400 dark:text-slate-500">—</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $user->created_at?->format('d/m/Y') ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="a {{ $user->full_name }}"
                        :show="route('admin.users.show', $user)"
                        :edit="route('admin.users.edit', $user)"
                        :delete="$user->id === auth()->id() ? null : 'deleteUser('.$user->id.')'"
                        deleteConfirm="¿Eliminar a «{{ $user->full_name }}»? Se liberan sus áreas y se borra su acceso. Esta acción no se puede deshacer." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty icon="search" title="No se encontraron usuarios"
                        hint="Ajusta la búsqueda o los filtros de rol y departamento, o da de alta un usuario nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.users.create') }}">Nuevo usuario</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($users->hasPages())
            <x-slot:foot>{{ $users->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Importación por CSV --}}
    @if ($showImportModal)
        <x-ui-modal wire:key="modal-import-users" title="Importar usuarios desde CSV"
            subtitle="Da de alta o actualiza muchos usuarios de una sola vez."
            close="closeImportModal" maxWidth="3xl">

            <x-ui.section title="1. Prepara el archivo" hint="Si no estás seguro del formato, descarga la plantilla desde el listado.">
                <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    <x-ui.kv label="Columnas obligatorias" value="name, email, password, role_name" />
                    <x-ui.kv label="Columnas opcionales" value="last_name, account, area_name" />
                    <x-ui.kv label="Rol y área" value="Se referencian por su nombre exacto" />
                </dl>
                <x-ui.note tone="info" class="mt-4">
                    El <strong>correo</strong> identifica al usuario: si ya existe, se <strong>actualiza</strong> en
                    lugar de duplicarse. La contraseña sólo es obligatoria al crear. El <strong>área</strong> sólo se
                    aplica cuando el rol es <strong>Supervisor</strong>.
                </x-ui.note>
            </x-ui.section>

            <x-ui.section title="2. Sube el archivo">
                <x-ui.field label="Archivo CSV" required :error="$errors->first('importFile')">
                    <input type="file" wire:model="importFile" accept=".csv,text/csv"
                        class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100 dark:text-slate-300 dark:file:bg-sky-900/40 dark:file:text-sky-300">
                </x-ui.field>
                <p wire:loading wire:target="importFile" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Subiendo archivo...</p>
            </x-ui.section>

            @if (!empty($importResults))
                <x-ui.section title="3. Resultado de la importación">
                    <x-ui.stats cols="4">
                        <x-ui.stat label="Creados" :value="$importResults['created'] ?? 0" tone="good" />
                        <x-ui.stat label="Actualizados" :value="$importResults['updated'] ?? 0" tone="info" />
                        <x-ui.stat label="Sin cambios" :value="$importResults['skipped'] ?? 0" />
                        <x-ui.stat label="Fallaron" :value="$importResults['failed'] ?? 0"
                            :tone="($importResults['failed'] ?? 0) > 0 ? 'bad' : 'neutral'" />
                    </x-ui.stats>

                    @if (!empty($importResults['errors']))
                        <details class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                            <summary class="cursor-pointer text-sm font-semibold text-amber-900 dark:text-amber-200">
                                Ver los {{ count($importResults['errors']) }} renglones que fallaron
                            </summary>
                            <ul class="mt-3 max-h-48 list-inside list-disc space-y-1 overflow-y-auto text-xs text-amber-800 dark:text-amber-200">
                                @foreach ($importResults['errors'] as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </x-ui.section>
            @endif

            <x-slot:note>
                Los usuarios que ya existan se actualizan; ninguno se elimina. Revisa el resultado antes de cerrar.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeImportModal">Cerrar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="importCsv"
                    wire:loading.attr="disabled" wire:target="importCsv,importFile">
                    <span wire:loading.remove wire:target="importCsv">Procesar archivo</span>
                    <span wire:loading wire:target="importCsv">Procesando...</span>
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
