@php
    $usageTotal = $productionStatus->tables->count() + $productionStatus->semiAutomatics->count() + $productionStatus->machines->count();
@endphp

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

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <span class="h-4 w-4 rounded-full border border-black/10 dark:border-white/10" style="background-color: {{ $productionStatus->color }}"></span>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $productionStatus->name }}</h1>
                @if ($productionStatus->active)
                    <span class="inline-flex rounded-full border-2 border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300">
                        Activo
                    </span>
                @else
                    <span class="inline-flex rounded-full border-2 border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
                        Inactivo
                    </span>
                @endif
            </div>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Detalle operativo del estado de produccion y su nivel de uso actual.
            </p>
        </div>

        <a
            href="{{ route('admin.production-statuses.edit', $productionStatus) }}"
            wire:navigate
            class="inline-flex items-center gap-2 self-start rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Editar estado
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Orden</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $productionStatus->order }}</div>
        </div>
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Color</div>
            <div class="mt-3 flex items-center gap-3">
                <span class="h-5 w-5 rounded-full border border-black/10 dark:border-white/10" style="background-color: {{ $productionStatus->color }}"></span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $productionStatus->color }}</span>
            </div>
        </div>
        <div class="rounded-lg border-2 border-blue-200 bg-white p-4 dark:border-blue-800 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Uso total</div>
            <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $usageTotal }}</div>
        </div>
        <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Puede eliminarse</div>
            <div class="mt-3">
                @if ($productionStatus->canBeDeleted())
                    <span class="inline-flex rounded-full border-2 border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300">
                        Si
                    </span>
                @else
                    <span class="inline-flex rounded-full border-2 border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                        En uso
                    </span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Informacion general</h2>
                </div>
                <dl class="grid grid-cols-1 gap-6 p-4 md:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nombre</dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $productionStatus->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">{{ $productionStatus->active ? 'Activo' : 'Inactivo' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Descripcion</dt>
                        <dd class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $productionStatus->description ?: 'Sin descripcion registrada.' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Creado</dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">{{ $productionStatus->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actualizado</dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">{{ $productionStatus->updated_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Uso del estado</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Referencias actuales dentro del sistema.
                    </p>
                </div>

                <div class="overflow-hidden">
                    <div class="grid grid-cols-1 divide-y divide-gray-200 dark:divide-gray-700">
                        <div class="flex items-center justify-between px-4 py-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Mesas</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Registros table vinculados</div>
                            </div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $productionStatus->tables->count() }}</div>
                        </div>
                        <div class="flex items-center justify-between px-4 py-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Semi-automaticos</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Registros semi automatic vinculados</div>
                            </div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $productionStatus->semiAutomatics->count() }}</div>
                        </div>
                        <div class="flex items-center justify-between px-4 py-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Maquinas</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Registros machine vinculados</div>
                            </div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $productionStatus->machines->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg border-2 border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Resumen</h2>
                <div class="mt-3 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <p>El orden actual es <span class="font-semibold text-gray-900 dark:text-white">{{ $productionStatus->order }}</span>.</p>
                    <p>El color definido es <span class="font-mono text-gray-900 dark:text-white">{{ $productionStatus->color }}</span>.</p>
                    <p>Este estado {{ $productionStatus->active ? 'esta disponible para seleccionarse en nuevos registros.' : 'esta conservado solo para historial si ya no debe usarse.' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
