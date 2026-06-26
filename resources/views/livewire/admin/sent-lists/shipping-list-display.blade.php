<div class="min-h-screen bg-gray-50 dark:bg-gray-900" wire:poll.30s="refreshDisplay">
    {{-- Mensajes Flash --}}
    @if (session()->has('message'))
        <div
            class="fixed top-4 right-4 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white px-4 py-3 rounded shadow-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm font-medium">{{ session('message') }}</span>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div
            class="fixed top-4 right-4 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white px-4 py-3 rounded shadow-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-20">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-white">LISTA DE ENVÍO</h1>
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <svg wire:loading class="w-4 h-4 animate-spin" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                        <span class="hidden sm:inline">Actualización cada {{ $refreshInterval }}s</span>
                        <span class="sm:hidden">{{ $refreshInterval }}s</span>
                    </div>
                </div>

                {{-- Filtros --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    {{-- Buscador --}}
                    <div class="relative w-full sm:w-80">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>
                        <input type="text" wire:model.live.debounce.300ms="searchTerm"
                            placeholder="Buscar por WO #, # parte o descripción..."
                            class="w-full h-10 pl-9 pr-9 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                        @if ($searchTerm)
                            <button wire:click="$set('searchTerm', '')"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                title="Limpiar búsqueda">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>

                    {{-- Filtro por tipo de estación --}}
                    <select wire:model.live="filterWorkstation" data-no-ts
                        class="w-full sm:w-52 h-10 px-3 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer">
                        <option value="">Todos los tipos</option>
                        <option value="Mesa">Mesa</option>
                        <option value="Máquina">Máquina</option>
                        <option value="Semi-Automática">Semi-Automática</option>
                        <option value="Sin Clasificar">Sin Clasificar</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Banner de vista enfocada (WO o SentList) --}}
    @if ($focusedWorkOrderId || $focusedSentListId)
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-lg px-4 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span class="text-sm text-indigo-800 dark:text-indigo-200">
                        @if ($focusedWorkOrderId)
                            Vista enfocada — mostrando sólo el WO <strong class="font-semibold">{{ $focusedWorkOrderLabel }}</strong>
                        @else
                            Vista enfocada — mostrando sólo la Lista de envío <strong class="font-semibold">{{ $focusedSentListLabel }}</strong>
                        @endif
                    </span>
                </div>
                <a href="{{ route('admin.sent-lists.display') }}" wire:navigate
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white dark:bg-gray-800 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-600 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/40 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Ver todo
                </a>
            </div>
        </div>
    @endif

    {{-- Panel Resumen del Ciclo Completo --}}
    @php
        $summaryTotal = array_sum($lifecycleSummary ?? []);
    @endphp
    @if ($summaryTotal > 0)
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Acciones pendientes:</span>

                    @if (($lifecycleSummary['material_release_pending'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-700">
                            <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['material_release_pending'] }}</strong> {{ Str::plural('lote', $lifecycleSummary['material_release_pending']) }} esperando liberación de material — <span class="font-semibold">Materiales</span>
                        </span>
                    @endif

                    @if (($lifecycleSummary['crimp_kit_pending'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-700">
                            <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['crimp_kit_pending'] }}</strong> CRIMP {{ Str::plural('viajero', $lifecycleSummary['crimp_kit_pending']) }} esperando liberación de material — <span class="font-semibold">Materiales</span>
                        </span>
                    @endif

                    @if (($lifecycleSummary['inspection_pending'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 border border-yellow-300 dark:border-yellow-700">
                            <span class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['inspection_pending'] }}</strong> {{ Str::plural('lote', $lifecycleSummary['inspection_pending']) }} esperando inspección — <span class="font-semibold">Calidad</span>
                        </span>
                    @endif

                    @if (($lifecycleSummary['production_pending'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700">
                            <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['production_pending'] }}</strong> {{ Str::plural('lote', $lifecycleSummary['production_pending']) }} esperando pesada de producción — <span class="font-semibold">Producción</span>
                        </span>
                    @endif

                    @if (($lifecycleSummary['quality_pending'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 border border-teal-300 dark:border-teal-700">
                            <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['quality_pending'] }}</strong> {{ Str::plural('lote', $lifecycleSummary['quality_pending']) }} esperando pesada de calidad — <span class="font-semibold">Calidad</span>
                        </span>
                    @endif

                    @if (($lifecycleSummary['viajero_pending'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 border border-orange-300 dark:border-orange-700">
                            <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['viajero_pending'] }}</strong> {{ Str::plural('lote', $lifecycleSummary['viajero_pending']) }} esperando entrega de viajero — <span class="font-semibold">Empaque</span>
                        </span>
                    @endif

                    @if (($lifecycleSummary['decision_pending'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-700">
                            <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['decision_pending'] }}</strong> {{ Str::plural('lote', $lifecycleSummary['decision_pending']) }} esperando decisión — <span class="font-semibold">Materiales</span>
                        </span>
                    @endif

                    @if (($lifecycleSummary['material_pending'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['material_pending'] }}</strong> {{ Str::plural('lote', $lifecycleSummary['material_pending']) }} esperando entrega de sobrantes — <span class="font-semibold">Empaque</span>
                        </span>
                    @endif

                    @if (($lifecycleSummary['material_inflight'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-700">
                            <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                            <strong>{{ $lifecycleSummary['material_inflight'] }}</strong> {{ Str::plural('lote', $lifecycleSummary['material_inflight']) }} esperando recepción de material — <span class="font-semibold">Materiales</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Contenido Principal --}}
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6 pb-20">
        @foreach ($workOrdersGrouped as $workstationType => $workOrders)
            {{-- Sección por Tipo de Estación --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                {{-- Header de Sección --}}
                <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 bg-indigo-800 dark:bg-gray-900/50">
                    <h2 class="text-white font-semibold text-gray-900 ">{{ $workstationType }}</h2>
                </div>

                {{-- Vista Desktop: Tabla --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    DOC</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    WO #</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Item #</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    # Parte</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Descripción</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Material</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Insp.</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Prod.</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Cal.</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Emp.</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider"
                                    title="Post-empaque: Viajero · Decisión · Sobrantes">
                                    Seguimiento</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Cant. WO</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Pz Enviadas</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Cant. Pendiente</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold text-orange-600 dark:text-orange-400 uppercase tracking-wider">
                                    Pz Sobrantes</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
                                    Pz Completadas</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Fecha Prog. A</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Fecha de Envío</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Fecha de Apertura</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    EG</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($workOrders as $wo)
                                @php
                                    $po = $wo->purchaseOrder;
                                    $part = $po->part;
                                    $allLots = $wo->lots;
                                    $completedLots = $wo->lots->where('status', \App\Models\Lot::STATUS_COMPLETED);
                                    $totalSent = $completedLots->sum('quantity');
                                    $cantWO = $wo->original_quantity; // Cantidad total del WO
                                    $pzEnviadas = $wo->sent_pieces; // Piezas enviadas
                                    $cantAEnviar = $cantWO - $pzEnviadas; // Cant. Pendiente = Cant. WO - Pz Enviadas

                                    // Piezas sobrantes: solo surplus real de empaque (rechazadas de calidad = descarte, no sobrantes)
                                    $woSobrantes = $allLots->sum(function ($l) {
                                        if ($l->isSurplusReceived()) return 0;
                                        if ($l->hasPackagingRecords()) return $l->getPackagingTotalSurplus();
                                        return 0;
                                    });

                                    // Piezas cerradas en cada ciclo de completado/cierre, acumuladas por WO
                                    $woCompletedPieces = $allLots->sum(fn ($l) => $l->getTotalCompletedPieces());

                                    // Obtener estados de departamentos (simulado por ahora)
                                    $departmentStatuses = [
                                        'materials' => 'pending',
                                        'inspection' => 'pending',
                                        'production' => 'pending',
                                    ];
                                @endphp

                                @php
                                    // Conteo de lotes pendientes / completados por fase para este WO
                                    $woLifecycle = [
                                        'material' => 0, 'crimp_kit' => 0, 'inspection' => 0, 'production' => 0, 'quality' => 0,
                                        'viajero' => 0, 'decision' => 0, 'material_deliver' => 0, 'material_receive' => 0,
                                        'completed' => 0,
                                        // Completados por fase
                                        'kit_done' => 0, 'inspection_done' => 0, 'production_done' => 0, 'quality_done' => 0,
                                    ];
                                    foreach ($allLots as $l) {
                                        // Lote completado: ciclo completo finalizado
                                        if ($l->hasPackagingRecords() && $l->isSurplusReceived()) {
                                            $woLifecycle['completed']++;
                                        }

                                        // Material liberado a nivel viajero (CRIMP y NO-CRIMP por material_status; ya no por Kit)
                                        if (($l->material_status ?? '') === 'released') {
                                            $woLifecycle['kit_done']++;
                                        }

                                        // Inspección completada
                                        if (($l->inspection_status ?? '') === 'approved') {
                                            $woLifecycle['inspection_done']++;
                                        }

                                        // Producción completada (pesadas al 100%)
                                        $prodWeighedDone = $l->weighings->sum('good_pieces') + $l->weighings->sum('bad_pieces');
                                        $reworkPendingDone = $l->qualityWeighings->where('rework_status', 'pending_rework')->sum('bad_pieces');
                                        $prodToWeighDone = $l->quantity + $reworkPendingDone;
                                        if ($prodWeighedDone > 0 && $prodWeighedDone >= $prodToWeighDone) {
                                            $woLifecycle['production_done']++;
                                        }

                                        // Calidad completada (verificación total)
                                        if ($l->getQualitySemaphoreStatus() === 'green') {
                                            $woLifecycle['quality_done']++;
                                        }
                                        // Material (a nivel viajero) — CRIMP y NO-CRIMP por material_status
                                        if ($part->is_crimp) {
                                            // CRIMP: liberación por material_status del viajero (ya no por kit)
                                            if (($l->material_status ?? 'pending') === 'pending') {
                                                $woLifecycle['crimp_kit']++;
                                            }
                                        } else {
                                            if (($l->material_status ?? 'pending') === 'pending') {
                                                $woLifecycle['material']++;
                                            }
                                        }

                                        // Inspección
                                        if (($l->inspection_status ?? 'pending') === 'pending' && $l->canBeInspected()) {
                                            $woLifecycle['inspection']++;
                                        }

                                        // Producción pendiente: hay piezas por pesar y la inspección está aprobada
                                        $prodWeighed = $l->weighings->sum('good_pieces') + $l->weighings->sum('bad_pieces');
                                        $reworkPending = $l->qualityWeighings->where('rework_status', 'pending_rework')->sum('bad_pieces');
                                        $prodToWeigh = $l->quantity + $reworkPending;
                                        if ($prodWeighed < $prodToWeigh && ($l->inspection_status ?? '') === 'approved') {
                                            $woLifecycle['production']++;
                                        }

                                        // Calidad pendiente: hay piezas pesadas en producción pero no todas verificadas
                                        if ($l->getQualitySemaphoreStatus() === 'yellow') {
                                            $woLifecycle['quality']++;
                                        }

                                        // Post-calidad (Empaque)
                                        $next = $l->getNextPendingAction();
                                        if ($next) {
                                            if ($next['phase'] === 'viajero')  $woLifecycle['viajero']++;
                                            if ($next['phase'] === 'decision') $woLifecycle['decision']++;
                                            if ($next['phase'] === 'material' && $next['state'] === 'pending')     $woLifecycle['material_deliver']++;
                                            if ($next['phase'] === 'material' && $next['state'] === 'in_progress') $woLifecycle['material_receive']++;
                                        }
                                    }
                                    $woHasPending = array_sum($woLifecycle) > 0;
                                    $woHasEmpPending = ($woLifecycle['viajero'] + $woLifecycle['decision'] + $woLifecycle['material_deliver'] + $woLifecycle['material_receive']) > 0;
                                @endphp
                                {{-- Fila Principal de WO --}}
                                <tr wire:key="wo-row-{{ $wo->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">WO</td>
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('admin.sent-lists.display.wo', $wo->id) }}"
                                            wire:navigate
                                            class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 hover:underline cursor-pointer"
                                            title="Ver solo este WO">
                                            {{ $po->wo }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $part->item_number }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white font-semibold">
                                        {{ $part->number }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-xs truncate"
                                        title="{{ $part->description }}">{{ $part->description }}</td>
                                    {{-- Kit / Material --}}
                                    <td class="px-4 py-3 text-center">
                                        @if ($woLifecycle['material'] > 0 || $woLifecycle['crimp_kit'] > 0 || $woLifecycle['kit_done'] > 0)
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                @if ($woLifecycle['material'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-700"
                                                        title="{{ $woLifecycle['material'] }} {{ Str::plural('lote', $woLifecycle['material']) }} esperando liberación de material — Materiales">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                                        {{ $woLifecycle['material'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['crimp_kit'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-700"
                                                        title="{{ $woLifecycle['crimp_kit'] }} CRIMP {{ Str::plural('viajero', $woLifecycle['crimp_kit']) }} esperando liberación de material — Materiales">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                                        CRIMP {{ $woLifecycle['crimp_kit'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['kit_done'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700"
                                                        title="{{ $woLifecycle['kit_done'] }} {{ Str::plural('lote', $woLifecycle['kit_done']) }} con {{ $part->is_crimp ? 'kit listo' : 'material liberado' }}">
                                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                        {{ $woLifecycle['kit_done'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    {{-- Inspección --}}
                                    <td class="px-4 py-3 text-center">
                                        @if ($woLifecycle['inspection'] > 0 || $woLifecycle['inspection_done'] > 0)
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                @if ($woLifecycle['inspection'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 border border-yellow-300 dark:border-yellow-700"
                                                        title="{{ $woLifecycle['inspection'] }} {{ Str::plural('lote', $woLifecycle['inspection']) }} esperando inspección — Calidad">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        {{ $woLifecycle['inspection'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['inspection_done'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700"
                                                        title="{{ $woLifecycle['inspection_done'] }} {{ Str::plural('lote', $woLifecycle['inspection_done']) }} aprobado{{ $woLifecycle['inspection_done'] > 1 ? 's' : '' }} en inspección">
                                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                        {{ $woLifecycle['inspection_done'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    {{-- Producción --}}
                                    <td class="px-4 py-3 text-center">
                                        @if ($woLifecycle['production'] > 0 || $woLifecycle['production_done'] > 0)
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                @if ($woLifecycle['production'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700"
                                                        title="{{ $woLifecycle['production'] }} {{ Str::plural('lote', $woLifecycle['production']) }} esperando pesada de producción — Producción">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                                        {{ $woLifecycle['production'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['production_done'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700"
                                                        title="{{ $woLifecycle['production_done'] }} {{ Str::plural('lote', $woLifecycle['production_done']) }} con pesada de producción completa">
                                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                        {{ $woLifecycle['production_done'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    {{-- Calidad --}}
                                    <td class="px-4 py-3 text-center">
                                        @if ($woLifecycle['quality'] > 0 || $woLifecycle['quality_done'] > 0)
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                @if ($woLifecycle['quality'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 border border-teal-300 dark:border-teal-700"
                                                        title="{{ $woLifecycle['quality'] }} {{ Str::plural('lote', $woLifecycle['quality']) }} esperando pesada de calidad — Calidad">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        {{ $woLifecycle['quality'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['quality_done'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700"
                                                        title="{{ $woLifecycle['quality_done'] }} {{ Str::plural('lote', $woLifecycle['quality_done']) }} con calidad verificada">
                                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                        {{ $woLifecycle['quality_done'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    {{-- Empaque (status agregado del WO) --}}
                                    <td class="px-4 py-3 text-center">
                                        @php $woEmpDone = $allLots->filter(fn($l) => $l->packagingRecords->isNotEmpty() || $l->packagingPieceWeighings->isNotEmpty() || $l->packagingCrimpWeighings->isNotEmpty())->count(); @endphp
                                        @if ($allLots->count() > 0)
                                            <span class="text-[10px] font-semibold {{ $woEmpDone === $allLots->count() ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $woEmpDone }}/{{ $allLots->count() }}</span>
                                        @else
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    {{-- Seguimiento (post-empaque): Viajero · Decisión · Sobrantes --}}
                                    <td class="px-4 py-3">
                                        @if ($woHasEmpPending)
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                @if ($woLifecycle['viajero'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 border border-orange-300 dark:border-orange-700"
                                                        title="{{ $woLifecycle['viajero'] }} {{ Str::plural('lote', $woLifecycle['viajero']) }} esperando entrega de viajero — Empaque">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1"/></svg>
                                                        {{ $woLifecycle['viajero'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['decision'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-700"
                                                        title="{{ $woLifecycle['decision'] }} {{ Str::plural('lote', $woLifecycle['decision']) }} esperando decisión — Materiales">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                                        {{ $woLifecycle['decision'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['material_deliver'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700"
                                                        title="{{ $woLifecycle['material_deliver'] }} {{ Str::plural('lote', $woLifecycle['material_deliver']) }} esperando entrega de sobrantes — Empaque">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                                        {{ $woLifecycle['material_deliver'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['material_receive'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-700"
                                                        title="{{ $woLifecycle['material_receive'] }} {{ Str::plural('lote', $woLifecycle['material_receive']) }} esperando recepción de material — Materiales">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                        {{ $woLifecycle['material_receive'] }}
                                                    </span>
                                                @endif
                                                @if ($woLifecycle['completed'] > 0)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700"
                                                        title="{{ $woLifecycle['completed'] }} {{ Str::plural('lote', $woLifecycle['completed']) }} completado{{ $woLifecycle['completed'] > 1 ? 's' : '' }} — ciclo finalizado">
                                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                        Completado {{ $woLifecycle['completed'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @elseif ($woLifecycle['completed'] > 0)
                                            {{-- Solo completados, sin pendientes --}}
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700"
                                                    title="{{ $woLifecycle['completed'] }} {{ Str::plural('lote', $woLifecycle['completed']) }} completado{{ $woLifecycle['completed'] > 1 ? 's' : '' }} — ciclo finalizado">
                                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                    Completado {{ $woLifecycle['completed'] }}
                                                </span>
                                            </div>
                                        @else
                                            <div class="text-center text-[10px] text-gray-400 dark:text-gray-500">—</div>
                                        @endif
                                    </td>
                                    {{-- Cantidades --}}
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-white font-medium">
                                        {{ number_format($cantWO) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($pzEnviadas) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($cantAEnviar) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $woSobrantes > 0 ? 'text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ number_format($woSobrantes) }}</td>
                                    {{-- Pz Completadas (acumulado de todas las decisiones de los lotes del WO) --}}
                                    <td class="px-4 py-3 text-right font-semibold {{ $woCompletedPieces > 0 ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20' : 'text-gray-400 dark:text-gray-500' }}"
                                        title="Total de piezas cerradas en todas las decisiones de 'Completar Lote' de este WO">
                                        {{ number_format($woCompletedPieces) }}</td>
                                    {{-- Fechas --}}
                                    <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                        {{ $wo->scheduled_send_date?->format('m/d/Y') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                        {{ $wo->actual_send_date?->format('m/d/Y') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                        {{ $wo->created_at->format('m/d/Y') }}</td>
                                    <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                        {{ $wo->sentList?->id ?? '-' }}</td>
                                </tr>

                                {{-- Filas de Lotes --}}
                                @foreach ($allLots as $lot)
                                    @php
                                        $lotStatusInfo = match ($lot->status) {
                                            \App\Models\Lot::STATUS_PENDING => [
                                                'bg' => 'bg-gray-100 dark:bg-gray-700',
                                                'text' => 'text-gray-700 dark:text-gray-300',
                                                'label' => 'Pendiente',
                                            ],
                                            \App\Models\Lot::STATUS_IN_PROGRESS => [
                                                'bg' => 'bg-indigo-50 dark:bg-indigo-900/30',
                                                'text' => 'text-indigo-700 dark:text-indigo-300',
                                                'label' => 'En Proceso',
                                            ],
                                            \App\Models\Lot::STATUS_COMPLETED => [
                                                'bg' => 'bg-green-50 dark:bg-green-900/30',
                                                'text' => 'text-green-700 dark:text-green-300',
                                                'label' => 'Completado',
                                            ],
                                            default => [
                                                'bg' => 'bg-gray-100 dark:bg-gray-700',
                                                'text' => 'text-gray-700 dark:text-gray-300',
                                                'label' => $lot->status,
                                            ],
                                        };

                                        // Verificar si el lote puede ser inspeccionado
                                        $canInspect = $lot->canBeInspected();
                                        $inspectionStatus = $lot->inspection_status ?? 'pending';

                                        // Color del semaforo de inspeccion (para el boton interactivo de Calidad)
                                        $lotInspectionColor = match ($inspectionStatus) {
                                            'rejected' => 'bg-red-500',
                                            'pending' => 'bg-yellow-400',
                                            'approved' => 'bg-green-500',
                                            default => 'bg-gray-400',
                                        };

                                        // Para el boton de Calidad: gris cuando no se puede inspeccionar
                                        $lotInspectionColorBtn = $canInspect
                                            ? $lotInspectionColor
                                            : 'bg-gray-300 dark:bg-gray-600';

                                        // Obtener razon de bloqueo si existe
                                        $inspectionBlockedReason = $lot->getInspectionBlockedReason();
                                    @endphp
                                    <tr wire:key="lot-row-{{ $lot->id }}" class="bg-gray-50 dark:bg-gray-700/20">
                                        <td class="px-4 py-2 pl-8 text-xs text-gray-600 dark:text-gray-400">{{ ($part->is_crimp ?? false) ? 'Viajero' : 'Lote' }}</td>
                                        <td class="px-4 py-2 text-xs">
                                            @if ($canMaterials)
                                                <button wire:click="openLotModal({{ $wo->id }})"
                                                    class="text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer">
                                                    {{ $lot->lot_number }}
                                                </button>
                                            @else
                                                <span class="text-gray-600 dark:text-gray-400">{{ $lot->lot_number }}</span>
                                            @endif
                                            @if ($lot->completion_count > 0)
                                                <span class="ml-1 px-1 py-0.5 text-[10px] bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded font-semibold" title="Ciclo de completado {{ $lot->completion_count }}">Completado {{ $lot->completion_count }}</span>
                                                <button wire:click="openCycleHistoryModal({{ $lot->id }})" type="button"
                                                    class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full bg-amber-200 dark:bg-amber-800 text-amber-700 dark:text-amber-300 hover:bg-amber-300 dark:hover:bg-amber-700 transition"
                                                    title="Ver historial de ciclos">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </button>
                                            @endif

                                            {{-- Próxima acción pendiente (lifecycle completo) --}}
                                            @php
                                                // Resolver la primera fase pendiente del lote
                                                $lotPendingPhase = null;

                                                // 1) Material (a nivel viajero)
                                                if (($lot->material_status ?? 'pending') === 'pending') {
                                                    $lotPendingPhase = $part->is_crimp
                                                        ? ['actor' => 'Materiales', 'label' => 'Materiales debe liberar el material (viajero CRIMP)', 'is_crimp' => true]
                                                        : ['actor' => 'Materiales', 'label' => 'Materiales debe liberar el material'];
                                                }

                                                // 2) Inspección
                                                if (!$lotPendingPhase && ($lot->inspection_status ?? 'pending') === 'pending' && $lot->canBeInspected()) {
                                                    $lotPendingPhase = ['actor' => 'Calidad', 'label' => 'Calidad debe inspeccionar el lote'];
                                                }

                                                // 3) Producción
                                                if (!$lotPendingPhase) {
                                                    $prodWeighedNow = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
                                                    $reworkPendingNow = $lot->qualityWeighings->where('rework_status', 'pending_rework')->sum('bad_pieces');
                                                    $prodToWeighNow = $lot->quantity + $reworkPendingNow;
                                                    if ($prodWeighedNow < $prodToWeighNow && ($lot->inspection_status ?? '') === 'approved') {
                                                        $remaining = $prodToWeighNow - $prodWeighedNow;
                                                        $lotPendingPhase = ['actor' => 'Producción', 'label' => 'Producción debe pesar ' . number_format($remaining) . ' pz'];
                                                    }
                                                }

                                                // 4) Calidad
                                                if (!$lotPendingPhase && $lot->getQualitySemaphoreStatus() === 'yellow') {
                                                    $qPending = $lot->getQualityPendingPieces();
                                                    $lotPendingPhase = ['actor' => 'Calidad', 'label' => 'Calidad debe pesar ' . number_format($qPending) . ' pz'];
                                                }

                                                // 5) Post-calidad (Viajero / Decisión / Material)
                                                if (!$lotPendingPhase) {
                                                    $next = $lot->getNextPendingAction();
                                                    if ($next) {
                                                        $lotPendingPhase = ['actor' => $next['actor'], 'label' => $next['label']];
                                                    }
                                                }

                                                $actorColorMap = [
                                                    'Materiales' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700',
                                                    'Calidad'    => 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 border-teal-300 dark:border-teal-700',
                                                    'Producción' => 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700',
                                                    'Empaque'    => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 border-orange-300 dark:border-orange-700',
                                                ];
                                            @endphp
                                            @if ($lotPendingPhase)
                                                @php
                                                    $isCrimpPhase = $lotPendingPhase['is_crimp'] ?? false;
                                                    $actorClass = $isCrimpPhase
                                                        ? 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300 border-cyan-300 dark:border-cyan-700'
                                                        : ($actorColorMap[$lotPendingPhase['actor']] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600');
                                                @endphp
                                                <div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[10px] font-medium {{ $actorClass }}"
                                                    title="{{ $lotPendingPhase['label'] }}">
                                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span>{{ $lotPendingPhase['actor'] }}{{ $isCrimpPhase ? ' (CRIMP)' : '' }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-700 dark:text-gray-300">
                                            {{ $part->item_number }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-700 dark:text-gray-300 font-medium">
                                            {{ $part->number }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-700 dark:text-gray-300 max-w-xs truncate"
                                            title="{{ $lot->description ?? $part->description }}">
                                            {{ $lot->description ?? $part->description }}</td>
                                        {{-- Material (liberación del viajero) + Lotes de CRIMP con sus campos --}}
                                        <td class="px-4 py-2 text-center">
                                            @php
                                                $matStatus = $lot->material_status ?? 'pending';
                                                $matColor = match ($matStatus) {
                                                    'released' => 'bg-green-500',
                                                    'rejected' => 'bg-red-500',
                                                    default => 'bg-gray-400',
                                                };
                                                $matLabel = match ($matStatus) {
                                                    'released' => 'Aprobado',
                                                    'rejected' => 'Rechazado',
                                                    default => 'Pendiente',
                                                };
                                            @endphp
                                            <div class="flex flex-col items-center gap-1.5">
                                                {{-- Indicador de liberación + gestionar lotes --}}
                                                <div class="flex items-center justify-center gap-1.5">
                                                    @if ($canMaterials)
                                                        <button wire:click="openMaterialModal({{ $lot->id }})"
                                                            class="w-5 h-5 rounded {{ $matColor }} hover:opacity-80 cursor-pointer transition-opacity shrink-0"
                                                            title="Material: {{ $matLabel }}"></button>
                                                    @else
                                                        <span class="w-5 h-5 rounded {{ $matColor }} opacity-60 shrink-0" title="Material: {{ $matLabel }}"></span>
                                                    @endif
                                                    @if ($part->is_crimp && $canMaterials)
                                                        <button wire:click="openCrimpLotModal({{ $lot->id }})"
                                                            class="w-5 h-5 rounded bg-cyan-500 hover:bg-cyan-600 cursor-pointer transition-colors flex items-center justify-center shrink-0"
                                                            title="Gestionar lotes de CRIMP">
                                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                        </button>
                                                    @endif
                                                </div>

                                                {{-- Lotes de CRIMP del viajero --}}
                                                @if ($part->is_crimp && $lot->crimpLots->isNotEmpty())
                                                    <div class="flex flex-wrap items-center justify-center gap-1 max-w-[130px]">
                                                        @foreach ($lot->crimpLots as $cl)
                                                            <span class="px-1.5 py-0.5 text-[10px] leading-tight bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-300 rounded font-mono"
                                                                title="Lote de CRIMP {{ $cl->crimp_lot_number }} · Lote de fabricante: {{ $cl->lote_fabricante ?: '—' }} · {{ number_format($cl->quantity) }} pz{{ $cl->comments ? ' · '.$cl->comments : '' }}">
                                                                {{ $cl->crimp_lot_number }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        {{-- Semaforo INSP - Status de Inspeccion por Lote --}}
                                        <td class="px-4 py-2 text-center">
                                            @if ($canQuality)
                                                <button wire:click="openInspectionModal({{ $lot->id }})"
                                                    class="w-5 h-5 rounded {{ $lotInspectionColorBtn }} {{ $canInspect ? 'hover:opacity-80 cursor-pointer' : 'cursor-not-allowed opacity-60' }} transition-opacity relative inline-flex items-center justify-center"
                                                    title="{{ $canInspect ? 'Status de Inspeccion: ' . ucfirst($inspectionStatus) : $inspectionBlockedReason ?? 'Bloqueado' }}">
                                                    @if (!$canInspect)
                                                        <svg class="w-3 h-3 text-gray-500 dark:text-gray-400"
                                                            fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    @endif
                                                </button>
                                            @else
                                                {{-- Solo-lectura: muestra siempre el color real del estado --}}
                                                <span class="inline-flex w-5 h-5 rounded {{ $lotInspectionColor }} opacity-70"
                                                    title="Inspección: {{ ucfirst($inspectionStatus) }}{{ !$canInspect ? ' (bloqueado)' : '' }}"></span>
                                            @endif
                                        </td>
                                        {{-- Semaforo Prod + Botón Pesada --}}
                                        <td class="px-4 py-2 text-center">
                                            @php
                                                $prodTotalWeighed = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
                                                $prodReworkPending = $lot->qualityWeighings
                                                    ->where('rework_status', 'pending_rework')
                                                    ->sum('bad_pieces');
                                                $prodTotalToWeigh = $lot->quantity + $prodReworkPending;
                                                $prodSemaphore = 'gray';
                                                if ($prodTotalWeighed > 0 && $prodTotalWeighed >= $prodTotalToWeigh) {
                                                    $prodSemaphore = 'green';
                                                } elseif ($prodTotalWeighed > 0) {
                                                    $prodSemaphore = 'yellow';
                                                }
                                                $prodSemColor = match ($prodSemaphore) {
                                                    'green' => 'bg-green-500',
                                                    'yellow' => 'bg-yellow-400',
                                                    default => 'bg-gray-300 dark:bg-gray-600',
                                                };
                                                $prodRemaining = max(0, $prodTotalToWeigh - $prodTotalWeighed);
                                                $prodTitle = match ($prodSemaphore) {
                                                    'green' => 'Prod: Completado',
                                                    'yellow' => 'Prod: Pendiente (' . number_format($prodRemaining) . ' pz)',
                                                    default => 'Prod: Sin pesadas',
                                                };
                                            @endphp
                                            <div class="flex items-center justify-center gap-1">
                                                <span class="inline-block w-5 h-5 rounded {{ $prodSemColor }}" title="{{ $prodTitle }}"></span>
                                                @if ($canProduction)
                                                    <button wire:click="openProductionModal({{ $lot->id }})"
                                                        class="w-5 h-5 rounded bg-indigo-500 hover:bg-indigo-600 cursor-pointer transition-colors flex items-center justify-center"
                                                        title="Pesada Lote">
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 6v12m6-6H6"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        {{-- Semaforo Calidad + Boton Pesada --}}
                                        <td class="px-4 py-2 text-center">
                                            @php
                                                $qualSemaphore = $lot->getQualitySemaphoreStatus();
                                                $qualColor = match ($qualSemaphore) {
                                                    'green' => 'bg-green-500',
                                                    'yellow' => 'bg-yellow-400',
                                                    'gray' => 'bg-gray-300 dark:bg-gray-600',
                                                    default => 'bg-gray-400',
                                                };
                                                $qualHasProduction = $lot->hasProductionWeighings();
                                                $qualPending = $lot->getQualityPendingPieces();
                                                $qualTitle = match ($qualSemaphore) {
                                                    'green' => 'Calidad: Verificado completamente',
                                                    'yellow' => 'Calidad: Pendiente (' . number_format($qualPending) . ' piezas)',
                                                    'gray' => 'Calidad: Sin pesadas de produccion',
                                                    default => 'Calidad',
                                                };
                                            @endphp
                                            <div class="flex items-center justify-center gap-1">
                                                <span class="inline-block w-5 h-5 rounded {{ $qualColor }} {{ !$qualHasProduction ? 'opacity-60' : '' }}"
                                                    title="{{ $qualTitle }}"></span>
                                                @if ($canQuality)
                                                    @if ($qualHasProduction)
                                                        <button wire:click="openQualityModal({{ $lot->id }})"
                                                            class="w-5 h-5 rounded bg-teal-500 hover:bg-teal-600 cursor-pointer transition-colors flex items-center justify-center"
                                                            title="Calidad Lote">
                                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 6v12m6-6H6"/>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                        {{-- Semaforo Empaque --}}
                                        <td class="px-4 py-2 text-center">
                                            @php
                                                $pkgSem = $lot->getPackagingSemaphoreStatus();
                                                $pkgSemColor = match ($pkgSem) {
                                                    'green' => 'bg-green-500',
                                                    'yellow' => 'bg-yellow-400',
                                                    'blue' => 'bg-blue-500',
                                                    'orange' => 'bg-orange-500',
                                                    default => 'bg-gray-300 dark:bg-gray-600',
                                                };
                                                $pkgSemTitle = match ($pkgSem) {
                                                    'green' => 'Empaque: Completado',
                                                    'yellow' => 'Empaque: Pendiente',
                                                    'blue' => 'Empaque: Viajero recibido, pendiente decisión',
                                                    'orange' => 'Empaque: Cerrado con sobrantes',
                                                    default => 'Empaque: Sin piezas de calidad',
                                                };
                                            @endphp
                                            @php
                                                $lifecycle = $lot->getPostQualityLifecycle();
                                                $stateColorMap = [
                                                    'idle'        => 'bg-gray-300 dark:bg-gray-600',
                                                    'pending'     => 'bg-amber-500',
                                                    'in_progress' => 'bg-orange-500',
                                                    'done'        => 'bg-green-600',
                                                ];
                                                $surplus = $lot->getPackagingTotalSurplus();
                                                $canDeliverSurplus = $canPackaging && $lot->viajero_received && $surplus > 0 && !$lot->surplus_delivered;
                                            @endphp
                                            <div class="flex items-center justify-center gap-1">
                                                {{-- Semáforo Empaque general (NO-CRIMP: abre el empaque por registro) --}}
                                                @if ($canPackaging && !$part->is_crimp)
                                                    <button wire:click="openPackagingModal({{ $lot->id }})"
                                                        class="w-5 h-5 rounded {{ $pkgSemColor }} hover:opacity-80 cursor-pointer transition-opacity"
                                                        title="{{ $pkgSemTitle }}"></button>
                                                @else
                                                    @php
                                                        $crimpPkgTitle = $part->is_crimp
                                                            ? 'Empaque CRIMP — Piezas: '.number_format($lot->getPackagedPiecesTotal()).'/'.number_format($lot->getPackagingAvailablePieces()).' · CRIMP: '.number_format($lot->getPackagedCrimpTotal()).'/'.number_format($lot->getCrimpTargetTotal())
                                                            : $pkgSemTitle;
                                                    @endphp
                                                    <span class="w-5 h-5 rounded {{ $pkgSemColor }} {{ $canPackaging ? '' : 'opacity-60' }}" title="{{ $crimpPkgTitle }}"></span>
                                                @endif

                                                {{-- CRIMP: Paso 5 — modal único de confirmación de empaque --}}
                                                @if ($part->is_crimp && $canPackaging)
                                                    <button wire:click="openConfirmModal({{ $lot->id }})"
                                                        class="w-5 h-5 rounded bg-indigo-500 hover:bg-indigo-600 cursor-pointer transition-colors flex items-center justify-center shrink-0"
                                                        title="Empacar / Confirmar (Paso 5)">
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        {{-- Seguimiento (post-empaque): Viajero · Decisión · Sobrantes --}}
                                        <td class="px-4 py-2 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                {{-- Indicador Viajero (Paso 7). CRIMP: botón → modal con resumen (marcar/revertir). NO-CRIMP: span sin cambios. --}}
                                                @if (($part->is_crimp ?? false) && $canPackaging && in_array($lifecycle['viajero']['state'], ['pending','done']))
                                                    <button wire:click="openViajeroModal({{ $lot->id }})"
                                                        class="w-5 h-5 rounded {{ $stateColorMap[$lifecycle['viajero']['state']] }} {{ $lifecycle['viajero']['state'] === 'pending' ? 'animate-pulse' : '' }} hover:opacity-80 cursor-pointer transition-opacity flex items-center justify-center"
                                                        title="Entrega de viajero — {{ $lifecycle['viajero']['label'] }}">
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                                        </svg>
                                                    </button>
                                                @else
                                                    <span class="w-5 h-5 rounded {{ $stateColorMap[$lifecycle['viajero']['state']] }} {{ $lifecycle['viajero']['state'] === 'in_progress' ? 'animate-pulse' : '' }} flex items-center justify-center"
                                                        title="Viajero: {{ $lifecycle['viajero']['label'] }}">
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                                        </svg>
                                                    </span>
                                                @endif

                                                {{-- Indicador Decisión --}}
                                                @if ($lifecycle['decision']['state'] === 'pending' && $canMaterials)
                                                    <button wire:click="openDecisionModal({{ $lot->id }})"
                                                        class="w-5 h-5 rounded bg-amber-500 hover:bg-amber-600 animate-pulse cursor-pointer transition-colors flex items-center justify-center"
                                                        title="Decisión: {{ $lifecycle['decision']['label'] }}">
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                        </svg>
                                                    </button>
                                                @elseif ($lifecycle['decision']['state'] === 'done' && $canMaterials)
                                                    <button wire:click="openDecisionModal({{ $lot->id }})"
                                                        class="w-5 h-5 rounded bg-green-600 hover:opacity-80 cursor-pointer transition-opacity flex items-center justify-center"
                                                        title="Decisión: {{ $lifecycle['decision']['label'] }}">
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                        </svg>
                                                    </button>
                                                @else
                                                    <span class="w-5 h-5 rounded {{ $stateColorMap[$lifecycle['decision']['state']] }} flex items-center justify-center"
                                                        title="Decisión: {{ $lifecycle['decision']['label'] }}">
                                                        <svg class="w-3 h-3 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                        </svg>
                                                    </span>
                                                @endif

                                                {{-- Indicador Material / Sobrantes --}}
                                                @if ($canDeliverSurplus)
                                                    <button wire:click="openDeliverMaterialModal({{ $lot->id }})"
                                                        class="w-5 h-5 rounded bg-amber-500 hover:bg-amber-600 animate-pulse cursor-pointer transition-colors flex items-center justify-center"
                                                        title="Material: {{ $lifecycle['material']['label'] }}">
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                        </svg>
                                                    </button>
                                                @else
                                                    <span class="w-5 h-5 rounded {{ $stateColorMap[$lifecycle['material']['state']] }} {{ $lifecycle['material']['state'] === 'pending' || $lifecycle['material']['state'] === 'in_progress' ? 'animate-pulse' : '' }} flex items-center justify-center"
                                                        title="Material: {{ $lifecycle['material']['label'] }}">
                                                        <svg class="w-3 h-3 text-white opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                        </svg>
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        {{-- Cantidades --}}
                                        <td class="px-4 py-2 text-right text-xs"></td>
                                        <td class="px-4 py-2 text-right text-xs"></td>
                                        <td class="px-4 py-2 text-right text-xs"></td>
                                        <td
                                            class="px-4 py-2 text-right text-xs font-medium text-gray-900 dark:text-white">
                                            {{ number_format($lot->quantity) }}
                                        </td>
                                        @php
                                            $lotSobrantes = $lot->isSurplusReceived() ? 0 : ($lot->hasPackagingRecords() ? $lot->getPackagingTotalSurplus() : 0);
                                        @endphp
                                        <td class="px-4 py-2 text-right text-xs font-medium {{ $lotSobrantes > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400 dark:text-gray-500' }}">
                                            {{ number_format($lotSobrantes) }}
                                        </td>
                                        {{-- Pz Completadas por ciclo (C1, C2, ...) + sumatoria --}}
                                        <td class="px-4 py-2 text-right text-xs">
                                            @php
                                                $lotCycles = $lot->getCompletionCycles();
                                                $lotCompletedTotal = array_sum(array_column($lotCycles, 'pieces'));
                                            @endphp
                                            @if (!empty($lotCycles))
                                                <div class="flex flex-wrap items-center justify-end gap-1">
                                                    @foreach ($lotCycles as $cycle)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300"
                                                            title="Ciclo {{ $cycle['cycle'] }}: {{ number_format($cycle['pieces']) }} pz cerradas">
                                                            C{{ $cycle['cycle'] }}: {{ number_format($cycle['pieces']) }}
                                                        </span>
                                                    @endforeach
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300"
                                                        title="Total de piezas completadas del lote">
                                                        &Sigma; {{ number_format($lotCompletedTotal) }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500">&mdash;</span>
                                            @endif
                                        </td>
                                        {{-- Fechas --}}
                                        <td class="px-4 py-2 text-center text-xs text-gray-600 dark:text-gray-400">
                                            {{ $wo->scheduled_send_date?->format('m/d/Y') ?? '-' }}</td>
                                        <td class="px-4 py-2 text-center text-xs text-gray-600 dark:text-gray-400">
                                            {{ $wo->actual_send_date?->format('m/d/Y') ?? '-' }}</td>
                                        <td class="px-4 py-2 text-center text-xs text-gray-600 dark:text-gray-400">
                                            {{ $wo->created_at->format('m/d/Y') }}</td>
                                        <td class="px-4 py-2 text-center text-xs text-gray-600 dark:text-gray-400">-
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Alerta si suma de lotes sobrepasa Cant. WO --}}
                                @php
                                    $totalLotQuantity = $allLots->sum('quantity');
                                    $exceedsWO = $totalLotQuantity > $cantWO;
                                @endphp
                                @if($exceedsWO)
                                    <tr class="bg-red-50 dark:bg-red-900/30">
                                        <td colspan="21" class="px-4 py-2">
                                            <div class="flex items-center text-red-600 dark:text-red-400 text-xs font-semibold">
                                                <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                                ALERTA: La suma de lotes ({{ number_format($totalLotQuantity) }}) sobrepasa la Cant. WO ({{ number_format($cantWO) }}) por {{ number_format($totalLotQuantity - $cantWO) }} piezas.
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Fila de Total --}}
                                @if ($allLots->count() > 1)
                                    <tr class="bg-gray-100 dark:bg-gray-700/40 font-semibold">
                                        <td colspan="14" class="px-4 py-2 text-right text-gray-900 dark:text-white">
                                            Total:</td>
                                        <td class="px-4 py-2 text-right {{ $exceedsWO ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                            {{ number_format($totalLotQuantity) }}</td>
                                        <td></td>
                                        <td class="px-4 py-2 text-right {{ $woCompletedPieces > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}"
                                            title="Total de piezas completadas en todas las decisiones de los lotes de este WO">
                                            &Sigma; {{ number_format($woCompletedPieces) }}</td>
                                        <td colspan="4"></td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Vista Móvil/Tablet: Cards --}}
                <div class="lg:hidden divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($workOrders as $wo)
                        @php
                            $po = $wo->purchaseOrder;
                            $part = $po->part;
                            $allLots = $wo->lots;
                            $completedLots = $wo->lots->where('status', \App\Models\Lot::STATUS_COMPLETED);
                            $cantWO = $wo->original_quantity; // Cantidad total del WO
                            $pzEnviadas = $wo->sent_pieces; // Piezas enviadas
                            $cantAEnviar = $cantWO - $pzEnviadas; // Cant. Pendiente = Cant. WO - Pz Enviadas

                            $woSobrantesMobile = $allLots->sum(function ($l) {
                                if ($l->isSurplusReceived()) return 0;
                                if ($l->hasPackagingRecords()) return $l->getPackagingTotalSurplus();
                                return $l->getQualityPendingPieces();
                            });

                            $departmentStatuses = [
                                'materials' => 'pending',
                                'inspection' => 'pending',
                                'production' => 'pending',
                            ];
                        @endphp

                        <div wire:key="wo-card-{{ $wo->id }}" class="p-4 space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span
                                            class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO</span>
                                        <span
                                            class="text-base font-semibold text-indigo-600 dark:text-indigo-400">{{ $po->wo }}</span>
                                    </div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                                        {{ $part->item_number }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2">
                                        {{ $part->description }}</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Cant. WO</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($cantWO) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Pz Enviadas</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($pzEnviadas) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Cant. Pendiente</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($cantAEnviar) }}</div>
                                </div>
                                <div class="{{ $woSobrantesMobile > 0 ? 'bg-orange-50 dark:bg-orange-900/20' : '' }} p-2">
                                    <div class="text-xs {{ $woSobrantesMobile > 0 ? 'text-orange-700 dark:text-orange-300 font-medium' : 'text-gray-500 dark:text-gray-400' }} mb-1">Pz Sobrantes</div>
                                    <div class="text-sm font-semibold {{ $woSobrantesMobile > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ number_format($woSobrantesMobile) }}</div>
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-3 gap-2 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400 mb-1">Fecha Prog. A</div>
                                    <div class="text-gray-900 dark:text-white">
                                        {{ $wo->scheduled_send_date?->format('m/d/Y') ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400 mb-1">Fecha Envío</div>
                                    <div class="text-gray-900 dark:text-white">
                                        {{ $wo->actual_send_date?->format('m/d/Y') ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400 mb-1">Fecha Apertura</div>
                                    <div class="text-gray-900 dark:text-white">{{ $wo->created_at->format('m/d/Y') }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-4 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">EG:</span>
                                    <span
                                        class="text-gray-900 dark:text-white font-medium ml-1">{{ $wo->sentList?->id ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">PR:</span>
                                    <span
                                        class="text-gray-900 dark:text-white font-medium ml-1">{{ $wo->priority ?? '-' }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Estado por Departamento
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex flex-col items-center gap-1">
                                        @php
                                            $materialsColor = match ($departmentStatuses['materials']) {
                                                'rejected' => 'bg-red-500',
                                                'pending' => 'bg-yellow-400',
                                                'in_progress' => 'bg-indigo-500',
                                                'approved' => 'bg-green-500',
                                                default => 'bg-gray-400',
                                            };
                                        @endphp
                                        <button
                                            wire:click="openDepartmentStatusModal({{ $wo->id }}, 'materials')"
                                            class="w-8 h-8 rounded {{ $materialsColor }} hover:opacity-80 transition-opacity"
                                            title="Materiales"></button>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Mat.</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-1">
                                        @php
                                            $inspectionColor = match ($departmentStatuses['inspection']) {
                                                'rejected' => 'bg-red-500',
                                                'pending' => 'bg-yellow-400',
                                                'in_progress' => 'bg-indigo-500',
                                                'approved' => 'bg-green-500',
                                                default => 'bg-gray-400',
                                            };
                                        @endphp
                                        <button wire:click="openDepartmentStatusModal({{ $wo->id }}, 'inspection')"
                                            class="w-8 h-8 rounded {{ $inspectionColor }} hover:opacity-80 transition-opacity"
                                            title="Inspeccion"></button>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Insp.</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-1">
                                        @php
                                            $productionColor = match ($departmentStatuses['production']) {
                                                'rejected' => 'bg-red-500',
                                                'pending' => 'bg-yellow-400',
                                                'in_progress' => 'bg-indigo-500',
                                                'approved' => 'bg-green-500',
                                                default => 'bg-gray-400',
                                            };
                                        @endphp
                                        <button
                                            wire:click="openDepartmentStatusModal({{ $wo->id }}, 'production')"
                                            class="w-8 h-8 rounded {{ $productionColor }} hover:opacity-80 transition-opacity"
                                            title="Producción"></button>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Prod.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @foreach ($allLots as $lot)
                            @php
                                $statusInfo = match ($lot->status) {
                                    \App\Models\Lot::STATUS_PENDING => [
                                        'bg' => 'bg-gray-100 dark:bg-gray-700',
                                        'text' => 'text-gray-700 dark:text-gray-300',
                                        'label' => 'Pendiente',
                                    ],
                                    \App\Models\Lot::STATUS_IN_PROGRESS => [
                                        'bg' => 'bg-indigo-50 dark:bg-indigo-900/30',
                                        'text' => 'text-indigo-700 dark:text-indigo-300',
                                        'label' => 'En Proceso',
                                    ],
                                    \App\Models\Lot::STATUS_COMPLETED => [
                                        'bg' => 'bg-green-50 dark:bg-green-900/30',
                                        'text' => 'text-green-700 dark:text-green-300',
                                        'label' => 'Completado',
                                    ],
                                    default => [
                                        'bg' => 'bg-gray-100 dark:bg-gray-700',
                                        'text' => 'text-gray-700 dark:text-gray-300',
                                        'label' => $lot->status,
                                    ],
                                };

                                // Verificar si el lote puede ser inspeccionado
                                $canInspectMobile = $lot->canBeInspected();
                                $inspectionStatusMobile = $lot->inspection_status ?? 'pending';

                                // Color del semaforo de inspeccion (estado real, siempre visible)
                                $lotInspectionColorMobile = match ($inspectionStatusMobile) {
                                    'rejected' => 'bg-red-500',
                                    'pending' => 'bg-yellow-400',
                                    'approved' => 'bg-green-500',
                                    default => 'bg-gray-400',
                                };

                                // Para el boton de Calidad: gris cuando no se puede inspeccionar
                                $lotInspectionColorMobileBtn = $canInspectMobile
                                    ? $lotInspectionColorMobile
                                    : 'bg-gray-300 dark:bg-gray-600';

                                // Obtener razon de bloqueo si existe
                                $inspectionBlockedReasonMobile = $lot->getInspectionBlockedReason();
                            @endphp
                            <div wire:key="lot-card-{{ $lot->id }}"
                                class="p-4 pl-8 bg-gray-50 dark:bg-gray-700/20 space-y-2 border-l-2 border-gray-300 dark:border-gray-600">
                                <div class="flex items-center justify-between">
                                    @if ($canMaterials)
                                        <button wire:click="openLotModal({{ $wo->id }})"
                                            class="flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Lote</span>
                                            <span>{{ $lot->lot_number }}</span>
                                            @if ($lot->completion_count > 0)
                                                <span class="px-1.5 py-0.5 text-[10px] bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded font-semibold">Completado {{ $lot->completion_count }}</span>
                                            @endif
                                        </button>
                                    @else
                                        <span class="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400">
                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Lote</span>
                                            <span>{{ $lot->lot_number }}</span>
                                            @if ($lot->completion_count > 0)
                                                <span class="px-1.5 py-0.5 text-[10px] bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded font-semibold">Completado {{ $lot->completion_count }}</span>
                                            @endif
                                        </span>
                                    @endif
                                    @if ($lot->completion_count > 0)
                                        <button wire:click="openCycleHistoryModal({{ $lot->id }})" type="button"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-medium rounded bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-800 transition"
                                            title="Ver historial de ciclos">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Historia
                                        </button>
                                    @endif
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded {{ $statusInfo['bg'] }} {{ $statusInfo['text'] }}">
                                        {{ $statusInfo['label'] }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2">
                                    {{ $lot->description ?? $part->description }}</div>

                                {{-- Semaforo de Inspeccion para movil --}}
                                <div
                                    class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-600">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Inspeccion:</span>
                                        @if ($canQuality)
                                            <button wire:click="openInspectionModal({{ $lot->id }})"
                                                class="w-6 h-6 rounded {{ $lotInspectionColorMobileBtn }} {{ $canInspectMobile ? 'hover:opacity-80 cursor-pointer' : 'cursor-not-allowed opacity-60' }} transition-opacity relative inline-flex items-center justify-center"
                                                title="{{ $canInspectMobile ? 'Status de Inspeccion: ' . ucfirst($inspectionStatusMobile) : $inspectionBlockedReasonMobile ?? 'Bloqueado' }}">
                                                @if (!$canInspectMobile)
                                                    <svg class="w-3 h-3 text-gray-500 dark:text-gray-400"
                                                        fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                            </button>
                                        @else
                                            {{-- Solo-lectura: muestra el color real del estado de inspección --}}
                                            <span class="inline-flex w-6 h-6 rounded {{ $lotInspectionColorMobile }} opacity-70"
                                                title="Inspección: {{ ucfirst($inspectionStatusMobile) }}{{ !$canInspectMobile ? ' (bloqueado)' : '' }}"></span>
                                        @endif
                                        @if ($canInspectMobile)
                                            <span
                                                class="text-xs {{ $inspectionStatusMobile === 'approved' ? 'text-green-600 dark:text-green-400' : ($inspectionStatusMobile === 'rejected' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400') }}">
                                                {{ ucfirst($inspectionStatusMobile === 'pending' ? 'Pendiente' : ($inspectionStatusMobile === 'approved' ? 'Aprobado' : 'No Aprobado')) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Bloqueado</span>
                                        @endif
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-600">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Cantidad:</span>
                                    <span
                                        class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($lot->quantity) }}</span>
                                </div>

                                {{-- Pz Completadas por ciclo --}}
                                @php
                                    $lotCyclesM = $lot->getCompletionCycles();
                                    $lotCompletedTotalM = array_sum(array_column($lotCyclesM, 'pieces'));
                                @endphp
                                @if (!empty($lotCyclesM))
                                    <div
                                        class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-600">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Pz Completadas:</span>
                                        <div class="flex flex-wrap items-center justify-end gap-1">
                                            @foreach ($lotCyclesM as $cycle)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                                                    C{{ $cycle['cycle'] }}: {{ number_format($cycle['pieces']) }}
                                                </span>
                                            @endforeach
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                                                &Sigma; {{ number_format($lotCompletedTotalM) }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if ($allLots->count() > 1)
                            @php
                                $woCompletedPiecesM = $allLots->sum(fn ($l) => $l->getTotalCompletedPieces());
                            @endphp
                            <div class="p-4 bg-gray-100 dark:bg-gray-700/40 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Total:</span>
                                    <span
                                        class="text-base font-semibold text-gray-900 dark:text-white">{{ number_format($allLots->sum('quantity')) }}</span>
                                </div>
                                @if ($woCompletedPiecesM > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Total Completadas:</span>
                                        <span class="text-base font-semibold text-emerald-700 dark:text-emerald-300">&Sigma; {{ number_format($woCompletedPiecesM) }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($workOrdersGrouped->isEmpty())
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                <h3 class="mt-4 text-base font-medium text-gray-900 dark:text-white">No hay lotes</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Los lotes aparecerán aquí automáticamente.</p>
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div
        class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs sm:text-sm">
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Última actualización: <span
                            class="font-medium text-gray-900 dark:text-white">{{ now()->format('d/m/Y H:i:s') }}</span></span>
                </div>
                <div class="flex items-center gap-4 sm:gap-6 text-gray-600 dark:text-gray-400">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="font-medium text-gray-900 dark:text-white">{{ $workOrdersGrouped->flatten()->count() }}</span>
                        <span>WOs</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="font-medium text-gray-900 dark:text-white">{{ $workOrdersGrouped->flatten()->sum(fn($wo) => $wo->lots->count()) }}</span>
                        <span>Lotes</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de Historial de Ciclos --}}
    @if ($showCycleHistoryModal && $selectedLotForCycleHistory)
        @php $lot_h = $selectedLotForCycleHistory; @endphp
        <div wire:key="modal-cycle-history" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closeCycleHistoryModal"></div>

                <div class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full rounded-2xl shadow-2xl">

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Historial de Ciclos — {{ ($lot_h->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote' }} {{ $lot_h->lot_number }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                WO: {{ $lot_h->workOrder->purchaseOrder->wo ?? '—' }}
                                · Parte: {{ $lot_h->workOrder->purchaseOrder->part->number ?? '—' }}
                                · Qty Original: {{ number_format($lot_h->completionLogs->first()?->original_quantity ?? $lot_h->quantity) }}
                            </p>
                        </div>
                        <button wire:click="closeCycleHistoryModal" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Tabla de ciclos --}}
                    <div class="overflow-x-auto">
                        @if ($lot_h->completionLogs->isNotEmpty())
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/50">
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap">Ciclo</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap">Qty Original</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase whitespace-nowrap">Empacadas</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-orange-500 dark:text-orange-400 uppercase whitespace-nowrap">Sobrantes</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-red-500 dark:text-red-400 uppercase whitespace-nowrap">Faltantes</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap">Prod. Buenas</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap">Cal. Buenas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap">Completado por</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($lot_h->completionLogs->sortBy('cycle_number') as $clog)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                                                    C{{ $clog->cycle_number }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ number_format($clog->original_quantity) }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-400 tabular-nums">{{ number_format($clog->packed_pieces) }}</td>
                                            <td class="px-4 py-3 text-right tabular-nums {{ $clog->surplus_pieces > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400 dark:text-gray-500' }}">{{ number_format($clog->surplus_pieces) }}</td>
                                            <td class="px-4 py-3 text-right tabular-nums {{ $clog->missing_pieces > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">{{ number_format($clog->missing_pieces) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 tabular-nums">{{ number_format($clog->production_good_pieces) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 tabular-nums">{{ number_format($clog->quality_good_pieces) }}</td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 text-xs whitespace-nowrap">{{ $clog->completedByUser?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">{{ $clog->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 font-semibold">
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 uppercase">Total</td>
                                        <td class="px-4 py-3 text-right text-xs text-gray-400">—</td>
                                        <td class="px-4 py-3 text-right text-xs text-emerald-700 dark:text-emerald-400 tabular-nums">{{ number_format($lot_h->completionLogs->sum('packed_pieces')) }}</td>
                                        <td class="px-4 py-3 text-right text-xs text-orange-600 dark:text-orange-400 tabular-nums">{{ number_format($lot_h->completionLogs->sum('surplus_pieces')) }}</td>
                                        <td class="px-4 py-3 text-right text-xs text-red-600 dark:text-red-400 tabular-nums">{{ number_format($lot_h->completionLogs->sum('missing_pieces')) }}</td>
                                        <td colspan="4"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        @else
                            <p class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">No hay ciclos completados registrados.</p>
                        @endif
                    </div>

                    {{-- Footer: rollback solo para admin --}}
                    @if (auth()->user()->hasRole('admin') && $lot_h->completionLogs->isNotEmpty() && !$lot_h->packingSlipItem)
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/10">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-red-700 dark:text-red-400">Zona de Admin — Reversión de Ciclo</p>
                                    <p class="text-xs text-red-600 dark:text-red-500 mt-0.5">
                                        Revierte el <strong>Ciclo {{ $lot_h->completionLogs->sortByDesc('cycle_number')->first()->cycle_number }}</strong>.
                                        Se eliminarán los registros del ciclo actual y se restaurarán los del ciclo anterior.
                                        Esta acción <strong>no se puede deshacer</strong>.
                                    </p>
                                </div>
                                <button
                                    wire:click="rollbackLastCycle({{ $lot_h->id }})"
                                    wire:confirm="¿Confirmas que deseas revertir el Ciclo {{ $lot_h->completionLogs->sortByDesc('cycle_number')->first()->cycle_number }}? Esta acción eliminará el ciclo actual y restaurará el estado anterior. No se puede deshacer."
                                    type="button"
                                    class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                    </svg>
                                    Revertir C{{ $lot_h->completionLogs->sortByDesc('cycle_number')->first()->cycle_number }}
                                </button>
                            </div>
                        </div>
                    @elseif (auth()->user()->hasRole('admin') && $lot_h->packingSlipItem)
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Reversión bloqueada: este lote ya tiene un Packing Slip generado.
                            </p>
                        </div>
                    @endif

                    {{-- Botón cerrar --}}
                    <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                        <button wire:click="closeCycleHistoryModal" type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Gestión de Lotes --}}
    @if ($showLotModal && $selectedWorkOrder)
        <div wire:key="modal-lot" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closeLotModal"></div>

                <div
                    class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full rounded-2xl shadow-2xl">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Gestión de Lotes</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    WO: {{ $selectedWorkOrder->purchaseOrder->wo }} | Parte:
                                    {{ $selectedWorkOrder->purchaseOrder->part->number }}
                                </p>
                            </div>
                            <button wire:click="closeLotModal"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
                        @if (count($lots) > 0)
                            <div class="space-y-3">
                                @foreach ($lots as $index => $lot)
                                    <div
                                        class="flex items-start gap-3 p-3 border border-gray-200 dark:border-gray-700">
                                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label
                                                    class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    No. Lote/Viajero
                                                </label>
                                                <input type="text" wire:model="lots.{{ $index }}.number"
                                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="Ej: 001">
                                                @error("lots.{$index}.number")
                                                    <span
                                                        class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div>
                                                <label
                                                    class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Cantidad
                                                </label>
                                                <input type="number" wire:model="lots.{{ $index }}.quantity"
                                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="Ej: 100" min="1">
                                                @error("lots.{$index}.quantity")
                                                    <span
                                                        class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <button wire:click="removeLot({{ $index }})"
                                            wire:confirm="¿Eliminar este lote?"
                                            type="button"
                                            class="flex-shrink-0 p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                            title="Eliminar lote">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                                    </path>
                                </svg>
                                <p class="text-sm">No hay lotes. Agrega uno nuevo.</p>
                            </div>
                        @endif

                        <div class="mt-4">
                            <button wire:click="addLot"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                Agregar Lote
                            </button>
                        </div>
                    </div>

                    <div
                        class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row gap-3 sm:justify-end">
                        <button wire:click="closeLotModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancelar
                        </button>
                        <button wire:click="saveLots"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition-colors">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Estado de Departamentos --}}
    @if ($showDepartmentStatusModal)
        <div wire:key="modal-dept-status" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closeDepartmentStatusModal">
                </div>

                <div
                    class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full rounded-2xl shadow-2xl">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Estado de Departamentos
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Actualizar estado por
                                    departamento</p>
                            </div>
                            <button wire:click="closeDepartmentStatusModal"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-4 space-y-4">
                        @foreach (['materials' => 'Materiales', 'inspection' => 'Inspeccion', 'production' => 'Produccion'] as $deptKey => $deptLabel)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $deptLabel }}
                                </label>
                                <div class="grid grid-cols-4 gap-2">
                                    <button wire:click="updateDepartmentStatus('{{ $deptKey }}', 'rejected')"
                                        class="px-3 py-2 text-xs font-medium border transition-colors {{ $departmentStatuses[$deptKey] === 'rejected' ? 'border-red-500 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400' }}">
                                        Rechazado
                                    </button>
                                    <button wire:click="updateDepartmentStatus('{{ $deptKey }}', 'pending')"
                                        class="px-3 py-2 text-xs font-medium border transition-colors {{ $departmentStatuses[$deptKey] === 'pending' ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400' }}">
                                        Pendiente
                                    </button>
                                    <button wire:click="updateDepartmentStatus('{{ $deptKey }}', 'in_progress')"
                                        class="px-3 py-2 text-xs font-medium border transition-colors {{ $departmentStatuses[$deptKey] === 'in_progress' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400' }}">
                                        En Progreso
                                    </button>
                                    <button wire:click="updateDepartmentStatus('{{ $deptKey }}', 'approved')"
                                        class="px-3 py-2 text-xs font-medium border transition-colors {{ $departmentStatuses[$deptKey] === 'approved' ? 'border-green-500 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400' }}">
                                        Aprobado
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div
                        class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row gap-3 sm:justify-end">
                        <button wire:click="closeDepartmentStatusModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancelar
                        </button>
                        <button wire:click="saveDepartmentStatuses"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition-colors">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Status de Inspeccion por Lote --}}
    @if ($showInspectionModal && $selectedLot)
        <div wire:key="modal-inspection" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="inspection-modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closeInspectionModal"></div>

                {{-- Modal Container --}}
                <div
                    class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full rounded-2xl shadow-2xl">
                    {{-- Header --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 id="inspection-modal-title"
                                    class="text-lg font-semibold text-gray-900 dark:text-white">Status de Inspeccion -
                                    Lote</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    WO: {{ $selectedLot->workOrder->purchaseOrder->wo ?? 'N/A' }} |
                                    Lote: {{ $selectedLot->lot_number }}
                                </p>
                            </div>
                            <button wire:click="closeInspectionModal"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-6">
                        {{-- Informacion del Lote --}}
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Informacion del Lote
                            </h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Parte:</span>
                                    <span class="ml-2 text-gray-900 dark:text-white font-medium">
                                        {{ $selectedLot->workOrder->purchaseOrder->part->number ?? 'N/A' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Cantidad:</span>
                                    <span class="ml-2 text-gray-900 dark:text-white font-medium">
                                        {{ number_format($selectedLot->quantity) }} piezas
                                    </span>
                                </div>
                                @if ($selectedLot->workOrder->purchaseOrder->part->is_crimp ?? false)
                                    @if ($selectedLot->crimpLots->count() > 0)
                                        <div class="col-span-2">
                                            <span class="text-gray-500 dark:text-gray-400">Lotes de CRIMP:</span>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach ($selectedLot->crimpLots as $cl)
                                                    <span class="px-1.5 py-0.5 text-xs bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-300 rounded font-mono">{{ $cl->crimp_lot_number }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- Status de Materiales (Solo lectura) --}}
                        <div
                            class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg border border-green-200 dark:border-green-800">
                            <div class="flex items-center">
                                <div class="w-4 h-4 rounded-full bg-green-500 mr-3"></div>
                                <span class="text-sm font-medium text-green-800 dark:text-green-200">
                                    MAT. Liberado - Habilitado para inspeccion
                                </span>
                            </div>
                        </div>

                        {{-- Status de Inspeccion (Alpine.js para feedback visual inmediato) --}}
                        <div x-data="{ status: $wire.entangle('inspectionStatus') }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Status de Inspeccion
                            </label>
                            <div class="grid grid-cols-3 gap-3">
                                {{-- Pendiente --}}
                                <button type="button"
                                    x-on:click="status = 'pending'"
                                    :class="status === 'pending'
                                        ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/30 ring-2 ring-yellow-300 dark:ring-yellow-700 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-600 hover:border-yellow-300 dark:hover:border-yellow-600 hover:bg-yellow-50/50 dark:hover:bg-yellow-900/10'"
                                    class="flex flex-col items-center p-4 border-2 rounded-lg transition-all duration-200 cursor-pointer">
                                    <div
                                        :class="status === 'pending' ? 'ring-2 ring-yellow-300 ring-offset-2 dark:ring-offset-gray-800' : ''"
                                        class="w-8 h-8 rounded-full bg-yellow-400 mb-2"></div>
                                    <span
                                        :class="status === 'pending' ? 'text-yellow-700 dark:text-yellow-300' : 'text-gray-700 dark:text-gray-300'"
                                        class="text-sm font-medium">
                                        Pendiente
                                    </span>
                                </button>

                                {{-- Aprobado --}}
                                <button type="button"
                                    x-on:click="status = 'approved'"
                                    :class="status === 'approved'
                                        ? 'border-green-500 bg-green-50 dark:bg-green-900/30 ring-2 ring-green-300 dark:ring-green-700 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-600 hover:border-green-300 dark:hover:border-green-600 hover:bg-green-50/50 dark:hover:bg-green-900/10'"
                                    class="flex flex-col items-center p-4 border-2 rounded-lg transition-all duration-200 cursor-pointer">
                                    <div
                                        :class="status === 'approved' ? 'ring-2 ring-green-300 ring-offset-2 dark:ring-offset-gray-800' : ''"
                                        class="w-8 h-8 rounded-full bg-green-500 mb-2"></div>
                                    <span
                                        :class="status === 'approved' ? 'text-green-700 dark:text-green-300' : 'text-gray-700 dark:text-gray-300'"
                                        class="text-sm font-medium">
                                        Aprobado
                                    </span>
                                </button>

                                {{-- No Aprobado --}}
                                <button type="button"
                                    x-on:click="status = 'rejected'"
                                    :class="status === 'rejected'
                                        ? 'border-red-500 bg-red-50 dark:bg-red-900/30 ring-2 ring-red-300 dark:ring-red-700 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-600 hover:border-red-300 dark:hover:border-red-600 hover:bg-red-50/50 dark:hover:bg-red-900/10'"
                                    class="flex flex-col items-center p-4 border-2 rounded-lg transition-all duration-200 cursor-pointer">
                                    <div
                                        :class="status === 'rejected' ? 'ring-2 ring-red-300 ring-offset-2 dark:ring-offset-gray-800' : ''"
                                        class="w-8 h-8 rounded-full bg-red-500 mb-2"></div>
                                    <span
                                        :class="status === 'rejected' ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300'"
                                        class="text-sm font-medium">
                                        No Aprobado
                                    </span>
                                </button>
                            </div>

                            {{-- Texto descriptivo del status seleccionado --}}
                            <div class="mt-3 text-sm text-center py-2 px-3 rounded-md transition-all duration-200"
                                :class="{
                                    'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300': status === 'pending',
                                    'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300': status === 'approved',
                                    'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300': status === 'rejected'
                                }">
                                <span x-show="status === 'pending'">Lote pendiente de inspeccion</span>
                                <span x-show="status === 'approved'">Lote aprobado - Habilitado para empaque</span>
                                <span x-show="status === 'rejected'">Lote rechazado - Requiere accion correctiva</span>
                            </div>

                            {{-- Comentarios --}}
                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Comentarios de Inspeccion
                                    <span x-show="status === 'rejected'" class="text-red-500">*</span>
                                </label>
                                <textarea wire:model="inspectionComments" rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    :placeholder="status === 'rejected' ? 'Describa el motivo del rechazo...' : 'Observaciones adicionales (opcional)...'"
                                ></textarea>
                                <p x-show="status === 'rejected'" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                    * El motivo del rechazo es requerido
                                </p>
                                @error('inspectionComments')
                                    <span
                                        class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div
                        class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row gap-3 sm:justify-end">
                        <button wire:click="closeInspectionModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="saveInspectionStatus"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors cursor-pointer">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif


    {{-- Modal de Lotes de CRIMP (CRIMP — reemplaza al Kit) --}}
    @if ($showCrimpLotModal)
        <x-ui-modal title="Lotes de CRIMP" :subtitle="$crimpLotViajeroLabel.' · Cantidad del Viajero: '.number_format($crimpLotViajeroQty)"
            close="closeCrimpLotModal" maxWidth="5xl"
            bodyClass="px-6 py-4 max-h-[60vh] overflow-y-auto space-y-3">
                    {{-- Restador automático: Cantidad del viajero − suma de lotes (sin tope, solo informativo) --}}
                    @php
                        $asignado = collect($crimpLots)->sum(fn ($r) => (int) ($r['quantity'] ?? 0));
                        $restante = (int) $crimpLotViajeroQty - $asignado;
                    @endphp
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 p-3 text-center">
                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Cantidad del Viajero</div>
                            <div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ number_format($crimpLotViajeroQty) }}</div>
                        </div>
                        <div class="rounded-lg bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-700 p-3 text-center">
                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Asignado (lotes)</div>
                            <div class="text-xl font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($asignado) }}</div>
                        </div>
                        <div class="rounded-lg border p-3 text-center {{ $restante === 0 ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700' : ($restante < 0 ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700' : 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-700') }}">
                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Restante</div>
                            <div class="text-xl font-bold {{ $restante === 0 ? 'text-green-700 dark:text-green-300' : ($restante < 0 ? 'text-red-700 dark:text-red-300' : 'text-amber-700 dark:text-amber-300') }}">{{ number_format($restante) }}</div>
                        </div>
                    </div>
                    @if ($restante < 0)
                        <p class="text-xs text-red-600 dark:text-red-400">⚠ La suma de lotes sobrepasa la cantidad del viajero por {{ number_format(abs($restante)) }} pz.</p>
                    @elseif ($restante === 0 && $asignado > 0)
                        <p class="text-xs text-green-600 dark:text-green-400">✓ La suma de lotes coincide con la cantidad del viajero.</p>
                    @endif

                    @forelse ($crimpLots as $index => $cl)
                        <div wire:key="crimplot-{{ $index }}" class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="flex items-start text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 min-h-[2rem] leading-tight">No. Lote CRIMP</label>
                                    <input type="text" wire:model="crimpLots.{{ $index }}.crimp_lot_number" placeholder="Ej: CL-001"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                                    @error("crimpLots.{$index}.crimp_lot_number") <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="flex items-start text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 min-h-[2rem] leading-tight">Lote de fabricante&nbsp;<span class="text-gray-400">(opcional)</span></label>
                                    <input type="text" wire:model="crimpLots.{{ $index }}.lote_fabricante" placeholder="Ej: FAB-2024"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                                </div>
                                <div>
                                    <label class="flex items-start text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 min-h-[2rem] leading-tight">Cantidad</label>
                                    <input type="number" wire:model.live.debounce.400ms="crimpLots.{{ $index }}.quantity" min="1" placeholder="0"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                                    @error("crimpLots.{$index}.quantity") <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="flex items-start text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 min-h-[2rem] leading-tight">Comentarios&nbsp;<span class="text-gray-400">(opcional)</span></label>
                                    <input type="text" wire:model="crimpLots.{{ $index }}.comments" placeholder="Observaciones..."
                                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                                </div>
                            </div>
                            <button wire:click="removeCrimpLotRow({{ $index }})" class="mt-5 p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg" title="Eliminar lote de CRIMP">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    @empty
                        <p class="text-center text-sm text-gray-400 dark:text-gray-500 py-4">No hay lotes de CRIMP. Agrega uno nuevo.</p>
                    @endforelse
                    <button wire:click="addCrimpLotRow" class="w-full py-2.5 border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-cyan-400 hover:text-cyan-500 rounded-lg text-sm font-medium flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Agregar Lote de CRIMP
                    </button>
            <x-slot:footer>
                <span></span>
                <div class="flex items-center gap-2">
                    <button wire:click="closeCrimpLotModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</button>
                    <button wire:click="saveCrimpLots" class="px-4 py-2 text-sm font-medium text-white bg-cyan-600 hover:bg-cyan-700 rounded-lg">Guardar Lotes de CRIMP</button>
                </div>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- ===== PASO 7 · MODAL ENTREGA DE VIAJERO ===== --}}
    @if ($showViajeroModal)
        @php
            $vjLot = \App\Models\Lot::with(['crimpLots','workOrder.purchaseOrder.part','qualityWeighings','packagingPieceWeighings','packagingCrimpWeighings'])->find($viajeroModalLotId);
            $vjWO = $vjLot?->workOrder;
            $vjPart = $vjWO?->purchaseOrder?->part;
            $vjWoNum = $vjWO?->purchaseOrder?->wo ?? $vjWO?->wo_number ?? '—';
            $vjReceived = (bool) ($vjLot?->viajero_received);
            $vjPiezas = $vjLot ? $vjLot->getPackagedPiecesTotal() : 0;
            $vjCrimp = $vjLot ? $vjLot->getPackagedCrimpTotal() : 0;
            $vjPiezasSob = $vjLot ? $vjLot->getPackagedPiecesSurplus() : 0;
            $vjCrimpSob = $vjLot ? $vjLot->getPackagedCrimpSurplus() : 0;
            $vjDecLabel = ($vjLot && $vjLot->closure_decision) ? $vjLot->getPostQualityLifecycle()['decision']['label'] : 'Sin decisión todavía';
            $vjCrimpLots = $vjLot?->crimpLots ?? collect();
        @endphp
        <x-ui-modal badge="Paso 7" title="Entrega de Viajero" subtitle="Empaque confirma «Viajero recibido»"
            close="closeViajeroModal" maxWidth="2xl">
            <x-slot:context>
                <x-ui-modal.ctx label="Descripción" :value="$vjPart?->description ?? $vjPart?->number ?? '—'" />
                <x-ui-modal.ctx label="No. Order (WO + Viajero)" :value="$vjWoNum.' · '.($vjLot?->lot_number ?? '—')" />
                <x-ui-modal.ctx label="Lotes de CRIMP" :value="$vjCrimpLots->pluck('crimp_lot_number')->join(', ') ?: '—'" />
                <x-ui-modal.ctx label="Cantidad en viajero" :value="number_format($vjLot?->quantity ?? 0)" />
            </x-slot:context>

            {{-- Estado actual --}}
            <div class="rounded-lg p-4 border {{ $vjReceived ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700' : 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-700' }}">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 shrink-0 {{ $vjReceived ? 'text-green-600 dark:text-green-400' : 'text-amber-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if ($vjReceived)
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @endif
                    </svg>
                    <div>
                        <div class="text-sm font-semibold {{ $vjReceived ? 'text-green-800 dark:text-green-200' : 'text-amber-800 dark:text-amber-200' }}">
                            {{ $vjReceived ? 'Viajero recibido por Materiales' : 'Pendiente: Empaque debe entregar el viajero' }}
                        </div>
                        @if ($vjReceived && $vjLot?->viajero_received_at)
                            <div class="text-xs text-gray-500 dark:text-gray-400">Recibido el {{ \Carbon\Carbon::parse($vjLot->viajero_received_at)->format('d/m/Y H:i') }}{{ $vjLot->viajeroReceivedByUser?->name ? ' · por '.$vjLot->viajeroReceivedByUser->name : '' }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Resumen del empaque --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Resumen del empaque</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-center">
                        <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas «manguitas»</div>
                        <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format($vjPiezas) }}</div>
                    </div>
                    <div class="rounded-lg bg-cyan-50 dark:bg-cyan-900/20 p-3 text-center">
                        <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas CRIMP</div>
                        <div class="text-lg font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($vjCrimp) }}</div>
                    </div>
                    <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 text-center">
                        <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Sobr. piezas</div>
                        <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($vjPiezasSob) }}</div>
                    </div>
                    <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 text-center">
                        <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Sobr. CRIMP</div>
                        <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($vjCrimpSob) }}</div>
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    <span class="text-gray-500 dark:text-gray-400">Decisión de Materiales:</span> <strong>{{ $vjDecLabel }}</strong>
                </div>
            </div>

            <x-slot:footer>
                <span class="text-xs text-gray-500 dark:text-gray-400">Paso 7 · luego Paso 8 (regresar sobrantes)</span>
                <div class="flex items-center gap-2">
                    <button wire:click="closeViajeroModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cerrar</button>
                    @if ($vjReceived)
                        <button wire:click="revertViajeroReceived({{ $viajeroModalLotId }})"
                            wire:confirm="¿Revertir la entrega? El viajero quedará como NO recibido."
                            class="px-4 py-2 text-sm font-semibold text-yellow-700 dark:text-yellow-300 bg-white dark:bg-gray-800 border-2 border-yellow-400 dark:border-yellow-600 rounded-lg hover:bg-yellow-50 dark:hover:bg-yellow-900/20 inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Revertir entrega
                        </button>
                    @else
                        <button wire:click="markViajeroReceived({{ $viajeroModalLotId }})"
                            class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Marcar viajero como recibido
                        </button>
                    @endif
                </div>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- ===== PASO 5 · MODAL DE CONFIRMACIÓN DE EMPAQUE (CRIMP) ===== --}}
    @if ($showConfirmModal)
        @php
            $cfLot = \App\Models\Lot::with(['crimpLots','workOrder.purchaseOrder.part','qualityWeighings','packagingPieceWeighings','packagingCrimpWeighings'])->find($confirmLotId);
            $cfWO    = $cfLot?->workOrder;
            $cfPart  = $cfWO?->purchaseOrder?->part;
            $cfWoNum = $cfWO?->purchaseOrder?->wo ?? $cfWO?->wo_number ?? '—';
            $cfCrimpLots = $cfLot?->crimpLots ?? collect();
            $cfSel   = $cfCrimpLots->firstWhere('id', $confirmCrimpLotId);
            $cfPW = $cfLot ? $cfLot->packagingPieceWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
            $cfCW = $cfLot ? $cfLot->packagingCrimpWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
            $cfPiecesTotal = (int) $cfPW->sum('quantity');
            $cfCrimpTotal  = (int) $cfCW->sum('quantity');
            $cfPiecesSurplus = $cfLot ? $cfLot->getPackagedPiecesSurplus() : 0;
            $cfCrimpSurplus  = $cfLot ? $cfLot->getPackagedCrimpSurplus() : 0;
            $cfOtherLots = $cfCrimpLots->where('id', '!=', $confirmCrimpLotId);
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-start sm:items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-900/70" wire:click="closeConfirmModal"></div>
                <div class="relative z-10 w-full max-w-4xl bg-white dark:bg-gray-800 rounded-2xl shadow-2xl my-8">

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <span class="inline-block px-2.5 py-0.5 text-xs font-bold bg-amber-300 text-amber-900 rounded-full">Paso 5</span>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1.5">Modal de confirmación</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Se despliega sobre la pantalla de Empaque</p>
                        </div>
                        <button wire:click="closeConfirmModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Tira de contexto --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700">
                        <div class="bg-white dark:bg-gray-800 px-4 py-2.5">
                            <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">Descripción</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $cfPart?->description ?? $cfPart?->number ?? '—' }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-2.5">
                            <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">No. Order (WO + Viajero)</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $cfWoNum }} · {{ $cfLot?->lot_number }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-2.5">
                            <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">No. en etiquetas (WO + Lote)</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $cfWoNum }} · {{ $cfSel?->crimp_lot_number ?? '—' }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-2.5">
                            <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500">Cantidad en viajero</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ number_format($cfLot?->quantity ?? 0) }}</div>
                        </div>
                    </div>

                    <div class="px-6 py-5 space-y-6 max-h-[65vh] overflow-y-auto">
                        @if ($cfCrimpLots->isEmpty())
                            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg text-sm text-yellow-800 dark:text-yellow-300">
                                Este viajero no tiene lotes de CRIMP. Captúralos primero en <strong>Materiales</strong>.
                            </div>
                        @else
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {{-- Paso 1: Selecciona lote de CRIMP --}}
                            <section>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-bold">1</span>
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Selecciona lote de CRIMP</h4>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">De los lotes asignados al viajero</p>
                                <div class="space-y-2">
                                    @foreach ($cfCrimpLots as $cl)
                                        <button type="button" wire:click="$set('confirmCrimpLotId', {{ $cl->id }})"
                                            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg border-2 text-left transition-colors
                                                {{ $confirmCrimpLotId == $cl->id ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/40' }}">
                                            <span class="text-indigo-600 dark:text-indigo-300">{{ $confirmCrimpLotId == $cl->id ? '●' : '○' }}</span>
                                            <span class="flex-1">
                                                <span class="block text-sm font-mono font-semibold text-gray-800 dark:text-gray-100">Lote CRIMP {{ $cl->crimp_lot_number }}</span>
                                                @if ($cl->lote_fabricante)
                                                    <span class="block text-[11px] text-gray-400">Fab: {{ $cl->lote_fabricante }}</span>
                                                @endif
                                            </span>
                                            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ number_format($cl->quantity) }} pz</span>
                                        </button>
                                    @endforeach
                                </div>
                                @if ($cfOtherLots->isNotEmpty())
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                                        <strong>División del lote:</strong> este contiene {{ number_format($cfSel?->quantity ?? 0) }}
                                        y existe{{ $cfOtherLots->count() > 1 ? 'n otros lotes' : ' otro lote' }} por
                                        {{ $cfOtherLots->map(fn($l) => number_format($l->quantity))->join(', ') }}.
                                    </p>
                                @endif
                            </section>

                            {{-- Paso 2: Captura pesadas --}}
                            <section>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-bold">2</span>
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Captura pesadas</h4>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Piezas «manguitas» / Piezas CRIMP</p>

                                @error('confirmCrimpLotId') <p class="text-xs text-red-600 dark:text-red-400 mb-2">{{ $message }}</p> @enderror

                                {{-- Tabla piezas «manguitas» --}}
                                <div class="mb-4 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <div class="px-3 py-1.5 bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold text-gray-600 dark:text-gray-300">Piezas «manguitas»</div>
                                    <table class="w-full text-xs">
                                        <thead class="text-gray-400 dark:text-gray-500">
                                            <tr><th class="px-3 py-1 text-left font-medium">Peso (kg)</th><th class="px-3 py-1 text-right font-medium">Piezas</th><th class="px-2 py-1"></th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @forelse ($cfPW as $w)
                                                <tr wire:key="cfpw-{{ $w->id }}">
                                                    <td class="px-3 py-1 text-gray-600 dark:text-gray-400">{{ $w->weight !== null ? number_format($w->weight, 3) : '—' }}</td>
                                                    <td class="px-3 py-1 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($w->quantity) }}</td>
                                                    <td class="px-2 py-1 text-center">
                                                        <button wire:click="deleteConfirmPieceWeighing({{ $w->id }})" class="text-red-400 hover:text-red-600">✕</button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="px-3 py-2 text-center text-gray-400 italic">Sin pesadas</td></tr>
                                            @endforelse
                                            <tr class="bg-gray-50/60 dark:bg-gray-900/20">
                                                <td class="px-3 py-1.5">
                                                    <input type="number" step="0.001" min="0" wire:model="cPieceWeight" placeholder="kg"
                                                        class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <input type="number" min="1" wire:model="cPieceQty" placeholder="pzs"
                                                        class="w-full px-2 py-1 text-xs text-right border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button wire:click="addConfirmPieceWeighing" class="text-indigo-600 hover:text-indigo-800 font-bold">＋</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-gray-50 dark:bg-gray-900/30">
                                            <tr><td class="px-3 py-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">Total</td><td class="px-3 py-1.5 text-right text-xs font-bold text-green-700 dark:text-green-400">{{ number_format($cfPiecesTotal) }}</td><td></td></tr>
                                        </tfoot>
                                    </table>
                                    @error('cPieceQty') <p class="text-xs text-red-600 dark:text-red-400 px-3 py-1">{{ $message }}</p> @enderror
                                </div>

                                {{-- Tabla piezas CRIMP --}}
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <div class="px-3 py-1.5 bg-cyan-50 dark:bg-cyan-900/20 text-xs font-semibold text-cyan-700 dark:text-cyan-300">Piezas CRIMP <span class="font-normal text-gray-400">(objetivo: {{ number_format($cfSel?->quantity ?? 0) }})</span></div>
                                    <table class="w-full text-xs">
                                        <thead class="text-gray-400 dark:text-gray-500">
                                            <tr><th class="px-3 py-1 text-left font-medium">Peso (kg)</th><th class="px-3 py-1 text-right font-medium">Piezas</th><th class="px-2 py-1"></th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @forelse ($cfCW as $w)
                                                <tr wire:key="cfcw-{{ $w->id }}">
                                                    <td class="px-3 py-1 text-gray-600 dark:text-gray-400">{{ $w->weight !== null ? number_format($w->weight, 3) : '—' }}</td>
                                                    <td class="px-3 py-1 text-right font-semibold text-cyan-700 dark:text-cyan-400">{{ number_format($w->quantity) }}</td>
                                                    <td class="px-2 py-1 text-center">
                                                        <button wire:click="deleteConfirmCrimpWeighing({{ $w->id }})" class="text-red-400 hover:text-red-600">✕</button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="px-3 py-2 text-center text-gray-400 italic">Sin pesadas</td></tr>
                                            @endforelse
                                            <tr class="bg-gray-50/60 dark:bg-gray-900/20">
                                                <td class="px-3 py-1.5">
                                                    <input type="number" step="0.001" min="0" wire:model="cCrimpWeight" placeholder="kg"
                                                        class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <input type="number" min="1" wire:model="cCrimpQty" placeholder="pzs"
                                                        class="w-full px-2 py-1 text-xs text-right border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button wire:click="addConfirmCrimpWeighing" class="text-cyan-600 hover:text-cyan-800 font-bold">＋</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-gray-50 dark:bg-gray-900/30">
                                            <tr><td class="px-3 py-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">Total</td><td class="px-3 py-1.5 text-right text-xs font-bold text-cyan-700 dark:text-cyan-400">{{ number_format($cfCrimpTotal) }}</td><td></td></tr>
                                        </tfoot>
                                    </table>
                                    @error('cCrimpQty') <p class="text-xs text-red-600 dark:text-red-400 px-3 py-1">{{ $message }}</p> @enderror
                                </div>
                            </section>
                        </div>

                        {{-- Paso 3: Confirma cantidades --}}
                        <section class="border-t border-gray-200 dark:border-gray-700 pt-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-bold">3</span>
                                <h4 class="font-semibold text-gray-800 dark:text-gray-100">Confirma cantidades</h4>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-center">
                                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas «manguitas»</div>
                                    <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format($cfPiecesTotal) }}</div>
                                </div>
                                <div class="rounded-lg bg-cyan-50 dark:bg-cyan-900/20 p-3 text-center">
                                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas CRIMP</div>
                                    <div class="text-lg font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($cfCrimpTotal) }}</div>
                                </div>
                                <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 text-center">
                                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">CRIMP sobrante</div>
                                    <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($cfCrimpSurplus) }}</div>
                                </div>
                                <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 p-3 text-center">
                                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Piezas sobrantes</div>
                                    <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($cfPiecesSurplus) }}</div>
                                </div>
                            </div>
                            <button wire:click="confirmPackaging"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg transition-colors
                                    {{ $confirmDone ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 cursor-default' : 'bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 hover:bg-gray-700' }}">
                                @if ($confirmDone)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Cantidades confirmadas
                                @else
                                    Confirmar cantidades
                                @endif
                            </button>
                        </section>

                        {{-- Empaque Terminado (al confirmar) --}}
                        @if ($confirmDone)
                            <section class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                        <span class="text-gray-400">→</span> Resumen generado — Empaque Terminado
                                    </h4>
                                    <span class="text-[11px] text-gray-400">Este documento ya no se descarga a PDF</span>
                                </div>
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700/50 text-sm font-bold text-gray-700 dark:text-gray-200">Empaque Terminado</div>
                                    <dl class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                        @php
                                            $cfRows = [
                                                ['Destinatarios', 'Empaque + Materiales'],
                                                ['Descripción', $cfPart?->description ?? $cfPart?->number ?? '—'],
                                                ['No. Order (WO + Viajero)', $cfWoNum.' + '.($cfLot?->lot_number ?? '—')],
                                                ['Número de orden en etiquetas (WO + Lote de CRIMP)', $cfWoNum.' + '.($cfSel?->crimp_lot_number ?? '—')],
                                                ['Cantidad en el viajero', number_format($cfLot?->quantity ?? 0)],
                                                ['División del lote', $cfOtherLots->isNotEmpty()
                                                    ? 'Este lote contiene '.number_format($cfSel?->quantity ?? 0).' y existe'.($cfOtherLots->count() > 1 ? 'n otros lotes' : ' otro lote').' por '.$cfOtherLots->map(fn($l) => number_format($l->quantity))->join(', ')
                                                    : 'Lote único'],
                                                ['Cantidad completa de piezas (manguitas)', number_format($cfPiecesTotal)],
                                                ['Cantidad completa de CRIMP', number_format($cfCrimpTotal)],
                                                ['CRIMP sobrante', number_format($cfCrimpSurplus)],
                                                ['Piezas / manguitas sobrantes', number_format($cfPiecesSurplus)],
                                                ['Empacado por', auth()->user()?->name ?? '—'],
                                                ['Fecha', now()->format('Y-m-d')],
                                                ['Comentarios', $confirmComments ?: '—'],
                                            ];
                                        @endphp
                                        @foreach ($cfRows as [$k, $v])
                                            <div class="flex gap-4 px-4 py-1.5">
                                                <dt class="w-1/2 text-gray-500 dark:text-gray-400">{{ $k }}</dt>
                                                <dd class="w-1/2 font-medium text-gray-800 dark:text-gray-100 text-right">{{ $v }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">No. de etiquetas <span class="text-gray-400">(opcional)</span></label>
                                        <input type="number" min="0" wire:model="confirmLabelCount" placeholder="—"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        @error('confirmLabelCount') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Comentarios <span class="text-gray-400">(opcional)</span></label>
                                        <input type="text" wire:model="confirmComments" placeholder="Observaciones para Empaque/Materiales..."
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    </div>
                                </div>
                            </section>
                        @endif
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 rounded-b-2xl">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Forma parte del flujo principal · continúa al Paso 6</span>
                        <div class="flex items-center gap-2">
                            <button wire:click="closeConfirmModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cerrar</button>
                            @if ($confirmDone)
                                <button wire:click="confirmAndNotifyFromModal"
                                    class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">
                                    {{ $cfLot?->packaging_notified_at ? 'Reenviar notificación' : 'Confirmar y notificar' }}
                                </button>
                            @endif
                            <button @if (!$confirmDone) disabled @endif
                                wire:click="goToDecisionFromConfirm"
                                class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors
                                    {{ $confirmDone ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}">
                                Continuar a Paso 6 ▸
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Material (No CRIMP) --}}
    @if ($showMaterialModal && $selectedLotForMaterial)
        <div wire:key="modal-material" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="material-modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closeMaterialModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal --}}
                <div
                    class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white" id="material-modal-title">Material del {{ ($selectedLotForMaterial->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote' }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                WO: {{ $selectedLotForMaterial->workOrder->purchaseOrder->wo ?? 'N/A' }} ·
                                {{ ($selectedLotForMaterial->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote' }}: {{ $selectedLotForMaterial->lot_number }}
                            </p>
                        </div>
                        <button wire:click="closeMaterialModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-6">
                        {{-- Informacion del Lote --}}
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Información del Lote</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Parte:</span>
                                    <span class="ml-2 text-gray-900 dark:text-white font-medium">
                                        {{ $selectedLotForMaterial->workOrder->purchaseOrder->part->number ?? 'N/A' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Cantidad:</span>
                                    <span class="ml-2 text-gray-900 dark:text-white font-medium">
                                        {{ number_format($selectedLotForMaterial->quantity) }} piezas
                                    </span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-gray-500 dark:text-gray-400">Lote:</span>
                                    <span class="ml-2 text-indigo-600 dark:text-indigo-400 font-medium">
                                        {{ $selectedLotForMaterial->lot_number }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Status selector (Alpine.js) --}}
                        <div x-data="{ matSt: $wire.entangle('materialStatus') }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Status del Material
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                {{-- Aprobado --}}
                                <button type="button"
                                    x-on:click="matSt = 'released'"
                                    :class="matSt === 'released'
                                        ? 'border-green-500 bg-green-50 dark:bg-green-900/30 ring-2 ring-green-300 dark:ring-green-700 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-600 hover:border-green-300 dark:hover:border-green-600 hover:bg-green-50/50 dark:hover:bg-green-900/10'"
                                    class="flex flex-col items-center p-4 border-2 rounded-lg transition-all duration-200 cursor-pointer">
                                    <div
                                        :class="matSt === 'released' ? 'ring-2 ring-green-300 ring-offset-2 dark:ring-offset-gray-800' : ''"
                                        class="w-8 h-8 rounded-full bg-green-500 mb-2 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span
                                        :class="matSt === 'released' ? 'text-green-700 dark:text-green-300' : 'text-gray-700 dark:text-gray-300'"
                                        class="text-sm font-medium">
                                        Aprobado
                                    </span>
                                </button>

                                {{-- Rechazado --}}
                                <button type="button"
                                    x-on:click="matSt = 'rejected'"
                                    :class="matSt === 'rejected'
                                        ? 'border-red-500 bg-red-50 dark:bg-red-900/30 ring-2 ring-red-300 dark:ring-red-700 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-600 hover:border-red-300 dark:hover:border-red-600 hover:bg-red-50/50 dark:hover:bg-red-900/10'"
                                    class="flex flex-col items-center p-4 border-2 rounded-lg transition-all duration-200 cursor-pointer">
                                    <div
                                        :class="matSt === 'rejected' ? 'ring-2 ring-red-300 ring-offset-2 dark:ring-offset-gray-800' : ''"
                                        class="w-8 h-8 rounded-full bg-red-500 mb-2 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </div>
                                    <span
                                        :class="matSt === 'rejected' ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300'"
                                        class="text-sm font-medium">
                                        Rechazado
                                    </span>
                                </button>
                            </div>

                            {{-- Texto descriptivo --}}
                            <div class="mt-3 text-sm text-center py-2 px-3 rounded-md transition-all duration-200"
                                :class="{
                                    'bg-gray-50 dark:bg-gray-700/20 text-gray-500 dark:text-gray-400': matSt === 'pending',
                                    'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300': matSt === 'released',
                                    'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300': matSt === 'rejected'
                                }">
                                <span x-show="matSt === 'pending'">Material pendiente de revision</span>
                                <span x-show="matSt === 'released'">Material aprobado - Listo para produccion</span>
                                <span x-show="matSt === 'rejected'">Material rechazado - Requiere correccion</span>
                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div
                        class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row gap-3 sm:justify-end">
                        <button wire:click="closeMaterialModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="saveMaterialStatus"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg transition-colors cursor-pointer">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Empaque por Lote — 4 Fases --}}
    @if ($showPackagingModal && $selectedLotForPackaging)
        <div wire:key="modal-packaging" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="packaging-modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closePackagingModal"></div>

                {{-- Modal Container --}}
                <div
                    class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full rounded-2xl shadow-2xl">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 id="packaging-modal-title" class="text-xl font-bold text-gray-900 dark:text-white">Empaque — Lote {{ $selectedLotForPackaging->lot_number }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                WO: {{ $selectedLotForPackaging->workOrder->purchaseOrder->wo ?? 'N/A' }} ·
                                Parte: {{ $selectedLotForPackaging->workOrder->purchaseOrder->part->number ?? 'N/A' }}
                            </p>
                        </div>
                        <button wire:click="closePackagingModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 max-h-[75vh] overflow-y-auto space-y-6">

                        {{-- Resumen de piezas --}}
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                            <div class="bg-indigo-50 dark:bg-indigo-900/20 p-3 rounded-lg text-center">
                                <div class="text-xs text-indigo-600 dark:text-indigo-400 mb-1">Producción</div>
                                <div class="text-lg font-bold text-indigo-700 dark:text-indigo-300">{{ number_format($pkgProductionPieces) }}</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg text-center">
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Calidad</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($pkgAvailablePieces) }}</div>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg text-center">
                                <div class="text-xs text-green-600 dark:text-green-400 mb-1">Ya Empacadas</div>
                                <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format($pkgAlreadyPacked) }}</div>
                            </div>
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg text-center">
                                <div class="text-xs text-yellow-600 dark:text-yellow-400 mb-1">Pendientes</div>
                                <div class="text-lg font-bold text-yellow-700 dark:text-yellow-300">{{ number_format($pkgPendingPieces) }}</div>
                            </div>
                            <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg text-center">
                                <div class="text-xs text-orange-600 dark:text-orange-400 mb-1">Sobrantes Total</div>
                                <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($pkgTotalSurplus) }}</div>
                            </div>
                        </div>

                        {{-- Acumulado de ciclos de completado previos --}}
                        @if ($pkgPreviousCyclesPacked > 0)
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg p-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <span class="text-xs font-semibold text-emerald-800 dark:text-emerald-200">Acumulado de ciclos de completado</span>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div class="bg-white/60 dark:bg-gray-800/40 rounded p-2">
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400">Ciclos anteriores</div>
                                        <div class="text-base font-bold text-gray-700 dark:text-gray-200">{{ number_format($pkgPreviousCyclesPacked) }}</div>
                                    </div>
                                    <div class="bg-white/60 dark:bg-gray-800/40 rounded p-2">
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400">Empacado este ciclo</div>
                                        <div class="text-base font-bold text-gray-700 dark:text-gray-200">{{ number_format($pkgAlreadyPacked) }}</div>
                                    </div>
                                    <div class="bg-emerald-100 dark:bg-emerald-800/40 rounded p-2">
                                        <div class="text-[10px] text-emerald-700 dark:text-emerald-300">Total acumulado</div>
                                        <div class="text-base font-bold text-emerald-700 dark:text-emerald-200">{{ number_format($pkgPreviousCyclesPacked + $pkgAlreadyPacked) }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ================================================ --}}
                        {{-- FASE 1: Registrar Empaque --}}
                        {{-- ================================================ --}}
                        @if ($pkgAvailablePieces > 0)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg">
                                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 rounded-t-lg">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-orange-500 text-white text-xs flex items-center justify-center font-bold">1</span>
                                        Registrar Empaque
                                    </h4>
                                </div>
                                <div class="p-4 space-y-4">
                                    {{-- Tabla de registros previos --}}
                                    @if (count($pkgRecordsList) > 0)
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-xs">
                                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                                    <tr>
                                                        <th class="px-2 py-2 text-left text-gray-600 dark:text-gray-400">Empacadas</th>
                                                        <th class="px-2 py-2 text-left text-gray-600 dark:text-gray-400">Sobrantes</th>
                                                        <th class="px-2 py-2 text-left text-gray-600 dark:text-gray-400">Ajuste</th>
                                                        <th class="px-2 py-2 text-left text-gray-600 dark:text-gray-400">Fecha</th>
                                                        <th class="px-2 py-2 text-left text-gray-600 dark:text-gray-400">Usuario</th>
                                                        <th class="px-2 py-2 text-center text-gray-600 dark:text-gray-400">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                    @foreach ($pkgRecordsList as $pr)
                                                        <tr class="{{ $pkgAdjustRecordId === $pr['id'] ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                                                            <td class="px-2 py-2 font-medium text-gray-900 dark:text-white">{{ number_format($pr['packed_pieces']) }}</td>
                                                            <td class="px-2 py-2 {{ $pr['surplus_pieces'] > 0 ? 'text-orange-600 dark:text-orange-400 font-medium' : 'text-gray-500' }}">{{ number_format($pr['surplus_pieces']) }}</td>
                                                            <td class="px-2 py-2">
                                                                @if ($pr['adjusted_surplus'] !== null)
                                                                    <span class="text-blue-600 dark:text-blue-400 font-medium">{{ number_format($pr['adjusted_surplus']) }}</span>
                                                                    @if ($pr['adjustment_reason'])
                                                                        <span class="text-gray-400 ml-1" title="{{ $pr['adjustment_reason'] }}">ℹ</span>
                                                                    @endif
                                                                @else
                                                                    <span class="text-gray-400">-</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-2 py-2 text-gray-600 dark:text-gray-400">{{ $pr['packed_at'] }}</td>
                                                            <td class="px-2 py-2 text-gray-600 dark:text-gray-400">{{ $pr['packed_by'] }}</td>
                                                            <td class="px-2 py-2 text-center">
                                                                <div class="flex items-center justify-center gap-1">
                                                                    @if (!$pkgViajeroReceived)
                                                                        <button wire:click="editPackagingRecord({{ $pr['id'] }})"
                                                                            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 cursor-pointer" title="Editar">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                                        </button>
                                                                        <button wire:click="deletePackagingRecord({{ $pr['id'] }})"
                                                                            wire:confirm="¿Eliminar este registro de empaque?"
                                                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 cursor-pointer" title="Eliminar">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                        </button>
                                                                    @endif
                                                                    @if ($pr['surplus_pieces'] > 0 && !$pkgViajeroReceived)
                                                                        <button wire:click="startAdjustSurplus({{ $pr['id'] }})"
                                                                            class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300 cursor-pointer" title="Ajustar sobrantes">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    {{-- Formulario de ajuste de sobrantes (Fase 2, inline) --}}
                                    @if ($pkgAdjustRecordId)
                                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 space-y-3">
                                            <h5 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 flex items-center gap-2">
                                                <span class="w-5 h-5 rounded-full bg-yellow-500 text-white text-xs flex items-center justify-center font-bold">2</span>
                                                Ajustar Sobrantes
                                            </h5>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sobrantes Ajustados</label>
                                                    <input type="number" wire:model="pkgAdjustedSurplus" min="0"
                                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded focus:ring-1 focus:ring-yellow-500">
                                                    @error('pkgAdjustedSurplus')
                                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Motivo del Ajuste *</label>
                                                    <input type="text" wire:model="pkgAdjustmentReason"
                                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded focus:ring-1 focus:ring-yellow-500"
                                                        placeholder="Ej: Reconteo manual">
                                                    @error('pkgAdjustmentReason')
                                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button wire:click="cancelAdjustSurplus"
                                                    class="px-3 py-1.5 text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer">
                                                    Cancelar
                                                </button>
                                                <button wire:click="saveAdjustSurplus"
                                                    class="px-3 py-1.5 text-xs bg-yellow-600 hover:bg-yellow-700 text-white rounded cursor-pointer">
                                                    Guardar Ajuste
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Formulario de nuevo empaque --}}
                                    @if (($pkgPendingPieces > 0 || $pkgEditingId) && !$pkgViajeroReceived)
                                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                            <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                                {{ $pkgEditingId ? 'Editar Registro' : 'Nuevo Registro de Empaque' }}
                                            </h5>
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Piezas Empacadas *</label>
                                                    <input type="number" wire:model.live="pkgPackedPieces" min="0" max="{{ $pkgPendingPieces }}"
                                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded focus:ring-1 focus:ring-orange-500">
                                                    @error('pkgPackedPieces')
                                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sobrantes</label>
                                                    <input type="number" wire:model.live="pkgSurplusPieces" min="0"
                                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded focus:ring-1 focus:ring-orange-500">
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5 block">Auto-calculado, editable si desea</span>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha/Hora *</label>
                                                    <input type="datetime-local" wire:model="pkgPackedAt"
                                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded focus:ring-1 focus:ring-orange-500">
                                                    @error('pkgPackedAt')
                                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios</label>
                                                <textarea wire:model="pkgComments" rows="2"
                                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded focus:ring-1 focus:ring-orange-500"
                                                    placeholder="Observaciones (opcional)..."></textarea>
                                            </div>
                                            <div class="mt-3 flex justify-end gap-2">
                                                @if ($pkgEditingId)
                                                    <button wire:click="cancelEditPackaging"
                                                        class="px-3 py-1.5 text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer">
                                                        Cancelar Edición
                                                    </button>
                                                @endif
                                                <button wire:click="savePackaging"
                                                    class="px-4 py-1.5 text-xs bg-orange-600 hover:bg-orange-700 text-white font-medium rounded cursor-pointer">
                                                    {{ $pkgEditingId ? 'Actualizar' : 'Registrar Empaque' }}
                                                </button>
                                            </div>
                                        </div>
                                    @elseif ($pkgAvailablePieces > 0 && $pkgPendingPieces <= 0 && !$pkgViajeroReceived)
                                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded p-3 text-center">
                                            <span class="text-sm text-green-700 dark:text-green-300 font-medium">Todas las piezas disponibles han sido empacadas.</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6 text-center">
                                <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No hay piezas disponibles para empacar. Calidad debe verificar piezas primero.</p>
                            </div>
                        @endif

                        {{-- ================================================ --}}
                        {{-- FASE 3: Recibí Viajero --}}
                        {{-- ================================================ --}}
                        @if ($pkgAlreadyPacked > 0 && !$pkgViajeroReceived)
                            <div class="border border-blue-200 dark:border-blue-700 rounded-lg">
                                <div class="px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-200 dark:border-blue-700 rounded-t-lg">
                                    <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200 flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-bold">3</span>
                                        Recibir Lote
                                    </h4>
                                </div>
                                <div class="p-4">
                                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded p-4 mb-4">
                                            <div class="grid grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <span class="text-gray-500 dark:text-gray-400">Total Empacadas:</span>
                                                    <span class="ml-2 font-bold text-green-600 dark:text-green-400">{{ number_format($pkgAlreadyPacked) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 dark:text-gray-400">Sobrantes Finales:</span>
                                                    <span class="ml-2 font-bold text-orange-600 dark:text-orange-400">{{ number_format($pkgTotalSurplus) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <button wire:click="receiveViajero"
                                            wire:confirm="¿Confirma que recibió el lote? Esta acción no se puede deshacer."
                                            class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Recibí Lote
                                        </button>
                                </div>
                            </div>
                        @elseif ($pkgViajeroReceived)
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm font-medium text-blue-800 dark:text-blue-200">Lote recibido</span>
                                    </div>
                                    <button wire:click="openDecisionFromPackaging"
                                        class="px-3 py-1.5 text-xs font-medium text-white bg-cyan-600 hover:bg-cyan-700 rounded-lg transition-colors cursor-pointer">
                                        Ir a Decisión
                                    </button>
                                </div>
                                <button wire:click="reopenPackaging"
                                    wire:confirm="¿Reabrir el empaque? El lote quedará como no recibido y podrás registrar más piezas."
                                    class="w-full px-3 py-2 text-xs font-medium text-yellow-700 dark:text-yellow-300 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-300 dark:border-yellow-600 hover:bg-yellow-100 dark:hover:bg-yellow-900/40 rounded-lg transition-colors cursor-pointer flex items-center justify-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Reabrir Empaque
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-end">
                        <button wire:click="closePackagingModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ================================================================ --}}
    {{-- MODAL: Decisión Control de Materiales --}}
    {{-- ================================================================ --}}
    @if ($showDecisionModal && $selectedLotForDecision)
        @php
            $dPart = $selectedLotForDecision->workOrder->purchaseOrder->part ?? null;
            $dWoNum = $selectedLotForDecision->workOrder->purchaseOrder->wo ?? $selectedLotForDecision->workOrder->wo_number ?? '—';
            $dOrderLabel = 'No. Order (WO + '.($decIsCrimp ? 'Viajero' : 'Lote').')';
            $dFirstCrimp = $decIsCrimp ? ($selectedLotForDecision->crimpLots->first()?->crimp_lot_number ?? '—') : null;
            $dSub = $decIsCrimp ? '4 opciones según sobrantes / faltantes del lote de CRIMP' : 'Lote '.$selectedLotForDecision->lot_number;
            if (($selectedLotForDecision->completion_count ?? 0) > 0) { $dSub .= ' · Completado '.$selectedLotForDecision->completion_count; }
        @endphp
        <x-ui-modal
            :badge="$decIsCrimp ? 'Paso 6' : null"
            :title="$decIsCrimp ? 'Resumen + toma de decisión' : 'Decisión – Control de Materiales'"
            :subtitle="$dSub"
            close="closeDecisionModal"
            maxWidth="3xl"
            bodyClass="px-6 py-5 space-y-5 max-h-[72vh] overflow-y-auto">
            <x-slot:context>
                <x-ui-modal.ctx label="Descripción" :value="$dPart?->description ?? $dPart?->number ?? '—'" />
                <x-ui-modal.ctx :label="$dOrderLabel" :value="$dWoNum.' · '.$selectedLotForDecision->lot_number" />
                @if ($decIsCrimp)
                    <x-ui-modal.ctx label="Lote de CRIMP" :value="$dFirstCrimp" />
                @else
                    <x-ui-modal.ctx label="Parte" :value="$dPart?->number ?? '—'" />
                @endif
                <x-ui-modal.ctx :label="$decIsCrimp ? 'Cantidad en viajero' : 'Cantidad en lote'" :value="number_format($decLotTotal)" />
            </x-slot:context>

                        {{-- Resumen --}}
                        @if ($decIsCrimp)
                            {{-- Paso 6 (diagrama 4): resumen del lote de CRIMP --}}
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-900 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-bold">∑</span>
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Resumen del lote de CRIMP</h4>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Total · Empacadas · Sobrantes · Faltantes</p>
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] uppercase text-gray-500 dark:text-gray-400">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Concepto</th>
                                                <th class="px-3 py-2 text-right">Total</th>
                                                <th class="px-3 py-2 text-right">Empacadas</th>
                                                <th class="px-3 py-2 text-right">Sobrantes</th>
                                                <th class="px-3 py-2 text-right">Faltantes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Piezas «manguitas»</td>
                                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($decLotTotal) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($decPacked) }}</td>
                                                <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($decSurplus) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold {{ $decMissing > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">{{ number_format($decMissing) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Piezas CRIMP</td>
                                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($decCrimpTotal) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold text-cyan-700 dark:text-cyan-400">{{ number_format($decCrimpPacked) }}</td>
                                                <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($decCrimpSurplus) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold {{ $decCrimpMissing > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">{{ number_format($decCrimpMissing) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Lote</div>
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($decLotTotal) }}</div>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg text-center">
                                    <div class="text-xs text-green-600 dark:text-green-400 mb-1">Empacadas</div>
                                    <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format($decPacked) }}</div>
                                </div>
                                <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg text-center">
                                    <div class="text-xs text-orange-600 dark:text-orange-400 mb-1">Sobrantes</div>
                                    <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format($decSurplus) }}</div>
                                </div>
                                <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-lg text-center">
                                    <div class="text-xs text-red-600 dark:text-red-400 mb-1">Faltantes</div>
                                    <div class="text-lg font-bold text-red-700 dark:text-red-300">{{ number_format($decMissing) }}</div>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                                Faltantes = Total Lote - Empacadas - Sobrantes
                            </p>
                        @endif

                        {{-- Acumulado de todos los ciclos — reconciliación contra el lote original --}}
                        @if ($decPreviousCyclesPacked > 0)
                            @php
                                $accPacked  = $decPreviousCyclesPacked + $decPacked;
                                $accSurplus = $decPreviousCyclesSurplus + $decSurplus;
                                $accTotal   = $accPacked + $accSurplus + $decMissing;
                                $accMatches = $accTotal === (int) $decOriginalQuantity;
                            @endphp
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-emerald-800 dark:text-emerald-200 flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        Acumulado de todos los ciclos
                                    </span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Lote original: <strong class="text-gray-700 dark:text-gray-200">{{ number_format($decOriginalQuantity) }}</strong></span>
                                </div>
                                <div class="grid grid-cols-4 gap-2 text-center">
                                    <div class="bg-white/60 dark:bg-gray-800/40 rounded p-2">
                                        <div class="text-[10px] text-green-600 dark:text-green-400">Empacado</div>
                                        <div class="text-base font-bold text-green-700 dark:text-green-300">{{ number_format($accPacked) }}</div>
                                    </div>
                                    <div class="bg-white/60 dark:bg-gray-800/40 rounded p-2">
                                        <div class="text-[10px] text-orange-600 dark:text-orange-400">Sobrantes</div>
                                        <div class="text-base font-bold text-orange-700 dark:text-orange-300">{{ number_format($accSurplus) }}</div>
                                    </div>
                                    <div class="bg-white/60 dark:bg-gray-800/40 rounded p-2">
                                        <div class="text-[10px] text-red-600 dark:text-red-400">Faltantes</div>
                                        <div class="text-base font-bold text-red-700 dark:text-red-300">{{ number_format($decMissing) }}</div>
                                    </div>
                                    <div class="bg-emerald-100 dark:bg-emerald-800/40 rounded p-2">
                                        <div class="text-[10px] text-emerald-700 dark:text-emerald-300">Total</div>
                                        <div class="text-base font-bold text-emerald-700 dark:text-emerald-200">{{ number_format($accTotal) }}</div>
                                    </div>
                                </div>
                                <p class="text-[10px] text-center mt-2 {{ $accMatches ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                    Empacado + Sobrantes + Faltantes = {{ number_format($accTotal) }}
                                    @if ($accMatches)
                                        &check; coincide con el lote original
                                    @else
                                        &#9888; no coincide con el lote original ({{ number_format($decOriginalQuantity) }})
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Decision options (only if no closure decision yet) --}}
                        @if (!$decClosureDecision)
                            @if ($decIsCrimp)
                                {{-- CRIMP · Paso 6 (diagrama 4 / wireframe): D1 Cerrar · D2 Completar (a/b/c) · D3 Nuevo lote --}}
                                <div x-data="{ sel: null, sub: null }">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-6 h-6 flex items-center justify-center rounded-full bg-amber-500 text-white text-xs font-bold">?</span>
                                        <h4 class="font-semibold text-gray-800 dark:text-gray-100">¿Qué decisión se toma?</h4>
                                    </div>

                                    {{-- 3 tarjetas de decisión --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <button type="button" x-on:click="sel='D1'; sub=null"
                                            :class="sel==='D1' ? 'border-rose-500 bg-rose-100 dark:bg-rose-900/40 ring-2 ring-rose-300 dark:ring-rose-700' : 'border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-900/15 hover:bg-rose-100 dark:hover:bg-rose-900/30'"
                                            class="p-3.5 border-2 rounded-xl text-left transition-all">
                                            <div class="text-sm font-bold text-rose-800 dark:text-rose-300">D1 · Cerrar lote</div>
                                            <div class="text-xs text-rose-600/80 dark:text-rose-400/80 mt-1">Así como está, sin crear un nuevo lote.</div>
                                        </button>
                                        <button type="button" x-on:click="sel='D2'"
                                            :class="sel==='D2' ? 'border-sky-500 bg-sky-100 dark:bg-sky-900/40 ring-2 ring-sky-300 dark:ring-sky-700' : 'border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/15 hover:bg-sky-100 dark:hover:bg-sky-900/30'"
                                            class="p-3.5 border-2 rounded-xl text-left transition-all">
                                            <div class="text-sm font-bold text-sky-800 dark:text-sky-300">D2 · Completar</div>
                                            <div class="text-xs text-sky-600/80 dark:text-sky-400/80 mt-1">Completar faltantes según el caso · 3 sub-decisiones.</div>
                                        </button>
                                        <button type="button" x-on:click="sel='D3'; sub=null"
                                            :class="sel==='D3' ? 'border-emerald-500 bg-emerald-100 dark:bg-emerald-900/40 ring-2 ring-emerald-300 dark:ring-emerald-700' : 'border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/15 hover:bg-emerald-100 dark:hover:bg-emerald-900/30'"
                                            class="p-3.5 border-2 rounded-xl text-left transition-all">
                                            <div class="text-sm font-bold text-emerald-800 dark:text-emerald-300">D3 · Nuevo lote</div>
                                            <div class="text-xs text-emerald-600/80 dark:text-emerald-400/80 mt-1">Crear o reiniciar un nuevo lote de CRIMP.</div>
                                        </button>
                                    </div>

                                    {{-- Detalle D1 --}}
                                    <div x-show="sel==='D1'" style="display:none" class="mt-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50/50 dark:bg-gray-900/20">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">D1 · Cerrar lote — así como está</div>
                                        <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                            <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Cerrar lote sin crear nuevo lote</span>
                                            <span class="text-gray-400">→</span>
                                            <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 1 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                            <span class="text-gray-400">→</span>
                                            <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Continúa Paso 7 · Entrega de viajero</span>
                                        </div>
                                        <button wire:click="decisionCloseAsIs" wire:confirm="¿Cerrar el viajero así como está, sin nuevo lote?"
                                            class="px-4 py-2 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 rounded-lg">Confirmar D1 y continuar a Paso 7 ▸</button>
                                    </div>

                                    {{-- Detalle D2 --}}
                                    <div x-show="sel==='D2'" style="display:none" class="mt-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50/50 dark:bg-gray-900/20">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-1">D2 · Completar — elige una sub-decisión</div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Completar CRIMP = piezas sobrantes − CRIMP sobrante = <strong>{{ number_format($decCompletarCrimp) }}</strong></p>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                            <button type="button" x-on:click="sub='a'" :class="sub==='a' ? 'border-cyan-500 bg-cyan-50 dark:bg-cyan-900/20' : 'border-gray-200 dark:border-gray-600'" class="p-2 border-2 rounded text-left transition-colors">
                                                <div class="text-xs font-bold text-gray-800 dark:text-gray-100">D2a · Solo completar CRIMP</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">Decisión de Materiales</div>
                                            </button>
                                            <button type="button" x-on:click="sub='b'" :class="sub==='b' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-600'" class="p-2 border-2 rounded text-left transition-colors">
                                                <div class="text-xs font-bold text-gray-800 dark:text-gray-100">D2b · Solo completar piezas</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">Decisión de Materiales</div>
                                            </button>
                                            <button type="button" x-on:click="sub='c'" :class="sub==='c' ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20' : 'border-gray-200 dark:border-gray-600'" class="p-2 border-2 rounded text-left transition-colors">
                                                <div class="text-xs font-bold text-gray-800 dark:text-gray-100">D2c · Piezas y CRIMP</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">Decisión de Materiales</div>
                                            </button>
                                        </div>

                                        {{-- D2a --}}
                                        <div x-show="sub==='a'" style="display:none" class="mt-3">
                                            <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Completar CRIMP = piezas sobrantes − CRIMP sobrante = {{ number_format($decCompletarCrimp) }}</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 2 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7</span>
                                            </div>
                                            <button wire:click="decisionCompleteCrimp" class="px-4 py-2 text-sm font-semibold text-white bg-cyan-600 hover:bg-cyan-700 rounded-lg">Confirmar D2a y continuar a Paso 7 ▸</button>
                                        </div>
                                        {{-- D2b --}}
                                        <div x-show="sub==='b'" style="display:none" class="mt-3">
                                            <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Materiales envía {{ number_format($decSurplus) }} pz a empaque</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Empaque completa piezas pendientes</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 3 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7</span>
                                            </div>
                                            <button wire:click="decisionCompletePieces" class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">Confirmar D2b y continuar a Paso 7 ▸</button>
                                        </div>
                                        {{-- D2c --}}
                                        <div x-show="sub==='c'" style="display:none" class="mt-3">
                                            <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Completar CRIMP = {{ number_format($decCompletarCrimp) }}</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Completar lote de CRIMP + capturar piezas CRIMP ({{ number_format($decSurplus) }} pz)</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 5 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                                <span class="text-gray-400">→</span>
                                                <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7</span>
                                            </div>
                                            <button wire:click="decisionCompleteBoth" class="px-4 py-2 text-sm font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-lg">Confirmar D2c y continuar a Paso 7 ▸</button>
                                        </div>
                                    </div>

                                    {{-- Detalle D3 --}}
                                    <div x-show="sel==='D3'" style="display:none" class="mt-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50/50 dark:bg-gray-900/20">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-1">D3 · Nuevo lote de CRIMP</div>
                                        <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">¿Reiniciar el mismo lote o crear uno nuevo? → <strong>Nuevo lote de CRIMP</strong></p>
                                        <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                                            <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">Sobrantes piezas (manguitas) = Nuevo lote = {{ number_format(intdiv(max(0, (int) $decSurplus), 100) * 100) }}</span>
                                            <span class="text-gray-400">→</span>
                                            <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión 6 <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                            <span class="text-gray-400">→</span>
                                            <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7</span>
                                        </div>
                                        <div class="text-[11px] text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded px-2 py-1 mb-3">Nota: redondear hacia abajo en múltiplos de 100.</div>
                                        <button wire:click="decisionNewLot" class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">Confirmar D3 — crear nuevo lote ▸</button>
                                    </div>
                                </div>
                            @else
                            @if ($decSurplus > 0 || $decMissing > 0)
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    {{-- Opción 1: Completar Lote (reinicia el mismo lote) --}}
                                    @if ($decMissing > 0)
                                        <button wire:click="decisionCompleteLot"
                                            wire:confirm="¿Completar lote con {{ number_format($decMissing) }} piezas faltantes? El lote se reiniciará para reprocesar esas piezas (inspección, producción, calidad, empaque)."
                                            class="p-4 border-2 border-indigo-200 dark:border-indigo-700 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors cursor-pointer text-center">
                                            <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-indigo-100 dark:bg-indigo-800 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                            </div>
                                            <div class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">Completar Lote</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Reiniciar con {{ number_format($decMissing) }} pz faltantes</div>
                                        </button>
                                    @endif

                                    {{-- Opción 2: Nuevo Lote --}}
                                    <button wire:click="decisionNewLot"
                                        class="p-4 border-2 border-green-200 dark:border-green-700 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors cursor-pointer text-center">
                                        <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                        </div>
                                        <div class="text-sm font-semibold text-green-700 dark:text-green-300">Nuevo Lote</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ number_format(max(0, $decLotTotal - $decPacked)) }} pz en lote nuevo</div>
                                    </button>

                                    {{-- Opción 3: Cerrar Lote como está --}}
                                    <button wire:click="decisionCloseAsIs"
                                        wire:confirm="¿Cerrar el lote aceptando {{ number_format($decMissing) }} piezas faltantes?"
                                        class="p-4 border-2 border-orange-200 dark:border-orange-700 rounded-lg hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors cursor-pointer text-center">
                                        <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-orange-100 dark:bg-orange-800 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                        <div class="text-sm font-semibold text-orange-700 dark:text-orange-300">Cerrar Lote</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Aceptar {{ number_format($decMissing) }} pz faltantes</div>
                                    </button>
                                </div>
                            @else
                                {{-- Sin faltantes: cerrar directamente --}}
                                <button wire:click="decisionCloseAsIs"
                                    wire:confirm="¿Cerrar el lote? No hay piezas faltantes."
                                    class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Cerrar Lote (Completo)
                                </button>
                            @endif
                            @endif
                        @else
                            {{-- Decisión ya tomada --}}
                            @php
                                $closureLabel = match ($decClosureDecision) {
                                    'complete_lot' => 'Completar Lote',
                                    'new_lot' => 'Nuevo Lote Creado',
                                    'close_as_is' => 'Lote Cerrado (faltantes aceptados)',
                                    'complete_crimp' => 'Completar CRIMP',
                                    'complete_pieces' => 'Completar piezas',
                                    'complete_both' => 'Completar piezas y CRIMP',
                                    default => $decClosureDecision,
                                };
                                $closureColor = match ($decClosureDecision) {
                                    'complete_lot' => 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-700 text-indigo-800 dark:text-indigo-200',
                                    'new_lot' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700 text-green-800 dark:text-green-200',
                                    'close_as_is' => 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-700 text-orange-800 dark:text-orange-200',
                                    default => 'bg-gray-50 dark:bg-gray-700/50 border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200',
                                };
                            @endphp
                            <div class="border rounded-lg p-3 {{ $closureColor }}">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm font-medium">Decisión: {{ $closureLabel }}</span>
                                </div>
                            </div>

                            @php $isCompletion = in_array($decClosureDecision, ['complete_crimp','complete_pieces','complete_both']); @endphp
                            @if ($isCompletion)
                                {{-- D2a/b/c — qué falta completar (diagrama 4) y continúa al Paso 7 --}}
                                @php
                                    $compCrimp  = (int) ($selectedLotForDecision->complete_crimp_qty ?? 0);
                                    $compPieces = (int) ($selectedLotForDecision->complete_pieces_qty ?? 0);
                                @endphp
                                <div class="bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-700 rounded-lg p-4 space-y-2">
                                    <h5 class="text-sm font-semibold text-sky-800 dark:text-sky-200">Por completar — decisión de Materiales</h5>
                                    @if ($decClosureDecision !== 'complete_pieces')
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-300">Completar CRIMP <span class="text-[11px] text-gray-400">(piezas sobrantes − CRIMP sobrante)</span></span>
                                            <span class="font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($compCrimp) }}</span>
                                        </div>
                                    @endif
                                    @if ($decClosureDecision !== 'complete_crimp')
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-300">Completar piezas «manguitas» <span class="text-[11px] text-gray-400">(Materiales envía a Empaque)</span></span>
                                            <span class="font-bold text-green-700 dark:text-green-300">{{ number_format($compPieces) }}</span>
                                        </div>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-2 text-xs pt-1">
                                        <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">{{ $decClosureDecision === 'complete_pieces' ? 'Materiales envía → Empaque completa piezas' : 'Materiales completa lo pendiente' }}</span>
                                        <span class="text-gray-400">→</span>
                                        <span class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded inline-flex items-center gap-1">Reporte de Decisión <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 rounded text-[10px]">por definir</span></span>
                                        <span class="text-gray-400">→</span>
                                        <span class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 rounded">Paso 7 · Entrega de viajero</span>
                                    </div>
                                    @if (in_array($decClosureDecision, ['complete_pieces','complete_both']))
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 pt-1">Empaque captura las piezas faltantes con el botón <strong>“Empacar / Confirmar”</strong> del viajero (Paso 5).</p>
                                    @endif
                                </div>
                                @if (!$decSurplusReceived)
                                    <button wire:click="confirmSurplusReceived" wire:confirm="¿Marcar la completación como realizada y continuar al Paso 7?"
                                        class="w-full px-4 py-3 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Confirmar completado · Continuar a Paso 7
                                    </button>
                                @else
                                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3">
                                        <span class="text-sm font-medium text-green-800 dark:text-green-200">Completado. Continúa al Paso 7 (entrega de viajero).</span>
                                    </div>
                                @endif
                            @else
                            {{-- Estado de recepción de material --}}
                            @if ($decSurplus > 0)
                                @if (!$decSurplusDelivered)
                                    {{-- Paso 1: Empaque aún no ha entregado el sobrante --}}
                                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-amber-800 dark:text-amber-200">Pendiente: Empaque debe entregar {{ number_format($decSurplus) }} pz sobrantes</span>
                                        </div>
                                    </div>
                                @elseif (!$decSurplusReceived)
                                    {{-- Paso 2: Entregado, pendiente recepción --}}
                                    <div class="border border-red-200 dark:border-red-700 rounded-lg p-4">
                                        <h5 class="text-sm font-semibold text-red-800 dark:text-red-200 mb-3">Pendiente: Recepción de Material Sobrante</h5>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                            Empaque entregó <strong class="text-orange-600">{{ number_format($decSurplus) }}</strong> piezas sobrantes. Confirmar recepción.
                                        </p>
                                        <button wire:click="confirmSurplusReceived"
                                            wire:confirm="¿Confirma que se recibieron {{ number_format($decSurplus) }} piezas sobrantes?"
                                            class="w-full px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                            Material Recibido
                                        </button>
                                    </div>
                                @else
                                    {{-- Paso 3: Todo completado --}}
                                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-sm font-medium text-green-800 dark:text-green-200">Material sobrante recibido. Lote completado.</span>
                                        </div>
                                    </div>
                                @endif
                            @else
                                {{-- Sin sobrantes: confirmar recepción de material --}}
                                @if (!$decSurplusReceived)
                                    <div class="border border-red-200 dark:border-red-700 rounded-lg p-4">
                                        <h5 class="text-sm font-semibold text-red-800 dark:text-red-200 mb-3">Pendiente: Confirmación de Recepción</h5>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                            Todas las piezas fueron empacadas. Sin sobrantes. Confirmar recepción de material.
                                        </p>
                                        <button wire:click="confirmSurplusReceived"
                                            wire:confirm="¿Confirma la recepción de material del lote?"
                                            class="w-full px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                            Material Recibido
                                        </button>
                                    </div>
                                @else
                                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-sm font-medium text-green-800 dark:text-green-200">Material recibido. Lote completado.</span>
                                        </div>
                                    </div>
                                @endif
                            @endif
                            @endif

                            {{-- Reabrir Lote (not available for completed lots since the cycle is irreversible) --}}
                            @if ($decClosureDecision !== 'complete_lot')
                                <button wire:click="reopenLot"
                                    wire:confirm="¿Desea reabrir este lote y anular la decisión tomada?"
                                    class="w-full px-4 py-3 border-2 border-yellow-400 dark:border-yellow-600 text-yellow-700 dark:text-yellow-300 font-semibold rounded-lg hover:bg-yellow-50 dark:hover:bg-yellow-900/20 transition-colors flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Reabrir Lote
                                </button>
                            @else
                                <div class="bg-gray-100 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg p-3 text-center">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Este lote fue completado y reiniciado. No se puede reabrir la decisión anterior.</span>
                                </div>
                            @endif

                        @endif

            <x-slot:footer>
                <span class="text-xs text-gray-500 dark:text-gray-400">@if($decIsCrimp)Todos los caminos continúan al Paso 7 (entrega) → Paso 8 (sobrantes)@endif</span>
                <button wire:click="closeDecisionModal"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cerrar
                </button>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- ================================================================ --}}
    {{-- MODAL: Crear Lote (from Decision) --}}
    {{-- ================================================================ --}}
    @if ($showCreateLotFormModal && $selectedLotForDecision)
        <div wire:key="modal-create-lot" class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="create-lot-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/70 dark:bg-gray-900/80 transition-opacity" wire:click="closeCreateLotFormModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 id="create-lot-modal-title" class="text-xl font-bold text-gray-900 dark:text-white">Crear {{ $decIsCrimp ? 'Nuevo Viajero' : 'Nuevo Lote' }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ $createLotType === 'complete' ? 'Completar lote con piezas faltantes' : 'Cerrar lote actual y crear nuevo' }}
                            </p>
                        </div>
                        <button wire:click="closeCreateLotFormModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5 space-y-4">
                        {{-- Info banner --}}
                        @if ($decIsCrimp)
                            <div class="bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-700 rounded-lg p-3">
                                <p class="text-xs text-cyan-700 dark:text-cyan-300">
                                    <strong>Parte con CRIMP:</strong> Se creará un nuevo viajero. Sus <strong>lotes de CRIMP</strong> se capturan en <strong>Materiales</strong> (ya no se usa Kit).
                                </p>
                            </div>
                        @endif

                        {{-- Lot Name --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre / Número de Lote</label>
                            <input type="text" wire:model="createLotName"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('createLotName')
                                <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Quantity --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cantidad (piezas)</label>
                            <input type="number" wire:model="createLotQuantity" min="1"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('createLotQuantity')
                                <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                            @enderror
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Piezas faltantes del Lote: {{ number_format($decMissing) }}
                            </p>
                        </div>

                        {{-- Summary --}}
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-sm">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Se creará:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    Lote "{{ $createLotName }}" — {{ number_format($createLotQuantity) }} pz
                                </span>
                            </div>
                            @if ($decIsCrimp)
                                <div class="flex justify-between text-gray-600 dark:text-gray-400 mt-1">
                                    <span>Lotes de CRIMP:</span>
                                    <span class="font-medium text-cyan-700 dark:text-cyan-300">Se capturan en Materiales</span>
                                </div>
                            @endif
                            @if ($createLotType === 'new_lot')
                                <div class="flex justify-between text-gray-600 dark:text-gray-400 mt-1">
                                    <span>Lote actual:</span>
                                    <span class="font-medium text-orange-600">Se cerrará</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-end gap-3">
                        <button wire:click="closeCreateLotFormModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmCreateLot"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors cursor-pointer">
                            Crear {{ $decIsCrimp ? 'Nuevo Viajero' : 'Lote' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ================================================================ --}}
    {{-- MODAL: Entregar Material Sobrante --}}
    {{-- ================================================================ --}}
    @if ($showDeliverMaterialModal && $selectedLotForDelivery)
        <div wire:key="modal-deliver-material" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closeDeliverMaterialModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full rounded-2xl shadow-2xl">

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Entregar Material</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ ($selectedLotForDelivery->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote' }} {{ $selectedLotForDelivery->lot_number }} ·
                                WO: {{ $selectedLotForDelivery->workOrder->purchaseOrder->wo ?? 'N/A' }} ·
                                Parte: {{ $selectedLotForDelivery->workOrder->purchaseOrder->part->number ?? 'N/A' }}
                            </p>
                        </div>
                        <button wire:click="closeDeliverMaterialModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5 space-y-4">
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4 text-center">
                            <p class="text-sm text-amber-800 dark:text-amber-200 mb-1">Material sobrante por entregar</p>
                            <p class="text-3xl font-bold text-amber-700 dark:text-amber-300">{{ number_format($deliverSurplusAmount) }} <span class="text-sm font-normal">piezas</span></p>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-sm space-y-1">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Lote:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $selectedLotForDelivery->lot_number }}</span>
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Cantidad del lote:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ number_format($selectedLotForDelivery->quantity) }} pz</span>
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Empacadas:</span>
                                <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($selectedLotForDelivery->getPackagingPackedPieces()) }} pz</span>
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Sobrantes:</span>
                                <span class="font-medium text-orange-600 dark:text-orange-400">{{ number_format($deliverSurplusAmount) }} pz</span>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                            Al confirmar, se registrará que el material sobrante fue entregado a Control de Materiales.
                        </p>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-end gap-3">
                        <button wire:click="closeDeliverMaterialModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmDeliverMaterial"
                            wire:confirm="¿Confirma la entrega de {{ number_format($deliverSurplusAmount) }} piezas sobrantes del Lote {{ $selectedLotForDelivery->lot_number }}?"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg transition-colors cursor-pointer flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Confirmar Entrega
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Pesada (Calidad) por Lote --}}
    @if ($showQualityModal && $selectedLotForQuality)
        <div wire:key="modal-quality" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="quality-modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closeQualityModal"></div>

                {{-- Modal Container --}}
                <div
                    class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full rounded-2xl shadow-2xl">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 id="quality-modal-title" class="text-xl font-bold text-gray-900 dark:text-white">Pesada de Calidad — {{ ($selectedLotForQuality->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote' }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                WO: {{ $selectedLotForQuality->workOrder->purchaseOrder->wo ?? 'N/A' }} ·
                                {{ ($selectedLotForQuality->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote' }}: {{ $selectedLotForQuality->lot_number }}
                            </p>
                        </div>
                        <button wire:click="closeQualityModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-5 max-h-[70vh] overflow-y-auto">
                        {{-- Info del Lote y Produccion --}}
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Parte:</span>
                                    <span class="ml-2 text-gray-900 dark:text-white font-medium">
                                        {{ $selectedLotForQuality->workOrder->purchaseOrder->part->number ?? 'N/A' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Lote:</span>
                                    <span class="ml-2 text-indigo-600 dark:text-indigo-400 font-medium">
                                        {{ $selectedLotForQuality->lot_number }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Resumen de Produccion --}}
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 p-4 rounded-lg">
                            <h4 class="text-sm font-semibold text-indigo-700 dark:text-indigo-300 mb-3">Resumen de Produccion</h4>
                            <div class="grid grid-cols-3 gap-3 text-sm">
                                <div class="text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Pz Pesadas Prod.</div>
                                    <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ number_format($qualProductionGoodPieces) }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Ya Verificadas</div>
                                    <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($qualAlreadyWeighed) }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Pendientes</div>
                                    <div class="text-lg font-bold {{ $qualRemainingPieces > 0 ? 'text-teal-600 dark:text-teal-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ number_format($qualRemainingPieces) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Historial de pesadas de calidad --}}
                        @if (count($qualWeighingsList) > 0)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Historial de Pesadas de Calidad</h4>
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Fecha</th>
                                                <th class="px-3 py-2 text-right text-green-600 dark:text-green-400">Aprobadas</th>
                                                <th class="px-3 py-2 text-right text-red-600 dark:text-red-400">Rechazadas</th>
                                                <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Por</th>
                                                <th class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach ($qualWeighingsList as $qw)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $qw['weighed_at'] }}</td>
                                                    <td class="px-3 py-2 text-right font-medium text-green-600 dark:text-green-400">{{ number_format($qw['good_pieces']) }}</td>
                                                    <td class="px-3 py-2 text-right font-medium text-red-600 dark:text-red-400">
                                                        {{ number_format($qw['bad_pieces']) }}
                                                        @if ($qw['bad_pieces'] > 0)
                                                            <span class="ml-1 text-gray-400">(descarte)</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $qw['weighed_by'] }}</td>
                                                    <td class="px-3 py-2 text-center">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <button wire:click="editQualityWeighing({{ $qw['id'] }})"
                                                                class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300" title="Editar">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                                </svg>
                                                            </button>
                                                            <button wire:click="deleteQualityWeighing({{ $qw['id'] }})"
                                                                wire:confirm="¿Eliminar esta pesada de calidad?"
                                                                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300" title="Eliminar">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Formulario de nueva/editar pesada --}}
                        @if ($qualRemainingPieces > 0 || $qualEditingId)
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $qualEditingId ? 'Editar Pesada de Calidad' : 'Nueva Pesada de Calidad' }}
                                    </h4>
                                    @if ($qualEditingId)
                                        <button wire:click="cancelEditQuality"
                                            class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 underline">
                                            Cancelar edicion
                                        </button>
                                    @endif
                                </div>

                                {{-- Pendiente de verificar --}}
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pendiente de Verificar</label>
                                    <div class="w-full px-3 py-2 border rounded-lg font-bold text-lg text-center
                                        border-teal-300 dark:border-teal-600 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300">
                                        {{ number_format($qualRemainingPieces) }} piezas
                                    </div>
                                </div>

                                {{-- Piezas aprobadas y rechazadas --}}
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Piezas Aprobadas *</label>
                                        <input wire:model="qualGoodPieces" type="number" min="0"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                            placeholder="0">
                                        @error('qualGoodPieces')
                                            <span class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Piezas Rechazadas *</label>
                                        <input wire:model="qualBadPieces" type="number" min="0"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                            placeholder="0">
                                        @error('qualBadPieces')
                                            <span class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Info de descarte --}}
                                @if ($qualBadPieces > 0)
                                    <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 rounded-lg">
                                        <div class="flex items-center text-sm text-red-700 dark:text-red-300">
                                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            Las {{ number_format($qualBadPieces) }} piezas rechazadas seran descartadas.
                                        </div>
                                    </div>
                                @endif

                                {{-- Fecha y hora --}}
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha y Hora *</label>
                                    <input wire:model="qualWeighedAt" type="datetime-local"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                    @error('qualWeighedAt')
                                        <span class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>

                                {{-- Comentarios --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios</label>
                                    <textarea wire:model="qualComments" rows="2"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                                        placeholder="Observaciones (opcional)..."></textarea>
                                </div>
                            </div>
                        @else
                            {{-- Lote completamente verificado --}}
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 rounded-lg text-center">
                                <svg class="w-8 h-8 text-green-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm font-medium text-green-700 dark:text-green-300">
                                    Todas las piezas de produccion han sido verificadas por Calidad.
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row gap-3 sm:justify-end">
                        <button wire:click="closeQualityModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            Cerrar
                        </button>
                        @if ($qualRemainingPieces > 0 || $qualEditingId)
                            <button wire:click="saveQuality"
                                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg transition-colors cursor-pointer">
                                {{ $qualEditingId ? 'Actualizar Pesada' : 'Registrar Pesada' }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Pesada (Producción) por Lote --}}
    @if ($showProductionModal && $selectedLotForProduction)
        <div wire:key="modal-production" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="production-modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-900/70 transition-opacity" wire:click="closeProductionModal"></div>

                {{-- Modal Container --}}
                <div
                    class="relative z-10 inline-block align-bottom bg-white dark:bg-gray-800 text-left overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full rounded-2xl shadow-2xl">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 id="production-modal-title" class="text-xl font-bold text-gray-900 dark:text-white">Nueva Pesada — Producción</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                WO: {{ $selectedLotForProduction->workOrder->purchaseOrder->wo ?? 'N/A' }} ·
                                {{ ($selectedLotForProduction->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote' }}: {{ $selectedLotForProduction->lot_number }}
                            </p>
                        </div>
                        <button wire:click="closeProductionModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-5">
                        {{-- Info del Lote --}}
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Parte:</span>
                                    <span class="ml-2 text-gray-900 dark:text-white font-medium">
                                        {{ $selectedLotForProduction->workOrder->purchaseOrder->part->number ?? 'N/A' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Lote:</span>
                                    <span class="ml-2 text-indigo-600 dark:text-indigo-400 font-medium">
                                        {{ $selectedLotForProduction->lot_number }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Cantidad del lote y pendiente de pesar --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cantidad del Lote</label>
                                <div class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700/50 text-gray-900 dark:text-white text-sm rounded-lg font-semibold">
                                    {{ number_format($prodQuantity) }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ya Pesadas</label>
                                <div class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700/50 text-gray-900 dark:text-white text-sm rounded-lg font-semibold">
                                    {{ number_format($prodAlreadyWeighed) }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $prodRemainingPieces < 0 ? 'Sobrante de Producción' : 'Pendiente de Pesar' }}
                            </label>
                            <div class="w-full px-3 py-2 border rounded-lg font-bold text-lg text-center
                                @if ($prodRemainingPieces > 0)
                                    border-indigo-300 dark:border-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300
                                @elseif ($prodRemainingPieces < 0)
                                    border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300
                                @else
                                    border-green-300 dark:border-green-600 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300
                                @endif">
                                @if ($prodRemainingPieces < 0)
                                    +{{ number_format(abs($prodRemainingPieces)) }} piezas sobrantes
                                @elseif ($prodRemainingPieces == 0)
                                    {{ number_format($prodRemainingPieces) }} piezas
                                    <span class="text-xs font-normal ml-2">(Lote completamente pesado)</span>
                                @else
                                    {{ number_format($prodRemainingPieces) }} piezas
                                @endif
                            </div>
                        </div>

                        {{-- Piezas pesadas --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Piezas Pesadas *</label>
                            <input wire:model="prodWeighedPieces" type="number" min="0"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="0">
                            @error('prodWeighedPieces')
                                <span class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Fecha y hora --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha y Hora de Pesada *</label>
                            <input wire:model="prodWeighedAt" type="datetime-local"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('prodWeighedAt')
                                <span class="text-xs text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Comentarios --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentarios</label>
                            <textarea wire:model="prodComments" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Observaciones (opcional)..."></textarea>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row gap-3 sm:justify-end">
                        <button wire:click="closeProductionModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="saveProduction"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors cursor-pointer">
                            Registrar Pesada
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

@script
    <script>
        $wire.on('refresh-display', () => {
            console.log('Display refreshed');
        });

        $wire.on('lotCompleted', () => {
            console.log('New lot completed!');
        });
    </script>
@endscript
