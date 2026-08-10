<x-ui.page eyebrow="Administración" title="Programar tiempo extra"
    subtitle="Jornada extraordinaria: sus horas-hombre se suman a la capacidad disponible del período."
    back="{{ route('admin.over-times.index') }}" backLabel="Volver a tiempo extra">

    <form wire:submit="save" class="space-y-5">
        <x-ui.section step="1" title="Cuándo y en qué turno"
            hint="El turno es opcional: sirve para saber a qué horario pertenece la jornada extra.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-ui.field label="Nombre" required
                    hint="Con qué se identifica en el listado."
                    :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" placeholder="Ej: Sábado extra línea 1" required>
                </x-ui.field>

                <x-ui.field label="Fecha" required
                    hint="No puede ser anterior a hoy."
                    :error="$errors->first('date')">
                    <input wire:model="date" type="date" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Turno" optional :error="$errors->first('shift_id')">
                    <select wire:model="shift_id" class="w-full">
                        <option value="">Sin turno</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section step="2" title="Horario" hint="Las horas netas descuentan los minutos de descanso.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-ui.field label="Hora de inicio" required :error="$errors->first('start_time')">
                    <input wire:model.blur="start_time" type="time" class="w-full tabular-nums" required>
                </x-ui.field>

                <x-ui.field label="Hora de fin" required :error="$errors->first('end_time')">
                    <input wire:model.blur="end_time" type="time" class="w-full tabular-nums" required>
                </x-ui.field>

                <x-ui.field label="Descanso" required
                    hint="Minutos que no se cuentan como trabajo."
                    :error="$errors->first('break_minutes')">
                    <input wire:model.blur="break_minutes" type="number" min="0" step="1"
                        class="w-full text-right tabular-nums" placeholder="0" required>
                </x-ui.field>
            </div>

            <x-ui.stats cols="3" class="mt-4">
                <x-ui.stat label="Horas netas" :value="$this->net_hours" unit="h"
                    help="Duración de la jornada menos el descanso." />
                <x-ui.stat label="Empleados" :value="count($selectedEmployeeIds)" />
                <x-ui.stat label="Horas-hombre" :value="$this->total_hours" unit="h" tone="accent"
                    help="Horas netas × empleados. Es lo que se suma a la capacidad." />
            </x-ui.stats>
        </x-ui.section>

        <x-ui.section step="3" title="Empleados" tone="accent"
            hint="Debes elegir al menos uno: sin empleados no hay horas-hombre que sumar.">

            @error('selectedEmployeeIds')
                <x-ui.note tone="danger" class="mb-4">{{ $message }}</x-ui.note>
            @enderror

            @if ($selectedEmployees->isNotEmpty())
                <div class="mb-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Seleccionados ({{ $selectedEmployees->count() }})
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($selectedEmployees as $emp)
                            <span wire:key="sel-{{ $emp->id }}"
                                class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">
                                {{ $emp->full_name }}
                                <button type="button" wire:click="removeEmployee('{{ $emp->id }}')"
                                    class="text-sky-600 hover:text-red-600 dark:text-sky-400 dark:hover:text-red-400"
                                    aria-label="Quitar a {{ $emp->full_name }}">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-ui.field label="Buscar empleado">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="employeeSearch"
                        placeholder="Nombre, número o posición..." class="w-full pl-10">
                </div>
            </x-ui.field>

            <div class="mt-3 max-h-72 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700">
                @forelse ($employees as $emp)
                    <label wire:key="emp-{{ $emp->id }}"
                        class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-2.5 last:border-b-0 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-700/40">
                        <input type="checkbox" wire:model.live="selectedEmployeeIds" value="{{ (string) $emp->id }}">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $emp->full_name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                {{ $emp->employee_number ?: '—' }} · {{ $emp->position ?: 'Sin posición' }}
                            </span>
                        </span>
                    </label>
                @empty
                    <x-ui.empty icon="search"
                        :title="$employeeSearch ? 'Sin resultados para «'.$employeeSearch.'»' : 'No hay empleados activos'"
                        :hint="$employeeSearch ? 'Prueba con otro nombre, número o posición.' : 'Da de alta empleados activos para poder programarles tiempo extra.'" />
                @endforelse
            </div>
        </x-ui.section>

        <x-ui.section title="Comentarios">
            <x-ui.field label="Comentarios" optional
                hint="Motivo de la jornada extra, autorizaciones, etc."
                :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="3" class="w-full" placeholder="Opcional..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.over-times.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear tiempo extra</x-ui.btn>
        </div>
    </form>
</x-ui.page>
