<x-ui.page eyebrow="Administración" title="Empleados"
    subtitle="Personal de planta: número, área de trabajo, turno y estado. Las cuentas de acceso al sistema se administran en Usuarios.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" wire:click="downloadTemplate" title="Descargar una plantilla CSV de ejemplo">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Plantilla
        </x-ui.btn>
        <x-ui.btn variant="secondary" wire:click="openImportModal">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M16 8l-4-4m0 0L8 8m4-4v12"/></svg>
            Importar CSV
        </x-ui.btn>
        <x-ui.btn variant="secondary" wire:click="exportCsv">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M8 12l4 4m0 0l4-4m-4 4V4"/></svg>
            Exportar CSV
        </x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.employees.create') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo empleado
        </x-ui.btn>
    </x-slot:actions>

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Total de empleados" :value="$totalEmployees" />
        <x-ui.stat label="Activos" :value="$activeEmployees" tone="good"
            help="Empleados dados de alta y trabajando." />
        <x-ui.stat label="Inactivos" :value="$inactiveEmployees" :tone="$inactiveEmployees > 0 ? 'warn' : 'neutral'"
            help="Siguen en el sistema y conservan su historial, pero ya no están en operación." />
        <x-ui.stat label="Sin número" :value="$withoutNumber" :tone="$withoutNumber > 0 ? 'info' : 'neutral'"
            help="El número de empleado es opcional al dar de alta y no se puede cambiar después." />
    </x-ui.stats>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por nombre, correo o número, y acota por área, turno o estado.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <x-ui.field label="Texto a buscar" class="lg:col-span-2">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Nombre, correo o número..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Área">
                <select wire:model.live="filterArea" class="w-full">
                    <option value="">Todas</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Turno">
                <select wire:model.live="filterShift" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Estado">
                <select wire:model.live="filterStatus" class="w-full">
                    <option value="">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
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

        @if ($search || $filterArea || $filterShift || $filterStatus !== '')
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    {{-- Listado --}}
    <x-ui.table>
        <x-slot:head>
            <tr>
                <x-ui.th sort="employee_number" :field="$sortField" :direction="$sortDirection">Número</x-ui.th>
                <x-ui.th sort="name" :field="$sortField" :direction="$sortDirection">Empleado</x-ui.th>
                <x-ui.th sort="email" :field="$sortField" :direction="$sortDirection">Correo</x-ui.th>
                <x-ui.th>Área</x-ui.th>
                <x-ui.th>Turno</x-ui.th>
                <x-ui.th sort="active" :field="$sortField" :direction="$sortDirection">Estado</x-ui.th>
                <x-ui.th align="right">Acciones</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($employees as $employee)
            <tr wire:key="employee-{{ $employee->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-mono font-semibold text-slate-900 dark:text-white">
                    {{ $employee->employee_number ?: '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
                            aria-hidden="true">{{ $employee->initials }}</span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-900 dark:text-white">{{ $employee->full_name }}</span>
                            @if ($employee->position)
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $employee->position }}</span>
                            @endif
                        </span>
                    </div>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $employee->email }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $employee->area?->name ?: '—' }}</td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $employee->shift?->name ?: '—' }}</td>
                <td class="px-4 py-3">
                    @if ($employee->active)
                        <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions label="a {{ $employee->full_name }}"
                        :show="route('admin.employees.show', $employee)"
                        :edit="route('admin.employees.edit', $employee)"
                        :delete="$employee->id === auth()->id() ? null : 'deleteEmployee('.$employee->id.')'"
                        deleteConfirm="¿Eliminar a «{{ $employee->full_name }}»? Deja de aparecer en el sistema, pero conserva su historial." />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty icon="search" title="No se encontraron empleados"
                        hint="Ajusta la búsqueda o los filtros de área, turno y estado, o da de alta un empleado nuevo.">
                        <x-slot:action>
                            <x-ui.btn variant="primary" href="{{ route('admin.employees.create') }}">Nuevo empleado</x-ui.btn>
                        </x-slot:action>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse

        @if ($employees->hasPages())
            <x-slot:foot>{{ $employees->links() }}</x-slot:foot>
        @endif
    </x-ui.table>

    {{-- Importación por CSV --}}
    @if ($showImportModal)
        <x-ui-modal wire:key="modal-import-employees" title="Importar empleados desde CSV"
            subtitle="Da de alta o actualiza muchos empleados de una sola vez."
            close="closeImportModal" maxWidth="3xl">

            <x-ui.section title="1. Prepara el archivo" hint="Si no estás seguro del formato, descarga la plantilla desde el listado.">
                <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    <x-ui.kv label="Columnas obligatorias" value="name, last_name, email, password, area_name, shift_name" />
                    <x-ui.kv label="Columnas opcionales" value="employee_number, position, birth_date, entry_date, active, comments" />
                    <x-ui.kv label="Área y turno" value="Se referencian por su nombre exacto" />
                    <x-ui.kv label="Fechas y sí/no" value="Fechas AAAA-MM-DD; active usa 1 o 0" />
                </dl>
                <x-ui.note tone="info" class="mt-4">
                    El <strong>correo</strong> identifica al empleado: si ya existe, se <strong>actualiza</strong> en
                    lugar de duplicarse. La contraseña sólo es obligatoria al crear, y a todo empleado nuevo se le
                    asigna el rol <strong>employee</strong>.
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
                Los empleados que ya existan se actualizan; ninguno se elimina. Revisa el resultado antes de cerrar.
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
