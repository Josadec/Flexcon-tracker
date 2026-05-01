<div class="min-h-screen bg-zinc-100 dark:bg-zinc-950">

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="border-b border-zinc-200 bg-white px-4 py-5 dark:border-zinc-800 dark:bg-zinc-900 sm:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-md bg-blue-900 text-white dark:bg-blue-950">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 sm:text-2xl">Generador de reportes</h1>
                <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">Flexcon Tracker — Exporta en PDF o Excel por departamento</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.reports.parts.index') }}"
                   class="inline-flex items-center gap-2 rounded-md border border-blue-700 bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    Reportes de Partes
                </a>
                <div class="inline-flex items-center gap-2 rounded-md border border-zinc-300 bg-zinc-50 px-3 py-1.5 text-xs font-medium text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                    Administrador
                </div>
            </div>
        </div>
    </div>

    <div class="px-4 py-8 sm:px-6 xl:px-8 2xl:px-10">

        {{-- ── 1. Selector de departamento (tabs) ─────────────── --}}
        <div class="mb-6">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                Paso 1 — Selecciona el departamento
            </p>
            <div class="flex flex-wrap gap-2">

                {{-- General --}}
                <button type="button" wire:click="$set('department','general')"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors
                        {{ $department === 'general'
                            ? 'border-blue-900 bg-blue-900 text-white'
                            : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    General
                </button>

                {{-- Producción --}}
                <button type="button" wire:click="$set('department','produccion')"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors
                        {{ $department === 'produccion'
                            ? 'border-amber-800 bg-amber-800 text-white'
                            : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Producción
                </button>

                {{-- Materiales --}}
                <button type="button" wire:click="$set('department','materiales')"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors
                        {{ $department === 'materiales'
                            ? 'border-slate-700 bg-slate-700 text-white'
                            : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    Materiales
                </button>

                {{-- Calidad --}}
                <button type="button" wire:click="$set('department','calidad')"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors
                        {{ $department === 'calidad'
                            ? 'border-emerald-800 bg-emerald-800 text-white'
                            : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    Calidad
                </button>

                {{-- Empaques --}}
                <button type="button" wire:click="$set('department','empaques')"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors
                        {{ $department === 'empaques'
                            ? 'border-rose-800 bg-rose-800 text-white'
                            : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                    Empaques
                </button>

            </div>
        </div>

        {{-- ── Grid principal ──────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.7fr)_360px]">

            {{-- Columna izquierda --}}
            <div class="space-y-5">

                {{-- 2. Rango de fechas --}}
                <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                        Paso 2 — Rango de fechas
                    </p>
                    <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                        <div>
                            <label for="report_start_date" class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1.5">Desde</label>
                            <input type="date"
                                id="report_start_date"
                                wire:model.live="startDate"
                                class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900
                                       focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600
                                       dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                        </div>
                        <div>
                            <label for="report_end_date" class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1.5">Hasta</label>
                            <input type="date"
                                id="report_end_date"
                                wire:model.live="endDate"
                                class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900
                                       focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600
                                       dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                        </div>
                        <button type="button" wire:click="clearDates"
                            class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-xs font-medium text-zinc-700
                                   hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                            Sin filtro
                        </button>
                        <p class="pb-0.5 text-xs text-zinc-400 dark:text-zinc-500">
                            @php
                                $filterField = match($department) {
                                    'produccion' => 'fecha de pesada',
                                    'materiales' => 'recepción del lote / creación del kit',
                                    'calidad'    => 'fecha de inspección',
                                    'empaques'   => 'fecha de empaque / documento PS',
                                    default      => 'fecha según departamento',
                                };
                            @endphp
                            Filtra por {{ $filterField }}
                        </p>
                    </div>
                </div>

                {{-- 3. Formato y descarga --}}
                <div class="rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                        Paso 3 — Formato y descarga
                    </p>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">

                        {{-- PDF --}}
                        <button type="button" wire:click="$set('format','pdf')"
                            class="flex items-center gap-2.5 rounded-md px-4 py-3 text-sm font-medium transition-colors
                                {{ $format === 'pdf'
                                    ? 'border-rose-700 bg-rose-700 text-white'
                                    : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <div class="text-left">
                                <div class="font-semibold leading-tight">PDF</div>
                                <div class="text-xs opacity-75 leading-tight">Listo para imprimir</div>
                            </div>
                        </button>

                        {{-- Excel --}}
                        <button type="button" wire:click="$set('format','excel')"
                            class="flex items-center gap-2.5 rounded-md px-4 py-3 text-sm font-medium transition-colors
                                {{ $format === 'excel'
                                    ? 'border-emerald-700 bg-emerald-700 text-white'
                                    : 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            <div class="text-left">
                                <div class="font-semibold leading-tight">Excel (.xlsx)</div>
                                <div class="text-xs opacity-75 leading-tight">
                                    @if($department === 'general') 4 hojas @else Hoja de datos @endif
                                </div>
                            </div>
                        </button>

                        {{-- Botón descarga --}}
                        @php $url = $this->getDownloadUrl(); @endphp
                        <a href="{{ $url }}" target="_blank"
                            class="sm:ml-auto inline-flex items-center gap-2 rounded-md px-5 py-3 text-sm font-semibold text-white shadow-sm transition-colors
                                {{ $format === 'pdf' ? 'bg-rose-700 hover:bg-rose-800' : 'bg-emerald-700 hover:bg-emerald-800' }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Descargar {{ strtoupper($format) }} — {{ ucfirst($department) }}
                        </a>

                    </div>
                </div>

            </div>

            {{-- Columna derecha: ¿Qué incluye? --}}
            <div>
                <div class="h-full rounded-md border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">

                    <div class="flex items-center gap-3 mb-5">
                        @php
                            $deptColors = [
                                'general'    => 'bg-blue-900 dark:bg-blue-950',
                                'produccion' => 'bg-amber-800',
                                'materiales' => 'bg-slate-700',
                                'calidad'    => 'bg-emerald-800',
                                'empaques'   => 'bg-rose-800',
                            ];
                        @endphp
                        <div class="flex h-10 w-10 items-center justify-center rounded-md {{ $deptColors[$department] }} text-white">
                            @if($department === 'general')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                            @elseif($department === 'produccion')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            @elseif($department === 'materiales')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            @elseif($department === 'calidad')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                            @else
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                            @endif
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                ¿Qué incluye este reporte?
                            </h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                Contenido del departamento seleccionado
                            </p>
                        </div>
                    </div>

                    @if($department === 'general')
                        <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-300">
                            Resumen unificado de los 4 departamentos en el mismo período.
                        </p>
                        <div class="space-y-2">
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800">
                                <div class="text-sm font-semibold text-amber-700 dark:text-amber-400">Producción</div>
                                <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">Pesadas, piezas buenas/malas, tasa de calidad</p>
                            </div>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800">
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Materiales</div>
                                <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">Lotes recibidos por estatus, kits por etapa</p>
                            </div>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800">
                                <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Calidad</div>
                                <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">Inspecciones, rechazos, rework y scrap</p>
                            </div>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800">
                                <div class="text-sm font-semibold text-rose-700 dark:text-rose-400">Empaques</div>
                                <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">Registros de empaque, packing slips, sobrante</p>
                            </div>
                        </div>

                    @elseif($department === 'produccion')
                        <div class="space-y-2">
                            @foreach([
                                ['Registros de pesada filtrados por fecha de pesada', 'weighed_at'],
                                ['Total piezas, buenas y malas por registro', null],
                                ['Lote y Work Order asociados', null],
                                ['Operador que realizó la pesada', null],
                                ['Tasa de calidad del período', null],
                            ] as [$text, $code])
                                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800">
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $text }}
                                        @if($code) <code class="ml-1 text-xs text-amber-700 dark:text-amber-400">({{ $code }})</code> @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>

                    @elseif($department === 'materiales')
                        <div class="space-y-2">
                            @foreach([
                                ['Lotes filtrados por fecha de recepción', 'receipt_date'],
                                ['Kits filtrados por fecha de creación', null],
                                ['Desglose: pendientes / liberados / rechazados', null],
                                ['Desglose de kits por etapa', null],
                            ] as [$text, $code])
                                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800">
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $text }}
                                        @if($code) <code class="ml-1 text-xs text-slate-600 dark:text-slate-400">({{ $code }})</code> @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>

                    @elseif($department === 'calidad')
                        <div class="space-y-2">
                            @foreach([
                                ['Inspecciones filtradas por fecha', 'weighed_at'],
                                ['Piezas inspeccionadas, buenas, malas, tasa', null],
                                ['Disposición de rechazos: scrap vs rework', null],
                                ['Estatus de rework: pendiente / en proceso / completado', null],
                                ['Inspector asignado por registro', null],
                            ] as [$text, $code])
                                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800">
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $text }}
                                        @if($code) <code class="ml-1 text-xs text-emerald-700 dark:text-emerald-400">({{ $code }})</code> @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>

                    @elseif($department === 'empaques')
                        <div class="space-y-2">
                            @foreach([
                                ['Registros de empaque filtrados por fecha', 'packed_at'],
                                ['Piezas disponibles, empacadas y sobrante', null],
                                ['Packing Slips filtrados por fecha de documento', 'document_date'],
                                ['Estatus de PS: borrador / pendiente / despachado / cancelado', null],
                                ['Invoice vinculado a cada PS', null],
                            ] as [$text, $code])
                                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800">
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $text }}
                                        @if($code) <code class="ml-1 text-xs text-rose-700 dark:text-rose-400">({{ $code }})</code> @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
            </div>

        </div>

    </div>
</div>
