<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.over-times.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Crear tiempo extra</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Registra un nuevo tiempo extra en el sistema</p>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <form wire:submit="save" class="divide-y divide-gray-200 dark:divide-gray-700">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Información básica</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nombre <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" placeholder="Nombre del tiempo extra" class="w-full px-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                        @error('name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fecha <span class="text-red-500">*</span></label>
                        <input type="date" wire:model="date" min="{{ now()->toDateString() }}" class="w-full px-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                        @error('date') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Turno</label>
                        <select wire:model="shift_id" class="w-full px-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">Selecciona un turno (opcional)</option>
                            @foreach($shifts as $shift)
                                <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                            @endforeach
                        </select>
                        @error('shift_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Horario</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hora inicio <span class="text-red-500">*</span></label>
                        <input type="time" wire:model.blur="start_time" class="w-full px-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                        @error('start_time') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hora fin <span class="text-red-500">*</span></label>
                        <input type="time" wire:model.blur="end_time" class="w-full px-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                        @error('end_time') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Descanso (min) <span class="text-red-500">*</span></label>
                        <input type="number" wire:model.blur="break_minutes" min="0" placeholder="0" class="w-full px-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                        @error('break_minutes') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4 p-3 rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <p class="text-xs text-blue-800 dark:text-blue-200">Horas netas: <strong>{{ $this->net_hours }} hrs</strong> · Horas totales: <strong>{{ $this->total_hours }} hrs</strong> ({{ count($selectedEmployeeIds) }} empleado{{ count($selectedEmployeeIds) !== 1 ? 's' : '' }})</p>
                </div>
            </div>

            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Empleados <span class="text-red-500">*</span></h3>

                @error('selectedEmployeeIds')
                    <p class="mb-3 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <!-- Empleados seleccionados -->
                @if($selectedEmployees->isNotEmpty())
                    <div class="mb-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Seleccionados ({{ $selectedEmployees->count() }}):</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedEmployees as $emp)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-700">
                                    {{ $emp->full_name }}
                                    <button type="button" wire:click="$set('selectedEmployeeIds', array_values(array_filter($selectedEmployeeIds, fn($id) => $id !== '{{ (string) $emp->id }}')))" class="ml-1 text-blue-600 dark:text-blue-400 hover:text-red-600 dark:hover:text-red-400">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Buscador de empleados -->
                <div class="mb-3">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="employeeSearch"
                            placeholder="Buscar empleado por nombre, numero o posicion..."
                            class="w-full pl-9 pr-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <!-- Lista de empleados con checkboxes -->
                <div class="border-2 border-gray-200 dark:border-gray-600 rounded-md overflow-hidden max-h-64 overflow-y-auto">
                    @forelse($employees as $emp)
                        <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                            <input
                                type="checkbox"
                                wire:model="selectedEmployeeIds"
                                value="{{ (string) $emp->id }}"
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $emp->full_name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $emp->employee_number ?? '—' }} · {{ $emp->position ?? 'Sin posición' }}</p>
                            </div>
                        </label>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            @if($employeeSearch)
                                No se encontraron empleados con "{{ $employeeSearch }}"
                            @else
                                No hay empleados activos disponibles
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Comentarios</h3>
                <textarea wire:model="comments" rows="3" placeholder="Opcional" class="w-full px-4 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
                @error('comments') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="p-6 bg-gray-50 dark:bg-gray-900/50">
                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.over-times.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">Crear tiempo extra</button>
                </div>
            </div>
        </form>
    </div>
</div>
