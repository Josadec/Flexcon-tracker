<div
    x-data="{ section: '{{ $activeSection }}' }"
    class="min-h-screen bg-zinc-100 dark:bg-zinc-950"
>
    @php
        $tutorialTabStyles = [
            'general' => [
                'on' => 'border-zinc-800 bg-zinc-800 text-white',
                'off' => 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800',
            ],
            'admin' => [
                'on' => 'border-blue-900 bg-blue-900 text-white',
                'off' => 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800',
            ],
            'produccion' => [
                'on' => 'border-amber-800 bg-amber-800 text-white',
                'off' => 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800',
            ],
            'calidad' => [
                'on' => 'border-emerald-800 bg-emerald-800 text-white',
                'off' => 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800',
            ],
            'materiales' => [
                'on' => 'border-slate-700 bg-slate-700 text-white',
                'off' => 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800',
            ],
            'empaques' => [
                'on' => 'border-rose-800 bg-rose-800 text-white',
                'off' => 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800',
            ],
        ];
    @endphp

    {{-- HEADER --}}
    <div class="border-b border-zinc-200 bg-white px-4 py-5 dark:border-zinc-800 dark:bg-zinc-900 sm:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 sm:text-2xl">Tutorial y guía del sistema</h1>
                <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">Flexcon Tracker — Documentación por módulo y rol</p>
            </div>
            <div class="flex items-start sm:items-center">
                <div class="inline-flex items-center gap-2 rounded-md border border-zinc-300 bg-zinc-50 px-3 py-1.5 text-xs font-medium text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                    Rol: {{ $roleLabel }}
                </div>
            </div>
        </div>
    </div>

    <div class="relative z-10 w-full px-4 py-8 sm:px-6 xl:px-8 2xl:px-10">

        @php $user = auth()->user(); @endphp

        {{-- TABS (solo admin) --}}
        @if ($user->hasRole('admin'))
        <div class="mb-8 flex flex-wrap gap-2">
            @foreach ($tabs as $tab)
            @php $ts = $tutorialTabStyles[$tab['key']] ?? $tutorialTabStyles['general']; @endphp
            <button
                type="button"
                @click="section = '{{ $tab['key'] }}'"
                :class="section === '{{ $tab['key'] }}' ? '{{ $ts['on'] }}' : '{{ $ts['off'] }}'"
                class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}" />
                </svg>
                {{ $tab['label'] }}
            </button>
            @endforeach
        </div>
        @endif

        <div class="mb-8 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.7fr)_360px]">
            <div>
                <div class="h-full rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
                @foreach ($sectionMeta as $key => $meta)
                    <div x-show="section === '{{ $key }}'" x-transition.opacity.duration.200ms>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">{{ $meta['eyebrow'] }}</p>
                        <h2 class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ $meta['title'] }}</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $meta['description'] }}</p>

                        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-800/80">
                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">1. Ubica tu módulo</div>
                                <p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-400">Empieza por la pestaña o el bloque que corresponde a tu rol actual.</p>
                            </div>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-800/80">
                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">2. Abre una acción real</div>
                                <p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-400">Usa los accesos rápidos para ir directo al módulo que vas a trabajar hoy.</p>
                            </div>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-800/80">
                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">3. Regresa al detalle</div>
                                <p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-400">Después vuelve a esta guía para consultar pasos, reglas y buenas prácticas.</p>
                            </div>
                        </div>
                    </div>
                @endforeach
                </div>
            </div>

            <div>
                <div class="h-full rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Inicio rápido</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Accesos según la sección activa.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($quickActions as $key => $actions)
                        <div x-show="section === '{{ $key }}'" x-transition.opacity.duration.200ms class="space-y-3">
                            @foreach ($actions as $action)
                                <a href="{{ $action['href'] }}"
                                   class="block rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 transition-colors hover:border-zinc-300 hover:bg-white dark:border-zinc-600 dark:bg-zinc-800 dark:hover:border-zinc-500 dark:hover:bg-zinc-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $action['label'] }}</div>
                                            <div class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-400">{{ $action['description'] }}</div>
                                        </div>
                                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{--                   SECCIÓN: GENERAL                      --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if ($user->hasRole('admin'))
        <div x-show="section === 'general'" x-transition.opacity.duration.300ms class="space-y-8">
        @else
        <div class="space-y-8">
        @endif

            {{-- Bienvenida General --}}
            <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="mb-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Bienvenido a Flexcon Tracker</h2>
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Flexcon Tracker es el sistema ERP de seguimiento de producción de Flexcon. Centraliza el control de órdenes de compra, órdenes de trabajo, producción, calidad, materiales y empaques en una sola plataforma. Este tutorial te guiará por cada módulo según tu rol.</p>
                    </div>
                </div>
            </div>

            {{-- Qué es Flexcon Tracker --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-zinc-50 dark:bg-zinc-700/50 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-zinc-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">¿Qué es Flexcon Tracker?</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">Flexcon Tracker es un sistema de rastreo de producción industrial que permite a cada departamento registrar, consultar y coordinar su trabajo en tiempo real. El sistema está diseñado para eliminar el uso de hojas de cálculo y papeles, centralizando toda la información en una base de datos compartida.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-md p-4 border border-blue-100 dark:border-blue-800">
                            <div class="text-blue-600 dark:text-blue-400 font-semibold text-sm mb-2">Trazabilidad Total</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Cada pieza producida queda registrada con empleado, mesa, turno, hora y cantidad. Puedes rastrear cualquier lote desde su creación hasta el envío al cliente.</p>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-md p-4 border border-green-100 dark:border-green-800">
                            <div class="text-green-600 dark:text-green-400 font-semibold text-sm mb-2">Coordinación en Tiempo Real</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Producción, Calidad, Materiales y Empaques trabajan sobre los mismos datos. Cuando Calidad aprueba un lote, Empaques lo ve de inmediato sin necesidad de comunicación manual.</p>
                        </div>
                        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-md p-4 border border-purple-100 dark:border-purple-800">
                            <div class="text-purple-600 dark:text-purple-400 font-semibold text-sm mb-2">Reportes Automáticos</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">El sistema genera automáticamente Packing Slips, listas de envío, reportes de capacidad y métricas de producción sin necesidad de cálculos manuales.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Flujo General de Producción --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-zinc-50 dark:bg-zinc-700/50 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-zinc-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Flujo General del Proceso Productivo</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">El proceso de producción sigue siempre este orden. Cada etapa está soportada por un módulo del sistema:</p>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        @foreach ([
                            ['label'=>'PO del Cliente','sub'=>'Admin recibe y valida','color'=>'blue','icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                            ['label'=>'Work Order','sub'=>'Admin crea WO','color'=>'blue','icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                            ['label'=>'Surtimiento','sub'=>'Materiales surte','color'=>'purple','icon'=>'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                            ['label'=>'Producción','sub'=>'Se fabrican piezas','color'=>'orange','icon'=>'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                            ['label'=>'Inspección','sub'=>'Calidad aprueba','color'=>'green','icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                            ['label'=>'Empaque','sub'=>'Empaques embala','color'=>'pink','icon'=>'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
                            ['label'=>'Envío','sub'=>'Packing Slip + despacho','color'=>'indigo','icon'=>'M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20'],
                        ] as $i => $step)
                        <div class="flex items-center gap-2">
                            <div class="flex flex-col items-center">
                                <div class="flex h-16 w-16 items-center justify-center rounded-md border border-{{ $step['color'] }}-600 bg-{{ $step['color'] }}-50 dark:border-{{ $step['color'] }}-500 dark:bg-{{ $step['color'] }}-950/50">
                                    <svg class="w-7 h-7 text-{{ $step['color'] }}-600 dark:text-{{ $step['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mt-1 text-center leading-tight">{{ $step['label'] }}</span>
                                <span class="text-xs text-zinc-400 dark:text-zinc-500 text-center leading-tight">{{ $step['sub'] }}</span>
                            </div>
                            @if ($i < 6)
                            <svg class="w-5 h-5 text-zinc-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-md p-4">
                        <p class="text-xs text-amber-700 dark:text-amber-400"><span class="font-bold">Nota sobre rechazos:</span> Si Calidad rechaza un lote, este regresa a Producción con una nota de acción correctiva. Producción corrige y el lote pasa nuevamente a Calidad para re-inspección. Este ciclo puede repetirse hasta que el lote sea aprobado o descartado.</p>
                    </div>
                </div>
            </div>

            {{-- Navegación en el sidebar --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-zinc-50 dark:bg-zinc-700/50 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-zinc-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Cómo navegar el sistema</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">El Menú Lateral (Sidebar)</h4>
                            <ul class="space-y-2">
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-zinc-200 dark:bg-zinc-700 rounded text-xs flex items-center justify-center font-bold flex-shrink-0 mt-0.5">1</span>El sidebar aparece en el lado izquierdo de la pantalla. En móviles se accede con el botón de menú (☰) en la parte superior.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-zinc-200 dark:bg-zinc-700 rounded text-xs flex items-center justify-center font-bold flex-shrink-0 mt-0.5">2</span>Los módulos están agrupados por área: Producción, Calidad, Materiales, Empaques y Administración.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-zinc-200 dark:bg-zinc-700 rounded text-xs flex items-center justify-center font-bold flex-shrink-0 mt-0.5">3</span>El ítem activo aparece resaltado. Puedes hacer clic en cualquier enlace para navegar sin recargar la página.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-zinc-200 dark:bg-zinc-700 rounded text-xs flex items-center justify-center font-bold flex-shrink-0 mt-0.5">4</span>Los grupos con sub-ítems pueden expandirse y colapsarse haciendo clic en el encabezado del grupo.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-zinc-200 dark:bg-zinc-700 rounded text-xs flex items-center justify-center font-bold flex-shrink-0 mt-0.5">5</span>Tu nombre de usuario y foto de perfil aparecen en la parte inferior del sidebar.</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Funciones Globales del Sistema</h4>
                            <ul class="space-y-2">
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-zinc-500 font-bold mt-0.5">•</span><span><strong class="text-zinc-700 dark:text-zinc-300">Búsqueda en tablas:</strong> Escribe en el campo de búsqueda y la tabla se filtra automáticamente después de 300ms sin necesidad de presionar Enter.</span></li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-zinc-500 font-bold mt-0.5">•</span><span><strong class="text-zinc-700 dark:text-zinc-300">Paginación:</strong> Las listas muestran un número limitado de registros. Usa los botones de página al pie de cada tabla para navegar.</span></li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-zinc-500 font-bold mt-0.5">•</span><span><strong class="text-zinc-700 dark:text-zinc-300">Ordenamiento:</strong> Haz clic en el encabezado de una columna para ordenar ascendente o descendente.</span></li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-zinc-500 font-bold mt-0.5">•</span><span><strong class="text-zinc-700 dark:text-zinc-300">Notificaciones toast:</strong> Aparecen en la esquina inferior derecha confirmando acciones (guardado, error, etc.).</span></li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-zinc-500 font-bold mt-0.5">•</span><span><strong class="text-zinc-700 dark:text-zinc-300">Botón Atrás:</strong> Usa siempre los botones del sistema, no el botón atrás del navegador, para evitar pérdida de datos.</span></li>
                            </ul>
                        </div>
                    </div>
                    {{-- Perfil y Configuración --}}
                    <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-5">
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Configuración de Perfil y Apariencia</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white dark:bg-zinc-800 rounded-lg p-3 border border-zinc-200 dark:border-zinc-600">
                                <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-2">Perfil</div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">Accede a <strong>Configuración → Perfil</strong> para actualizar tu nombre, correo electrónico y foto de perfil. Guarda los cambios con el botón "Guardar".</p>
                            </div>
                            <div class="bg-white dark:bg-zinc-800 rounded-lg p-3 border border-zinc-200 dark:border-zinc-600">
                                <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-2">Contraseña</div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">Ve a <strong>Configuración → Contraseña</strong>. Ingresa tu contraseña actual, luego la nueva contraseña dos veces. La contraseña debe tener al menos 8 caracteres.</p>
                            </div>
                            <div class="bg-white dark:bg-zinc-800 rounded-lg p-3 border border-zinc-200 dark:border-zinc-600">
                                <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-2">Modo Oscuro</div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">En <strong>Configuración → Apariencia</strong> puedes cambiar entre modo Claro, Oscuro o Sistema. El modo oscuro usa tonos zinc para mayor comodidad visual.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        @if ($user->hasRole('admin'))
        </div>
        @else
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{--               SECCIÓN: ADMINISTRACIÓN                   --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if ($user->hasRole('admin'))
        <div x-show="section === 'admin'" x-transition.opacity.duration.300ms class="space-y-8">

            <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold mb-2">Panel de Administración</h2>
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Como administrador tienes acceso completo al sistema. Esta guía cubre todos los módulos: Purchase Orders, Work Orders, Catálogos (partes, precios, estándares), Capacity Wizard y la administración del sistema (usuarios, empleados, turnos, mesas, roles y más).</p>
                    </div>
                </div>
            </div>

            {{-- Flujo admin --}}
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                <p class="text-xs text-blue-700 dark:text-blue-400 font-medium">Flujo del Administrador: <span class="font-normal">Recibir PO del cliente → Validar precios → Crear WO → Ejecutar Capacity Wizard → Monitorear producción → Cerrar WO cuando se completa</span></p>
            </div>

            {{-- Dashboard --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">1. Dashboard Principal</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">El Dashboard es la pantalla de inicio del administrador. Ofrece una vista ejecutiva del estado operativo actual de la planta sin necesidad de entrar a cada módulo.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Tarjetas de Métricas</h4>
                            <ul class="space-y-2">
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><span><strong>POs Activas:</strong> Número de Purchase Orders que no han sido completadas ni canceladas.</span></li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><span><strong>WOs en Proceso:</strong> Work Orders con estado diferente a "Completa" o "Cancelada".</span></li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><span><strong>Piezas del Día:</strong> Total de piezas producidas registradas en el día de hoy.</span></li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><span><strong>Lotes Pendientes de Calidad:</strong> Lotes que aún no han sido inspeccionados.</span></li>
                            </ul>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Cómo interpretar los colores</h4>
                            <ul class="space-y-2">
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-green-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Verde: Todo dentro de lo esperado, sin alertas.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-amber-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Amarillo: Situación que requiere atención próxima.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-red-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Rojo: Problema urgente: fecha vencida, rechazo, faltante.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-blue-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Azul: Información general sin carácter de urgencia.</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Purchase Orders --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">2. Purchase Orders (PO)</h3>
                </div>
                <div class="p-6 space-y-6">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Una Purchase Order (Orden de Compra) es el documento inicial del cliente que autoriza a Flexcon a fabricar un conjunto de partes. El ciclo de vida completo de la PO es gestionado por el administrador.</p>

                    {{-- Crear PO --}}
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Cómo crear una nueva PO</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">1</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 pt-1">Ve a <strong>Purchase Orders → Nueva PO</strong> en el sidebar.</p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">2</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 pt-1">Ingresa el <strong>número de PO</strong> tal como aparece en el documento del cliente (ej. PO-2024-001).</p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">3</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 pt-1">Selecciona el <strong>cliente</strong> del catálogo. Si el cliente no existe, debes crearlo primero en Catálogos → Clientes.</p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">4</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 pt-1">Ingresa la <strong>fecha de entrega</strong> comprometida con el cliente.</p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">5</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 pt-1">Agrega las <strong>partes</strong>: haz clic en "Agregar Parte", selecciona del catálogo, ingresa cantidad y precio unitario.</p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">6</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 pt-1">Repite el paso 5 para todas las partes de la PO. Puedes agregar múltiples partes en una sola PO.</p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">7</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 pt-1">Haz clic en <strong>"Guardar PO"</strong>. El sistema valida los precios contra el catálogo antes de guardar.</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                                    <h5 class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Campos requeridos</h5>
                                    <ul class="space-y-1">
                                        <li class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-red-500">*</span> Número de PO (único en el sistema)</li>
                                        <li class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-red-500">*</span> Cliente</li>
                                        <li class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-red-500">*</span> Fecha de entrega</li>
                                        <li class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-red-500">*</span> Al menos una parte con cantidad y precio</li>
                                    </ul>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                                    <p class="text-xs text-blue-700 dark:text-blue-400"><strong>Validación de precios:</strong> El sistema compara el precio ingresado contra el precio registrado en el catálogo para esa parte y cliente. Si hay discrepancia, muestra una alerta y solicita confirmación o corrección antes de guardar.</p>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                                    <h5 class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Estados de una PO</h5>
                                    <ul class="space-y-1">
                                        <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300 rounded-full font-medium">Pendiente</span> Recién creada, sin WOs generadas</li>
                                        <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 rounded-full font-medium">En Proceso</span> Al menos una WO activa</li>
                                        <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 rounded-full font-medium">Completada</span> Todas las WOs completadas</li>
                                        <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-400 rounded-full font-medium">Cancelada</span> PO cancelada por el cliente</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Acciones PO --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h5 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Ver Detalle</h5>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Haz clic en el número de PO o en el ícono de ojo en la lista. Verás todas las partes, cantidades, precios y las WOs generadas a partir de esta PO.</p>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h5 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Generar Work Orders</h5>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Desde el detalle de la PO, haz clic en "Generar WO". El sistema crea automáticamente una Work Order por cada parte de la PO. Puedes también crear WOs manualmente desde Manage PO.</p>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h5 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Editar y Eliminar</h5>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Solo se pueden editar POs en estado Pendiente. Las POs con WOs activas no se pueden eliminar; primero se deben cancelar las WOs. La eliminación requiere confirmación.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Work Orders --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">3. Work Orders — Manage PO</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Diferencia entre PO y WO</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border border-blue-100 dark:border-blue-800">
                                <div class="text-xs font-bold text-blue-600 dark:text-blue-400 mb-1">Purchase Order (PO)</div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">Documento del cliente. Puede incluir múltiples partes diferentes. Es el contrato comercial. Una PO puede abarcar varias entregas y semanas.</p>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 border border-green-100 dark:border-green-800">
                                <div class="text-xs font-bold text-green-600 dark:text-green-400 mb-1">Work Order (WO)</div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">Orden interna de trabajo. Una WO corresponde a UNA parte de la PO. Es la unidad de control de producción. Todo el seguimiento (pesadas, lotes, calidad) se hace por WO.</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Campos de una Work Order</h4>
                            <ul class="space-y-1.5">
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><strong>Número WO:</strong> Generado automáticamente por el sistema.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><strong>Parte:</strong> La pieza a fabricar (del catálogo de partes).</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><strong>Cantidad Requerida:</strong> Piezas totales que pide el cliente.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><strong>Cantidad Producida:</strong> Se actualiza automáticamente con cada pesada registrada.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><strong>Estado:</strong> Estado actual de la WO (configurable).</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><strong>Fecha de inicio y fin:</strong> Período de producción planificado.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-blue-500 font-bold mt-0.5">•</span><strong>PO Asociada:</strong> La PO del cliente de la que proviene esta WO.</li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                                <h5 class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Cambiar estado de una WO</h5>
                                <ol class="space-y-1">
                                    <li class="text-xs text-zinc-600 dark:text-zinc-400">1. Abre el detalle de la WO.</li>
                                    <li class="text-xs text-zinc-600 dark:text-zinc-400">2. Haz clic en el selector de estado actual.</li>
                                    <li class="text-xs text-zinc-600 dark:text-zinc-400">3. Selecciona el nuevo estado del menú desplegable.</li>
                                    <li class="text-xs text-zinc-600 dark:text-zinc-400">4. El cambio se guarda automáticamente y queda registrado en el historial.</li>
                                </ol>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-md p-3">
                                <p class="text-xs text-amber-700 dark:text-amber-400"><strong>Cuándo marcar como Completa:</strong> Solo cuando la cantidad producida aprobada por Calidad iguala o supera la cantidad requerida, Y el Packing Slip ha sido generado. Si queda pendiente, usa "BackOrder".</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Capacity Wizard --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">4. Capacity Wizard — Planificación de Capacidad</h3>
                </div>
                <div class="p-6 space-y-5">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">El Capacity Wizard es una herramienta de 4 pasos que calcula si la planta tiene capacidad suficiente para cumplir con las Work Orders activas en el tiempo disponible, considerando turnos, descansos, tiempo extra y días festivos.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ([
                            ['step'=>'1','title'=>'Seleccionar partes y cantidades','desc'=>'Elige las WOs activas que quieres incluir en el plan. El sistema pre-llena las cantidades requeridas. Puedes ajustar las cantidades objetivo si planeas hacer entregas parciales.','color'=>'blue'],
                            ['step'=>'2','title'=>'Configurar turnos y horarios','desc'=>'Selecciona qué turnos están disponibles (Matutino, Vespertino, Nocturno). El sistema calcula el total de horas productivas por turno, descontando automáticamente los tiempos de descanso configurados.','color'=>'blue'],
                            ['step'=>'3','title'=>'Tiempo extra y días festivos','desc'=>'Agrega horas de tiempo extra autorizado por fecha si las hay. El sistema excluye automáticamente los días festivos registrados en el catálogo. Verás el total de horas disponibles ajustado.','color'=>'blue'],
                            ['step'=>'4','title'=>'Ver resultado y guardar plan','desc'=>'El sistema muestra si la capacidad disponible es suficiente para producir las cantidades requeridas. Verás por parte: horas necesarias vs. horas disponibles, piezas/hora del estándar, y fecha estimada de terminación. Guarda el plan para referencia.','color'=>'green'],
                        ] as $w)
                        <div class="flex gap-4 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <div class="w-10 h-10 rounded-full bg-{{ $w['color'] }}-600 text-white flex items-center justify-center text-sm font-bold flex-shrink-0">{{ $w['step'] }}</div>
                            <div>
                                <h5 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-1">{{ $w['title'] }}</h5>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $w['desc'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-md p-4">
                        <p class="text-xs text-indigo-700 dark:text-indigo-400"><strong>Tip:</strong> El Capacity Wizard usa los "Estándares" del catálogo (piezas por hora por parte) para calcular el tiempo requerido. Si un estándar no está configurado para una parte, el wizard no podrá calcular la capacidad para esa WO. Asegúrate de tener todos los estándares actualizados.</p>
                    </div>
                </div>
            </div>

            {{-- Catálogos --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">5. Catálogos — Partes, Precios y Estándares</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4 space-y-2">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-6 h-6 bg-blue-600 rounded flex items-center justify-center flex-shrink-0"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></div>
                                <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Partes</h4>
                            </div>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Número de parte:</strong> Código único (ej. FL-1234-A)</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Descripción:</strong> Nombre o descripción de la pieza</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Cliente:</strong> A quién pertenece esta parte</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Especificaciones:</strong> Notas técnicas relevantes</li>
                            </ul>
                            <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-2">Usa el buscador para encontrar partes rápidamente. El número de parte debe ser único en el sistema.</p>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4 space-y-2">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-6 h-6 bg-blue-600 rounded flex items-center justify-center flex-shrink-0"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                                <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Precios</h4>
                            </div>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Relación:</strong> Parte + Cliente + Precio unitario</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Vigencia:</strong> Fecha de inicio y fin de cada precio</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Historial:</strong> Todos los precios anteriores quedan guardados</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Moneda:</strong> Se registra en USD (dólares americanos)</li>
                            </ul>
                            <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-2">El precio activo es el que tiene fecha de inicio más reciente y no ha vencido. Se usa para validar POs.</p>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4 space-y-2">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-6 h-6 bg-blue-600 rounded flex items-center justify-center flex-shrink-0"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                                <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Estándares</h4>
                            </div>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Parte:</strong> A qué pieza aplica el estándar</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Piezas/hora:</strong> Cuántas piezas produce un operario por hora</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Tipo proceso:</strong> Manual, semi-automático, automático</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400"><strong>Vigencia:</strong> Fecha de aplicación del estándar</li>
                            </ul>
                            <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-2">Los estándares son fundamentales para el Capacity Wizard. Sin estándar no se puede calcular la capacidad.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Administración del sistema --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">6. Administración del Sistema</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ([
                            ['title'=>'Usuarios','icon'=>'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z','desc'=>'Crear cuenta: ve a Usuarios → Nuevo Usuario. Ingresa nombre, correo y contraseña. Asigna un rol (admin, Produccion, Calidad, Materiales, Empaques). El usuario puede activarse o desactivarse sin eliminarlo. Un usuario desactivado no puede iniciar sesión.'],
                            ['title'=>'Empleados','icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z','desc'=>'Los empleados son los operarios de planta. El número de empleado se genera automáticamente. Registra: nombre completo, área donde trabaja, turno asignado. Los empleados se asocian a las pesadas de producción para trazabilidad.'],
                            ['title'=>'Departamentos y Áreas','icon'=>'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4','desc'=>'La jerarquía es: Departamento → Área → Mesa/Máquina. Ejemplo: Depto. Producción → Área Ensamble → Mesa 01. Esta jerarquía organiza dónde se registra la producción y cómo se asignan los empleados.'],
                            ['title'=>'Mesas y Semi-Automáticos','icon'=>'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z','desc'=>'Mesas: estaciones de trabajo manuales. Cada mesa tiene número único y área asignada. Semi-Automáticos: equipos que asisten al operario. Tienen tipo (prensa, soldadora, etc.) y área. Ambos se usan al registrar pesadas.'],
                            ['title'=>'Roles del Sistema','icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z','desc'=>'Admin: acceso total. Produccion: pesadas, kits, lotes, lista envío. Calidad: inspección, pesadas de calidad, lotes. Materiales: gestión de materiales, kits, lotes. Empaques: packaging, packing slips, shipping queue. Cada rol solo ve los módulos de su área.'],
                            ['title'=>'Turnos y Descansos','icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z','desc'=>'Turnos: Matutino (06:00-14:00), Vespertino (14:00-22:00), Nocturno (22:00-06:00) o configuración personalizada. Cada turno tiene nombre, hora inicio y fin. Descansos: intervalos dentro del turno (almuerzo, café). Se descuentan automáticamente del tiempo productivo en el Capacity Wizard.'],
                            ['title'=>'Días Festivos','icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z','desc'=>'Registra las fechas no laborables del año (feriados nacionales, recesos). Estos días se excluyen automáticamente del cálculo de capacidad en el Wizard. Puedes agregar una descripción (ej. "Día de la Independencia") y activar/desactivar cada festivo.'],
                            ['title'=>'Tiempo Extra','icon'=>'M12 4v16m8-8H4','desc'=>'El tiempo extra debe ser autorizado y registrado en el sistema antes del Capacity Wizard. Registra: fecha, número de horas extra, área o turno que aplica. El Wizard suma estas horas a la capacidad disponible. Esto permite comprometer más producción cuando hay urgencia.'],
                        ] as $item)
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-6 h-6 bg-blue-600 rounded flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/></svg>
                                </div>
                                <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $item['title'] }}</h4>
                            </div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">{{ $item['desc'] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{--                  SECCIÓN: PRODUCCIÓN                    --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if ($user->hasRole('admin') || $user->hasRole('Produccion'))
        <div
            @if ($user->hasRole('admin')) x-show="section === 'produccion'" x-transition.opacity.duration.300ms @endif
            class="space-y-8"
        >
            <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold mb-2">Módulo de Producción</h2>
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">El rol Producción registra el trabajo realizado en planta: pesadas, kits, lotes y listas de envío. Todo lo fabricado pasa por este módulo antes de ir a Calidad.</p>
                    </div>
                </div>
            </div>
            <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-md p-4">
                <p class="text-xs text-orange-700 dark:text-orange-400 font-medium">Flujo: <span class="font-normal">Recibir WO de Admin → Verificar materiales surtidos → Registrar pesadas por turno → Crear Lotes → Enviar a Calidad → Seguimiento en lista de envío</span></p>
            </div>

            {{-- Dashboard Producción --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-orange-50 dark:bg-orange-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">1. Dashboard de Producción</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">El dashboard de Producción muestra el estado actual del turno y las WOs en proceso. Es el punto de partida de cada jornada de trabajo.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Métricas del turno</h4>
                            <ul class="space-y-2">
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold mt-0.5">•</span><strong>Piezas producidas hoy:</strong> Total acumulado del día en todas las WOs activas.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold mt-0.5">•</span><strong>WOs activas:</strong> Work Orders con producción en curso o pendiente.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold mt-0.5">•</span><strong>Pesadas del turno:</strong> Registros ingresados en las últimas horas.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold mt-0.5">•</span><strong>Lotes en inspección:</strong> Enviados a Calidad que aún no tienen resultado.</li>
                            </ul>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Colores de estado</h4>
                            <ul class="space-y-2">
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-green-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Verde: WO al ritmo esperado según estándar.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-amber-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Amarillo: WO por debajo del ritmo, requiere atención.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-red-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Rojo: WO con fecha próxima o vencida.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-orange-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Naranja: Lote rechazado de Calidad que regresó.</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pesadas --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-orange-50 dark:bg-orange-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">2. Pesadas — Registro de Producción</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-md p-4">
                        <h4 class="text-sm font-semibold text-orange-700 dark:text-orange-400 mb-1">Qué es una Pesada</h4>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Una pesada es el registro de la cantidad de piezas producidas por un empleado en una mesa o equipo semi-automático durante un período del turno. Es la unidad mínima de control de producción y queda vinculada permanentemente a una WO, empleado, mesa, turno y hora.</p>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-4">Registrar una pesada — paso a paso</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">1</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Ir al módulo</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Sidebar: Producción → Pesadas. Botón "Nueva Pesada" en la parte superior derecha.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">2</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Buscar la WO</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Escribe el número de WO. El sistema muestra: parte, cantidad requerida, producida hasta ahora y cuánto falta.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">3</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Seleccionar empleado</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Busca por nombre o número de empleado. Solo aparecen empleados activos del área.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">4</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Mesa o Semi-Automático</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Selecciona si fue en una Mesa (manual) o Semi-Automático, luego elige el número o equipo específico.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">5</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Seleccionar turno</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Indica el turno: Matutino, Vespertino o Nocturno. Importante para reportes y cálculos de capacidad.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">6</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Ingresar cantidad</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Número de piezas producidas en esta pesada. El sistema valida que sea positivo y advierte si supera el restante de la WO.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">7</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Registrar hora</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Por defecto toma la hora actual. Puedes ajustarla si la pesada se registra con retraso.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">8</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Guardar</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Clic en "Guardar Pesada". La cantidad de la WO se actualiza automáticamente. Aparece confirmación toast.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Corregir una pesada errónea</h4>
                            <ol class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">1. Localiza la pesada en la lista (filtra por WO o empleado).</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">2. Haz clic en el ícono de editar (lápiz).</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">3. Corrige la cantidad, hora u otros campos.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">4. Guarda. El sistema recalcula el total de la WO automáticamente.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">5. Solo puedes editar pesadas del día actual. Pesadas de días anteriores requieren permiso de admin.</li>
                            </ol>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-2">Cuándo NO registrar una pesada</h4>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Las piezas están en un lote enviado a Calidad con resultado pendiente.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• La WO está en estado "Completada" o "Cancelada".</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Son piezas rechazadas de Calidad (se registran mediante acción correctiva).</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• No tienes certeza del número exacto — espera a contar correctamente.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kits --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-orange-50 dark:bg-orange-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">3. Kits</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-md p-4 mb-4">
                                <h4 class="text-sm font-semibold text-orange-700 dark:text-orange-400 mb-1">Qué es un Kit</h4>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">Un Kit es un conjunto predefinido de partes que se producen o ensamblan juntas. A diferencia de un Lote (piezas de UNA parte), un Kit puede contener múltiples partes distintas con sus cantidades por unidad de kit. Los Kits facilitan el surtimiento de materiales y la planificación de ensambles complejos.</p>
                            </div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Crear un Kit</h4>
                            <ol class="space-y-2">
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-orange-500 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>Ve a Producción → Kits → Nuevo Kit.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-orange-500 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>Ingresa el nombre del kit (ej. "Kit Ensamble A-123").</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-orange-500 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>Agrega una descripción del propósito del kit.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-orange-500 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">4</span>Haz clic en "Agregar Parte" y selecciona cada parte con su cantidad requerida por unidad de kit.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-orange-500 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">5</span>Guarda el Kit. Queda disponible para surtimiento en Materiales.</li>
                            </ol>
                        </div>
                        <div class="space-y-3">
                            <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                                <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Diferencia Kit vs. Lote</h4>
                                <div class="space-y-2">
                                    <div><span class="text-orange-500 font-bold text-xs">KIT:</span><span class="text-xs text-zinc-600 dark:text-zinc-400 ml-1">Plantilla de múltiples partes. Define QUÉ piezas y en qué cantidad. No está atado a una WO. Es reutilizable y sirve de referencia.</span></div>
                                    <div><span class="text-blue-500 font-bold text-xs">LOTE:</span><span class="text-xs text-zinc-600 dark:text-zinc-400 ml-1">Instancia real de producción. Una parte, una WO, fecha y cantidad real. Va físicamente a inspección de Calidad.</span></div>
                                </div>
                            </div>
                            <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                                <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Buscar y ver Kits</h4>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">En la lista de Kits usa el buscador para filtrar por nombre. Haz clic en un Kit para ver su detalle: partes, cantidades y lotes asociados. Solo se puede editar si no tiene lotes activos vinculados.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lotes --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-orange-50 dark:bg-orange-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">4. Lotes</h3>
                </div>
                <div class="p-6 space-y-5">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Un Lote es un conjunto de piezas del mismo tipo producidas en una sesión, que se envían juntas a Calidad. El Lote es la unidad que viaja entre Producción, Calidad y Empaques a lo largo del proceso.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Crear un Lote</h4>
                            <ol class="space-y-2">
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-orange-500 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>Ve a Producción → Lotes → Nuevo Lote.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-orange-500 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>Selecciona la WO a la que pertenece este lote.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-orange-500 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>Ingresa la cantidad de piezas en el lote.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-orange-500 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">4</span>Registra la fecha de producción (por defecto hoy).</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-orange-500 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">5</span>Agrega notas si es necesario (condición especial, observación).</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-orange-500 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">6</span>Guarda. El número de lote se asigna automáticamente. Estado inicial: "En Proceso".</li>
                            </ol>
                        </div>
                        <div class="space-y-3">
                            <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                                <h5 class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Estados del Lote</h5>
                                <ul class="space-y-1.5">
                                    <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-400 rounded-full font-medium">En Proceso</span> Siendo fabricado, no enviado aún</li>
                                    <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 rounded-full font-medium">En Inspección</span> Enviado a Calidad, esperando resultado</li>
                                    <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 rounded-full font-medium">Aprobado</span> Calidad aprobó — avanza a Empaques</li>
                                    <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-400 rounded-full font-medium">Rechazado</span> Calidad rechazó — regresa a Producción</li>
                                    <li class="flex items-center gap-2 text-xs"><span class="px-2 py-0.5 bg-zinc-100 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300 rounded-full font-medium">Terminado</span> Empacado y despachado al cliente</li>
                                </ul>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-md p-3">
                                <p class="text-xs text-red-700 dark:text-red-400"><strong>Lote rechazado:</strong> Cuando Calidad rechaza un lote, regresa a Producción con nota de la razón. Se debe tomar acción correctiva, corregir las piezas y crear un nuevo lote para re-inspección. El lote rechazado queda como registro histórico.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lista de Envío --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-orange-50 dark:bg-orange-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 3h6m-6 4h6m-6 4h4"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">5. Lista de Envío y Listas Preliminares</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Lista de Envío</h4>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Muestra las WOs con producción aprobada por Calidad, listas para despacho. Puedes filtrar por cliente, fecha o estado.</p>
                            <ul class="space-y-1">
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold">•</span>Muestra WO, parte, cantidad aprobada y fecha límite de entrega.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold">•</span>WOs urgentes (fecha próxima) aparecen destacadas en rojo o amarillo.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold">•</span>Producción puede verificar desde aquí qué está listo para empacar.</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Listas Preliminares</h4>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Borrador de la lista de envío para coordinar con Empaques antes de confirmar el despacho definitivo.</p>
                            <ul class="space-y-1">
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold">•</span>La preliminar puede modificarse; la lista final no.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold">•</span>Para confirmar: todos los lotes deben estar en estado Aprobado.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-orange-500 font-bold">•</span>Al confirmar se genera la lista oficial visible para Empaques.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                        <p class="text-xs text-blue-700 dark:text-blue-400"><strong>Monitor TV:</strong> Diseñado para pantallas grandes en el piso de planta. Muestra en tiempo real WOs activas, piezas producidas y estado de cada línea. Se configura desde Producción → Monitor TV. Una vez configurado no requiere sesión activa del usuario.</p>
                    </div>
                </div>
            </div>

        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{--                   SECCIÓN: CALIDAD                      --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if ($user->hasRole('admin') || $user->hasRole('Calidad'))
        <div
            @if ($user->hasRole('admin')) x-show="section === 'calidad'" x-transition.opacity.duration.300ms @endif
            class="space-y-8"
        >
            <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold mb-2">Módulo de Calidad</h2>
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">El rol Calidad inspecciona los lotes provenientes de Producción, registra resultados, aprueba o rechaza, y mantiene los registros de calidad necesarios para la trazabilidad del cliente.</p>
                    </div>
                </div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4">
                <p class="text-xs text-green-700 dark:text-green-400 font-medium">Flujo de Calidad: <span class="font-normal">Recibir lote de Producción → Inspeccionar → Pesar (verificación por peso) → Aprobar o Rechazar → Si aprueba: lote pasa a Empaques. Si rechaza: lote regresa a Producción con nota.</span></p>
            </div>

            {{-- Dashboard Calidad --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-green-50 dark:bg-green-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">1. Dashboard de Calidad</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-md p-4 border border-green-100 dark:border-green-800">
                            <div class="text-green-600 dark:text-green-400 font-semibold text-sm mb-1">Lotes Pendientes</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Lotes en estado "En Inspección" que aún no tienen resultado. Son los que Calidad debe procesar de forma prioritaria hoy.</p>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-md p-4 border border-red-100 dark:border-red-800">
                            <div class="text-red-600 dark:text-red-400 font-semibold text-sm mb-1">Rechazos del Día</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Número de lotes rechazados en el día actual. Un rechazo alto indica un problema en el proceso productivo que debe escalarse al supervisor.</p>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-md p-4 border border-blue-100 dark:border-blue-800">
                            <div class="text-blue-600 dark:text-blue-400 font-semibold text-sm mb-1">Tasa de Aprobación</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Porcentaje de lotes aprobados vs. total inspeccionados en el período. Una tasa menor al 90% es una señal de alerta para el equipo de producción.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Inspección --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-green-50 dark:bg-green-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">2. Inspección de Lotes</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4">
                        <h4 class="text-sm font-semibold text-green-700 dark:text-green-400 mb-2">¿Qué se inspecciona y cuándo?</h4>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Calidad inspecciona cada lote que llega de Producción antes de que las piezas puedan ser empacadas y enviadas al cliente. La inspección puede ser dimensional (medidas), visual (apariencia, acabado) o funcional (prueba de operación), según las especificaciones de cada parte.</p>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-4">Proceso de inspección — paso a paso</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">1</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Recibir el lote</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Ve a Calidad → Inspección. La lista muestra todos los lotes en estado "En Inspección" enviados por Producción.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">2</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Abrir el lote</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Haz clic en el lote a inspeccionar. Verás número de WO, parte, cantidad, fecha de producción y especificaciones de la parte.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">3</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Inspección física</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Realiza la inspección del lote físico según los criterios de la parte: medidas dimensionales, inspección visual del acabado, prueba funcional si aplica.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">4</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Registrar piezas aprobadas y rechazadas</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Ingresa cuántas piezas pasan la inspección y cuántas son rechazadas. El sistema calcula automáticamente la diferencia respecto al lote total.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">5</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Tipo de defecto</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Si hay rechazos, selecciona el tipo: Dimensional (fuera de tolerancia), Visual (rayones, marcas, color), o Funcional (no opera correctamente).</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">6</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Agregar observaciones</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Escribe la descripción del defecto encontrado. Esta nota es esencial para que Producción entienda qué corregir en la acción correctiva.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">7</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Firma digital</div><p class="text-xs text-zinc-600 dark:text-zinc-400">El inspector firma digitalmente el registro de inspección en el campo de firma. Esto garantiza la trazabilidad y responsabilidad del resultado.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-green-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">8</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Emitir resultado</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Selecciona "Aprobar" o "Rechazar". Si aprueba: el lote avanza a Empaques automáticamente. Si rechaza: el lote regresa a Producción con la nota de defecto.</p></div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4">
                            <h5 class="text-sm font-semibold text-green-700 dark:text-green-400 mb-2">Cuando se APRUEBA</h5>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• El lote cambia a estado "Aprobado".</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Empaques puede verlo en su lista de WOs listos para empacar.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• La cantidad aprobada se suma al contador de la WO en el sistema.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Se genera un registro de inspección con firma que queda en el historial.</li>
                            </ul>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-md p-4">
                            <h5 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-2">Cuando se RECHAZA</h5>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• El lote cambia a estado "Rechazado".</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Producción recibe una notificación con la nota de rechazo.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Producción debe corregir y crear un nuevo lote para re-inspección.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• El registro queda en el historial con el motivo del rechazo y la firma del inspector.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pesadas de Calidad --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-green-50 dark:bg-green-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">3. Pesadas de Calidad (Verificación por Peso)</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Las pesadas de Calidad son un método de verificación que compara el peso real de un lote con el peso estándar esperado. Esta técnica permite detectar rápidamente si hay piezas faltantes, excedentes o piezas con defecto de material sin necesitar contar pieza por pieza.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Campos del registro</h4>
                            <ul class="space-y-1.5">
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-green-500 font-bold mt-0.5">•</span><strong>WO:</strong> A qué Work Order pertenece la pesada de calidad.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-green-500 font-bold mt-0.5">•</span><strong>Báscula:</strong> Identificador del equipo de medición utilizado.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-green-500 font-bold mt-0.5">•</span><strong>Peso registrado:</strong> Peso real medido del lote en kilogramos.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-green-500 font-bold mt-0.5">•</span><strong>Peso estándar:</strong> Peso esperado según el estándar de la parte (cargado automáticamente).</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-green-500 font-bold mt-0.5">•</span><strong>Diferencia:</strong> Calculada automáticamente. Positiva = exceso. Negativa = faltante.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-green-500 font-bold mt-0.5">•</span><strong>Resultado:</strong> Aceptable o No Aceptable según umbral de tolerancia.</li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                                <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Umbrales de tolerancia</h4>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mb-2">Cada parte tiene una tolerancia definida en el estándar (ej. ±2%). Si la diferencia porcentual entre peso real y peso estándar cae dentro del umbral, el resultado es Aceptable. Si supera el umbral, es No Aceptable y se requiere revisión manual.</p>
                                <div class="flex gap-2">
                                    <span class="px-2 py-1 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 text-xs rounded-lg font-medium">Dentro de tolerancia = Aceptable</span>
                                </div>
                                <div class="flex gap-2 mt-1">
                                    <span class="px-2 py-1 bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-400 text-xs rounded-lg font-medium">Fuera de tolerancia = Revisar</span>
                                </div>
                            </div>
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-3">
                                <p class="text-xs text-blue-700 dark:text-blue-400"><strong>Después de guardar:</strong> La pesada de calidad queda registrada y vinculada a la inspección del lote. Si el resultado es No Aceptable, Calidad decide si rechaza el lote completo o investiga la causa antes de emitir resultado final.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lista de envío calidad --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-green-50 dark:bg-green-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 3h6m-6 4h6"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">4. Lista de Envío y Monitor TV de Calidad</h3>
                </div>
                <div class="p-6 space-y-3">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">La Lista de Envío de Calidad muestra todas las WOs cuyos lotes han sido aprobados por el departamento. Esta vista confirma qué partidas están autorizadas para pasar a Empaques.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Lista de Envío de Calidad</h4>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Muestra WO, parte, cantidad aprobada, fecha de inspección e inspector responsable.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Sirve como respaldo documental para auditorías de calidad.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Puedes exportar o imprimir el reporte desde esta vista.</li>
                            </ul>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Listas Preliminares y Monitor TV</h4>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Las Listas Preliminares permiten coordinar con Producción antes de confirmar el cierre de inspección de un lote.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• El Monitor TV de Calidad muestra en tiempo real el estado de lotes en inspección en la sala de calidad.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{--                  SECCIÓN: MATERIALES                    --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if ($user->hasRole('admin') || $user->hasRole('Materiales'))
        <div
            @if ($user->hasRole('admin')) x-show="section === 'materiales'" x-transition.opacity.duration.300ms @endif
            class="space-y-8"
        >
            <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold mb-2">Módulo de Materiales</h2>
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">El rol Materiales es responsable de surtir los materiales e insumos necesarios para que Producción pueda iniciar el trabajo en cada Work Order. Sin el surtimiento registrado, Producción no puede comenzar.</p>
                    </div>
                </div>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-md p-4">
                <p class="text-xs text-purple-700 dark:text-purple-400 font-medium">Flujo de Materiales: <span class="font-normal">Recibir WO activa → Revisar materiales requeridos → Surtir materiales → Confirmar surtimiento completo → Producción puede iniciar → Seguimiento por lotes</span></p>
            </div>

            {{-- Dashboard Materiales --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-purple-50 dark:bg-purple-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">1. Dashboard de Materiales</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">El dashboard de Materiales muestra las WOs activas ordenadas por prioridad. Las que tienen fecha de entrega más próxima aparecen primero y con color de alerta.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Cómo leer las prioridades</h4>
                            <ul class="space-y-2">
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-red-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Rojo: Fecha de entrega hoy o ya vencida. Surtir de inmediato.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-amber-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Amarillo: Entrega en los próximos 3 días. Alta prioridad.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-green-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Verde: Entrega en más de 3 días. Sin urgencia inmediata.</span></li>
                                <li class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-full bg-purple-500 flex-shrink-0"></span><span class="text-zinc-600 dark:text-zinc-400">Morado: WO con surtimiento parcial en progreso.</span></li>
                            </ul>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Qué ordena las WOs</h4>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mb-2">El dashboard ordena las WOs por: 1) Estado de surtimiento (sin surtir primero), 2) Fecha de entrega más próxima, 3) Número de WO como desempate. Puedes reordenar haciendo clic en cualquier encabezado de columna.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Gestión de Materiales --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-purple-50 dark:bg-purple-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">2. Gestión de Materiales — Proceso de Surtimiento</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-4">Cómo surtir una WO — paso a paso</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">1</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Seleccionar la WO</div><p class="text-xs text-zinc-600 dark:text-zinc-400">En el dashboard o en la lista, haz clic en la WO que vas a surtir. Puedes buscarla por número de WO, parte o cliente.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">2</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Ver materiales requeridos</div><p class="text-xs text-zinc-600 dark:text-zinc-400">El sistema muestra la lista de materiales e insumos necesarios por cada parte de la WO, con las cantidades requeridas y las ya surtidas.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">3</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Marcar como surtido</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Para cada material que tengas disponible, marca la casilla o ingresa la cantidad surtida. Si tienes el 100%, márcalo completo.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">4</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Surtimiento parcial</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Si no tienes suficiente de algún material, ingresa la cantidad parcial disponible y registra el motivo del faltante (ej. "Pendiente de recibir de proveedor", "Stock insuficiente").</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">5</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Confirmar surtimiento</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Cuando todos los materiales estén surtidos al 100%, haz clic en "Confirmar Surtimiento Completo". Esto habilita a Producción para iniciar el trabajo en esa WO.</p></div>
                            </div>
                            <div class="flex gap-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-3">
                                <div class="w-7 h-7 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">6</div>
                                <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-0.5">Notificación a Producción</div><p class="text-xs text-zinc-600 dark:text-zinc-400">Al confirmar el surtimiento, la WO cambia de estado y Producción puede verla como disponible para trabajar. El sistema registra quién realizó el surtimiento y a qué hora.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-md p-4">
                        <p class="text-xs text-indigo-700 dark:text-indigo-400"><strong>Tip — Coordinación con Producción:</strong> Cuando hay faltantes de material, comunica directamente al supervisor de Producción la fecha estimada en que llegará el material faltante, para que puedan planificar otras WOs mientras esperan. Registra el faltante en el sistema para que quede en el historial de la WO.</p>
                    </div>
                </div>
            </div>

            {{-- Kits y Lotes desde Materiales --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-purple-50 dark:bg-purple-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">3. Kits y Lotes desde Materiales</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Kits desde Materiales</h4>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mb-2">Materiales puede consultar el detalle de cada Kit para saber exactamente qué piezas y materiales se necesitan preparar. Esto es especialmente útil para kits de ensamble complejos.</p>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Ve a Materiales → Kits para ver todos los kits activos.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Haz clic en un Kit para ver la lista completa de partes y cantidades.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Usa esta información para preparar los materiales antes de que Producción los solicite.</li>
                            </ul>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Lotes desde Materiales</h4>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mb-2">Materiales puede consultar el estado de los lotes para seguimiento de trazabilidad de materiales. Esto permite saber qué materiales se usaron en qué lote.</p>
                            <ul class="space-y-1">
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Ver en qué lotes se usaron los materiales surtidos.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Útil para rastrear si un material defectuoso afectó lotes específicos.</li>
                                <li class="text-xs text-zinc-600 dark:text-zinc-400">• Consulta el historial de surtimiento desde el detalle de cada lote.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{--                   SECCIÓN: EMPAQUES                     --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if ($user->hasRole('admin') || $user->hasRole('Empaques'))
        <div
            @if ($user->hasRole('admin')) x-show="section === 'empaques'" x-transition.opacity.duration.300ms @endif
            class="space-y-8"
        >
            <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold mb-2">Módulo de Empaques</h2>
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">El rol Empaques recibe los lotes aprobados por Calidad, realiza el proceso de empaque según las especificaciones del cliente, genera los Packing Slips (FPL-10) y coordina el despacho final al cliente.</p>
                    </div>
                </div>
            </div>
            <div class="bg-pink-50 dark:bg-pink-900/20 border border-pink-200 dark:border-pink-800 rounded-md p-4">
                <p class="text-xs text-pink-700 dark:text-pink-400 font-medium">Flujo de Empaques: <span class="font-normal">Recibir WOs aprobadas por Calidad → Empacar según especificaciones → Registrar empaque → Generar Packing Slip (FPL-10) → Imprimir y adjuntar → Despachar al cliente</span></p>
            </div>

            {{-- Dashboard Empaques --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-pink-50 dark:bg-pink-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-pink-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">1. Dashboard de Empaques</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">El dashboard de Empaques muestra las WOs listas para empacar (aprobadas por Calidad) ordenadas por urgencia de entrega.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-pink-50 dark:bg-pink-900/20 rounded-md p-4 border border-pink-100 dark:border-pink-800">
                            <div class="text-pink-600 dark:text-pink-400 font-semibold text-sm mb-1">WOs para Empacar</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Número de WOs con lotes aprobados que aún no han sido empacados y no tienen Packing Slip generado.</p>
                        </div>
                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-md p-4 border border-amber-100 dark:border-amber-800">
                            <div class="text-amber-600 dark:text-amber-400 font-semibold text-sm mb-1">PS Generados Hoy</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Packing Slips generados en el día de hoy. Indica la productividad del área de empaques.</p>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-md p-4 border border-green-100 dark:border-green-800">
                            <div class="text-green-600 dark:text-green-400 font-semibold text-sm mb-1">Despachos del Día</div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">WOs marcadas como despachadas al cliente en el día de hoy.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Proceso de Empaque --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-pink-50 dark:bg-pink-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-pink-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">2. Gestión de Empaques</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">El proceso de empaque convierte los lotes aprobados en cajas listas para despacho, siguiendo las especificaciones de empaque de cada cliente.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Proceso de empaque</h4>
                            <ol class="space-y-2">
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-pink-600 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>Verifica en el dashboard qué WOs tienen lotes aprobados listos para empacar.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-pink-600 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>Consulta las especificaciones de empaque del cliente: tipo de caja, cantidad por caja, etiqueta especial.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-pink-600 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>Realiza el empaque físico del lote en las cajas indicadas.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-pink-600 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">4</span>Registra en el sistema: tipo de caja, número de cajas usadas, cantidad por caja, peso bruto y peso neto.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-pink-600 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">5</span>Si hay condiciones especiales del cliente (temperatura, frágil, orientación), aplícalas y regístralas en la observación.</li>
                                <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"><span class="w-6 h-6 bg-green-600 text-white rounded-full text-xs flex items-center justify-center font-bold flex-shrink-0">6</span>Marca como "Empacado". La WO queda lista para generación del Packing Slip.</li>
                            </ol>
                        </div>
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-amber-700 dark:text-amber-400 mb-2">Tip — Coordinación con Materiales</h4>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mb-2">Si faltan cajas, etiquetas, flejes u otros materiales de empaque, notifica a Materiales de inmediato. Registra en el sistema el faltante como "Empaque en espera de insumo" para que el estatus de la WO sea visible para todos.</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">No dejes WOs en estado "Aprobada" sin procesar más de 24 horas sin una nota de motivo. Esto puede generar confusión sobre el estado real de la producción.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Packing Slips --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-pink-50 dark:bg-pink-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-pink-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">3. Packing Slips (FPL-10) — Guía Completa</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="bg-pink-50 dark:bg-pink-900/20 border border-pink-200 dark:border-pink-800 rounded-md p-4">
                        <h4 class="text-sm font-semibold text-pink-700 dark:text-pink-400 mb-1">¿Qué es el FPL-10?</h4>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">El Packing Slip (documento FPL-10) es el documento oficial de despacho que acompaña físicamente cada envío al cliente. Contiene toda la información necesaria para que el cliente reciba, identifique e ingrese las piezas en su sistema. Es un documento legalmente importante y debe ser preciso.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Campos del Packing Slip</h4>
                            <ul class="space-y-1.5">
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Número PS:</strong> Generado automáticamente (ej. PS-2024-001).</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Fecha:</strong> Fecha de generación del PS.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Cliente:</strong> Nombre y dirección del cliente destino.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Número de PO:</strong> La PO del cliente de origen.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Número WO:</strong> La Work Order correspondiente.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Parte:</strong> Número y descripción de la parte empacada.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Cantidad:</strong> Total de piezas en el envío.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Número de caja:</strong> Identificador de la caja o contenedor.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Peso bruto:</strong> Peso total incluyendo empaque (kg).</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="text-pink-500 font-bold mt-0.5">•</span><strong>Peso neto:</strong> Peso solo de las piezas sin empaque (kg).</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Crear un Packing Slip — paso a paso</h4>
                            <ol class="space-y-2">
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>Ve a Empaques → Packing Slips → Nuevo PS.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>Selecciona la WO desde la lista de WOs empacadas (solo aparecen las que ya fueron empacadas en el paso anterior).</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>El sistema llena automáticamente: cliente, PO, parte, cantidad aprobada por Calidad y número WO desde los datos de la WO.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">4</span>Ingresa el número de caja, peso bruto y peso neto (datos físicos del empaque).</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">5</span>Verifica que todos los datos sean correctos antes de generar.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">6</span>Haz clic en "Generar Packing Slip". El sistema crea el PDF del FPL-10.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-green-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">7</span>Descarga o imprime el PDF. Adjunta el original a la caja física antes del despacho.</li>
                            </ol>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h5 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Re-imprimir un PS</h5>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Ve al historial de Packing Slips, localiza el PS por número o fecha y haz clic en "Descargar PDF". Puedes reimprimir todas las veces necesarias sin generar un nuevo número de PS.</p>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h5 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Ver historial de PS</h5>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Empaques → Packing Slips → Lista. Puedes filtrar por cliente, número de WO, número de PS o rango de fechas. El historial nunca se elimina del sistema.</p>
                        </div>
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-md p-4">
                            <h5 class="text-sm font-semibold text-amber-700 dark:text-amber-400 mb-2">Error en PS ya generado</h5>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">Si el PS ya fue generado con datos incorrectos, notifica al administrador. No se puede editar un PS generado; se debe crear una nota de corrección y en casos graves generar un PS sustituto documentando el motivo.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Shipping Queue --}}
            <div class="bg-white dark:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 bg-pink-50 dark:bg-pink-900/20 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="w-8 h-8 bg-pink-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">4. Shipping Queue — Cola de Despacho</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">La Shipping Queue (Cola de Despacho) muestra todas las WOs que ya tienen Packing Slip generado y están físicamente listas para ser despachadas al cliente. Es el último paso antes de que las piezas salgan de la planta.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Cómo procesar la cola</h4>
                            <ol class="space-y-2">
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>Revisa la cola ordenada por fecha de entrega (más urgente arriba).</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>Verifica físicamente que las cajas estén etiquetadas y el PS adjunto.</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-pink-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>Una vez el camión o transporte haya recogido el pedido, marca la WO como "Despachada".</li>
                                <li class="flex items-start gap-2 text-xs text-zinc-600 dark:text-zinc-400"><span class="w-5 h-5 bg-green-600 text-white rounded text-xs flex items-center justify-center font-bold flex-shrink-0">4</span>La WO se mueve al historial y, si era la última WO de la PO, la PO se marca automáticamente como "Completada".</li>
                            </ol>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Listas Preliminares y Monitor TV</h4>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mb-3">Las Listas Preliminares de Empaques permiten preparar un borrador del despacho para presentar al cliente antes de confirmar las cantidades definitivas.</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">El Monitor TV de Empaques muestra en tiempo real el estado de las WOs en empaque: cuántas están en cola, cuántas PS generados y despachos del día. Ideal para la pantalla del área de empaques.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{--                   PIE DE PÁGINA COMÚN                   --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div class="mt-12 overflow-hidden rounded-md border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center gap-3 px-6 py-4 bg-zinc-50 dark:bg-zinc-700/50 border-b border-zinc-200 dark:border-zinc-700">
                <div class="w-8 h-8 bg-zinc-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Configuración Personal — Aplica a Todos los Roles</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-6 h-6 bg-zinc-500 rounded flex items-center justify-center"><svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Perfil</h4>
                        </div>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">Actualiza tu nombre, correo y foto de perfil en Configuración → Perfil. Los cambios de correo requieren verificación por email antes de aplicarse.</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-6 h-6 bg-zinc-500 rounded flex items-center justify-center"><svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg></div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Contraseña</h4>
                        </div>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">En Configuración → Contraseña ingresa tu contraseña actual y la nueva dos veces. Mínimo 8 caracteres. Recomendado: usa letras, números y un símbolo especial.</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-md p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-6 h-6 bg-zinc-500 rounded flex items-center justify-center"><svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></div>
                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Apariencia</h4>
                        </div>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">En Configuración → Apariencia elige entre modo Claro, Oscuro o Sistema (sigue la configuración de tu dispositivo). El modo oscuro usa tonos zinc para reducir fatiga visual en turnos nocturnos.</p>
                    </div>
                </div>
                <div class="mt-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-md p-4">
                    <h4 class="text-sm font-semibold text-indigo-700 dark:text-indigo-400 mb-2">Ayuda y Soporte</h4>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">Si encuentras un error o necesitas ayuda adicional con algún módulo, comunícate con el administrador del sistema. Proporciona siempre: tu nombre de usuario, el módulo donde ocurrió el problema, la acción que realizabas y un captura de pantalla si es posible. Esto agiliza considerablemente la resolución.</p>
                </div>
                <div class="mt-4 text-center text-xs text-zinc-400 dark:text-zinc-600 pb-2">
                    Flexcon Tracker — Sistema de Seguimiento de Producción v1.0 — Flexcon Industries
                </div>
            </div>
        </div>

    </div>{{-- max-w container --}}
</div>{{-- x-data root --}}
