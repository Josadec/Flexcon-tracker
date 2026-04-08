<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Estados de Produccion</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Gestiona estados operativos para mesas, semi-automaticos y maquinas.
            </p>
        </div>

        <a
            href="{{ route('admin.production-statuses.create') }}"
            wire:navigate
            class="inline-flex items-center gap-2 self-start rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nuevo estado
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Total</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['total'] }}</div>
        </div>
        <div class="rounded-lg border-2 border-green-200 bg-white p-4 dark:border-green-800 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Activos</div>
            <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $stats['active'] }}</div>
        </div>
        <div class="rounded-lg border-2 border-red-200 bg-white p-4 dark:border-red-800 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Inactivos</div>
            <div class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ $stats['inactive'] }}</div>
        </div>
    </div>

    <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Buscar</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Buscar por nombre o descripcion..."
                        class="block w-full rounded-md border-2 border-gray-200 bg-white py-2 pr-4 pl-10 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                <select
                    wire:model.live="filterStatus"
                    class="block w-full rounded-md border-2 border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    <option value="">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            <button wire:click="sortBy('order')" class="flex items-center gap-2 transition-colors hover:text-gray-900 dark:hover:text-white">
                                Orden
                                @if ($sortBy === 'order')
                                    <svg class="h-4 w-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            <button wire:click="sortBy('name')" class="flex items-center gap-2 transition-colors hover:text-gray-900 dark:hover:text-white">
                                Estado
                                @if ($sortBy === 'name')
                                    <svg class="h-4 w-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Color</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Descripcion</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                    @forelse ($productionStatuses as $status)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $status->order }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <span class="h-3.5 w-3.5 rounded-full border border-black/10 dark:border-white/10" style="background-color: {{ $status->color }}"></span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $status->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                                    <span class="h-3 w-3 rounded-full border border-black/10 dark:border-white/10" style="background-color: {{ $status->color }}"></span>
                                    {{ $status->color }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($status->active)
                                    <span class="inline-flex rounded-full border-2 border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        Activo
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full border-2 border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
                                        Inactivo
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                <div class="max-w-md truncate">
                                    {{ $status->description ?: 'Sin descripcion' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        href="{{ route('admin.production-statuses.show', $status) }}"
                                        wire:navigate
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-blue-600 transition-colors hover:border-blue-300 hover:bg-blue-50 dark:text-blue-400 dark:hover:border-blue-700 dark:hover:bg-blue-900/20"
                                        title="Ver estado"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a
                                        href="{{ route('admin.production-statuses.edit', $status) }}"
                                        wire:navigate
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-indigo-600 transition-colors hover:border-indigo-300 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:border-indigo-700 dark:hover:bg-indigo-900/20"
                                        title="Editar estado"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <button
                                        wire:click="confirmDelete({{ $status->id }})"
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 border-transparent text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 dark:text-red-400 dark:hover:border-red-700 dark:hover:bg-red-900/20"
                                        title="Eliminar estado"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                                        <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-medium text-gray-900 dark:text-white">No se encontraron estados</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajusta los filtros o crea un nuevo estado de produccion.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($productionStatuses->hasPages())
            <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/50">
                {{ $productionStatuses->links() }}
            </div>
        @endif
    </div>

    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 p-4">
            <div class="w-full max-w-lg rounded-lg border-2 border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Eliminar estado</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Esta accion no se puede deshacer. Si el estado esta en uso, el sistema bloqueara la eliminacion.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                    <button
                        wire:click="cancelDelete"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border-2 border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-700/60"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="deleteStatus"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
