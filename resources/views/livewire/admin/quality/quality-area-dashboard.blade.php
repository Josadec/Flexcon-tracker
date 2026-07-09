<div class="space-y-6">
    <!-- Header -->
    <x-area-header accent="teal" title="Calidad" subtitle="Inspección y verificación de pesadas">
        <x-slot:icon>
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
            </svg>
        </x-slot:icon>
    </x-area-header>

    {{-- ===== Calidad CRIMP (viajeros) ===== --}}
    <section class="space-y-4">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Calidad CRIMP</h2>
            <span class="text-xs text-gray-400 dark:text-gray-500">Inspección y verificación a nivel viajero</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-teal-200 dark:border-teal-800 p-5 shadow-sm">
                <div class="text-xs font-medium text-teal-600 dark:text-teal-400 uppercase tracking-wide">Por inspeccionar</div>
                <div class="mt-1 text-3xl font-bold text-teal-700 dark:text-teal-300">{{ number_format($qInspeccion) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Material liberado · inspección pendiente</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-emerald-200 dark:border-emerald-800 p-5 shadow-sm">
                <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Por verificar</div>
                <div class="mt-1 text-3xl font-bold text-emerald-700 dark:text-emerald-300">{{ number_format($qVerificar) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Producción hecha · calidad pendiente</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-cyan-200 dark:border-cyan-800 p-5 shadow-sm">
                <div class="text-xs font-medium text-cyan-600 dark:text-cyan-400 uppercase tracking-wide">Pendientes</div>
                <div class="mt-1 text-3xl font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($qPendientes->count()) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Viajeros esperando Calidad</div>
            </div>
        </div>

        @include('livewire.admin.partials.crimp-pending-list', [
            'titulo' => 'Pendientes de Calidad',
            'pendientes' => $qPendientes,
            'badges' => [
                'inspect' => 'bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300',
                'verify'  => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
            ],
            'empty' => 'Sin viajeros CRIMP pendientes de Calidad. 🎉',
        ])
    </section>

    <!-- Pending Sent Lists -->
    @include('livewire.admin.sent-lists.partials.pending-lists-panel', [
        'pendingSentLists' => $pendingSentLists,
        'deptLabel'        => 'Calidad / Inspección',
        'deptColor'        => 'yellow',
    ])

    <!-- Area Progress Donuts -->
    @include('partials.area-progress-donuts', ['areaStats' => $areaStats])

    <!-- Work Orders Overview -->
    <section>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Work Orders
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total WOs</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalWOs) }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-700 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-green-200 dark:border-green-700 rounded-lg p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-green-600 dark:text-green-400 mb-1">Activas</p>
                        <p class="text-2xl font-semibold text-green-700 dark:text-green-300">{{ number_format($activeWOs) }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-700 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Cerradas</p>
                        <p class="text-2xl font-semibold text-gray-700 dark:text-gray-300">{{ number_format($closedWOs) }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Access Cards -->
    <section>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            Acceso Rápido
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Inspección Card -->
            <a href="{{ route('admin.quality.inspection') }}" wire:navigate
                class="group bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-teal-300 dark:hover:border-teal-700 hover:shadow-lg transition-all">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-xl bg-teal-50 dark:bg-teal-900/20 border-2 border-teal-200 dark:border-teal-700 flex items-center justify-center flex-shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-900/40 transition-colors">
                        <svg class="w-7 h-7 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">Inspección</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Revisar y aprobar/rechazar lotes de producción</p>
                        <div class="flex items-center gap-4 mt-4">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 border-2 border-yellow-300 dark:border-yellow-600"></span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $pendingInspection }} pendientes</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-green-300 dark:border-green-600"></span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $approvedInspection }} aprobados</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-500 border-2 border-red-300 dark:border-red-600"></span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $rejectedInspection }} rechazados</span>
                            </div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-teal-500 transition-colors flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>

            <!-- Pesadas de Calidad Card -->
            <a href="{{ route('admin.quality.weighings') }}" wire:navigate
                class="group bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-teal-300 dark:hover:border-teal-700 hover:shadow-lg transition-all">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-xl bg-teal-50 dark:bg-teal-900/20 border-2 border-teal-200 dark:border-teal-700 flex items-center justify-center flex-shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-900/40 transition-colors">
                        <svg class="w-7 h-7 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">Pesadas de Calidad</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Verificar pesadas de producción</p>
                        <div class="flex items-center gap-4 mt-4">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 border-2 border-yellow-300 dark:border-yellow-600"></span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $pendingQuality }} pendientes</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-green-300 dark:border-green-600"></span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $completedQuality }} verificados</span>
                            </div>
                            @if ($withRejected > 0)
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-red-500 border-2 border-red-300 dark:border-red-600"></span>
                                    <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ $withRejected }} con rechazos</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-teal-500 transition-colors flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Resumen General -->
    <section>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Resumen General
        </h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Lotes con Pesada</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalWithProd) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-yellow-200 dark:border-yellow-700 rounded-lg p-4 text-center">
                <div class="text-xs text-yellow-600 dark:text-yellow-400 mb-1">Pendiente Calidad</div>
                <div class="text-2xl font-semibold text-yellow-700 dark:text-yellow-300">{{ number_format($pendingQuality) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-green-200 dark:border-green-700 rounded-lg p-4 text-center">
                <div class="text-xs text-green-600 dark:text-green-400 mb-1">Verificados</div>
                <div class="text-2xl font-semibold text-green-700 dark:text-green-300">{{ number_format($completedQuality) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-red-200 dark:border-red-700 rounded-lg p-4 text-center">
                <div class="text-xs text-red-600 dark:text-red-400 mb-1">Con Rechazos</div>
                <div class="text-2xl font-semibold text-red-700 dark:text-red-300">{{ number_format($withRejected) }}</div>
            </div>
        </div>
    </section>

    <!-- Actividad Reciente -->
    <section>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Actividad Reciente de Calidad
        </h2>
        <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            @if ($recentQualityWeighings->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">WO</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Lote</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Parte</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-green-600 dark:text-green-400 uppercase">Aprobadas</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-red-600 dark:text-red-400 uppercase">Rechazadas</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Inspector</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($recentQualityWeighings as $qw)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $qw->weighed_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-blue-600 dark:text-blue-400 font-medium">{{ $qw->lot->workOrder->purchaseOrder->wo ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">{{ $qw->lot->lot_number ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $qw->lot->workOrder->purchaseOrder->part->number ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-right font-medium text-green-600 dark:text-green-400">{{ number_format($qw->good_pieces) }}</td>
                                    <td class="px-6 py-4 text-right font-medium text-red-600 dark:text-red-400">{{ number_format($qw->bad_pieces) }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $qw->weighedBy->name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('admin.quality.weighings', ['search' => $qw->lot->lot_number ?? '']) }}" wire:navigate
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-900/20 border-2 border-teal-200 dark:border-teal-800 rounded-md hover:bg-teal-100 dark:hover:bg-teal-900/40 transition-colors">
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="mt-4 text-base font-medium text-gray-900 dark:text-white">Sin actividad reciente</p>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Las pesadas de calidad aparecerán aquí cuando se registren.</p>
                </div>
            @endif
        </div>
    </section>
</div>
