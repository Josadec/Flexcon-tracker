<div class="space-y-6">
    <!-- Header -->
    <x-area-header accent="amber" title="Materiales" subtitle="Lotes, lotes de CRIMP y work orders para producción">
        <x-slot:icon>
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
            </svg>
        </x-slot:icon>
    </x-area-header>

    {{-- ===== Acciones de Materiales (CRIMP) ===== --}}
    <section class="space-y-4">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </span>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Acciones de Materiales (CRIMP)</h2>
            <span class="text-xs text-gray-400 dark:text-gray-500">Liberar material · Decisión (Paso 6) · Recibir sobrantes</span>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-blue-200 dark:border-blue-800 p-5 shadow-sm">
                <div class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wide">Liberar material</div>
                <div class="mt-1 text-3xl font-bold text-blue-700 dark:text-blue-300">{{ number_format($matLiberar) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Viajeros esperando liberación</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-amber-200 dark:border-amber-800 p-5 shadow-sm">
                <div class="text-xs font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wide">Tomar decisión</div>
                <div class="mt-1 text-3xl font-bold text-amber-700 dark:text-amber-300">{{ number_format($matDecision) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paso 6 · tras el empaque</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-orange-200 dark:border-orange-800 p-5 shadow-sm">
                <div class="text-xs font-medium text-orange-600 dark:text-orange-400 uppercase tracking-wide">Recibir sobrantes</div>
                <div class="mt-1 text-3xl font-bold text-orange-700 dark:text-orange-300">{{ number_format($matSobrantes) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paso 8 · regreso de material</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-cyan-200 dark:border-cyan-800 p-5 shadow-sm">
                <div class="text-xs font-medium text-cyan-600 dark:text-cyan-400 uppercase tracking-wide">Lotes de CRIMP</div>
                <div class="mt-1 text-3xl font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($totalCrimpLots) }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($viajerosConCrimp) }} viajeros · {{ number_format($crimpLotsQty) }} pz</div>
            </div>
        </div>

        {{-- Lista de pendientes de Materiales --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Pendientes de Materiales</h3>
                <span class="px-2 py-0.5 text-xs font-semibold bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-300 rounded-full">{{ $matPendientes->count() }}</span>
            </div>
            @if ($matPendientes->isNotEmpty())
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($matPendientes as $row)
                        @php
                            $vj = $row['lot']; $wo = $vj->workOrder;
                            $badge = match ($row['kind']) {
                                'release'  => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
                                'decision' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
                                'surplus'  => 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
                                default    => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
                            };
                        @endphp
                        <div class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-100">Viajero {{ $vj->lot_number }}</span>
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $badge }}">{{ $row['action'] }}</span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    WO {{ $wo->purchaseOrder->wo ?? $wo->wo_number }} · {{ $wo->purchaseOrder->part->number ?? '' }} · {{ $wo->purchaseOrder->part->description ?? '' }}
                                </div>
                            </div>
                            <a href="{{ route('admin.sent-lists.display.wo', $wo->id) }}" wire:navigate
                                class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-cyan-600 hover:bg-cyan-700 rounded-lg transition-colors">
                                Ir al tablero
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                    Sin acciones de Materiales CRIMP pendientes. 🎉
                </div>
            @endif
        </div>
    </section>

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

    <!-- Quick Access -->
    <section>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            Acceso Rápido
        </h2>
        <a href="{{ route('admin.materials.manage') }}" wire:navigate
            class="group bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-amber-300 dark:hover:border-amber-700 hover:shadow-lg transition-all block">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-xl bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-200 dark:border-amber-700 flex items-center justify-center flex-shrink-0 group-hover:bg-amber-100 dark:group-hover:bg-amber-900/40 transition-colors">
                    <svg class="w-7 h-7 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">Gestión de Materiales</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Work Orders activas, lotes y lotes de CRIMP — solo muestra órdenes abiertas</p>
                    <div class="flex items-center gap-4 mt-4">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-green-300 dark:border-green-600"></span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $activeWOs }} WOs activas</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 border-2 border-yellow-300 dark:border-yellow-600"></span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $pendingLots }} lotes pendientes</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 border-2 border-cyan-300 dark:border-cyan-600"></span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $totalCrimpLots }} lotes de CRIMP</span>
                        </div>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-amber-500 transition-colors flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>
    </section>

    <!-- Summary -->
    <section>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Resumen General
        </h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Lotes -->
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Lotes</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalLots) }}</div>
                <div class="flex items-center justify-center gap-2 mt-2 text-xs">
                    <span class="text-yellow-600 dark:text-yellow-400">{{ $pendingLots }} pend.</span>
                    <span class="text-blue-600 dark:text-blue-400">{{ $inProgressLots }} proc.</span>
                    <span class="text-green-600 dark:text-green-400">{{ $completedLots }} comp.</span>
                </div>
            </div>
            <!-- Lotes de CRIMP -->
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Lotes de CRIMP</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalCrimpLots) }}</div>
            </div>
            <!-- Piezas CRIMP -->
            <div class="bg-white dark:bg-gray-800 border-2 border-cyan-200 dark:border-cyan-700 rounded-lg p-4 text-center">
                <div class="text-xs text-cyan-600 dark:text-cyan-400 mb-1">Piezas en CRIMP</div>
                <div class="text-2xl font-semibold text-cyan-700 dark:text-cyan-300">{{ number_format($crimpLotsQty) }}</div>
            </div>
            <!-- Viajeros con CRIMP -->
            <div class="bg-white dark:bg-gray-800 border-2 border-sky-200 dark:border-sky-700 rounded-lg p-4 text-center">
                <div class="text-xs text-sky-600 dark:text-sky-400 mb-1">Viajeros con CRIMP</div>
                <div class="text-2xl font-semibold text-sky-700 dark:text-sky-300">{{ number_format($viajerosConCrimp) }}</div>
            </div>
        </div>
    </section>

    <!-- Recent Sent Lists -->
    <section>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Listas de Envío Recientes
        </h2>
        <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            @if ($recentSentLists->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Work Orders</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($recentSentLists as $sl)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4 text-blue-600 dark:text-blue-400 font-medium">#{{ $sl->id }}</td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $sl->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">{{ $sl->workOrders->count() }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('admin.sent-lists.show', $sl->id) }}" wire:navigate
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-200 dark:border-amber-800 rounded-md hover:bg-amber-100 dark:hover:bg-amber-900/40 transition-colors">
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
                    <p class="mt-4 text-base font-medium text-gray-900 dark:text-white">Sin listas de envío</p>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Las listas aparecerán aquí cuando se creen.</p>
                </div>
            @endif
        </div>
    </section>
</div>
