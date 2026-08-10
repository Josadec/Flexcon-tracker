<x-ui.page eyebrow="Administración" :title="$employee->full_name"
    :subtitle="collect([$employee->position, $employee->employee_number])->filter()->implode(' · ') ?: 'Empleado de planta.'"
    back="{{ route('admin.employees.index') }}" backLabel="Volver a empleados">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.employees.edit', $employee) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar empleado
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Ficha --}}
    <x-ui.section title="Información del empleado">
        <x-slot:aside>
            @if ($employee->active)
                <x-ui.badge tone="good" dot>Activo</x-ui.badge>
            @else
                <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
            @endif
        </x-slot:aside>

        <div class="mb-4 flex items-center gap-4">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-slate-100 text-lg font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
                aria-hidden="true">{{ $employee->initials }}</span>
            <div class="min-w-0">
                <p class="truncate text-base font-bold text-slate-900 dark:text-white">{{ $employee->full_name }}</p>
                <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $employee->email }}</p>
            </div>
        </div>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Nombre completo" :value="$employee->full_name" />
            <x-ui.kv label="Correo electrónico" :value="$employee->email" />
            <x-ui.kv label="Número de empleado" :value="$employee->employee_number ?: 'No asignado'"
                help="Se captura al dar de alta y no se puede modificar." />
            <x-ui.kv label="Fecha de nacimiento" :value="$employee->birth_date?->format('d/m/Y') ?? 'No registrada'" />
        </dl>
    </x-ui.section>

    {{-- Puesto --}}
    <x-ui.section title="Puesto" hint="Dónde y en qué horario trabaja.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Área">
                @if ($employee->area)
                    <a href="{{ route('admin.areas.show', $employee->area) }}" wire:navigate
                        class="font-bold text-sky-700 hover:underline dark:text-sky-300">{{ $employee->area->name }}</a>
                @else
                    <span class="font-normal text-slate-400 dark:text-slate-500">Sin área asignada</span>
                @endif
            </x-ui.kv>

            <x-ui.kv label="Turno">
                @if ($employee->shift)
                    <a href="{{ route('admin.shifts.show', $employee->shift) }}" wire:navigate
                        class="font-bold text-sky-700 hover:underline dark:text-sky-300">{{ $employee->shift->name }}</a>
                @else
                    <span class="font-normal text-slate-400 dark:text-slate-500">Sin turno asignado</span>
                @endif
            </x-ui.kv>

            <x-ui.kv label="Posición o cargo" :value="$employee->position ?: 'No especificada'" />
            <x-ui.kv label="Fecha de ingreso" :value="$employee->entry_date?->format('d/m/Y') ?? 'No registrada'" />
        </dl>
    </x-ui.section>

    {{-- Comentarios --}}
    @if ($employee->comments)
        <x-ui.section title="Comentarios" hint="Notas internas capturadas en su ficha.">
            <p class="whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $employee->comments }}</p>
        </x-ui.section>
    @endif

    {{-- Registro --}}
    <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Alta" :value="$employee->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$employee->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>
</x-ui.page>
