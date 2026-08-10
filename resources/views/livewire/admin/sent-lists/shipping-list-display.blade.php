{{-- `visible`: el refresco se detiene cuando la pestaña está en segundo plano.
     Una pantalla de piso abierta todo el día hacía 2,880 recargas completas
     diarias por dispositivo, estuviera alguien mirando o no. --}}
<div class="shipping-screen min-h-screen bg-slate-50 dark:bg-slate-950" wire:poll.visible.30s="refreshDisplay">
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
    <div class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
        <div class="mx-auto max-w-full px-4 py-5 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300">Control de producción</div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white sm:text-3xl">Lista de envío</h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">Consulta el avance de cada viajero y selecciona la acción pendiente en su semáforo.</p>
                    </div>
                    <div class="inline-flex w-fit items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <svg wire:loading class="w-4 h-4 animate-spin" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                        <span class="size-2 rounded-full bg-emerald-500" wire:loading.remove></span>
                        <span>Actualización automática · {{ $refreshInterval }} s</span>
                    </div>
                </div>

                {{-- Filtros --}}
                <div class="grid grid-cols-1 gap-3 sm:max-w-2xl sm:grid-cols-[minmax(0,1fr)_14rem]">
                    {{-- Buscador --}}
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Buscar orden</span>
                        <span class="relative block">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>
                        <input type="text" wire:model.live.debounce.300ms="searchTerm"
                            placeholder="Buscar por WO #, # parte o descripción..."
                            class="w-full border border-slate-300 bg-white pl-10 pr-10 text-sm text-slate-900 shadow-sm focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                        @if ($searchTerm)
                            <button wire:click="$set('searchTerm', '')"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                title="Limpiar búsqueda">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                        </span>
                    </label>

                    {{-- Filtro por tipo de estación --}}
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Área de trabajo</span>
                        <select wire:model.live="filterWorkstation" data-no-ts
                            class="w-full border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                            <option value="">Todos los tipos</option>
                            <option value="Mesa">Mesa</option>
                            <option value="Máquina">Máquina</option>
                            <option value="Semi-Automática">Semi-Automática</option>
                            <option value="Sin Clasificar">Sin Clasificar</option>
                        </select>
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- Banner de vista enfocada (WO o SentList) --}}
    @if ($focusedWorkOrderId || $focusedSentListId)
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div class="flex items-center justify-between gap-3 border-l-4 border-sky-600 bg-sky-50 px-4 py-3 dark:bg-sky-950/30">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-sky-700 dark:text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span class="text-sm text-sky-900 dark:text-sky-100">
                        @if ($focusedWorkOrderId)
                            Vista enfocada — mostrando sólo el WO <strong class="font-semibold">{{ $focusedWorkOrderLabel }}</strong>
                        @else
                            Vista enfocada — mostrando sólo la Lista de envío <strong class="font-semibold">{{ $focusedSentListLabel }}</strong>
                        @endif
                    </span>
                </div>
                <a href="{{ route('admin.sent-lists.display') }}" wire:navigate
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-white dark:bg-gray-800 text-sky-700 dark:text-sky-300 rounded hover:bg-sky-100 dark:hover:bg-sky-900/40 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Ver todo
                </a>
            </div>
        </div>
    @endif

    {{-- ==========================================================================
         Panel "Acciones pendientes"
         Un renglón por tipo de pendiente, agrupado por el área responsable.
         Se define como datos (no como 9 bloques repetidos) para que agregar un
         estado nuevo sea una línea y el diseño no se pueda desalinear.
         ========================================================================== --}}
    @php
        $summaryTotal = array_sum($lifecycleSummary ?? []);

        $pendingKinds = [
            // clave                     área          qué falta                              unidad     tono
            'material_release_pending' => ['Materiales', 'liberar el material',                'lote',    'sky'],
            'crimp_kit_pending'        => ['Materiales', 'liberar el material (CRIMP)',        'viajero', 'cyan'],
            'inspection_pending'       => ['Calidad',    'inspeccionar',                       'lote',    'emerald'],
            'production_pending'       => ['Producción', 'pesar producción',                   'lote',    'sky'],
            'quality_pending'          => ['Calidad',    'pesar calidad',                      'lote',    'teal'],
            'viajero_pending'          => ['Empaque',    'entregar el viajero',                'lote',    'orange'],
            'decision_pending'         => ['Materiales', 'tomar la decisión',                  'lote',    'amber'],
            'material_pending'         => ['Empaque',    'entregar los sobrantes',             'lote',    'amber'],
            'material_inflight'        => ['Materiales', 'recibir el material sobrante',       'lote',    'cyan'],
        ];

        // Clases literales: Tailwind necesita verlas completas para generarlas.
        $pendingTones = [
            'sky'     => ['dot' => 'bg-sky-500',     'num' => 'text-sky-700 dark:text-sky-300'],
            'cyan'    => ['dot' => 'bg-cyan-500',    'num' => 'text-cyan-700 dark:text-cyan-300'],
            'emerald' => ['dot' => 'bg-emerald-500', 'num' => 'text-emerald-700 dark:text-emerald-300'],
            'teal'    => ['dot' => 'bg-teal-500',    'num' => 'text-teal-700 dark:text-teal-300'],
            'orange'  => ['dot' => 'bg-orange-500',  'num' => 'text-orange-700 dark:text-orange-300'],
            'amber'   => ['dot' => 'bg-amber-500',   'num' => 'text-amber-700 dark:text-amber-300'],
        ];
    @endphp
    @if ($summaryTotal > 0)
        {{-- Plegado como la guía, pero con el total a la vista en la barra: esto
             es trabajo pendiente, no ayuda, y esconderlo sin dejar rastro sería
             peor que no plegarlo. --}}
        <div class="mx-auto max-w-full px-4 pt-4 sm:px-6 lg:px-8">
            <x-ui.disclosure title="Acciones pendientes" hint="Quién debe mover cada lote">
                <x-slot:aside>
                    <x-ui.badge tone="warn" dot>
                        {{ $summaryTotal }} {{ Str::plural('pendiente', $summaryTotal) }}
                    </x-ui.badge>
                </x-slot:aside>

                <div class="grid grid-cols-1 gap-px bg-slate-200 dark:bg-slate-700 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($pendingKinds as $key => [$area, $what, $unit, $tone])
                        @continue(($lifecycleSummary[$key] ?? 0) <= 0)
                        @php $n = $lifecycleSummary[$key]; @endphp
                        <div wire:key="pending-{{ $key }}" class="flex items-center gap-3 bg-white px-4 py-3 dark:bg-slate-800">
                            <span class="size-2.5 shrink-0 rounded-full {{ $pendingTones[$tone]['dot'] }}" aria-hidden="true"></span>
                            <span class="w-10 shrink-0 text-lg font-bold tabular-nums {{ $pendingTones[$tone]['num'] }}">{{ $n }}</span>
                            <span class="min-w-0 text-sm leading-5 text-slate-600 dark:text-slate-300">
                                {{ Str::plural($unit, $n) }} por <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $what }}</span>
                                <span class="block text-xs text-slate-400 dark:text-slate-500">Responsable: {{ $area }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-ui.disclosure>
        </div>
    @endif

    {{-- ==========================================================================
         Guía de uso + leyenda
         La prueba con el equipo mostró que la duda no era "qué significa el
         color" sino "dónde le pico". Por eso la guía va en tres pasos cortos y
         la leyenda muestra los controles reales tal como se ven en la tabla.

         Va plegada: es ayuda de una vez, no trabajo diario. Quien ya se sabe el
         tablero no quiere perder esa franja de pantalla todos los días, y quien
         la necesita la tiene a un clic.
         ========================================================================== --}}
    <div class="mx-auto max-w-full px-4 pt-4 sm:px-6 lg:px-8">
        <x-ui.disclosure title="Cómo usar este tablero"
            hint="Guía en 3 pasos y qué significa cada recuadro">
            <div class="grid grid-cols-1 gap-px overflow-hidden bg-slate-200 lg:grid-cols-[minmax(0,1fr)_auto] dark:bg-slate-700">

                {{-- Cómo continuar un viajero --}}
                <div class="bg-white px-4 py-4 dark:bg-slate-800">
                    <h2 class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                        <svg class="size-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Cómo continuar un viajero
                    </h2>
                    <ol class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['Busca tu orden', 'Escribe el WO, el # de parte o la descripción en el buscador de arriba.'],
                            ['Ubica tu columna', 'En la fila del viajero, busca la columna de tu área: Material, Insp., Prod., Cal. o Emp.'],
                            ['Presiona el recuadro', 'Si el recuadro tiene borde, se puede presionar y abre la ventana para registrar.'],
                        ] as $i => [$stepTitle, $stepText])
                            <li class="flex gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-sky-700 text-xs font-bold text-white">{{ $i + 1 }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $stepTitle }}</span>
                                    <span class="mt-0.5 block text-xs leading-4 text-slate-500 dark:text-slate-400">{{ $stepText }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- Leyenda: los mismos controles que aparecen en la tabla --}}
                <div class="bg-white px-4 py-4 dark:bg-slate-800 lg:max-w-xs">
                    <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Qué significa cada recuadro</h2>
                    <ul class="space-y-2">
                        <li class="flex items-center gap-3">
                            <span class="w-28 shrink-0"><x-ui.row-action state="pending" label="Liberar" hint="Ejemplo de acción pendiente" :clickable="true" /></span>
                            <span class="text-xs leading-4 text-slate-600 dark:text-slate-300"><strong class="text-slate-900 dark:text-white">Te toca a ti.</strong> Presiónalo para registrar.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-28 shrink-0"><x-ui.row-action state="done" label="Liberado" hint="Ejemplo de paso completado" :clickable="true" /></span>
                            <span class="text-xs leading-4 text-slate-600 dark:text-slate-300"><strong class="text-slate-900 dark:text-white">Ya se hizo.</strong> Puedes abrirlo para consultar.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-28 shrink-0"><x-ui.row-action state="idle" label="No disponible" hint="Ejemplo de paso bloqueado" /></span>
                            <span class="text-xs leading-4 text-slate-600 dark:text-slate-300"><strong class="text-slate-900 dark:text-white">Aún no.</strong> Falta que termine el paso anterior.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </x-ui.disclosure>
    </div>

    {{-- Barra de terminados: el tablero enseña lo pendiente; lo cerrado se
         puede recuperar de un clic, para verificar lo que se acaba de capturar. --}}
    @if ($showingFinished || $totalFinishedHidden > 0)
        <div class="mx-auto max-w-full px-4 pt-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2.5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if ($showingFinished)
                        Se están mostrando también los viajeros ya terminados.
                    @else
                        Hay <strong class="text-slate-700 dark:text-slate-200">{{ $totalFinishedHidden }}</strong>
                        {{ Str::plural('viajero', $totalFinishedHidden) }} {{ Str::plural('terminado', $totalFinishedHidden) }}
                        fuera de la vista.
                    @endif
                </p>

                <button type="button" wire:click="toggleFinished"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-white">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        @if ($showingFinished)
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        @endif
                    </svg>
                    {{ $showingFinished ? 'Ocultar terminados' : 'Ver terminados' }}
                </button>
            </div>
        </div>
    @endif

    {{-- Se alcanzó el tope de órdenes por vista: hay que acotar con el buscador --}}
    @if ($reachedLimit)
        <div class="mx-auto max-w-full px-4 pt-4 sm:px-6 lg:px-8">
            <x-ui.note tone="warn" title="Se están mostrando las primeras {{ $maxWorkOrders }} órdenes">
                Hay más órdenes abiertas de las que caben en una pantalla. Usa el buscador o el
                filtro de estación para acotar, o entra a una lista de envío concreta.
            </x-ui.note>
        </div>
    @endif

    {{-- Contenido Principal --}}
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6 pb-20">
        @foreach ($workOrdersGrouped as $workstationType => $workOrders)
            {{-- Sección por Tipo de Estación --}}
            <div class="overflow-hidden bg-white shadow-sm dark:bg-slate-800">
                {{-- Header de Sección --}}
                <div class="flex items-center justify-between gap-4 bg-slate-800 px-5 py-4 dark:bg-slate-900 sm:px-6">
                    <div>
                        <h2 class="font-bold text-white">{{ $workstationType }}</h2>
                        <p class="mt-0.5 text-xs text-slate-200">Selecciona un viajero para registrar o consultar su avance.</p>
                    </div>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold text-white ring-1 ring-inset ring-white/20">{{ $workOrders->count() }} {{ Str::plural('WO', $workOrders->count()) }}</span>
                </div>

                {{-- Vista Desktop: Tabla --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="shipping-workflow-table w-full text-sm">
                        {{--
                            Encabezado en dos niveles: el nivel de arriba separa las tres
                            zonas de la tabla (identificación · flujo de trabajo · números y
                            fechas) para que el operador encuentre su columna de un vistazo
                            en lugar de leer 20 títulos sueltos.
                        --}}
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                            <tr class="border-b border-slate-200 dark:border-slate-700">
                                <th colspan="5" class="px-4 pb-1.5 pt-2.5 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">Identificación</th>
                                <th colspan="6" class="ui-flow-start ui-flow-end bg-sky-50/60 px-4 pb-1.5 pt-2.5 text-center text-[10px] font-bold uppercase tracking-[0.14em] text-sky-700 dark:bg-sky-950/30 dark:text-sky-300">
                                    Flujo de trabajo — presiona el recuadro de tu área
                                </th>
                                <th colspan="9" class="px-4 pb-1.5 pt-2.5 text-right text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">Cantidades y fechas</th>
                            </tr>
                            <tr>
                                @foreach (['DOC', 'WO #', 'Item #', '# Parte', 'Descripción'] as $th)
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">{{ $th }}</th>
                                @endforeach

                                @foreach ([
                                    ['Material', 'Materiales libera el material del viajero', 'ui-flow-start'],
                                    ['Insp.',    'Calidad inspecciona el lote antes de producir', ''],
                                    ['Prod.',    'Producción registra las pesadas', ''],
                                    ['Cal.',     'Calidad verifica las piezas producidas', ''],
                                    ['Emp.',     'Empaque registra lo empacado', ''],
                                    ['Seguimiento', 'Después de empacar: entrega del viajero · decisión · sobrantes', 'ui-flow-end'],
                                ] as [$th, $thHelp, $thEdge])
                                    <th class="ui-flow-cell {{ $thEdge }} py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300"
                                        title="{{ $thHelp }}">{{ $th }}</th>
                                @endforeach

                                @foreach ([
                                    ['Cant. WO', 'text-slate-700 dark:text-slate-300'],
                                    ['Pz Enviadas', 'text-slate-700 dark:text-slate-300'],
                                    ['Cant. Pendiente', 'text-slate-700 dark:text-slate-300'],
                                    ['Pz Sobrantes', 'text-orange-600 dark:text-orange-400'],
                                    ['Pz Completadas', 'text-emerald-600 dark:text-emerald-400'],
                                ] as [$th, $thColor])
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider {{ $thColor }}">{{ $th }}</th>
                                @endforeach

                                @foreach (['Fecha Prog. A', 'Fecha de Envío', 'Fecha de Apertura', 'EG'] as $th)
                                    <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">{{ $th }}</th>
                                @endforeach
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
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.sent-lists.display.wo', $wo->id) }}"
                                                wire:navigate
                                                class="text-sky-700 dark:text-sky-300 hover:text-sky-900 dark:hover:text-sky-200 hover:underline cursor-pointer"
                                                title="Ver solo este WO">
                                                {{ $po->wo }}
                                            </a>
                                            <a href="{{ route('admin.sent-lists.display.wo.resume', $wo->id) }}"
                                                wire:navigate
                                                class="inline-flex items-center justify-center w-6 h-6 rounded bg-cyan-600 hover:bg-cyan-700 text-white shadow-sm shrink-0 transition-colors"
                                                title="Resumen del WO — viajeros y lotes de CRIMP (total · empacadas · sobrantes · faltantes · estado)">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h6v6m-9 4h12a2 2 0 002-2V7l-5-4H6a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            </a>
                                        </div>
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
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-100 dark:bg-sky-900/30 text-sky-800 dark:text-sky-200"
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
                                                'bg' => 'bg-sky-50 dark:bg-sky-900/30',
                                                'text' => 'text-sky-800 dark:text-sky-200',
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

                                    @endphp
                                    <tr wire:key="lot-row-{{ $lot->id }}" class="bg-gray-50 dark:bg-gray-700/20">
                                        <td class="px-4 py-2 pl-8 text-xs text-gray-600 dark:text-gray-400">{{ ($part->is_crimp ?? false) ? 'Viajero' : 'Lote' }}</td>
                                        <td class="px-4 py-2 text-xs">
                                            @if ($canMaterials)
                                                <button wire:click="openLotModal({{ $wo->id }})"
                                                    class="text-sky-700 dark:text-sky-300 hover:underline cursor-pointer">
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

                                            {{-- El aviso de datos incoherentes NO va aquí: un operador de piso
                                                 no puede corregirlo y sólo le mete ruido a la fila. Se reporta
                                                 en el Tablero, que es donde está quien sí puede actuar. --}}

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
                                                    'Producción' => 'bg-sky-100 dark:bg-sky-900/30 text-sky-800 dark:text-sky-200',
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
                                        {{-- ==================================================
                                             LAS 6 CELDAS DEL FLUJO
                                             Regla: una celda = un control (<x-ui.row-action>).
                                             El punto de color dice cómo va; el texto dice qué
                                             hacer. Si no hay permiso o el paso está bloqueado,
                                             se pinta sin borde y no se puede presionar.
                                             ================================================== --}}

                                        {{-- 1. Material — liberación del viajero --}}
                                        <td class="ui-flow-cell ui-flow-start py-2">
                                            @php
                                                $matStatus = $lot->material_status ?? 'pending';
                                                $matState = match ($matStatus) {
                                                    'released' => 'done',
                                                    'rejected' => 'error',
                                                    default    => 'pending',
                                                };
                                                $matLabel = match ($matStatus) {
                                                    'released' => 'Liberado',
                                                    'rejected' => 'Rechazado',
                                                    default    => 'Liberar',
                                                };
                                                $matHint = match ($matStatus) {
                                                    'released' => 'Material liberado — puedes abrirlo para consultar o corregir',
                                                    'rejected' => 'Material rechazado — requiere corrección de Materiales',
                                                    default    => 'Materiales debe liberar el material de este viajero',
                                                };
                                            @endphp
                                            <div class="space-y-1">
                                                @if ($canMaterials)
                                                    <x-ui.row-action :state="$matState" :label="$matLabel" :hint="$matHint"
                                                        wire:click="openMaterialModal({{ $lot->id }})" />
                                                @else
                                                    <x-ui.row-action :state="$matState" :label="$matLabel"
                                                        hint="Sólo el área de Materiales puede cambiar este estado" />
                                                @endif

                                                {{-- CRIMP: los lotes del viajero se capturan aquí mismo. --}}
                                                @if ($part->is_crimp)
                                                    @php $crimpCount = $lot->crimpLots->count(); @endphp
                                                    @if ($canMaterials)
                                                        <x-ui.row-action
                                                            :state="$crimpCount > 0 ? 'done' : 'pending'"
                                                            :label="$crimpCount > 0 ? $crimpCount.' '.Str::plural('lote', $crimpCount) : 'Capturar lotes'"
                                                            :hint="$crimpCount > 0
                                                                ? 'Lotes de CRIMP: '.$lot->crimpLots->pluck('crimp_lot_number')->join(', ')
                                                                : 'Materiales debe capturar los lotes de CRIMP de este viajero'"
                                                            wire:click="openCrimpLotModal({{ $lot->id }})" />
                                                    @elseif ($crimpCount > 0)
                                                        <x-ui.row-action state="done"
                                                            :label="$crimpCount.' '.Str::plural('lote', $crimpCount)"
                                                            :hint="'Lotes de CRIMP: '.$lot->crimpLots->pluck('crimp_lot_number')->join(', ')" />
                                                    @endif
                                                @endif
                                            </div>
                                        </td>

                                        {{-- 2. Inspección --}}
                                        <td class="ui-flow-cell py-2">
                                            @php
                                                $inspState = match (true) {
                                                    !$canInspect                    => 'idle',
                                                    $inspectionStatus === 'approved' => 'done',
                                                    $inspectionStatus === 'rejected' => 'error',
                                                    default                          => 'pending',
                                                };
                                                $inspLabel = match (true) {
                                                    !$canInspect                     => 'No disponible',
                                                    $inspectionStatus === 'approved' => 'Aprobado',
                                                    $inspectionStatus === 'rejected' => 'Rechazado',
                                                    default                          => 'Inspeccionar',
                                                };
                                                $inspHint = !$canInspect
                                                    ? ($lot->getInspectionBlockedReason() ?: 'Falta liberar el material antes de inspeccionar')
                                                    : match ($inspectionStatus) {
                                                        'approved' => 'Inspección aprobada — habilitado para producción',
                                                        'rejected' => 'Inspección rechazada — requiere acción correctiva',
                                                        default    => 'Calidad debe inspeccionar este lote',
                                                    };
                                            @endphp
                                            @if ($canQuality && $canInspect)
                                                <x-ui.row-action :state="$inspState" :label="$inspLabel" :hint="$inspHint"
                                                    wire:click="openInspectionModal({{ $lot->id }})" />
                                            @else
                                                <x-ui.row-action :state="$inspState" :label="$inspLabel" :hint="$inspHint" />
                                            @endif
                                        </td>

                                        {{-- 3. Producción --}}
                                        <td class="ui-flow-cell py-2">
                                            @php
                                                $prodTotalWeighed = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
                                                $prodReworkPending = $lot->qualityWeighings
                                                    ->where('rework_status', 'pending_rework')
                                                    ->sum('bad_pieces');
                                                $prodTotalToWeigh = $lot->quantity + $prodReworkPending;
                                                $prodRemaining = max(0, $prodTotalToWeigh - $prodTotalWeighed);
                                                $prodDone = $prodTotalWeighed > 0 && $prodTotalWeighed >= $prodTotalToWeigh;

                                                // El flujo es secuencial: sin inspección aprobada esta etapa
                                                // está cerrada, igual que Inspección lo está sin material
                                                // liberado. Antes faltaba esta compuerta y "Pesar" aparecía
                                                // habilitado aunque Calidad no hubiera inspeccionado.
                                                $canProduce = $lot->canBeProduced();

                                                $prodState = match (true) {
                                                    !$canProduce            => 'idle',
                                                    $prodDone               => 'done',
                                                    $prodTotalWeighed > 0   => 'active',
                                                    default                 => 'pending',
                                                };
                                                $prodLabel = match (true) {
                                                    !$canProduce          => 'No disponible',
                                                    $prodDone             => 'Pesado',
                                                    $prodTotalWeighed > 0 => 'Faltan '.number_format($prodRemaining),
                                                    default               => 'Pesar',
                                                };
                                                $prodHint = !$canProduce
                                                    ? ($lot->getProductionBlockedReason() ?: 'Falta aprobar la inspección antes de producir')
                                                    : ($prodDone
                                                        ? 'Producción pesó las '.number_format($prodTotalToWeigh).' piezas del lote'
                                                        : 'Producción debe pesar '.number_format($prodRemaining).' de '.number_format($prodTotalToWeigh).' piezas');
                                            @endphp
                                            @if ($canProduction && $canProduce)
                                                <x-ui.row-action :state="$prodState" :label="$prodLabel" :hint="$prodHint"
                                                    wire:click="openProductionModal({{ $lot->id }})" />
                                            @else
                                                <x-ui.row-action :state="$prodState" :label="$prodLabel" :hint="$prodHint" />
                                            @endif
                                        </td>

                                        {{-- 4. Calidad --}}
                                        <td class="ui-flow-cell py-2">
                                            @php
                                                $qualSemaphore = $lot->getQualitySemaphoreStatus();
                                                $qualHasProduction = $lot->hasProductionWeighings();
                                                $qualPending = $lot->getQualityPendingPieces();

                                                $qualState = match ($qualSemaphore) {
                                                    'green'  => 'done',
                                                    'yellow' => 'pending',
                                                    default  => 'idle',
                                                };
                                                $qualLabel = match ($qualSemaphore) {
                                                    'green'  => 'Verificado',
                                                    'yellow' => 'Verificar',
                                                    default  => 'No disponible',
                                                };
                                                $qualHint = match ($qualSemaphore) {
                                                    'green'  => 'Calidad verificó todas las piezas de producción',
                                                    'yellow' => 'Calidad debe verificar '.number_format($qualPending).' piezas',
                                                    default  => 'Falta que Producción registre pesadas antes de verificar',
                                                };
                                            @endphp
                                            @if ($canQuality && $qualHasProduction)
                                                <x-ui.row-action :state="$qualState" :label="$qualLabel" :hint="$qualHint"
                                                    wire:click="openQualityModal({{ $lot->id }})" />
                                            @else
                                                <x-ui.row-action :state="$qualState" :label="$qualLabel" :hint="$qualHint" />
                                            @endif
                                        </td>

                                        {{-- 5. Empaque --}}
                                        <td class="ui-flow-cell py-2">
                                            @php
                                                $lifecycle = $lot->getPostQualityLifecycle();
                                                $surplus = $lot->getPackagingTotalSurplus();
                                                $canDeliverSurplus = $canPackaging && $lot->canBePackaged()
                                                    && $lot->viajero_received && $surplus > 0 && !$lot->surplus_delivered;

                                                // Compuerta de la cadena: sin producción registrada y piezas
                                                // aprobadas por Calidad no se empieza a empacar. Los lotes que
                                                // YA tienen actividad de empaque pasan, para no ocultar avance.
                                                $canPack = $lot->canBePackaged();

                                                $pkgSem = $lot->getPackagingSemaphoreStatus();
                                                $pkgState = match (true) {
                                                    !$canPack          => 'idle',
                                                    $pkgSem === 'green'  => 'done',
                                                    $pkgSem === 'yellow' => 'pending',
                                                    $pkgSem === 'blue'   => 'active',
                                                    $pkgSem === 'orange' => 'active',
                                                    default              => 'idle',
                                                };
                                                $pkgLabel = match (true) {
                                                    !$canPack          => 'No disponible',
                                                    $pkgSem === 'green'  => 'Empacado',
                                                    $pkgSem === 'yellow' => 'Empacar',
                                                    $pkgSem === 'blue'   => 'Recibido',
                                                    $pkgSem === 'orange' => 'Con sobrantes',
                                                    default              => 'No disponible',
                                                };
                                                $pkgHint = !$canPack
                                                    ? ($lot->getPackagingBlockedReason() ?: 'Falta que Calidad apruebe piezas antes de empacar')
                                                    : ($part->is_crimp
                                                    ? 'Empaque CRIMP — piezas: '.number_format($lot->getPackagedPiecesTotal()).'/'.number_format($lot->getPackagingAvailablePieces())
                                                        .' · CRIMP: '.number_format($lot->getPackagedCrimpTotal()).'/'.number_format($lot->getCrimpTargetTotal())
                                                    : match ($pkgSem) {
                                                        'green'  => 'Empaque completado',
                                                        'yellow' => 'Empaque debe registrar las piezas empacadas',
                                                        'blue'   => 'Viajero recibido — falta la decisión de Materiales',
                                                        'orange' => 'Cerrado con sobrantes por entregar',
                                                        default  => 'Falta que Calidad verifique piezas antes de empacar',
                                                    });
                                            @endphp
                                            @if ($canPackaging && $pkgState !== 'idle')
                                                {{-- CRIMP usa el modal único del Paso 5; el resto, el modal de empaque por lote. --}}
                                                <x-ui.row-action :state="$pkgState" :label="$pkgLabel" :hint="$pkgHint"
                                                    wire:click="{{ $part->is_crimp ? 'openConfirmModal' : 'openPackagingModal' }}({{ $lot->id }})" />
                                            @else
                                                <x-ui.row-action :state="$pkgState" :label="$pkgLabel" :hint="$pkgHint" />
                                            @endif
                                        </td>

                                        {{-- 6. Seguimiento — entrega del viajero · decisión · sobrantes --}}
                                        <td class="ui-flow-cell ui-flow-end py-2">
                                            @php
                                                // Se muestra sólo el siguiente paso post-empaque, no los tres a la vez:
                                                // son secuenciales y mostrarlos juntos era la principal fuente de ruido.
                                                $trackStates = ['idle' => 'idle', 'pending' => 'pending', 'in_progress' => 'active', 'done' => 'done'];
                                                $vjState  = $trackStates[$lifecycle['viajero']['state']] ?? 'idle';
                                                $decState = $trackStates[$lifecycle['decision']['state']] ?? 'idle';
                                                $matState2 = $trackStates[$lifecycle['material']['state']] ?? 'idle';

                                                // Todo esto es posterior al empaque: si el lote todavía no
                                                // puede empacarse, nada de aquí está disponible.
                                                if (! $canPack) {
                                                    $vjState = $decState = $matState2 = 'idle';
                                                }

                                                $trackIsCrimp = $part->is_crimp ?? false;
                                            @endphp
                                            <div class="space-y-1">
                                                {{-- Paso 7 · entrega del viajero --}}
                                                @if ($trackIsCrimp && $canPackaging && $canPack && in_array($lifecycle["viajero"]["state"], ["pending", "done"]))
                                                    <x-ui.row-action :state="$vjState"
                                                        :label="$lifecycle['viajero']['state'] === 'done' ? 'Viajero entregado' : 'Entregar viajero'"
                                                        :hint="'Paso 7 · '.$lifecycle['viajero']['label']"
                                                        wire:click="openViajeroModal({{ $lot->id }})" />
                                                @elseif ($vjState !== 'idle')
                                                    <x-ui.row-action :state="$vjState" label="Entrega de viajero"
                                                        :hint="'Paso 7 · '.$lifecycle['viajero']['label']" />
                                                @endif

                                                {{-- Decisión de Materiales --}}
                                                @if (in_array($lifecycle['decision']['state'], ['pending', 'done']) && $canMaterials && $canPack)
                                                    <x-ui.row-action :state="$decState"
                                                        :label="$lifecycle['decision']['state'] === 'done' ? 'Ver decisión' : 'Decidir'"
                                                        :hint="'Decisión · '.$lifecycle['decision']['label']"
                                                        wire:click="openDecisionModal({{ $lot->id }})" />
                                                @elseif ($decState !== 'idle')
                                                    <x-ui.row-action :state="$decState" label="Decisión"
                                                        :hint="'Decisión · '.$lifecycle['decision']['label']" />
                                                @endif

                                                {{-- Sobrantes de material --}}
                                                @if ($canDeliverSurplus)
                                                    <x-ui.row-action state="pending"
                                                        :label="'Entregar '.number_format($surplus).' pz'"
                                                        :hint="'Sobrantes · '.$lifecycle['material']['label']"
                                                        wire:click="openDeliverMaterialModal({{ $lot->id }})" />
                                                @elseif ($matState2 !== 'idle')
                                                    <x-ui.row-action :state="$matState2" label="Sobrantes"
                                                        :hint="'Sobrantes · '.$lifecycle['material']['label']" />
                                                @endif

                                                {{-- Nada pendiente después de empaque. --}}
                                                @if ($vjState === 'idle' && $decState === 'idle' && $matState2 === 'idle')
                                                    <x-ui.row-action state="idle" label="—"
                                                        hint="Todavía no hay pasos posteriores al empaque" />
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

                                {{-- Sin viajeros abiertos pero con piezas pendientes: es el
                                     lunes por la mañana. La orden sigue viva y alguien tiene
                                     que ver que falta abrir los viajeros de la semana. --}}
                                @if ($allLots->isEmpty() && $cantAEnviar > 0)
                                    <tr class="bg-amber-50 dark:bg-amber-900/20">
                                        <td colspan="21" class="px-4 py-3">
                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <span class="text-xs font-semibold text-amber-800 dark:text-amber-200">
                                                    Faltan {{ number_format($cantAEnviar) }} pz de esta orden y no hay ningún viajero abierto.
                                                </span>
                                                <a href="{{ route('admin.materials.manage') }}" wire:navigate
                                                    class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                                    Crear viajeros
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Viajeros terminados que se están escondiendo --}}
                                @if (($finishedCounts[$wo->id] ?? 0) > 0)
                                    <tr class="bg-slate-50 dark:bg-slate-900/30">
                                        <td colspan="21" class="px-4 py-2">
                                            <button type="button" wire:click="toggleFinished"
                                                class="text-xs font-medium text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline dark:text-slate-400 dark:hover:text-slate-100">
                                                {{ $finishedCounts[$wo->id] }}
                                                {{ Str::plural('viajero', $finishedCounts[$wo->id]) }}
                                                {{ Str::plural('terminado', $finishedCounts[$wo->id]) }}
                                                de esta orden · ver
                                            </button>
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

                            // Estado por departamento del WO: se DERIVA de sus lotes, no se
                            // captura. Antes era un array fijo en 'pending' con un modal que
                            // no guardaba nada, así que los tres semáforos siempre salían
                            // amarillos y las selecciones se perdían al refrescar.
                            $woDeptStatus = function (callable $done, callable $failed) use ($allLots) {
                                if ($allLots->isEmpty())              return 'pending';
                                if ($allLots->contains($failed))      return 'rejected';
                                if ($allLots->every($done))           return 'approved';
                                if ($allLots->contains($done))        return 'in_progress';
                                return 'pending';
                            };

                            $departmentStatuses = [
                                'materials'  => $woDeptStatus(
                                    fn ($l) => ($l->material_status ?? 'pending') === 'released',
                                    fn ($l) => ($l->material_status ?? 'pending') === 'rejected',
                                ),
                                'inspection' => $woDeptStatus(
                                    fn ($l) => ($l->inspection_status ?? 'pending') === \App\Models\Lot::INSPECTION_APPROVED,
                                    fn ($l) => ($l->inspection_status ?? 'pending') === \App\Models\Lot::INSPECTION_REJECTED,
                                ),
                                'production' => $woDeptStatus(
                                    fn ($l) => $l->weighings->sum('good_pieces') + $l->weighings->sum('bad_pieces') >= $l->quantity,
                                    fn ($l) => false,
                                ),
                            ];

                            $deptLabels = [
                                'rejected'    => 'Rechazado',
                                'pending'     => 'Pendiente',
                                'in_progress' => 'En progreso',
                                'approved'    => 'Aprobado',
                            ];
                        @endphp

                        <div wire:key="wo-card-{{ $wo->id }}" class="p-4 space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span
                                            class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO</span>
                                        <span
                                            class="text-base font-semibold text-sky-700 dark:text-sky-300">{{ $po->wo }}</span>
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
                                    @foreach (['materials' => 'Mat.', 'inspection' => 'Insp.', 'production' => 'Prod.'] as $deptKey => $deptShort)
                                        @php
                                            $deptColor = match ($departmentStatuses[$deptKey]) {
                                                'rejected' => 'bg-red-500',
                                                'pending' => 'bg-yellow-400',
                                                'in_progress' => 'bg-sky-600',
                                                'approved' => 'bg-green-500',
                                                default => 'bg-gray-400',
                                            };
                                        @endphp
                                        <div wire:key="wo-{{ $wo->id }}-dept-{{ $deptKey }}" class="flex flex-col items-center gap-1">
                                            <span class="w-8 h-8 rounded {{ $deptColor }}"
                                                title="{{ $deptShort }} — {{ $deptLabels[$departmentStatuses[$deptKey]] ?? '—' }} (se calcula desde los lotes)"></span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ $deptShort }}</span>
                                        </div>
                                    @endforeach
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
                                        'bg' => 'bg-sky-50 dark:bg-sky-900/30',
                                        'text' => 'text-sky-800 dark:text-sky-200',
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

                                // Obtener razon de bloqueo si existe
                                $inspectionBlockedReasonMobile = $lot->getInspectionBlockedReason();
                            @endphp
                            {{--
                                Tarjeta de lote en móvil/tablet.
                                Usa exactamente los mismos <x-ui.row-action> que la tabla, sólo
                                que apilados y con el nombre del área a la izquierda: quien
                                aprende en el escritorio no tiene que reaprender en la tablet.
                            --}}
                            <div wire:key="lot-card-{{ $lot->id }}"
                                class="space-y-3 border-l-4 border-slate-300 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-800/40">

                                {{-- Identificación --}}
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ ($part->is_crimp ?? false) ? 'Viajero' : 'Lote' }}</span>
                                        @if ($canMaterials)
                                            <button wire:click="openLotModal({{ $wo->id }})" type="button"
                                                class="text-sm font-bold text-sky-700 underline-offset-2 hover:underline dark:text-sky-300">{{ $lot->lot_number }}</button>
                                        @else
                                            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $lot->lot_number }}</span>
                                        @endif
                                        @if ($lot->completion_count > 0)
                                            <button wire:click="openCycleHistoryModal({{ $lot->id }})" type="button"
                                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                                                title="Ver historial de ciclos">Completado {{ $lot->completion_count }}</button>
                                        @endif
                                    </div>
                                    <span class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($lot->quantity) }} <span class="text-xs font-medium text-slate-400">pz</span></span>
                                </div>

                                <p class="line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $lot->description ?? $part->description }}</p>

                                {{-- Flujo: mismas seis etapas que en la tabla --}}
                                @php
                                    $mInsp = $lot->inspection_status ?? 'pending';
                                    $mCanInsp = $lot->canBeInspected();
                                    $mMat = $lot->material_status ?? 'pending';

                                    $mProdWeighed = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
                                    $mRework = $lot->qualityWeighings->where('rework_status', 'pending_rework')->sum('bad_pieces');
                                    $mProdTarget = $lot->quantity + $mRework;
                                    $mProdDone = $mProdWeighed > 0 && $mProdWeighed >= $mProdTarget;

                                    $mQual = $lot->getQualitySemaphoreStatus();
                                    $mPkg = $lot->getPackagingSemaphoreStatus();
                                    $mLife = $lot->getPostQualityLifecycle();
                                    $mSurplus = $lot->getPackagingTotalSurplus();

                                    // [área, estado, etiqueta, método a abrir (o null si no hay acción)]
                                    $mobileFlow = [
                                        ['Material', match ($mMat) { 'released' => 'done', 'rejected' => 'error', default => 'pending' },
                                            match ($mMat) { 'released' => 'Liberado', 'rejected' => 'Rechazado', default => 'Liberar' },
                                            $canMaterials ? 'openMaterialModal' : null],

                                        ['Inspección', !$mCanInsp ? 'idle' : match ($mInsp) { 'approved' => 'done', 'rejected' => 'error', default => 'pending' },
                                            !$mCanInsp ? 'No disponible' : match ($mInsp) { 'approved' => 'Aprobado', 'rejected' => 'Rechazado', default => 'Inspeccionar' },
                                            ($canQuality && $mCanInsp) ? 'openInspectionModal' : null],

                                        ['Producción', $mProdDone ? 'done' : ($mProdWeighed > 0 ? 'active' : 'pending'),
                                            $mProdDone ? 'Pesado' : ($mProdWeighed > 0 ? 'Faltan '.number_format(max(0, $mProdTarget - $mProdWeighed)) : 'Pesar'),
                                            $canProduction ? 'openProductionModal' : null],

                                        ['Calidad', match ($mQual) { 'green' => 'done', 'yellow' => 'pending', default => 'idle' },
                                            match ($mQual) { 'green' => 'Verificado', 'yellow' => 'Verificar', default => 'No disponible' },
                                            ($canQuality && $lot->hasProductionWeighings()) ? 'openQualityModal' : null],

                                        ['Empaque', match ($mPkg) { 'green' => 'done', 'yellow' => 'pending', 'blue', 'orange' => 'active', default => 'idle' },
                                            match ($mPkg) { 'green' => 'Empacado', 'yellow' => 'Empacar', 'blue' => 'Recibido', 'orange' => 'Con sobrantes', default => 'No disponible' },
                                            ($canPackaging && $mPkg !== 'gray') ? (($part->is_crimp ?? false) ? 'openConfirmModal' : 'openPackagingModal') : null],
                                    ];
                                @endphp

                                <ul class="space-y-1.5 border-t border-slate-200 pt-3 dark:border-slate-700">
                                    @foreach ($mobileFlow as [$mArea, $mState, $mLabel, $mMethod])
                                        <li class="flex items-center gap-3">
                                            <span class="w-24 shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $mArea }}</span>
                                            <span class="min-w-0 flex-1">
                                                @if ($mMethod)
                                                    <x-ui.row-action :state="$mState" :label="$mLabel" wire:click="{{ $mMethod }}({{ $lot->id }})" />
                                                @else
                                                    <x-ui.row-action :state="$mState" :label="$mLabel" />
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach

                                    {{-- Seguimiento posterior al empaque --}}
                                    @if ($canPackaging && ($part->is_crimp ?? false) && in_array($mLife['viajero']['state'], ['pending', 'done']))
                                        <li class="flex items-center gap-3">
                                            <span class="w-24 shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Viajero</span>
                                            <span class="min-w-0 flex-1">
                                                <x-ui.row-action :state="$mLife['viajero']['state'] === 'done' ? 'done' : 'pending'"
                                                    :label="$mLife['viajero']['state'] === 'done' ? 'Viajero entregado' : 'Entregar viajero'"
                                                    :hint="$mLife['viajero']['label']"
                                                    wire:click="openViajeroModal({{ $lot->id }})" />
                                            </span>
                                        </li>
                                    @endif
                                    @if ($canMaterials && in_array($mLife['decision']['state'], ['pending', 'done']))
                                        <li class="flex items-center gap-3">
                                            <span class="w-24 shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Decisión</span>
                                            <span class="min-w-0 flex-1">
                                                <x-ui.row-action :state="$mLife['decision']['state'] === 'done' ? 'done' : 'pending'"
                                                    :label="$mLife['decision']['state'] === 'done' ? 'Ver decisión' : 'Decidir'"
                                                    :hint="$mLife['decision']['label']"
                                                    wire:click="openDecisionModal({{ $lot->id }})" />
                                            </span>
                                        </li>
                                    @endif
                                    @if ($canPackaging && $lot->viajero_received && $mSurplus > 0 && !$lot->surplus_delivered)
                                        <li class="flex items-center gap-3">
                                            <span class="w-24 shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Sobrantes</span>
                                            <span class="min-w-0 flex-1">
                                                <x-ui.row-action state="pending" :label="'Entregar '.number_format($mSurplus).' pz'"
                                                    :hint="$mLife['material']['label']"
                                                    wire:click="openDeliverMaterialModal({{ $lot->id }})" />
                                            </span>
                                        </li>
                                    @endif
                                </ul>

                                {{-- Pz Completadas por ciclo --}}
                                @php
                                    $lotCyclesM = $lot->getCompletionCycles();
                                    $lotCompletedTotalM = array_sum(array_column($lotCyclesM, 'pieces'));
                                @endphp
                                @if (!empty($lotCyclesM))
                                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pz completadas</span>
                                        <div class="flex flex-wrap items-center justify-end gap-1">
                                            @foreach ($lotCyclesM as $cycle)
                                                <span class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                                    C{{ $cycle['cycle'] }}: {{ number_format($cycle['pieces']) }}
                                                </span>
                                            @endforeach
                                            <span class="inline-flex items-center rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
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
        <x-ui-modal wire:key="modal-cycle-history"
            :title="'Historial de ciclos — '.(($lot_h->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero ' : 'Lote ').$lot_h->lot_number"
            subtitle="Consulta lo empacado, sobrante y faltante de cada ciclo."
            close="closeCycleHistoryModal" maxWidth="7xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$lot_h->workOrder->purchaseOrder->wo ?? '—'" />
                <x-ui-modal.ctx label="Parte" :value="$lot_h->workOrder->purchaseOrder->part->number ?? '—'" />
                <x-ui-modal.ctx label="Lote / viajero" :value="$lot_h->lot_number" />
                <x-ui-modal.ctx label="Cantidad original" :value="number_format($lot_h->completionLogs->first()?->original_quantity ?? $lot_h->quantity).' piezas'" />
            </x-slot:context>

            <x-ui.section title="Ciclos de completado" hint="Cada renglón es una vez que el lote se cerró y se volvió a abrir.">
                @if ($lot_h->completionLogs->isNotEmpty())
                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr class="text-[11px] uppercase tracking-wide">
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400">Ciclo</th>
                                    <th class="px-3 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Qty original</th>
                                    <th class="px-3 py-2 text-right font-semibold text-emerald-700 dark:text-emerald-400">Empacadas</th>
                                    <th class="px-3 py-2 text-right font-semibold text-orange-700 dark:text-orange-400">Sobrantes</th>
                                    <th class="px-3 py-2 text-right font-semibold text-red-700 dark:text-red-400">Faltantes</th>
                                    <th class="px-3 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Prod. buenas</th>
                                    <th class="px-3 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Cal. buenas</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400">Completado por</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400">Fecha</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach ($lot_h->completionLogs->sortBy('cycle_number') as $clog)
                                    <tr wire:key="clog-{{ $clog->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                        <td class="px-3 py-2.5">
                                            <span class="inline-flex items-center rounded bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                C{{ $clog->cycle_number }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($clog->original_quantity) }}</td>
                                        <td class="px-3 py-2.5 text-right font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($clog->packed_pieces) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums {{ $clog->surplus_pieces > 0 ? 'text-orange-700 dark:text-orange-400' : 'text-slate-400' }}">{{ number_format($clog->surplus_pieces) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums {{ $clog->missing_pieces > 0 ? 'font-bold text-red-700 dark:text-red-400' : 'text-slate-400' }}">{{ number_format($clog->missing_pieces) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ number_format($clog->production_good_pieces) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ number_format($clog->quality_good_pieces) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-xs text-slate-700 dark:text-slate-300">{{ $clog->completedByUser?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $clog->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-900/50">
                                <tr class="text-xs font-bold">
                                    <td class="px-3 py-2.5 uppercase tracking-wide text-slate-500 dark:text-slate-400">Total</td>
                                    <td class="px-3 py-2.5 text-right text-slate-400">—</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($lot_h->completionLogs->sum('packed_pieces')) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-orange-700 dark:text-orange-400">{{ number_format($lot_h->completionLogs->sum('surplus_pieces')) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-red-700 dark:text-red-400">{{ number_format($lot_h->completionLogs->sum('missing_pieces')) }}</td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <x-ui.note tone="muted" title="Sin ciclos registrados">
                        Este lote todavía no se ha completado ninguna vez.
                    </x-ui.note>
                @endif
            </x-ui.section>

            {{-- Reversión: sólo admin, siempre al final y visualmente aparte por ser destructiva. --}}
            @if (auth()->user()->hasRole('admin') && $lot_h->completionLogs->isNotEmpty() && !$lot_h->packingSlipItem)
                @php $lastCycle = $lot_h->completionLogs->sortByDesc('cycle_number')->first()->cycle_number; @endphp
                <x-ui.section title="Zona de administrador" hint="Sólo para corregir un ciclo capturado por error.">
                    <x-ui.note tone="danger" title="Revertir el ciclo {{ $lastCycle }}">
                        Se eliminarán los registros del ciclo actual y se restaurarán los del anterior.
                        Esta acción <strong>no se puede deshacer</strong>.
                    </x-ui.note>
                    <x-ui.btn variant="danger" class="mt-4"
                        wire:click="rollbackLastCycle({{ $lot_h->id }})"
                        wire:confirm="¿Confirmas que deseas revertir el Ciclo {{ $lastCycle }}? Esta acción eliminará el ciclo actual y restaurará el estado anterior. No se puede deshacer.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        Revertir ciclo {{ $lastCycle }}
                    </x-ui.btn>
                </x-ui.section>
            @elseif (auth()->user()->hasRole('admin') && $lot_h->packingSlipItem)
                <x-ui.section title="Zona de administrador">
                    <x-ui.note tone="muted" title="Reversión bloqueada">
                        Este lote ya tiene un Packing Slip generado, así que sus ciclos no se pueden revertir.
                    </x-ui.note>
                </x-ui.section>
            @endif

            <x-slot:note>
                Los ciclos se muestran del más antiguo al más reciente. Esta ventana es sólo de consulta.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeCycleHistoryModal">Cerrar</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Modal de Gestión de Lotes --}}
    @if ($showLotModal && $selectedWorkOrder)
        <x-ui-modal wire:key="modal-lot" title="Lotes y viajeros"
            subtitle="Agrega, edita o elimina las divisiones de esta orden."
            close="closeLotModal" maxWidth="4xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$selectedWorkOrder->purchaseOrder->wo" />
                <x-ui-modal.ctx label="Parte" :value="$selectedWorkOrder->purchaseOrder->part->number" />
                <x-ui-modal.ctx label="Registros" :value="count($lots).' lotes / viajeros'" />
                <x-ui-modal.ctx label="Cantidad WO" :value="number_format($selectedWorkOrder->original_quantity).' piezas'" />
            </x-slot:context>
            @php
                $lotsAsignado = collect($lots)->sum(fn ($r) => (int) ($r['quantity'] ?? 0));
                $lotsRestante = (int) $selectedWorkOrder->original_quantity - $lotsAsignado;
            @endphp

            {{-- Restador: misma mecánica que en los lotes de CRIMP, para no aprender dos cosas. --}}
            <x-ui.section title="Reparto de la orden" hint="La suma de los lotes debería igualar la cantidad del WO.">
                <x-ui.stats cols="3">
                    <x-ui.stat label="Cantidad del WO" :value="number_format($selectedWorkOrder->original_quantity)" unit="pz" />
                    <x-ui.stat label="Asignado a lotes" :value="number_format($lotsAsignado)" unit="pz" tone="info" />
                    <x-ui.stat label="Restante" :value="number_format($lotsRestante)" unit="pz"
                        :tone="$lotsRestante === 0 ? 'good' : ($lotsRestante < 0 ? 'bad' : 'warn')" />
                </x-ui.stats>

                @if ($lotsRestante < 0)
                    <x-ui.note tone="danger" class="mt-4">
                        La suma de los lotes sobrepasa la cantidad del WO por <strong>{{ number_format(abs($lotsRestante)) }} pz</strong>.
                    </x-ui.note>
                @elseif ($lotsRestante === 0 && $lotsAsignado > 0)
                    <x-ui.note tone="success" class="mt-4">La suma coincide con la cantidad del WO.</x-ui.note>
                @endif
            </x-ui.section>

            <x-ui.section title="Lotes y viajeros de la orden"
                hint="Cada renglón es una división física de la orden. El número es el que se ve en piso.">
                <div class="space-y-3">
                    @forelse ($lots as $index => $lot)
                        <div wire:key="lot-row-form-{{ $index }}"
                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-start gap-3">
                                <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                                    <x-ui.field label="No. de lote / viajero" required
                                        :error="$errors->first('lots.'.$index.'.number')">
                                        <input type="text" wire:model="lots.{{ $index }}.number"
                                            placeholder="Ej: 001" class="w-full">
                                    </x-ui.field>

                                    <x-ui.field label="Cantidad (piezas)" required
                                        :error="$errors->first('lots.'.$index.'.quantity')">
                                        <input type="number" min="1" wire:model="lots.{{ $index }}.quantity"
                                            placeholder="Ej: 100" class="w-full text-right font-bold tabular-nums">
                                    </x-ui.field>
                                </div>

                                <div class="pt-6">
                                    <x-ui.icon-btn tone="danger" label="Eliminar este lote"
                                        wire:click="removeLot({{ $index }})" wire:confirm="¿Eliminar este lote?">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </x-ui.icon-btn>
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-ui.note tone="muted" title="Esta orden no tiene lotes">
                            Agrega al menos uno para poder registrar el avance en piso.
                        </x-ui.note>
                    @endforelse
                </div>

                <x-ui.btn variant="primary" class="mt-4" wire:click="addLot">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Agregar lote
                </x-ui.btn>
            </x-ui.section>

            <x-slot:note>
                Verifica que la suma de las cantidades corresponda a la orden antes de guardar.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeLotModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveLots" wire:loading.attr="disabled" wire:target="saveLots">
                    Guardar cambios
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Modal de Estado de Departamentos --}}
    {{-- Modal de Status de Inspeccion por Lote --}}
    @if ($showInspectionModal && $selectedLot)
        <x-ui-modal wire:key="modal-inspection" title="Inspección del lote"
            subtitle="Selecciona el resultado de la inspección y agrega comentarios si son necesarios."
            close="closeInspectionModal" maxWidth="4xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$selectedLot->workOrder->purchaseOrder->wo ?? 'N/A'" />
                <x-ui-modal.ctx label="Parte" :value="$selectedLot->workOrder->purchaseOrder->part->number ?? 'N/A'" />
                <x-ui-modal.ctx label="Lote / viajero" :value="$selectedLot->lot_number" />
                <x-ui-modal.ctx label="Cantidad" :value="number_format($selectedLot->quantity).' piezas'" />
            </x-slot:context>
                        @if (($selectedLot->workOrder->purchaseOrder->part->is_crimp ?? false) && $selectedLot->crimpLots->count() > 0)
                            <x-ui.section title="Lotes de CRIMP en este viajero" hint="Inspecciona el viajero completo: estos son los lotes que trae.">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($selectedLot->crimpLots as $cl)
                                        <span wire:key="inspection-crimp-{{ $cl->id }}"
                                            class="rounded bg-cyan-100 px-2.5 py-1 font-mono text-xs font-semibold text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-200">
                                            {{ $cl->crimp_lot_number }}
                                        </span>
                                    @endforeach
                                </div>
                            </x-ui.section>
                        @endif

                        {{-- Alpine da respuesta inmediata al tocar; el valor real vive en Livewire. --}}
                        <div x-data="{ status: $wire.entangle('inspectionStatus') }">
                            <x-ui.section title="Resultado de la inspección"
                                hint="Elige una opción. Si rechazas, el motivo es obligatorio.">

                                <x-ui.note tone="success" class="mb-4">
                                    Material liberado: el lote está habilitado para inspección.
                                </x-ui.note>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <x-ui.choice tone="warn" title="Pendiente"
                                        desc="Aún no se revisa. El lote no avanza."
                                        x-on:click="status = 'pending'" x-bind:aria-pressed="status === 'pending'" />
                                    <x-ui.choice tone="good" title="Aprobado"
                                        desc="El lote pasa a Producción."
                                        x-on:click="status = 'approved'" x-bind:aria-pressed="status === 'approved'" />
                                    <x-ui.choice tone="bad" title="No aprobado"
                                        desc="Requiere acción correctiva antes de continuar."
                                        x-on:click="status = 'rejected'" x-bind:aria-pressed="status === 'rejected'" />
                                </div>

                                <div class="mt-4">
                                    <div x-show="status === 'pending'" x-cloak>
                                        <x-ui.note tone="muted">El lote queda pendiente de inspección.</x-ui.note>
                                    </div>
                                    <div x-show="status === 'approved'" x-cloak>
                                        <x-ui.note tone="success">Al guardar, <strong>Producción</strong> podrá registrar pesadas.</x-ui.note>
                                    </div>
                                    <div x-show="status === 'rejected'" x-cloak>
                                        <x-ui.note tone="danger">El lote se detiene hasta que se corrija lo señalado.</x-ui.note>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="mb-1.5 flex items-baseline gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        <span>Comentarios de inspección</span>
                                        <span x-show="status === 'rejected'" x-cloak class="text-red-600 dark:text-red-400">*</span>
                                        <span x-show="status !== 'rejected'" x-cloak class="font-normal text-slate-400">(opcional)</span>
                                    </label>
                                    <textarea wire:model="inspectionComments" rows="3" class="w-full"
                                        x-bind:placeholder="status === 'rejected' ? 'Describe el motivo del rechazo...' : 'Observaciones adicionales (opcional)...'"></textarea>
                                    <p x-show="status === 'rejected'" x-cloak class="mt-1 text-[11px] font-medium text-red-600 dark:text-red-400">
                                        El motivo del rechazo es obligatorio.
                                    </p>
                                    @error('inspectionComments')
                                        <p class="mt-1 text-[11px] font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </x-ui.section>
                        </div>

            <x-slot:note>
                El resultado mueve el semáforo de <strong>Insp.</strong> y decide si el lote pasa a Producción.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeInspectionModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveInspectionStatus" wire:loading.attr="disabled" wire:target="saveInspectionStatus">
                    Guardar resultado
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif


    {{-- Modal de Lotes de CRIMP (CRIMP — reemplaza al Kit) --}}
    @if ($showCrimpLotModal)
        <x-ui-modal wire:key="modal-crimp-lots" title="Lotes de CRIMP"
            :subtitle="$crimpLotViajeroLabel.' · Cantidad del viajero: '.number_format($crimpLotViajeroQty).' pz'"
            close="closeCrimpLotModal" maxWidth="6xl">
            @php
                $asignado = collect($crimpLots)->sum(fn ($r) => (int) ($r['quantity'] ?? 0));
                $restante = (int) $crimpLotViajeroQty - $asignado;
            @endphp

            {{-- 1. Restador: la cuenta que el operador vigila mientras captura. --}}
            <x-ui.section title="Reparto del viajero" hint="La suma de los lotes debería igualar la cantidad del viajero.">
                <x-ui.stats cols="3">
                    <x-ui.stat label="Cantidad del viajero" :value="number_format($crimpLotViajeroQty)" unit="pz" />
                    <x-ui.stat label="Asignado a lotes" :value="number_format($asignado)" unit="pz" tone="accent" />
                    <x-ui.stat label="Restante" :value="number_format($restante)" unit="pz"
                        :tone="$restante === 0 ? 'good' : ($restante < 0 ? 'bad' : 'warn')" />
                </x-ui.stats>

                @if ($restante < 0)
                    <x-ui.note tone="danger" class="mt-4">
                        La suma de los lotes sobrepasa la cantidad del viajero por <strong>{{ number_format(abs($restante)) }} pz</strong>.
                    </x-ui.note>
                @elseif ($restante === 0 && $asignado > 0)
                    <x-ui.note tone="success" class="mt-4">La suma de los lotes coincide con la cantidad del viajero.</x-ui.note>
                @elseif ($restante > 0)
                    <x-ui.note tone="muted" class="mt-4">
                        Faltan <strong>{{ number_format($restante) }} pz</strong> por asignar. Puedes guardar así si el viajero se completará después.
                    </x-ui.note>
                @endif
            </x-ui.section>

            {{-- 2. Captura de lotes --}}
            <x-ui.section title="Lotes de CRIMP del viajero"
                hint="Un renglón por lote físico. El número de lote es el que aparece en la etiqueta.">
                <div class="space-y-3">
                    @forelse ($crimpLots as $index => $cl)
                        <div wire:key="crimplot-{{ $index }}"
                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-start gap-3">
                                <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <x-ui.field label="No. lote CRIMP" required
                                        :error="$errors->first('crimpLots.'.$index.'.crimp_lot_number')">
                                        <input type="text" wire:model="crimpLots.{{ $index }}.crimp_lot_number"
                                            placeholder="Ej: CL-001" class="w-full">
                                    </x-ui.field>

                                    <x-ui.field label="Lote de fabricante" optional>
                                        <input type="text" wire:model="crimpLots.{{ $index }}.lote_fabricante"
                                            placeholder="Ej: FAB-2024" class="w-full">
                                    </x-ui.field>

                                    <x-ui.field label="Cantidad" required
                                        :error="$errors->first('crimpLots.'.$index.'.quantity')">
                                        <input type="number" min="1" placeholder="0"
                                            wire:model.live.debounce.400ms="crimpLots.{{ $index }}.quantity"
                                            class="w-full text-right font-bold tabular-nums">
                                    </x-ui.field>

                                    <x-ui.field label="Comentarios" optional>
                                        <input type="text" wire:model="crimpLots.{{ $index }}.comments"
                                            placeholder="Observaciones..." class="w-full">
                                    </x-ui.field>
                                </div>

                                <div class="pt-6">
                                    <x-ui.icon-btn tone="danger" label="Eliminar este lote de CRIMP"
                                        wire:click="removeCrimpLotRow({{ $index }})"
                                        wire:confirm="¿Eliminar este lote de CRIMP?">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </x-ui.icon-btn>
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-ui.note tone="muted" title="Todavía no hay lotes de CRIMP">
                            Agrega al menos uno para que Empaque pueda registrar sus pesadas.
                        </x-ui.note>
                    @endforelse
                </div>

                <x-ui.btn variant="accent" class="mt-4" wire:click="addCrimpLotRow">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Agregar lote de CRIMP
                </x-ui.btn>
            </x-ui.section>

            <x-slot:note>
                Empaque necesita estos lotes para poder capturar el <strong>Paso 5</strong>. El lote de fabricante es opcional.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeCrimpLotModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="accent" wire:click="saveCrimpLots" wire:loading.attr="disabled" wire:target="saveCrimpLots">
                    Guardar lotes de CRIMP
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Paso 5 y Paso 7: markup compartido con la vista de Empaque. --}}
    @include('livewire.admin.sent-lists.partials.modal-viajero')

    @include('livewire.admin.sent-lists.partials.modal-confirm-empaque')

    {{-- Modal de Material (No CRIMP) --}}
    @if ($showMaterialModal && $selectedLotForMaterial)
        <x-ui-modal wire:key="modal-material"
            :title="'Material del '.(($selectedLotForMaterial->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'viajero' : 'lote')"
            subtitle="Selecciona el estado del material y guarda el cambio."
            close="closeMaterialModal" maxWidth="4xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$selectedLotForMaterial->workOrder->purchaseOrder->wo ?? 'N/A'" />
                <x-ui-modal.ctx label="Parte" :value="$selectedLotForMaterial->workOrder->purchaseOrder->part->number ?? 'N/A'" />
                <x-ui-modal.ctx :label="($selectedLotForMaterial->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote'" :value="$selectedLotForMaterial->lot_number" />
                <x-ui-modal.ctx label="Cantidad" :value="number_format($selectedLotForMaterial->quantity).' piezas'" />
            </x-slot:context>
                        {{-- Selector de estado. Alpine mantiene la selección instantánea;
                             el valor real vive en Livewire ($materialStatus). --}}
                        <div x-data="{ matSt: $wire.entangle('materialStatus') }">
                            <x-ui.section title="¿El material está listo?"
                                hint="Este estado abre o detiene el resto del flujo: sin material liberado no se puede inspeccionar.">

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <x-ui.choice tone="good" title="Aprobado"
                                        desc="El material está completo y correcto. Habilita la inspección."
                                        x-on:click="matSt = 'released'" x-bind:aria-pressed="matSt === 'released'" />
                                    <x-ui.choice tone="bad" title="Rechazado"
                                        desc="Falta material o viene mal. El lote se detiene hasta corregirlo."
                                        x-on:click="matSt = 'rejected'" x-bind:aria-pressed="matSt === 'rejected'" />
                                </div>

                                <div class="mt-4">
                                    <div x-show="matSt === 'pending'" x-cloak>
                                        <x-ui.note tone="muted">Todavía no se ha revisado el material de este lote.</x-ui.note>
                                    </div>
                                    <div x-show="matSt === 'released'" x-cloak>
                                        <x-ui.note tone="success" title="Material aprobado">
                                            Al guardar, <strong>Calidad</strong> podrá inspeccionar el lote.
                                        </x-ui.note>
                                    </div>
                                    <div x-show="matSt === 'rejected'" x-cloak>
                                        <x-ui.note tone="danger" title="Material rechazado">
                                            El lote queda detenido. Corrige el material y vuelve a cambiar el estado.
                                        </x-ui.note>
                                    </div>
                                </div>
                            </x-ui.section>
                        </div>

            <x-slot:note>
                El estado del material controla el semáforo <strong>Material</strong> y habilita o detiene el siguiente paso.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeMaterialModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveMaterialStatus" wire:loading.attr="disabled" wire:target="saveMaterialStatus">
                    Guardar estado
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Modal de Empaque por Lote — 4 Fases --}}
    @if ($showPackagingModal && $selectedLotForPackaging)
        <x-ui-modal wire:key="modal-packaging" title="Empaque del lote"
            subtitle="Registra el empaque y revisa el avance antes de cerrar el lote."
            close="closePackagingModal" maxWidth="6xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$selectedLotForPackaging->workOrder->purchaseOrder->wo ?? 'N/A'" />
                <x-ui-modal.ctx label="Parte" :value="$selectedLotForPackaging->workOrder->purchaseOrder->part->number ?? 'N/A'" />
                <x-ui-modal.ctx label="Lote" :value="$selectedLotForPackaging->lot_number" />
                <x-ui-modal.ctx label="Cantidad" :value="number_format($selectedLotForPackaging->quantity).' piezas'" />
            </x-slot:context>

                        {{-- 1. Lectura: de dónde salen las piezas disponibles --}}
                        <x-ui.section title="Piezas de este lote" hint="Empaque sólo puede empacar piezas que Calidad ya verificó.">
                            <x-ui.stats cols="5">
                                <x-ui.stat label="Producción" :value="number_format($pkgProductionPieces)" tone="info"
                                    help="Piezas buenas que registró Producción." />
                                <x-ui.stat label="Calidad" :value="number_format($pkgAvailablePieces)"
                                    help="Piezas aprobadas por Calidad: son las que se pueden empacar." />
                                <x-ui.stat label="Ya empacadas" :value="number_format($pkgAlreadyPacked)" tone="good" />
                                <x-ui.stat label="Pendientes" :value="number_format($pkgPendingPieces)"
                                    :tone="$pkgPendingPieces > 0 ? 'warn' : 'good'" />
                                <x-ui.stat label="Sobrantes" :value="number_format($pkgTotalSurplus)" tone="warn"
                                    help="Piezas buenas que no entraron en la caja. Hay que entregarlas a Materiales." />
                            </x-ui.stats>

                            @if ($pkgPreviousCyclesPacked > 0)
                                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                                    <p class="mb-3 text-xs font-bold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">Acumulado de ciclos de completado</p>
                                    <x-ui.stats cols="3">
                                        <x-ui.stat label="Ciclos anteriores" :value="number_format($pkgPreviousCyclesPacked)" />
                                        <x-ui.stat label="Empacado este ciclo" :value="number_format($pkgAlreadyPacked)" />
                                        <x-ui.stat label="Total acumulado" :value="number_format($pkgPreviousCyclesPacked + $pkgAlreadyPacked)" tone="good" />
                                    </x-ui.stats>
                                </div>
                            @endif
                        </x-ui.section>

                        @if ($pkgAvailablePieces > 0)
                            {{-- 2. Registros ya capturados --}}
                            @if (count($pkgRecordsList) > 0)
                                <x-ui.section step="1" title="Empaques registrados"
                                    hint="Cada renglón es una caja o tanda registrada. Se pueden corregir mientras no se reciba el lote.">
                                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                        <table class="w-full text-sm">
                                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                                <tr class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                                    <th class="px-3 py-2 text-right font-semibold">Empacadas</th>
                                                    <th class="px-3 py-2 text-right font-semibold">Sobrantes</th>
                                                    <th class="px-3 py-2 text-right font-semibold">Ajuste</th>
                                                    <th class="px-3 py-2 text-left font-semibold">Fecha</th>
                                                    <th class="px-3 py-2 text-left font-semibold">Usuario</th>
                                                    <th class="px-3 py-2 text-right font-semibold">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                                @foreach ($pkgRecordsList as $pr)
                                                    <tr wire:key="pkg-rec-{{ $pr['id'] }}" class="{{ $pkgAdjustRecordId === $pr['id'] ? 'bg-amber-50 dark:bg-amber-900/20' : '' }}">
                                                        <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($pr['packed_pieces']) }}</td>
                                                        <td class="px-3 py-2 text-right tabular-nums {{ $pr['surplus_pieces'] > 0 ? 'font-semibold text-orange-700 dark:text-orange-400' : 'text-slate-400' }}">{{ number_format($pr['surplus_pieces']) }}</td>
                                                        <td class="px-3 py-2 text-right tabular-nums">
                                                            @if ($pr['adjusted_surplus'] !== null)
                                                                <span class="font-semibold text-sky-700 dark:text-sky-400"
                                                                    @if ($pr['adjustment_reason']) title="Motivo: {{ $pr['adjustment_reason'] }}" @endif>
                                                                    {{ number_format($pr['adjusted_surplus']) }}
                                                                </span>
                                                            @else
                                                                <span class="text-slate-400">—</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $pr['packed_at'] }}</td>
                                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $pr['packed_by'] }}</td>
                                                        <td class="px-3 py-2">
                                                            <div class="flex items-center justify-end gap-1.5">
                                                                @if (!$pkgViajeroReceived)
                                                                    <x-ui.icon-btn tone="primary" label="Editar este registro"
                                                                        wire:click="editPackagingRecord({{ $pr['id'] }})">
                                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                                    </x-ui.icon-btn>
                                                                    <x-ui.icon-btn tone="danger" label="Eliminar este registro"
                                                                        wire:click="deletePackagingRecord({{ $pr['id'] }})"
                                                                        wire:confirm="¿Eliminar este registro de empaque?">
                                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                    </x-ui.icon-btn>
                                                                    @if ($pr['surplus_pieces'] > 0)
                                                                        <x-ui.icon-btn tone="neutral" label="Ajustar los sobrantes de este registro"
                                                                            wire:click="startAdjustSurplus({{ $pr['id'] }})">
                                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                                                        </x-ui.icon-btn>
                                                                    @endif
                                                                @else
                                                                    <span class="text-[11px] text-slate-400">Bloqueado</span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    {{-- Ajuste de sobrantes, en línea --}}
                                    @if ($pkgAdjustRecordId)
                                        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/30">
                                            <h5 class="text-sm font-bold text-amber-900 dark:text-amber-100">Ajustar sobrantes</h5>
                                            <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">Usa esto cuando el reconteo físico no coincide con lo calculado.</p>
                                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <x-ui.field label="Sobrantes ajustados" required :error="$errors->first('pkgAdjustedSurplus')">
                                                    <input type="number" wire:model="pkgAdjustedSurplus" min="0"
                                                        class="w-full text-right font-bold tabular-nums">
                                                </x-ui.field>
                                                <x-ui.field label="Motivo del ajuste" required
                                                    hint="Queda registrado para auditoría."
                                                    :error="$errors->first('pkgAdjustmentReason')">
                                                    <input type="text" wire:model="pkgAdjustmentReason" class="w-full"
                                                        placeholder="Ej: Reconteo manual">
                                                </x-ui.field>
                                            </div>
                                            <div class="mt-4 flex flex-wrap justify-end gap-2">
                                                <x-ui.btn variant="secondary" size="sm" wire:click="cancelAdjustSurplus">Cancelar</x-ui.btn>
                                                <x-ui.btn variant="warning" size="sm" wire:click="saveAdjustSurplus">Guardar ajuste</x-ui.btn>
                                            </div>
                                        </div>
                                    @endif
                                </x-ui.section>
                            @endif

                            {{-- 3. Captura de un nuevo empaque --}}
                            @if (($pkgPendingPieces > 0 || $pkgEditingId) && !$pkgViajeroReceived)
                                <x-ui.section step="2" :title="$pkgEditingId ? 'Editar registro de empaque' : 'Registrar empaque'"
                                    hint="Los sobrantes se calculan solos a partir de lo que empacaste; puedes corregirlos.">
                                    @if ($pkgEditingId)
                                        <x-slot:aside>
                                            <x-ui.btn variant="ghost" size="sm" wire:click="cancelEditPackaging">Cancelar edición</x-ui.btn>
                                        </x-slot:aside>
                                    @endif

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <x-ui.field label="Piezas empacadas" required
                                            :hint="'Máximo disponible: '.number_format($pkgPendingPieces).' pz.'"
                                            :error="$errors->first('pkgPackedPieces')">
                                            <input type="number" wire:model.live="pkgPackedPieces" min="0" max="{{ $pkgPendingPieces }}"
                                                class="w-full text-right text-lg font-bold tabular-nums">
                                        </x-ui.field>

                                        <x-ui.field label="Sobrantes"
                                            hint="Se calcula solo. Edítalo si contaste algo distinto.">
                                            <input type="number" wire:model.live="pkgSurplusPieces" min="0"
                                                class="w-full text-right text-lg font-bold tabular-nums">
                                        </x-ui.field>

                                        <x-ui.field label="Fecha y hora" required :error="$errors->first('pkgPackedAt')">
                                            <input type="datetime-local" wire:model="pkgPackedAt" class="w-full">
                                        </x-ui.field>
                                    </div>

                                    <x-ui.field label="Comentarios" optional class="mt-4">
                                        <textarea wire:model="pkgComments" rows="2" class="w-full"
                                            placeholder="Observaciones (opcional)..."></textarea>
                                    </x-ui.field>

                                    <div class="mt-4 flex justify-end">
                                        <x-ui.btn variant="warning" wire:click="savePackaging">
                                            {{ $pkgEditingId ? 'Actualizar registro' : 'Registrar empaque' }}
                                        </x-ui.btn>
                                    </div>
                                </x-ui.section>
                            @elseif ($pkgPendingPieces <= 0 && !$pkgViajeroReceived)
                                <x-ui.section>
                                    <x-ui.note tone="success" title="Todo empacado">
                                        Ya se empacaron todas las piezas disponibles. Continúa recibiendo el lote.
                                    </x-ui.note>
                                </x-ui.section>
                            @endif
                        @else
                            <x-ui.section>
                                <x-ui.note tone="muted" title="Todavía no hay piezas para empacar">
                                    Falta que <strong>Calidad</strong> verifique piezas de este lote. En cuanto haya piezas aprobadas aparecerán aquí.
                                </x-ui.note>
                            </x-ui.section>
                        @endif

                        {{-- 4. Recepción del lote --}}
                        @if ($pkgAlreadyPacked > 0 && !$pkgViajeroReceived)
                            <x-ui.section step="3" title="Recibir el lote"
                                hint="Confirma sólo cuando ya no vayas a registrar más empaque: después queda bloqueado.">
                                <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                    <x-ui.kv label="Total empacadas" :value="number_format($pkgAlreadyPacked).' pz'" tone="good" />
                                    <x-ui.kv label="Sobrantes finales" :value="number_format($pkgTotalSurplus).' pz'" tone="warn" />
                                </dl>
                                <x-ui.btn variant="primary" block class="mt-4"
                                    wire:click="receiveViajero"
                                    wire:confirm="¿Confirma que recibió el lote? Esta acción no se puede deshacer.">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Recibí el lote
                                </x-ui.btn>
                            </x-ui.section>
                        @elseif ($pkgViajeroReceived)
                            <x-ui.section step="3" title="Lote recibido" hint="El siguiente paso lo toma Control de Materiales.">
                                <x-ui.note tone="success">
                                    El lote ya fue recibido. Sigue la <strong>decisión de Materiales</strong>.
                                </x-ui.note>
                                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                    <x-ui.btn variant="accent" wire:click="openDecisionFromPackaging">Ir a la decisión</x-ui.btn>
                                    <x-ui.btn variant="secondary" wire:click="reopenPackaging"
                                        wire:confirm="¿Reabrir el empaque? El lote quedará como no recibido y podrás registrar más piezas.">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Reabrir empaque
                                    </x-ui.btn>
                                </div>
                            </x-ui.section>
                        @endif

            <x-slot:note>
                Cada registro que guardas mueve el semáforo de <strong>Emp.</strong> en la tabla. Los sobrantes hay que entregarlos a Materiales.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closePackagingModal">Cerrar</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
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
            $dSub = $decIsCrimp ? 'Revisa el resultado y elige qué debe ocurrir con este viajero.' : 'Lote '.$selectedLotForDecision->lot_number;
            if (($selectedLotForDecision->completion_count ?? 0) > 0) { $dSub .= ' · Completado '.$selectedLotForDecision->completion_count; }
        @endphp
        <x-ui-modal wire:key="modal-decision"
            :badge="$decIsCrimp ? 'Paso 6' : null"
            :title="$decIsCrimp ? 'Resumen + toma de decisión' : 'Decisión – Control de Materiales'"
            :subtitle="$dSub"
            close="closeDecisionModal"
            maxWidth="7xl"
            bodyClass="px-5 py-5 sm:px-6 sm:py-6 space-y-6 overflow-y-auto overscroll-contain">
            <x-slot:context>
                <x-ui-modal.ctx label="Descripción" :value="$dPart?->description ?? $dPart?->number ?? '—'" />
                <x-ui-modal.ctx :label="$dOrderLabel" :value="$dWoNum.$selectedLotForDecision->lot_number" />
                @if ($decIsCrimp)
                    <x-ui-modal.ctx label="Lote de CRIMP" :value="$dFirstCrimp" />
                @else
                    <x-ui-modal.ctx label="Parte" :value="$dPart?->number ?? '—'" />
                @endif
                <x-ui-modal.ctx :label="$decIsCrimp ? 'Cantidad en viajero' : 'Cantidad en lote'" :value="number_format($decLotTotal)" />
            </x-slot:context>

                        {{-- ==============================================================
                             PASO 1 · Revisar cantidades
                             Siempre primero y siempre de sólo lectura: nadie decide sin
                             ver antes cuánto se empacó, cuánto sobró y cuánto falta.
                             ============================================================== --}}
                        <x-ui.section step="1" title="Revisa las cantidades"
                            hint="Compara lo empacado contra el total. De aquí sale la decisión que tomarás abajo.">

                            @if ($decIsCrimp)
                                <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                    <table class="w-full text-sm">
                                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                                            <tr class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                                <th class="px-3 py-2 text-left font-semibold">Concepto</th>
                                                <th class="px-3 py-2 text-right font-semibold">Total</th>
                                                <th class="px-3 py-2 text-right font-semibold">Empacadas</th>
                                                <th class="px-3 py-2 text-right font-semibold" title="Piezas/CRIMP buenos que NO se empacaron: existen físicamente y Empaque debe entregarlos.">Sobrantes</th>
                                                <th class="px-3 py-2 text-right font-semibold" title="Piezas que faltan del objetivo: se perdieron o las rechazó Calidad. Se reponen al «Completar» o se aceptan al «Cerrar».">Faltantes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                            <tr>
                                                <td class="px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200">Piezas «manguitas»</td>
                                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($decLotTotal) }}</td>
                                                <td class="px-3 py-2.5 text-right font-bold tabular-nums text-green-700 dark:text-green-400">{{ number_format($decPacked) }}</td>
                                                <td class="px-3 py-2.5 text-right tabular-nums text-orange-700 dark:text-orange-400">{{ number_format($decSurplus) }}</td>
                                                <td class="px-3 py-2.5 text-right font-bold tabular-nums {{ $decMissing > 0 ? 'text-red-700 dark:text-red-400' : 'text-slate-400' }}">{{ number_format($decMissing) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200">Piezas CRIMP</td>
                                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($decCrimpTotal) }}</td>
                                                <td class="px-3 py-2.5 text-right font-bold tabular-nums text-cyan-700 dark:text-cyan-400">{{ number_format($decCrimpPacked) }}</td>
                                                <td class="px-3 py-2.5 text-right tabular-nums text-orange-700 dark:text-orange-400">{{ number_format($decCrimpSurplus) }}</td>
                                                <td class="px-3 py-2.5 text-right font-bold tabular-nums {{ $decCrimpMissing > 0 ? 'text-red-700 dark:text-red-400' : 'text-slate-400' }}">{{ number_format($decCrimpMissing) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <x-ui.note tone="muted" class="mt-3">
                                    <strong>Sobrantes</strong> = piezas buenas que no se empacaron y existen físicamente.
                                    <strong>Faltantes</strong> = piezas que ya no están (perdidas o rechazadas por Calidad).
                                </x-ui.note>
                            @else
                                <x-ui.stats cols="4">
                                    <x-ui.stat label="Total del lote" :value="number_format($decLotTotal)" unit="pz" />
                                    <x-ui.stat label="Empacadas" :value="number_format($decPacked)" unit="pz" tone="good" />
                                    <x-ui.stat label="Sobrantes" :value="number_format($decSurplus)" unit="pz" tone="warn"
                                        help="Piezas buenas que no se empacaron. Existen físicamente y hay que entregarlas." />
                                    <x-ui.stat label="Faltantes" :value="number_format($decMissing)" unit="pz" tone="bad"
                                        help="Piezas que ya no están: se perdieron o las rechazó Calidad." />
                                </x-ui.stats>
                                <x-ui.note tone="muted" class="mt-3">
                                    Faltantes = Total del lote − Empacadas − Sobrantes.
                                </x-ui.note>
                            @endif

                            {{-- Reconciliación contra el lote original cuando hubo varios ciclos --}}
                            @if ($decPreviousCyclesPacked > 0)
                                @php
                                    $accPacked  = $decPreviousCyclesPacked + $decPacked;
                                    $accSurplus = $decPreviousCyclesSurplus + $decSurplus;
                                    $accTotal   = $accPacked + $accSurplus + $decMissing;
                                    $accMatches = $accTotal === (int) $decOriginalQuantity;
                                @endphp
                                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">Acumulado de todos los ciclos</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Lote original: <strong class="text-slate-700 dark:text-slate-200">{{ number_format($decOriginalQuantity) }}</strong></span>
                                    </div>
                                    <x-ui.stats cols="4">
                                        <x-ui.stat label="Empacado" :value="number_format($accPacked)" tone="good" />
                                        <x-ui.stat label="Sobrantes" :value="number_format($accSurplus)" tone="warn" />
                                        <x-ui.stat label="Faltantes" :value="number_format($decMissing)" tone="bad" />
                                        <x-ui.stat label="Total" :value="number_format($accTotal)" tone="neutral" />
                                    </x-ui.stats>
                                    <p class="mt-3 text-center text-xs {{ $accMatches ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-400' }}">
                                        Empacado + Sobrantes + Faltantes = {{ number_format($accTotal) }} —
                                        {{ $accMatches ? '✓ coincide con el lote original' : '⚠ no coincide con el lote original ('.number_format($decOriginalQuantity).')' }}
                                    </p>
                                </div>
                            @endif
                        </x-ui.section>

                        {{-- ==============================================================
                             PASO 2 · Tomar la decisión
                             ============================================================== --}}
                        @if (!$decClosureDecision)
                            @if ($decIsCrimp)
                                {{-- CRIMP · tres caminos: cerrar, completar (a/b/c) o crear otro viajero. --}}
                                <div x-data="{ sel: null, sub: null }">
                                    <x-ui.section step="2" tone="accent" title="Selecciona qué debe ocurrir ahora"
                                        hint="Elige una opción; abajo aparecerá qué implica y el botón para confirmarla.">

                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <x-ui.choice tone="bad" eyebrow="Opción 1" title="Cerrar con estas cantidades"
                                                desc="Finaliza el viajero tal como está. No crea otro lote."
                                                x-on:click="sel='D1'; sub=null" x-bind:aria-pressed="sel==='D1'" />
                                            <x-ui.choice tone="info" eyebrow="Opción 2" title="Completar lo que falta"
                                                desc="Repone piezas, CRIMP o ambos antes de cerrar."
                                                x-on:click="sel='D2'" x-bind:aria-pressed="sel==='D2'" />
                                            <x-ui.choice tone="good" eyebrow="Opción 3" title="Crear otro viajero"
                                                desc="Usa los sobrantes para iniciar un viajero nuevo."
                                                x-on:click="sel='D3'; sub=null" x-bind:aria-pressed="sel==='D3'" />
                                        </div>

                                        {{-- Detalle D1 · cerrar --}}
                                        <div x-show="sel==='D1'" x-cloak class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-950/30">
                                            <h5 class="text-sm font-bold text-slate-900 dark:text-white">Cerrar con las cantidades actuales</h5>
                                            <ol class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                                                <li>1. Se cierra el viajero sin crear ningún lote nuevo.</li>
                                                <li>2. Se genera el reporte de decisión.</li>
                                                <li>3. Continúa el <strong>Paso 7 · Entrega de viajero</strong>.</li>
                                            </ol>
                                            <x-ui.btn variant="warning" class="mt-4"
                                                wire:click="decisionCloseAsIs"
                                                wire:confirm="¿Cerrar el viajero así como está, sin nuevo lote?"
                                                wire:loading.attr="disabled" wire:target="decisionCloseAsIs">
                                                Confirmar y continuar al paso 7
                                            </x-ui.btn>
                                        </div>

                                        {{-- Detalle D2 · completar --}}
                                        <div x-show="sel==='D2'" x-cloak class="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-4 dark:border-sky-800 dark:bg-sky-950/30">
                                            <h5 class="text-sm font-bold text-slate-900 dark:text-white">¿Qué necesitas completar?</h5>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                Completar CRIMP = piezas sobrantes − CRIMP sobrante = <strong>{{ number_format($decCompletarCrimp) }}</strong>
                                            </p>

                                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                <x-ui.choice tone="accent" title="Sólo CRIMP" desc="Completar el material CRIMP faltante."
                                                    x-on:click="sub='a'" x-bind:aria-pressed="sub==='a'" />
                                                <x-ui.choice tone="good" title="Sólo piezas" desc="Completar las piezas o manguitas faltantes."
                                                    x-on:click="sub='b'" x-bind:aria-pressed="sub==='b'" />
                                                <x-ui.choice tone="info" title="Piezas y CRIMP" desc="Completar ambos materiales."
                                                    x-on:click="sub='c'" x-bind:aria-pressed="sub==='c'" />
                                            </div>

                                            <div x-show="sub==='a'" x-cloak class="mt-4 border-t border-sky-200 pt-4 dark:border-sky-800">
                                                <p class="text-sm text-slate-600 dark:text-slate-300">
                                                    Materiales completa <strong>{{ number_format($decCompletarCrimp) }}</strong> de CRIMP y el viajero sigue al Paso 7.
                                                </p>
                                                <x-ui.btn variant="accent" class="mt-3" wire:click="decisionCompleteCrimp"
                                                    wire:loading.attr="disabled" wire:target="decisionCompleteCrimp">
                                                    Confirmar sólo CRIMP y continuar
                                                </x-ui.btn>
                                            </div>

                                            <div x-show="sub==='b'" x-cloak class="mt-4 border-t border-sky-200 pt-4 dark:border-sky-800">
                                                <p class="text-sm text-slate-600 dark:text-slate-300">
                                                    Materiales envía <strong>{{ number_format($decSurplus) }} pz</strong> a Empaque; Empaque completa las piezas pendientes y el viajero sigue al Paso 7.
                                                </p>
                                                <x-ui.btn variant="success" class="mt-3" wire:click="decisionCompletePieces"
                                                    wire:loading.attr="disabled" wire:target="decisionCompletePieces">
                                                    Confirmar sólo piezas y continuar
                                                </x-ui.btn>
                                            </div>

                                            <div x-show="sub==='c'" x-cloak class="mt-4 border-t border-sky-200 pt-4 dark:border-sky-800">
                                                <p class="text-sm text-slate-600 dark:text-slate-300">
                                                    Se completa el lote de CRIMP (<strong>{{ number_format($decCompletarCrimp) }}</strong>) y además se capturan
                                                    <strong>{{ number_format($decSurplus) }} pz</strong>. Después continúa el Paso 7.
                                                </p>
                                                <x-ui.btn variant="primary" class="mt-3" wire:click="decisionCompleteBoth"
                                                    wire:loading.attr="disabled" wire:target="decisionCompleteBoth">
                                                    Confirmar piezas y CRIMP
                                                </x-ui.btn>
                                            </div>
                                        </div>

                                        {{-- Detalle D3 · nuevo viajero --}}
                                        <div x-show="sel==='D3'" x-cloak class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                                            <h5 class="text-sm font-bold text-slate-900 dark:text-white">Crear otro viajero con los sobrantes</h5>
                                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                                Con {{ number_format($decSurplus) }} pz sobrantes se puede abrir un viajero nuevo de
                                                <strong>{{ number_format(intdiv(max(0, (int) $decSurplus), 100) * 100) }} pz</strong>.
                                            </p>
                                            <x-ui.note tone="warn" class="mt-3">
                                                La cantidad se redondea hacia abajo en múltiplos de 100.
                                            </x-ui.note>
                                            <div class="mt-4 flex flex-wrap gap-2">
                                                <x-ui.btn variant="success" wire:click="decisionNewLot"
                                                    wire:loading.attr="disabled" wire:target="decisionNewLot">
                                                    Crear nuevo viajero
                                                </x-ui.btn>
                                                <x-ui.btn variant="secondary" wire:click="createCrimpLotFromDecision">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    Crear lote de CRIMP
                                                </x-ui.btn>
                                            </div>
                                        </div>
                                    </x-ui.section>
                                </div>
                            @else
                                {{-- NO-CRIMP · mismas tres opciones, en tarjetas idénticas a las de CRIMP. --}}
                                <x-ui.section step="2" tone="accent" title="Selecciona qué debe ocurrir ahora"
                                    hint="Cada opción dice exactamente qué pasa con las piezas de este lote.">
                                    @if ($decSurplus > 0 || $decMissing > 0)
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            @if ($decMissing > 0)
                                                <x-ui.choice tone="info" eyebrow="Opción 1" title="Completar lote"
                                                    :desc="'Reinicia el lote para reprocesar '.number_format($decMissing).' pz faltantes (inspección, producción, calidad y empaque).'"
                                                    wire:click="decisionCompleteLot"
                                                    wire:confirm="¿Completar lote con {{ number_format($decMissing) }} piezas faltantes? El lote se reiniciará para reprocesar esas piezas (inspección, producción, calidad, empaque)." />
                                            @endif
                                            <x-ui.choice tone="good" eyebrow="Opción 2" title="Nuevo lote"
                                                :desc="'Cierra este lote y abre uno nuevo con '.number_format(max(0, $decLotTotal - $decPacked)).' pz.'"
                                                wire:click="decisionNewLot" />
                                            <x-ui.choice tone="warn" eyebrow="Opción 3" title="Cerrar lote"
                                                :desc="'Finaliza aceptando '.number_format($decMissing).' pz faltantes. No se reprocesa nada.'"
                                                wire:click="decisionCloseAsIs"
                                                wire:confirm="¿Cerrar el lote aceptando {{ number_format($decMissing) }} piezas faltantes?" />
                                        </div>
                                    @else
                                        <x-ui.note tone="success" title="Lote completo" class="mb-4">
                                            No hay piezas faltantes ni sobrantes. Puedes cerrarlo directamente.
                                        </x-ui.note>
                                        <x-ui.btn variant="success" block wire:click="decisionCloseAsIs"
                                            wire:confirm="¿Cerrar el lote? No hay piezas faltantes.">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Cerrar lote (completo)
                                        </x-ui.btn>
                                    @endif
                                </x-ui.section>
                            @endif

                        @else
                            {{-- ==========================================================
                                 La decisión ya está tomada: se muestra cuál fue y qué sigue.
                                 ========================================================== --}}
                            @php
                                $closureLabel = match ($decClosureDecision) {
                                    'complete_lot'    => 'Completar lote',
                                    'new_lot'         => 'Nuevo lote creado',
                                    'close_as_is'     => 'Lote cerrado (faltantes aceptados)',
                                    'complete_crimp'  => 'Completar CRIMP',
                                    'complete_pieces' => 'Completar piezas',
                                    'complete_both'   => 'Completar piezas y CRIMP',
                                    default           => $decClosureDecision,
                                };
                                $isCompletion = in_array($decClosureDecision, ['complete_crimp', 'complete_pieces', 'complete_both']);
                            @endphp

                            <x-ui.section step="2" title="Decisión tomada" hint="Ya no se puede elegir otra opción sin reabrir el lote.">
                                <x-ui.note tone="success" :title="$closureLabel">
                                    Esta es la decisión registrada para el lote. Abajo aparece lo que falta para cerrarlo.
                                </x-ui.note>
                            </x-ui.section>

                            <x-ui.section step="3" title="Qué falta para terminar"
                                hint="Este es el único paso pendiente en este momento.">

                                @if ($isCompletion)
                                    @php
                                        $compCrimp  = (int) ($selectedLotForDecision->complete_crimp_qty ?? 0);
                                        $compPieces = (int) ($selectedLotForDecision->complete_pieces_qty ?? 0);
                                    @endphp
                                    <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                        @if ($decClosureDecision !== 'complete_pieces')
                                            <x-ui.kv label="Completar CRIMP" help="Piezas sobrantes − CRIMP sobrante"
                                                :value="number_format($compCrimp).' pz'" tone="info" />
                                        @endif
                                        @if ($decClosureDecision !== 'complete_crimp')
                                            <x-ui.kv label="Completar piezas «manguitas»" help="Materiales las envía a Empaque"
                                                :value="number_format($compPieces).' pz'" tone="good" />
                                        @endif
                                    </dl>

                                    @if (in_array($decClosureDecision, ['complete_pieces', 'complete_both']))
                                        <x-ui.note tone="info" class="mt-4">
                                            Empaque captura las piezas faltantes desde la columna <strong>Emp.</strong> del viajero (Paso 5).
                                        </x-ui.note>
                                    @endif

                                    @if (!$decSurplusReceived)
                                        <x-ui.btn variant="primary" block class="mt-4"
                                            wire:click="confirmSurplusReceived"
                                            wire:confirm="¿Marcar la completación como realizada y continuar al Paso 7?">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Confirmar completado y continuar al Paso 7
                                        </x-ui.btn>
                                    @else
                                        <x-ui.note tone="success" class="mt-4">
                                            Completado. Continúa el <strong>Paso 7 · entrega de viajero</strong>.
                                        </x-ui.note>
                                    @endif

                                @elseif ($decSurplus > 0)
                                    @if (!$decSurplusDelivered)
                                        <x-ui.note tone="warn" title="Falta que Empaque entregue el sobrante">
                                            Empaque debe entregar <strong>{{ number_format($decSurplus) }} pz</strong> a Control de Materiales.
                                            El botón está en la columna <strong>Seguimiento</strong> de la tabla.
                                        </x-ui.note>
                                    @elseif (!$decSurplusReceived)
                                        <x-ui.note tone="info" title="Falta confirmar la recepción">
                                            Empaque ya entregó <strong>{{ number_format($decSurplus) }} pz</strong> sobrantes. Confirma que las recibiste.
                                        </x-ui.note>
                                        <x-ui.btn variant="danger" block class="mt-4"
                                            wire:click="confirmSurplusReceived"
                                            wire:confirm="¿Confirma que se recibieron {{ number_format($decSurplus) }} piezas sobrantes?">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            Confirmar material recibido
                                        </x-ui.btn>
                                    @else
                                        <x-ui.note tone="success" title="Ciclo terminado">
                                            Material sobrante recibido. El lote quedó completado.
                                        </x-ui.note>
                                    @endif

                                @else
                                    @if (!$decSurplusReceived)
                                        <x-ui.note tone="info" title="Falta confirmar la recepción">
                                            Todas las piezas fueron empacadas y no hubo sobrantes. Confirma la recepción para cerrar el lote.
                                        </x-ui.note>
                                        <x-ui.btn variant="danger" block class="mt-4"
                                            wire:click="confirmSurplusReceived"
                                            wire:confirm="¿Confirma la recepción de material del lote?">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            Confirmar material recibido
                                        </x-ui.btn>
                                    @else
                                        <x-ui.note tone="success" title="Ciclo terminado">
                                            Material recibido. El lote quedó completado.
                                        </x-ui.note>
                                    @endif
                                @endif
                            </x-ui.section>

                            {{-- Reabrir: acción destructiva, siempre al final y visualmente aparte. --}}
                            <x-ui.section title="Corregir la decisión" hint="Úsalo sólo si la decisión anterior fue un error.">
                                @if ($decClosureDecision !== 'complete_lot')
                                    @if (auth()->user()?->can(\App\Services\ReopeningService::PERMISSION))
                                        <x-ui.btn variant="secondary" block wire:click="openReopenModal">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            Reabrir lote y anular la decisión
                                        </x-ui.btn>
                                    @else
                                        <x-ui.note tone="muted">
                                            La decisión ya está tomada. Sólo Administración puede reabrirla:
                                            pídelo indicando el viajero y qué hay que corregir.
                                        </x-ui.note>
                                    @endif
                                @else
                                    <x-ui.note tone="muted">
                                        Este lote fue completado y reiniciado. La decisión anterior ya no se puede reabrir.
                                    </x-ui.note>
                                @endif
                            </x-ui.section>
                        @endif

            <x-slot:note>
                @if ($decIsCrimp)
                    Elijas la opción que elijas, el viajero continúa al <strong>Paso 7</strong> (entrega) y luego al <strong>Paso 8</strong> (sobrantes).
                @else
                    La decisión define si el lote se reprocesa, se divide o se cierra. Queda registrada con tu usuario.
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeDecisionModal">Cerrar</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- ================================================================ --}}
    {{-- MODAL: Crear Lote (from Decision) --}}
    {{-- ================================================================ --}}
    @if ($showCreateLotFormModal && $selectedLotForDecision)
        <x-ui-modal wire:key="modal-create-lot"
            :title="$decIsCrimp ? 'Crear nuevo viajero' : 'Crear nuevo lote'"
            :subtitle="$createLotType === 'complete' ? 'Completa las piezas faltantes del lote actual.' : 'Cierra el lote actual y registra el siguiente.'"
            close="closeCreateLotFormModal" maxWidth="3xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$selectedLotForDecision->workOrder->purchaseOrder->wo ?? 'N/A'" />
                <x-ui-modal.ctx label="Parte" :value="$selectedLotForDecision->workOrder->purchaseOrder->part->number ?? 'N/A'" />
                <x-ui-modal.ctx label="Lote actual" :value="$selectedLotForDecision->lot_number" />
                <x-ui-modal.ctx label="Piezas faltantes" :value="number_format($decMissing)" />
            </x-slot:context>
                        {{-- Info banner --}}
                        @if ($decIsCrimp)
                            <x-ui.note tone="info" title="Parte con CRIMP">
                                Se creará un <strong>nuevo viajero</strong>. Sus lotes de CRIMP se capturan después, desde la columna
                                <strong>Material</strong> de la tabla.
                            </x-ui.note>
                        @endif

                        <x-ui.section :title="$decIsCrimp ? 'Datos del nuevo viajero' : 'Datos del nuevo lote'"
                            hint="El número identifica el registro en piso; la cantidad define cuántas piezas arrastra.">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-ui.field :label="$decIsCrimp ? 'Número de viajero' : 'Número de lote'" required
                                    hint="Como aparecerá en la lista de envío."
                                    :error="$errors->first('createLotName')">
                                    <input type="text" wire:model="createLotName" class="w-full">
                                </x-ui.field>

                                <x-ui.field label="Cantidad (piezas)" required
                                    :hint="'Faltantes del lote actual: '.number_format($decMissing).' pz.'"
                                    :error="$errors->first('createLotQuantity')">
                                    <input type="number" wire:model="createLotQuantity" min="1"
                                        class="w-full text-right text-lg font-bold tabular-nums">
                                </x-ui.field>
                            </div>
                        </x-ui.section>

                        <x-ui.section title="Resumen de lo que se va a crear" hint="Revísalo antes de confirmar: el registro se crea de inmediato.">
                            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                <x-ui.kv :label="$decIsCrimp ? 'Nuevo viajero' : 'Nuevo lote'"
                                    :value="'"'.$createLotName.'" — '.number_format((int) $createLotQuantity).' pz'" />
                                @if ($decIsCrimp)
                                    <x-ui.kv label="Lotes de CRIMP" value="Se capturan después, en Materiales" tone="info" />
                                @endif
                                @if ($createLotType === 'new_lot')
                                    <x-ui.kv label="Lote actual" value="Se cerrará" tone="warn" />
                                @endif
                            </dl>
                        </x-ui.section>

            <x-slot:note>
                Revisa el número y la cantidad: el registro se crea de inmediato y aparecerá en la lista de envío.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeCreateLotFormModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="confirmCreateLot" wire:loading.attr="disabled" wire:target="confirmCreateLot">
                    Crear {{ $decIsCrimp ? 'nuevo viajero' : 'lote' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- ================================================================ --}}
    {{-- MODAL: Entregar Material Sobrante --}}
    {{-- ================================================================ --}}
    @if ($showDeliverMaterialModal && $selectedLotForDelivery)
        <x-ui-modal wire:key="modal-deliver-material" title="Entregar material sobrante"
            subtitle="Confirma la cantidad que se entrega a Control de Materiales."
            close="closeDeliverMaterialModal" maxWidth="3xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$selectedLotForDelivery->workOrder->purchaseOrder->wo ?? 'N/A'" />
                <x-ui-modal.ctx label="Parte" :value="$selectedLotForDelivery->workOrder->purchaseOrder->part->number ?? 'N/A'" />
                <x-ui-modal.ctx :label="($selectedLotForDelivery->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote'" :value="$selectedLotForDelivery->lot_number" />
                <x-ui-modal.ctx label="Sobrantes" :value="number_format($deliverSurplusAmount).' piezas'" />
            </x-slot:context>

                        <x-ui.section title="Material sobrante por entregar"
                            hint="Empaque entrega físicamente estas piezas a Control de Materiales.">
                            <div class="rounded-lg bg-amber-50 px-4 py-5 text-center dark:bg-amber-900/20">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Cantidad a entregar</div>
                                <div class="mt-1 text-4xl font-bold tabular-nums text-amber-700 dark:text-amber-300">
                                    {{ number_format($deliverSurplusAmount) }}
                                    <span class="text-base font-medium">piezas</span>
                                </div>
                            </div>
                        </x-ui.section>

                        <x-ui.section title="De dónde sale esta cantidad" hint="Verifica que coincida con lo que tienes físicamente antes de confirmar.">
                            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                <x-ui.kv label="Lote / viajero" :value="$selectedLotForDelivery->lot_number" />
                                <x-ui.kv label="Cantidad del lote" :value="number_format($selectedLotForDelivery->quantity).' pz'" />
                                <x-ui.kv label="Empacadas" :value="number_format($selectedLotForDelivery->getPackagingPackedPieces()).' pz'" tone="good" />
                                <x-ui.kv label="Sobrantes a entregar" :value="number_format($deliverSurplusAmount).' pz'" tone="warn" />
                            </dl>
                        </x-ui.section>

            <x-slot:note>
                Al confirmar queda registrado que Empaque entregó el sobrante. Después, <strong>Materiales</strong> debe confirmar que lo recibió.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeDeliverMaterialModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="warning" wire:click="confirmDeliverMaterial"
                    wire:loading.attr="disabled" wire:target="confirmDeliverMaterial"
                    wire:confirm="¿Confirma la entrega de {{ number_format($deliverSurplusAmount) }} piezas sobrantes del Lote {{ $selectedLotForDelivery->lot_number }}?">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Confirmar entrega
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Modal de Pesada (Calidad) por Lote --}}
    @if ($showQualityModal && $selectedLotForQuality)
        <x-ui-modal wire:key="modal-quality" title="Pesada de calidad"
            subtitle="Revisa el avance y registra piezas aprobadas y rechazadas."
            close="closeQualityModal" maxWidth="6xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$selectedLotForQuality->workOrder->purchaseOrder->wo ?? 'N/A'" />
                <x-ui-modal.ctx label="Parte" :value="$selectedLotForQuality->workOrder->purchaseOrder->part->number ?? 'N/A'" />
                <x-ui-modal.ctx :label="($selectedLotForQuality->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote'" :value="$selectedLotForQuality->lot_number" />
                <x-ui-modal.ctx label="Pendiente por verificar" :value="number_format($qualRemainingPieces).' piezas'" />
            </x-slot:context>
                        {{-- 1. Lectura --}}
                        <x-ui.section title="Avance de la verificación" hint="Calidad sólo puede verificar piezas que Producción ya pesó.">
                            <x-ui.stats cols="3">
                                <x-ui.stat label="Pesadas por Producción" :value="number_format($qualProductionGoodPieces)" unit="pz" />
                                <x-ui.stat label="Ya verificadas" :value="number_format($qualAlreadyWeighed)" unit="pz" tone="info" />
                                <x-ui.stat label="Pendientes" :value="number_format($qualRemainingPieces)" unit="pz"
                                    :tone="$qualRemainingPieces > 0 ? 'warn' : 'good'" />
                            </x-ui.stats>
                        </x-ui.section>

                        {{-- 2. Historial --}}
                        @if (count($qualWeighingsList) > 0)
                            <x-ui.section title="Pesadas ya registradas" hint="Puedes corregir o eliminar una pesada mientras el lote no se haya cerrado.">
                                <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                    <table class="w-full text-sm">
                                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                                            <tr class="text-[11px] uppercase tracking-wide">
                                                <th class="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400">Fecha</th>
                                                <th class="px-3 py-2 text-right font-semibold text-green-700 dark:text-green-400">Aprobadas</th>
                                                <th class="px-3 py-2 text-right font-semibold text-red-700 dark:text-red-400">Rechazadas</th>
                                                <th class="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400">Por</th>
                                                <th class="px-3 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                            @foreach ($qualWeighingsList as $qw)
                                                <tr wire:key="qual-w-{{ $qw['id'] }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $qw['weighed_at'] }}</td>
                                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-green-700 dark:text-green-400">{{ number_format($qw['good_pieces']) }}</td>
                                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-red-700 dark:text-red-400">
                                                        {{ number_format($qw['bad_pieces']) }}
                                                        @if ($qw['bad_pieces'] > 0)
                                                            <span class="ml-1 text-[11px] font-normal text-slate-400">descarte</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $qw['weighed_by'] }}</td>
                                                    <td class="px-3 py-2">
                                                        <div class="flex items-center justify-end gap-1.5">
                                                            <x-ui.icon-btn tone="primary" label="Editar esta pesada"
                                                                wire:click="editQualityWeighing({{ $qw['id'] }})">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                            </x-ui.icon-btn>
                                                            <x-ui.icon-btn tone="danger" label="Eliminar esta pesada"
                                                                wire:click="deleteQualityWeighing({{ $qw['id'] }})"
                                                                wire:confirm="¿Eliminar esta pesada de calidad?">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </x-ui.icon-btn>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </x-ui.section>
                        @endif

                        {{-- 3. Captura --}}
                        @if ($qualRemainingPieces > 0 || $qualEditingId)
                            <x-ui.section :title="$qualEditingId ? 'Editar pesada de calidad' : 'Nueva pesada de calidad'"
                                hint="Aprobadas y rechazadas se suman: el total no puede pasar de las piezas pendientes.">
                                @if ($qualEditingId)
                                    <x-slot:aside>
                                        <x-ui.btn variant="ghost" size="sm" wire:click="cancelEditQuality">Cancelar edición</x-ui.btn>
                                    </x-slot:aside>
                                @endif

                                <x-ui.note tone="info">
                                    Quedan <strong>{{ number_format($qualRemainingPieces) }} piezas</strong> por verificar en este lote.
                                </x-ui.note>

                                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <x-ui.field label="Piezas aprobadas" required
                                        hint="Piezas que pasan a Empaque."
                                        :error="$errors->first('qualGoodPieces')">
                                        <input wire:model="qualGoodPieces" type="number" min="0" placeholder="0"
                                            class="w-full text-right text-lg font-bold tabular-nums">
                                    </x-ui.field>

                                    <x-ui.field label="Piezas rechazadas" required
                                        hint="Se descartan: no regresan al lote."
                                        :error="$errors->first('qualBadPieces')">
                                        <input wire:model="qualBadPieces" type="number" min="0" placeholder="0"
                                            class="w-full text-right text-lg font-bold tabular-nums">
                                    </x-ui.field>
                                </div>

                                @if ($qualBadPieces > 0)
                                    <x-ui.note tone="danger" class="mt-4">
                                        Las <strong>{{ number_format($qualBadPieces) }} piezas rechazadas</strong> serán descartadas y no se podrán recuperar.
                                    </x-ui.note>
                                @endif

                                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <x-ui.field label="Fecha y hora" required :error="$errors->first('qualWeighedAt')">
                                        <input wire:model="qualWeighedAt" type="datetime-local" class="w-full">
                                    </x-ui.field>

                                    <x-ui.field label="Comentarios" optional>
                                        <textarea wire:model="qualComments" rows="2" class="w-full"
                                            placeholder="Observaciones (opcional)..."></textarea>
                                    </x-ui.field>
                                </div>
                            </x-ui.section>
                        @else
                            <x-ui.section>
                                <x-ui.note tone="success" title="Lote verificado por completo">
                                    Calidad ya revisó todas las piezas que Producción pesó. Sigue <strong>Empaque</strong>.
                                </x-ui.note>
                            </x-ui.section>
                        @endif

            <x-slot:note>
                Aprobadas + rechazadas no pueden superar las piezas pendientes. Las rechazadas se descartan.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeQualityModal">Cerrar</x-ui.btn>
                @if ($qualRemainingPieces > 0 || $qualEditingId)
                    <x-ui.btn variant="primary" wire:click="saveQuality" wire:loading.attr="disabled" wire:target="saveQuality">
                        {{ $qualEditingId ? 'Actualizar pesada' : 'Registrar pesada' }}
                    </x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Modal de Pesada (Producción) por Lote --}}
    @if ($showProductionModal && $selectedLotForProduction)
        <x-ui-modal wire:key="modal-production" title="Registrar pesada de producción"
            subtitle="Captura cuántas piezas se pesaron en este registro."
            close="closeProductionModal" maxWidth="4xl">
            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$selectedLotForProduction->workOrder->purchaseOrder->wo ?? 'N/A'" />
                <x-ui-modal.ctx label="Parte" :value="$selectedLotForProduction->workOrder->purchaseOrder->part->number ?? 'N/A'" />
                <x-ui-modal.ctx :label="($selectedLotForProduction->workOrder->purchaseOrder->part->is_crimp ?? false) ? 'Viajero' : 'Lote'" :value="$selectedLotForProduction->lot_number" />
                <x-ui-modal.ctx label="Cantidad" :value="number_format($prodQuantity).' piezas'" />
            </x-slot:context>
                        {{-- 1. Lectura: en qué va el lote. Siempre antes de los campos. --}}
                        <x-ui.section title="Avance de la pesada" hint="Compara lo ya pesado contra la cantidad del lote antes de capturar.">
                            <x-ui.stats cols="3">
                                <x-ui.stat label="Cantidad del lote" :value="number_format($prodQuantity)" unit="pz" />
                                <x-ui.stat label="Ya pesadas" :value="number_format($prodAlreadyWeighed)" unit="pz" tone="info" />
                                <x-ui.stat
                                    :label="$prodRemainingPieces < 0 ? 'Sobrante de producción' : 'Pendiente de pesar'"
                                    :value="$prodRemainingPieces < 0 ? '+'.number_format(abs($prodRemainingPieces)) : number_format($prodRemainingPieces)"
                                    unit="pz"
                                    :tone="$prodRemainingPieces > 0 ? 'info' : ($prodRemainingPieces < 0 ? 'warn' : 'good')" />
                            </x-ui.stats>

                            @if ($prodRemainingPieces < 0)
                                <x-ui.note tone="warn" class="mt-4">
                                    Se pesaron más piezas de las que tiene el lote. Revisa la captura o avisa a Calidad antes de continuar.
                                </x-ui.note>
                            @elseif ($prodRemainingPieces === 0)
                                <x-ui.note tone="success" class="mt-4">
                                    El lote ya está pesado por completo. Sigue <strong>Calidad</strong> con la verificación.
                                </x-ui.note>
                            @endif
                        </x-ui.section>

                        {{-- 2. Historial: corregir o borrar lo ya capturado --}}
                        @if (count($prodWeighingsList) > 0)
                            <x-ui.section title="Pesadas ya registradas"
                                hint="Corrige la cantidad o elimina una pesada mal capturada.">
                                <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                    <table class="w-full text-sm">
                                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                                            <tr class="text-[11px] uppercase tracking-wide">
                                                <th class="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400">Fecha</th>
                                                <th class="px-3 py-2 text-right font-semibold text-sky-700 dark:text-sky-400">Piezas</th>
                                                <th class="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400">Por</th>
                                                <th class="px-3 py-2 text-right font-semibold text-slate-500 dark:text-slate-400">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                            @foreach ($prodWeighingsList as $pw)
                                                <tr wire:key="prod-w-{{ $pw['id'] }}"
                                                    class="hover:bg-slate-50 dark:hover:bg-slate-700/30 {{ $prodEditingId === $pw['id'] ? 'bg-sky-50 dark:bg-sky-900/20' : '' }}">
                                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $pw['weighed_at'] }}</td>
                                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-sky-700 dark:text-sky-400">{{ number_format($pw['good_pieces']) }}</td>
                                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $pw['weighed_by'] }}</td>
                                                    <td class="px-3 py-2">
                                                        <div class="flex items-center justify-end gap-1.5">
                                                            <x-ui.icon-btn tone="primary" label="Editar esta pesada"
                                                                wire:click="editProductionWeighing({{ $pw['id'] }})"
                                                                wire:loading.attr="disabled" wire:target="editProductionWeighing({{ $pw['id'] }})">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                            </x-ui.icon-btn>
                                                            <x-ui.icon-btn tone="danger" label="Eliminar esta pesada"
                                                                wire:click="deleteProductionWeighing({{ $pw['id'] }})"
                                                                wire:confirm="¿Eliminar esta pesada de producción?"
                                                                wire:loading.attr="disabled" wire:target="deleteProductionWeighing({{ $pw['id'] }})">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </x-ui.icon-btn>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </x-ui.section>
                        @endif

                        {{-- 3. Captura --}}
                        <x-ui.section :title="$prodEditingId ? 'Corregir la pesada' : 'Registrar la pesada'"
                            hint="Estos datos quedan guardados con tu usuario y la hora del sistema.">
                            @if ($prodEditingId)
                                <x-slot:aside>
                                    <x-ui.btn variant="ghost" size="sm" wire:click="cancelEditProduction">Cancelar edición</x-ui.btn>
                                </x-slot:aside>
                            @endif

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-ui.field label="Piezas pesadas" required
                                    hint="Cuántas piezas entraron en esta pesada."
                                    :error="$errors->first('prodWeighedPieces')">
                                    <input wire:model="prodWeighedPieces" type="number" min="0" placeholder="0"
                                        class="w-full text-right text-lg font-bold tabular-nums">
                                </x-ui.field>

                                <x-ui.field label="Fecha y hora de pesada" required
                                    hint="Ajusta sólo si estás capturando una pesada anterior."
                                    :error="$errors->first('prodWeighedAt')">
                                    <input wire:model="prodWeighedAt" type="datetime-local" class="w-full">
                                </x-ui.field>
                            </div>

                            <x-ui.field label="Comentarios" optional class="mt-4"
                                hint="Anota cualquier detalle que el siguiente turno deba saber.">
                                <textarea wire:model="prodComments" rows="2" class="w-full"
                                    placeholder="Observaciones (opcional)..."></textarea>
                            </x-ui.field>
                        </x-ui.section>

            <x-slot:note>
                @if ($prodEditingId)
                    Al guardar se corrige esa pesada; el total de <strong>Producción</strong> se recalcula solo.
                @else
                    Al registrar, el semáforo de <strong>Producción</strong> avanza y el lote queda listo para que Calidad verifique.
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeProductionModal">Cerrar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveProduction" wire:loading.attr="disabled" wire:target="saveProduction">
                    {{ $prodEditingId ? 'Actualizar pesada' : 'Registrar pesada' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Paso 5 · Confirmación de empaque (CRIMP) y Paso 7 · Entrega de viajero.
         Mismo markup que usa la vista de Empaque: viven en partials para que el
         tablero y el departamento no se separen otra vez. --}}
    @include('livewire.admin.sent-lists.partials.modal-confirm-empaque')
    @include('livewire.admin.sent-lists.partials.modal-viajero')

    {{-- Reapertura: qué se va a deshacer, y por qué --}}
    @if ($showReopenModal && $selectedLotForDecision)
        <x-ui-modal wire:key="modal-reabrir-{{ $selectedLotForDecision->id }}"
            title="Reabrir el viajero {{ $selectedLotForDecision->lot_number }}"
            subtitle="Anula la decisión de cierre y devuelve el viajero al flujo para volver a trabajarlo."
            close="closeReopenModal" maxWidth="2xl">

            @if (count($reopenCascade) > 0)
                <x-ui.note tone="warn" title="Esto no afecta sólo al viajero">
                    <p>Para reabrirlo hay que deshacer también:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($reopenCascade as $paso)
                            <li>{{ $paso }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2">Se hace todo junto o no se hace nada.</p>
                </x-ui.note>
            @else
                <x-ui.note tone="info">
                    El viajero volverá a estar abierto en Empaque. Todavía no está en ningún
                    packing slip, así que no hay nada más que deshacer.
                </x-ui.note>
            @endif

            <x-ui.section step="1" title="¿Por qué se reabre?" tone="accent"
                hint="Queda guardado en el historial junto a tu nombre. Es lo que explicará este cambio dentro de un año.">
                <x-ui.field label="Motivo" required
                    hint="Mínimo 10 caracteres. Sé concreto: qué estaba mal y quién lo detectó."
                    :error="$errors->first('reopenReason')">
                    <textarea wire:model="reopenReason" rows="3" class="w-full"
                        placeholder="Ej: el cliente reportó 20 piezas menos de las facturadas en el viajero 0042."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>Todo lo que se deshaga queda registrado con tu nombre y la fecha.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeReopenModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="reopenLot">Reabrir</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
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
