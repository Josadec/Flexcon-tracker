<div class="space-y-6">

    {{-- ── Header ─────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Reportes</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Genera reportes PDF o Excel por departamento con filtro de fechas
            </p>
        </div>
    </div>

    {{-- ── Selector de departamento ────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">1. Selecciona el departamento</h2>
        </div>
        <div class="p-4 grid grid-cols-2 sm:grid-cols-5 gap-3">

            {{-- General --}}
            <label class="cursor-pointer">
                <input type="radio" wire:model.live="department" value="general" class="sr-only">
                <div class="rounded-lg border-2 p-4 text-center transition-all
                    {{ $department === 'general'
                        ? 'border-blue-700 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-400'
                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                    <svg class="w-7 h-7 mx-auto mb-2 {{ $department === 'general' ? 'text-blue-700 dark:text-blue-400' : 'text-gray-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    <div class="text-xs font-semibold {{ $department === 'general' ? 'text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400' }}">
                        General
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">Todos los depts.</div>
                </div>
            </label>

            {{-- Producción --}}
            <label class="cursor-pointer">
                <input type="radio" wire:model.live="department" value="produccion" class="sr-only">
                <div class="rounded-lg border-2 p-4 text-center transition-all
                    {{ $department === 'produccion'
                        ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-400'
                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                    <svg class="w-7 h-7 mx-auto mb-2 {{ $department === 'produccion' ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <div class="text-xs font-semibold {{ $department === 'produccion' ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400' }}">
                        Producción
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">Pesadas / Lotes</div>
                </div>
            </label>

            {{-- Materiales --}}
            <label class="cursor-pointer">
                <input type="radio" wire:model.live="department" value="materiales" class="sr-only">
                <div class="rounded-lg border-2 p-4 text-center transition-all
                    {{ $department === 'materiales'
                        ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-400'
                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                    <svg class="w-7 h-7 mx-auto mb-2 {{ $department === 'materiales' ? 'text-amber-500 dark:text-amber-400' : 'text-gray-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    <div class="text-xs font-semibold {{ $department === 'materiales' ? 'text-amber-500 dark:text-amber-400' : 'text-gray-600 dark:text-gray-400' }}">
                        Materiales
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">Lotes / Kits</div>
                </div>
            </label>

            {{-- Calidad --}}
            <label class="cursor-pointer">
                <input type="radio" wire:model.live="department" value="calidad" class="sr-only">
                <div class="rounded-lg border-2 p-4 text-center transition-all
                    {{ $department === 'calidad'
                        ? 'border-green-600 bg-green-50 dark:bg-green-900/20 dark:border-green-400'
                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                    <svg class="w-7 h-7 mx-auto mb-2 {{ $department === 'calidad' ? 'text-green-600 dark:text-green-400' : 'text-gray-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    <div class="text-xs font-semibold {{ $department === 'calidad' ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400' }}">
                        Calidad
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">Inspecciones</div>
                </div>
            </label>

            {{-- Empaques --}}
            <label class="cursor-pointer">
                <input type="radio" wire:model.live="department" value="empaques" class="sr-only">
                <div class="rounded-lg border-2 p-4 text-center transition-all
                    {{ $department === 'empaques'
                        ? 'border-purple-600 bg-purple-50 dark:bg-purple-900/20 dark:border-purple-400'
                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                    <svg class="w-7 h-7 mx-auto mb-2 {{ $department === 'empaques' ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                    <div class="text-xs font-semibold {{ $department === 'empaques' ? 'text-purple-600 dark:text-purple-400' : 'text-gray-600 dark:text-gray-400' }}">
                        Empaques
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">Empaque / Envíos</div>
                </div>
            </label>
        </div>
    </div>

    {{-- ── Período ──────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">2. Rango de fechas</h2>
        </div>
        <div class="p-5 flex flex-col sm:flex-row sm:items-end gap-4">
            <div>
                <label for="report_start_date" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Desde</label>
                <input type="date"
                    id="report_start_date"
                    wire:model.live="startDate"
                    class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-white
                           focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
                <label for="report_end_date" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Hasta</label>
                <input type="date"
                    id="report_end_date"
                    wire:model.live="endDate"
                    class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-white
                           focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="pb-0.5">
                <button type="button"
                    wire:click="clearDates"
                    class="px-3 py-2 text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300
                           border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                    Sin filtro
                </button>
            </div>
            <div class="text-xs text-gray-400 dark:text-gray-500 pb-0.5">
                @php
                    $filterField = match($department) {
                        'produccion' => 'fecha de pesada',
                        'materiales' => 'fecha de recepción del lote / creación del kit',
                        'calidad'    => 'fecha de inspección',
                        'empaques'   => 'fecha de empaque / documento PS',
                        default      => 'fecha según departamento',
                    };
                @endphp
                Filtra por {{ $filterField }}
            </div>
        </div>
    </div>

    {{-- ── Formato y descarga ───────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">3. Formato y descarga</h2>
        </div>
        <div class="p-5 flex flex-col sm:flex-row sm:items-center gap-4">

            {{-- PDF --}}
            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border-2 px-4 py-3 transition-colors
                {{ $format === 'pdf'
                    ? 'border-red-500 bg-red-50 dark:bg-red-900/20 dark:border-red-400'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                <input type="radio" wire:model.live="format" value="pdf" class="sr-only">
                <svg class="w-5 h-5 {{ $format === 'pdf' ? 'text-red-500' : 'text-gray-400' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <div>
                    <div class="text-sm font-semibold {{ $format === 'pdf' ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">PDF</div>
                    <div class="text-xs text-gray-400">Listo para imprimir</div>
                </div>
            </label>

            {{-- Excel --}}
            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border-2 px-4 py-3 transition-colors
                {{ $format === 'excel'
                    ? 'border-green-500 bg-green-50 dark:bg-green-900/20 dark:border-green-400'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                <input type="radio" wire:model.live="format" value="excel" class="sr-only">
                <svg class="w-5 h-5 {{ $format === 'excel' ? 'text-green-600' : 'text-gray-400' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                </svg>
                <div>
                    <div class="text-sm font-semibold {{ $format === 'excel' ? 'text-green-700 dark:text-green-400' : 'text-gray-700 dark:text-gray-300' }}">Excel (.xlsx)</div>
                    <div class="text-xs text-gray-400">
                        @if($department === 'general') 4 hojas (una por dept.) @else Hoja con datos del departamento @endif
                    </div>
                </div>
            </label>

            {{-- Botón descarga --}}
            @php $url = $this->getDownloadUrl(); @endphp
            <div class="sm:ml-auto">
                <a href="{{ $url }}" target="_blank"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors shadow-sm
                        {{ $format === 'pdf' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar {{ strtoupper($format) }}
                    &mdash;
                    {{ ucfirst($department) }}
                </a>
            </div>
        </div>
    </div>

    {{-- ── Qué incluye ─────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">¿Qué incluye este reporte?</h2>
        </div>
        <div class="p-5">
            @if($department === 'general')
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                    Resumen unificado de los 4 departamentos en el mismo período.
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-gray-500 dark:text-gray-400">
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-3">
                        <div class="font-semibold text-indigo-700 dark:text-indigo-400 mb-1">Producción</div>
                        Pesadas, piezas buenas/malas, tasa de calidad
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3">
                        <div class="font-semibold text-amber-600 dark:text-amber-400 mb-1">Materiales</div>
                        Lotes recibidos por estatus, kits por etapa
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                        <div class="font-semibold text-green-700 dark:text-green-400 mb-1">Calidad</div>
                        Inspecciones, rechazos, rework y scrap
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3">
                        <div class="font-semibold text-purple-700 dark:text-purple-400 mb-1">Empaques</div>
                        Registros de empaque, packing slips, sobrante
                    </div>
                </div>
            @elseif($department === 'produccion')
                <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                    <li>• Registros de pesada filtrados por fecha de pesada (<code>weighed_at</code>)</li>
                    <li>• Total piezas, buenas y malas por registro</li>
                    <li>• Lote y Work Order asociados</li>
                    <li>• Operador que realizó la pesada</li>
                    <li>• Tasa de calidad del período</li>
                </ul>
            @elseif($department === 'materiales')
                <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                    <li>• Lotes filtrados por fecha de recepción (<code>receipt_date</code>), con estatus de material</li>
                    <li>• Kits filtrados por fecha de creación, con etapa del kit</li>
                    <li>• Desglose de lotes: pendientes / liberados / rechazados</li>
                    <li>• Desglose de kits: preparando / listos / en ensamble / rechazados</li>
                </ul>
            @elseif($department === 'calidad')
                <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                    <li>• Inspecciones de calidad filtradas por fecha (<code>weighed_at</code>)</li>
                    <li>• Piezas inspeccionadas, buenas, malas, tasa de aprobación</li>
                    <li>• Disposición de rechazos: scrap vs rework</li>
                    <li>• Estatus de rework: pendiente / en proceso / completado</li>
                    <li>• Inspector asignado por registro</li>
                </ul>
            @elseif($department === 'empaques')
                <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                    <li>• Registros de empaque filtrados por fecha de empaque (<code>packed_at</code>)</li>
                    <li>• Piezas disponibles, empacadas y sobrante por registro</li>
                    <li>• Packing Slips filtrados por fecha de documento (<code>document_date</code>)</li>
                    <li>• Estatus de PS: borrador / pendiente / despachado / cancelado</li>
                    <li>• Invoice vinculado a cada PS</li>
                </ul>
            @endif
        </div>
    </div>

</div>
