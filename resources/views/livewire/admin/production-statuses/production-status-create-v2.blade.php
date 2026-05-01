<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a
                href="{{ route('admin.production-statuses.index') }}"
                wire:navigate
                class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver a estados
            </a>
            <h1 class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">Nuevo estado de produccion</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Registra un estado operativo nuevo para el flujo de produccion.
            </p>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Datos del estado</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Define nombre, orden, color y visibilidad.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 p-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre</label>
                    <input
                        wire:model="name"
                        type="text"
                        placeholder="Ej: En produccion"
                        class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                    @error('name')
                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Orden</label>
                    <input
                        wire:model="order"
                        type="number"
                        min="0"
                        placeholder="1"
                        class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                    @error('order')
                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <input
                            wire:model.live="color"
                            type="color"
                            class="h-11 w-20 rounded-md border-2 border-gray-200 bg-white p-1 dark:border-gray-600 dark:bg-gray-700"
                        >
                        <input
                            wire:model="color"
                            type="text"
                            placeholder="#10b981"
                            class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm font-mono text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                    </div>
                    @error('color')
                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Descripcion</label>
                    <textarea
                        wire:model="description"
                        rows="4"
                        placeholder="Describe cuando debe usarse este estado..."
                        class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    ></textarea>
                    @error('description')
                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-3 rounded-lg border-2 border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50">
                        <input
                            wire:model="active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                        >
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Estado activo</span>
                    </label>
                    @error('active')
                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-4 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                <a
                    href="{{ route('admin.production-statuses.index') }}"
                    wire:navigate
                    class="inline-flex items-center justify-center rounded-md border-2 border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                >
                    Cancelar
                </a>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                >
                    Crear estado
                </button>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="text-xs text-gray-500 dark:text-gray-400">Vista previa</div>
                <div class="mt-4 flex items-center gap-3">
                    <span class="h-4 w-4 rounded-full border border-black/10 dark:border-white/10" style="background-color: {{ $color }}"></span>
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $name ?: 'Nuevo estado' }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Orden {{ $order !== '' ? $order : '-' }}</div>
                    </div>
                </div>
                <div class="mt-4">
                    @if ($active)
                        <span class="inline-flex rounded-full border-2 border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300">
                            Activo
                        </span>
                    @else
                        <span class="inline-flex rounded-full border-2 border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
                            Inactivo
                        </span>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recomendaciones</h2>
                <div class="mt-3 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <p>Usa nombres cortos y claros para que se lean bien en tablas y badges.</p>
                    <p>El orden define la posicion natural del estado dentro de listados y selects.</p>
                    <p>Desactiva un estado si quieres conservar historial sin seguir ofreciendolo operativamente.</p>
                </div>
            </div>
        </div>
    </form>
</div>
