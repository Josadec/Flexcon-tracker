@php
    $po = $workOrder->purchaseOrder;
    $part = $po?->part;
    $statusHex = $workOrder->status?->color ?? '#6b7280';

    $progress =
        $workOrder->original_quantity > 0
            ? round(($workOrder->sent_pieces / $workOrder->original_quantity) * 100, 1)
            : 0;
    $lotsCount = $workOrder->lots->count();
    $crimpLotsCount = $workOrder->lots->sum(fn($l) => $l->crimpLots->count());
    $totalWeighings = $workOrder->lots->sum(fn($l) => $l->weighings->count());

    // Estado del lote y fase de avance → tonos del sistema (x-ui.badge).
    $lotTones = [
        'pending' => 'neutral',
        'in_progress' => 'warn',
        'completed' => 'good',
        'cancelled' => 'bad',
    ];
    $phaseTones = [
        'zinc' => 'neutral',
        'amber' => 'warn',
        'cyan' => 'accent',
        'blue' => 'info',
        'emerald' => 'good',
        'green' => 'good',
        'red' => 'bad',
    ];
    $phaseBars = [
        'zinc' => 'bg-slate-400',
        'amber' => 'bg-amber-500',
        'cyan' => 'bg-cyan-500',
        'blue' => 'bg-sky-500',
        'emerald' => 'bg-emerald-500',
        'green' => 'bg-green-500',
        'red' => 'bg-red-500',
    ];
@endphp

<x-ui.page eyebrow="Órdenes" :title="'WO ' . ($po?->wo ?? $workOrder->wo_number)" :subtitle="trim(($part?->number ?? '') . ' — ' . Str::limit($part?->description ?? '', 60), ' —') ?:
    'Detalle de la orden de trabajo.'" back="{{ route('admin.work-orders.index') }}"
    backLabel="Volver a órdenes de trabajo">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.work-orders.edit', $workOrder) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Editar WO
        </x-ui.btn>
    </x-slot:actions>

    @if (session('success'))
        <x-ui.note tone="success">{{ session('success') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Resumen --}}
    <x-ui.stats cols="5">
        <x-ui.stat label="Estado">
            <span
                class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold text-white"
                style="background-color: {{ $statusHex }}">{{ $workOrder->status?->name ?? 'Sin estado' }}</span>
        </x-ui.stat>
        <x-ui.stat label="Cantidad" :value="number_format($workOrder->original_quantity)" />
        <x-ui.stat label="Lotes" :value="$lotsCount" tone="info" />
        <x-ui.stat label="Lotes de CRIMP" :value="$crimpLotsCount" tone="accent"
            help="Sublotes de CRIMP creados a partir de los viajeros de esta orden." />
        <x-ui.stat label="Pesadas" :value="$totalWeighings" tone="accent" />
    </x-ui.stats>

    {{-- Pestañas --}}
    @php
        $tabs = [
            'general' => ['label' => 'General', 'count' => null],
            'lots' => ['label' => 'Lotes', 'count' => $lotsCount],
            'weighings' => ['label' => 'Pesadas', 'count' => $totalWeighings],
        ];
    @endphp
    {{-- Patrón ARIA de pestañas con activación manual: las flechas ← → mueven el
         FOCO y Enter/Espacio cambia de pestaña. No se activa al enfocar porque
         cada pestaña es una ida al servidor. Sólo la pestaña activa entra en el
         orden de tabulación (tabindex roving); desde ella, Tab salta al panel. --}}
    <div class="border-b border-slate-200 dark:border-slate-700" x-data="{
        moveFocus(step) {
            // `this.$el` (no `$el` suelto): dentro de un método de x-data el
            // magic sólo está garantizado a través de `this`.
            const tabs = [...this.$el.querySelectorAll('[role=\'tab\']')];
            const i = tabs.indexOf(document.activeElement);
            if (i === -1) return;
            tabs[(i + step + tabs.length) % tabs.length].focus();
        },
    }"
        @keydown.arrow-right.prevent="moveFocus(1)" @keydown.arrow-left.prevent="moveFocus(-1)"
        @keydown.home.prevent="$el.querySelector('[role=\'tab\']').focus()"
        @keydown.end.prevent="[...$el.querySelectorAll('[role=\'tab\']')].pop().focus()">
        <div class="-mb-px flex flex-wrap gap-x-6 overflow-x-auto" role="tablist" aria-label="Secciones de la orden">
            @foreach ($tabs as $tab => $info)
                @php $isActiveTab = $activeTab === $tab; @endphp
                <button type="button" wire:key="wo-tab-{{ $tab }}" wire:click="setTab('{{ $tab }}')"
                    id="wo-tab-{{ $tab }}" role="tab" aria-selected="{{ $isActiveTab ? 'true' : 'false' }}"
                    aria-controls="wo-panel-{{ $tab }}" tabindex="{{ $isActiveTab ? '0' : '-1' }}"
                    class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition-colors
                        {{ $isActiveTab
                            ? 'border-sky-600 text-sky-700 dark:text-sky-300'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                    {{ $info['label'] }}
                    @if ($info['count'] !== null)
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ $info['count'] }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ============================================================= --}}
    {{-- PESTAÑA: GENERAL                                              --}}
    {{-- ============================================================= --}}
    @if ($activeTab === 'general')
        <div id="wo-panel-general" role="tabpanel" aria-labelledby="wo-tab-general" tabindex="0"
            class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            {{-- Ficha --}}
            <div class="space-y-5 lg:col-span-2">
                <x-ui.section title="Información de la orden"
                    hint="Datos que vienen de la orden de compra y el avance de envío.">
                    <x-slot:aside>
                        <div class="flex items-center gap-2">
                            @if ($part?->is_crimp)
                                <x-ui.badge tone="accent">CRIMP</x-ui.badge>
                            @endif
                            @if ($workOrder->isComplete())
                                <x-ui.badge tone="good" dot>Completa</x-ui.badge>
                            @else
                                <x-ui.badge tone="warn" dot>Pendiente</x-ui.badge>
                            @endif
                        </div>
                    </x-slot:aside>

                    <dl
                        class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                        @if ($po?->wo)
                            {{-- El número que ve el cliente: es el que se imprime en los documentos. --}}
                            <x-ui.kv label="WO (cliente)" :value="$po->wo" tone="info"
                                help="Número de Work Order del cliente." />
                        @endif
                        <x-ui.kv label="ID interno" :value="$workOrder->wo_number" />
                        @if ($po)
                            <x-ui.kv label="Orden de compra">
                                <a href="{{ route('admin.purchase-orders.show', $po) }}" wire:navigate
                                    class="font-bold text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">
                                    {{ $po->po_number }}
                                </a>
                            </x-ui.kv>
                            <x-ui.kv label="Parte" :value="$part?->number ?? '—'" />
                            <x-ui.kv label="Descripción" :value="$part?->description ?: '—'" />
                        @endif
                        <x-ui.kv label="Cantidad original" :value="number_format($workOrder->original_quantity)" />
                        <x-ui.kv label="Piezas enviadas" :value="number_format($workOrder->sent_pieces)" />
                        <x-ui.kv label="Cantidad pendiente" :value="number_format($workOrder->pending_quantity)" :tone="$workOrder->pending_quantity > 0 ? 'warn' : 'good'" />
                        <x-ui.kv label="Fecha de apertura" :value="$workOrder->opened_date?->format('d/m/Y') ?? '—'" />
                        <x-ui.kv label="Fecha programada de envío" :value="$workOrder->scheduled_send_date?->format('d/m/Y') ?? 'No definida'" />
                        <x-ui.kv label="Fecha real de envío" :value="$workOrder->actual_send_date?->format('d/m/Y') ?? 'No enviado'" />
                        @if ($workOrder->eq)
                            <x-ui.kv label="Equipo (EQ)" :value="$workOrder->eq" />
                        @endif
                        @if ($workOrder->pr)
                            <x-ui.kv label="Personal (PR)" :value="$workOrder->pr" />
                        @endif
                        @if ($workOrder->comments)
                            <x-ui.kv label="Comentarios" :value="$workOrder->comments" />
                        @endif
                    </dl>
                </x-ui.section>

                {{-- Documento firmado de la PO: se sube desde aquí para no salir del WO. --}}
                @if ($po)
                    <x-ui.section title="Documento firmado"
                        hint="La PO firmada por el cliente. Se guarda en la orden de compra.">
                        @if ($po->signed_document_path)
                            <div
                                class="mb-4 flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:bg-slate-900/40">
                                <div class="flex min-w-0 items-center gap-3">
                                    <svg class="size-8 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"
                                        aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ basename($po->signed_document_path) }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Documento firmado cargado
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.btn variant="secondary" size="sm"
                                        href="{{ Storage::url($po->signed_document_path) }}" :navigate="false"
                                        target="_blank">Ver</x-ui.btn>
                                    <x-ui.btn variant="secondary" size="sm"
                                        href="{{ Storage::url($po->signed_document_path) }}" :navigate="false"
                                        download>Descargar</x-ui.btn>
                                    <x-ui.btn variant="danger" size="sm" wire:click="deleteSignedDocument"
                                        wire:confirm="¿Eliminar el documento firmado? Esta acción no se puede deshacer.">Eliminar</x-ui.btn>
                                </div>
                            </div>
                        @endif

                        <x-ui.field for="signedDocument" :label="$po->signed_document_path
                            ? 'Reemplazar documento firmado'
                            : 'Subir documento firmado'"
                            hint="Máximo 10 MB. Formatos: PDF, JPG o PNG." :error="$errors->first('signedDocument')">
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input wire:model="signedDocument" id="signedDocument" type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100 dark:text-slate-300 dark:file:bg-sky-900/40 dark:file:text-sky-300">
                                <x-ui.btn variant="primary" wire:click="uploadSignedDocument"
                                    wire:loading.attr="disabled" wire:target="signedDocument,uploadSignedDocument">
                                    <span wire:loading.remove
                                        wire:target="signedDocument,uploadSignedDocument">Subir</span>
                                    <span wire:loading
                                        wire:target="signedDocument,uploadSignedDocument">Subiendo...</span>
                                </x-ui.btn>
                            </div>
                        </x-ui.field>
                    </x-ui.section>
                @endif
            </div>

            {{-- Columna de control --}}
            <div class="space-y-5">
                <x-ui.section title="Cambiar estado"
                    hint="El estado decide si la orden aparece en Capacidad y en la Lista de Envío.">
                    {{-- Administrar el catálogo (colores incluidos) sin salir del WO. --}}
                    @can(\App\Livewire\Admin\StatusesWO\StatusWOManager::PERMISSION_VIEW)
                        <x-slot:aside>
                            <x-ui.btn variant="secondary" size="sm"
                                wire:click="$dispatch('open-statuses-wo-manager')"
                                title="Administrar estados y sus colores">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828L11.828 17M7 17h.01" />
                                </svg>
                                Administrar estados
                            </x-ui.btn>
                        </x-slot:aside>
                    @endcan

                    <div class="flex flex-col gap-2">
                        @foreach ($statuses as $status)
                            @if ($status->id === $workOrder->status_id)
                                <div wire:key="wo-status-{{ $status->id }}"
                                    class="flex items-center gap-2 rounded-lg border-2 px-3 py-2"
                                    style="border-color: {{ $status->color }}">
                                    <span class="size-3 shrink-0 rounded-full"
                                        style="background-color: {{ $status->color }}" aria-hidden="true"></span>
                                    <span
                                        class="text-sm font-bold text-slate-900 dark:text-white">{{ $status->name }}</span>
                                    <span
                                        class="ml-auto text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Actual</span>
                                </div>
                            @else
                                <button type="button" wire:key="wo-status-{{ $status->id }}"
                                    wire:click="updateStatus({{ $status->id }})"
                                    wire:confirm="¿Cambiar el estado del WO a «{{ $status->name }}»?"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left transition-colors hover:border-slate-300 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700/50">
                                    <span class="size-3 shrink-0 rounded-full"
                                        style="background-color: {{ $status->color }}" aria-hidden="true"></span>
                                    <span
                                        class="text-sm text-slate-700 dark:text-slate-200">{{ $status->name }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>

                    <x-ui.note tone="muted" class="mt-4">
                        Al marcar <strong>Completed</strong> el WO desaparece de la Lista de Envío.
                        Reábrelo con <strong>Open</strong> para que vuelva a aparecer en Capacidad y en la Lista de
                        Envío.
                    </x-ui.note>
                </x-ui.section>

                <x-ui.section title="Progreso" hint="Piezas ya enviadas contra la cantidad original.">
                    <div class="mb-2 flex items-baseline justify-between text-sm">
                        <span class="text-slate-600 dark:text-slate-300">Completado</span>
                        <span
                            class="font-bold tabular-nums text-slate-900 dark:text-white">{{ $progress }}%</span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                        role="progressbar" aria-valuenow="{{ min($progress, 100) }}" aria-valuemin="0"
                        aria-valuemax="100" aria-label="Avance de envío de la orden">
                        <div class="h-3 rounded-full {{ $progress >= 100 ? 'bg-green-500' : 'bg-sky-500' }}"
                            style="width: {{ min($progress, 100) }}%"></div>
                    </div>
                    <p class="mt-4 text-center text-2xl font-bold tabular-nums text-slate-900 dark:text-white">
                        {{ number_format($workOrder->sent_pieces) }} /
                        {{ number_format($workOrder->original_quantity) }}
                    </p>
                    <p class="text-center text-xs text-slate-500 dark:text-slate-400">piezas enviadas</p>
                </x-ui.section>

                <x-ui.section title="Historial de estados"
                    hint="Los últimos cinco cambios, del más reciente al más antiguo.">
                    @if ($workOrder->statusLogs->count() > 0)
                        <ul role="list" class="-mb-6">
                            @foreach ($workOrder->statusLogs->sortByDesc('created_at')->take(5) as $log)
                                <li wire:key="wo-log-{{ $log->id }}" class="relative pb-6">
                                    @if (!$loop->last)
                                        <span
                                            class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-slate-200 dark:bg-slate-700"
                                            aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex gap-3">
                                        <span
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-slate-800"
                                            style="background-color: {{ $log->toStatus->color ?? '#6B7280' }}"
                                            aria-hidden="true">
                                            <svg class="size-4 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <div
                                            class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-4 gap-y-1 pt-1.5">
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                @if ($log->fromStatus)
                                                    <span class="font-semibold"
                                                        style="color: {{ $log->fromStatus->color }}">{{ $log->fromStatus->name }}</span>
                                                    →
                                                @else
                                                    Creado como
                                                @endif
                                                <span class="font-semibold"
                                                    style="color: {{ $log->toStatus->color }}">{{ $log->toStatus->name }}</span>
                                                @if ($log->user)
                                                    por <span
                                                        class="font-semibold text-slate-900 dark:text-white">{{ $log->user->name }}</span>
                                                @endif
                                            </p>
                                            <span
                                                class="whitespace-nowrap text-xs tabular-nums text-slate-400 dark:text-slate-500">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <x-ui.empty icon="doc" title="Sin cambios de estado"
                            hint="Aquí quedará registrado quién cambió el estado y cuándo." />
                    @endif
                </x-ui.section>
            </div>
        </div>
    @endif

    {{-- ============================================================= --}}
    {{-- PESTAÑA: LOTES                                                --}}
    {{-- ============================================================= --}}
    @if ($activeTab === 'lots')
        <x-ui.table id="wo-panel-lots" role="tabpanel" aria-labelledby="wo-tab-lots" tabindex="0"
            title="Lotes de esta orden de trabajo"
            hint="La suma de los lotes no puede sobrepasar la cantidad de la orden.">
            <x-slot:aside>
                <x-ui.btn variant="primary" size="sm" wire:click="openCreateLotModal">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Agregar lote
                </x-ui.btn>
            </x-slot:aside>

            <x-slot:head>
                <tr>
                    <x-ui.th>Lote</x-ui.th>
                    <x-ui.th align="right" class="w-24">Cantidad</x-ui.th>
                    <x-ui.th class="w-28">Estado</x-ui.th>
                    <x-ui.th>Lotes de CRIMP</x-ui.th>
                    <x-ui.th>Producción</x-ui.th>
                    <x-ui.th>Calidad</x-ui.th>
                    <x-ui.th>Empaque</x-ui.th>
                    <x-ui.th>Avance</x-ui.th>
                    <x-ui.th align="right">Acciones</x-ui.th>
                </tr>
            </x-slot:head>

            @forelse ($workOrder->lots as $lot)
                @php
                    $prodGood = $lot->getProductionGoodPieces();
                    $prodBad = $lot->getProductionBadPieces();
                    $prodTotal = $prodGood + $prodBad;
                    $prodPct = $lot->quantity > 0 ? min(100, (int) round(($prodTotal / $lot->quantity) * 100)) : 0;

                    $qSem = $lot->getQualitySemaphoreStatus();
                    $qGood = $lot->getQualityGoodPieces();
                    $qPending = $lot->getQualityPendingPieces();

                    $pSem = $lot->getPackagingSemaphoreStatus();
                    $pPacked = $lot->getPackagingPackedPieces();
                    $pPending = $lot->getPackagingPendingPieces();

                    $lotProgress = $lot->getProgressSummary();

                    $dotHex = [
                        'gray' => '#d1d5db',
                        'yellow' => '#fbbf24',
                        'green' => '#22c55e',
                        'blue' => '#3b82f6',
                        'orange' => '#f97316',
                    ];

                    $qText =
                        $qSem === 'gray'
                            ? 'Sin pesadas'
                            : number_format($qGood) .
                                ' ok' .
                                ($qPending > 0 ? ' · ' . number_format($qPending) . ' pend.' : '');
                    $pText =
                        $pSem === 'gray'
                            ? 'Sin avance'
                            : number_format($pPacked) .
                                ' emp.' .
                                ($pPending > 0 ? ' · ' . number_format($pPending) . ' pend.' : '');

                    // Calidad: piezas verificadas / piezas buenas producidas.
                    $qWeighed = $qGood + $lot->getQualityBadPieces();
                    $qBase = max($prodGood, $qWeighed);
                    $qPct = $qBase > 0 ? min(100, (int) round(($qWeighed / $qBase) * 100)) : 0;

                    // Empaque: piezas empacadas / piezas aprobadas por calidad.
                    $pAvail = $lot->getPackagingAvailablePieces();
                    $pBase = max($pAvail, $pPacked);
                    $pPct = $pBase > 0 ? min(100, (int) round(($pPacked / $pBase) * 100)) : 0;
                @endphp
                <tr wire:key="lot-{{ $lot->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="whitespace-nowrap px-4 py-3 align-top">
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</div>
                        @if ($lot->description)
                            <div class="max-w-[140px] truncate text-xs text-slate-500 dark:text-slate-400"
                                title="{{ $lot->description }}">{{ $lot->description }}</div>
                        @endif
                    </td>

                    <td
                        class="whitespace-nowrap px-4 py-3 text-right align-top tabular-nums text-slate-900 dark:text-white">
                        {{ number_format($lot->quantity) }}
                    </td>

                    <td class="px-4 py-3 align-top">
                        <x-ui.badge :tone="$lotTones[$lot->status] ?? 'neutral'" dot>{{ $lot->status_label }}</x-ui.badge>
                    </td>

                    <td class="px-4 py-3 align-top">
                        @if ($lot->crimpLots->isEmpty())
                            <span class="whitespace-nowrap text-xs text-slate-400 dark:text-slate-500">Sin lotes de
                                CRIMP</span>
                        @else
                            <div class="flex max-w-[210px] flex-wrap gap-1">
                                @foreach ($lot->crimpLots as $cl)
                                    <x-ui.badge wire:key="crimp-{{ $cl->id }}"
                                        tone="accent">{{ $cl->crimp_lot_number }}</x-ui.badge>
                                @endforeach
                            </div>
                        @endif
                    </td>

                    {{-- Producción --}}
                    <td class="px-4 py-3 align-top">
                        <div class="mb-1 whitespace-nowrap text-xs tabular-nums text-slate-600 dark:text-slate-300">
                            {{ number_format($prodTotal) }} / {{ number_format($lot->quantity) }} pz
                        </div>
                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full bg-amber-500" style="width: {{ $prodPct }}%"></div>
                        </div>
                        @if ($prodBad > 0)
                            <div class="mt-0.5 text-[10px] font-semibold text-red-600 dark:text-red-400">
                                {{ number_format($prodBad) }} malas</div>
                        @endif
                    </td>

                    {{-- Calidad --}}
                    <td class="px-4 py-3 align-top">
                        <div class="mb-1.5 flex items-center gap-2 whitespace-nowrap">
                            <span class="inline-block size-3 shrink-0 rounded-full"
                                style="background-color: {{ $dotHex[$qSem] ?? '#d1d5db' }}; box-shadow: 0 0 0 3px {{ $dotHex[$qSem] ?? '#d1d5db' }}33;"
                                aria-hidden="true"></span>
                            <span
                                class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ $qText }}</span>
                        </div>
                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full bg-cyan-500" style="width: {{ $qPct }}%"></div>
                        </div>
                    </td>

                    {{-- Empaque --}}
                    <td class="px-4 py-3 align-top">
                        <div class="mb-1.5 flex items-center gap-2 whitespace-nowrap">
                            <span class="inline-block size-3 shrink-0 rounded-full"
                                style="background-color: {{ $dotHex[$pSem] ?? '#d1d5db' }}; box-shadow: 0 0 0 3px {{ $dotHex[$pSem] ?? '#d1d5db' }}33;"
                                aria-hidden="true"></span>
                            <span
                                class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ $pText }}</span>
                        </div>
                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full bg-sky-500" style="width: {{ $pPct }}%"></div>
                        </div>
                    </td>

                    {{-- Avance general --}}
                    <td class="px-4 py-3 align-top">
                        <div class="mb-1 flex items-center gap-1.5 whitespace-nowrap">
                            @if ($lotProgress['done'])
                                <svg class="size-4 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                        clip-rule="evenodd" />
                                </svg>
                            @endif
                            <x-ui.badge :tone="$phaseTones[$lotProgress['color']] ?? 'neutral'">{{ $lotProgress['label'] }}</x-ui.badge>
                        </div>
                        <div class="h-1.5 w-28 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full {{ $phaseBars[$lotProgress['color']] ?? 'bg-slate-400' }}"
                                style="width: {{ $lotProgress['percent'] }}%"></div>
                        </div>
                    </td>

                    {{-- El borrado abre su propio modal de confirmación (no wire:confirm),
                         porque el modelo puede rechazarlo según el estado del lote. --}}
                    <td class="px-4 py-3 align-top">
                        <x-ui.row-actions label="el lote {{ $lot->lot_number }}">
                            {{-- Ojo: el seguimiento es de la ORDEN completa, no de este
                                 lote (la pantalla de Lista de envío no recibe lote). El
                                 nombre accesible tiene que decir la verdad. --}}
                            <x-ui.icon-btn tone="neutral" label="Ver el seguimiento de los lotes de esta orden"
                                href="{{ route('admin.sent-lists.display.wo', $workOrder) }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </x-ui.icon-btn>
                            <x-ui.icon-btn tone="primary" label="Editar el lote {{ $lot->lot_number }}"
                                wire:click="openEditLotModal({{ $lot->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </x-ui.icon-btn>
                            <x-ui.icon-btn tone="danger" label="Eliminar el lote {{ $lot->lot_number }}"
                                wire:click="confirmDeleteLot({{ $lot->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </x-ui.icon-btn>
                        </x-ui.row-actions>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        <x-ui.empty icon="box" title="Todavía no hay lotes"
                            hint="Los lotes dividen la cantidad de la orden en viajeros que recorren producción, calidad y empaque.">
                            <x-slot:action>
                                <x-ui.btn variant="primary" wire:click="openCreateLotModal">Agregar el primer
                                    lote</x-ui.btn>
                            </x-slot:action>
                        </x-ui.empty>
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif

    {{-- ============================================================= --}}
    {{-- PESTAÑA: PESADAS                                              --}}
    {{-- ============================================================= --}}
    @if ($activeTab === 'weighings')
        <x-ui.table id="wo-panel-weighings" role="tabpanel" aria-labelledby="wo-tab-weighings" tabindex="0"
            title="Pesadas de esta orden de trabajo" hint="Cada pesada descuenta piezas del pendiente de su lote.">
            <x-slot:aside>
                @if ($workOrder->lots->isNotEmpty())
                    <x-ui.btn variant="primary" size="sm" wire:click="openCreateWeighingModal">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4" />
                        </svg>
                        Registrar pesada
                    </x-ui.btn>
                @else
                    <span class="text-xs text-slate-500 dark:text-slate-400">Crea al menos un lote primero.</span>
                @endif
            </x-slot:aside>

            <x-slot:head>
                <tr>
                    <x-ui.th>Fecha</x-ui.th>
                    <x-ui.th>Lote</x-ui.th>
                    <x-ui.th align="right">Buenas</x-ui.th>
                    <x-ui.th align="right">Malas</x-ui.th>
                    <x-ui.th align="right">Total</x-ui.th>
                    <x-ui.th>Pesó</x-ui.th>
                    <x-ui.th align="right">Acciones</x-ui.th>
                </tr>
            </x-slot:head>

            @forelse ($weighings as $weighing)
                <tr wire:key="weighing-{{ $weighing->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="whitespace-nowrap px-4 py-3 text-slate-900 dark:text-white">
                        {{ $weighing->weighed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">
                        {{ $weighing->lot?->lot_number ?? '—' }}</td>
                    <td
                        class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-green-700 dark:text-green-300">
                        {{ number_format($weighing->good_pieces) }}</td>
                    <td
                        class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-red-700 dark:text-red-300">
                        {{ number_format($weighing->bad_pieces) }}</td>
                    <td
                        class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                        {{ number_format($weighing->good_pieces + $weighing->bad_pieces) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                        {{ $weighing->weighedBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        {{-- Igual que en Lotes: la confirmación es un modal propio. --}}
                        <x-ui.row-actions label="esta pesada">
                            <x-ui.icon-btn tone="primary" label="Editar esta pesada"
                                wire:click="openEditWeighingModal({{ $weighing->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </x-ui.icon-btn>
                            <x-ui.icon-btn tone="danger" label="Eliminar esta pesada"
                                wire:click="confirmDeleteWeighing({{ $weighing->id }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </x-ui.icon-btn>
                        </x-ui.row-actions>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-ui.empty icon="doc" title="Sin pesadas registradas"
                            hint="Las pesadas registran cuántas piezas buenas y malas salieron de cada lote." />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif

    {{-- ============================================================= --}}
    {{-- MODALES                                                       --}}
    {{-- ============================================================= --}}

    {{-- Alta y edición de lotes --}}
    @if ($showLotModal)
        <x-ui-modal wire:key="modal-lot-{{ $editingLotId ?? 'new' }}" :title="$editingLotId ? 'Editar lote' : 'Agregar lote'"
            subtitle="El lote es el viajero con el que la parte recorre producción, calidad y empaque."
            close="closeLotModal" maxWidth="2xl">

            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$po?->wo ?? $workOrder->wo_number" />
                <x-ui-modal.ctx label="Parte" :value="$part?->number ?? '—'" />
                <x-ui-modal.ctx label="Cantidad de la WO" :value="number_format($workOrder->original_quantity)" />
                <x-ui-modal.ctx label="Lotes actuales" :value="(string) $lotsCount" />
            </x-slot:context>

            {{-- El <form> deja que Enter guarde; el botón del pie llama al mismo método. --}}
            <form wire:submit="saveLot">
                <x-ui.section step="1" title="Identificación y cantidad"
                    hint="La suma de todos los lotes no puede sobrepasar la cantidad de la orden.">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.field label="Número de lote" required :error="$errors->first('lotNumber')">
                            <input type="text" wire:model="lotNumber" class="w-full" placeholder="Ej: 001"
                                required>
                        </x-ui.field>

                        <x-ui.field label="Cantidad" required hint="Piezas que lleva este lote." :error="$errors->first('lotQuantity')">
                            <input type="number" wire:model="lotQuantity" min="1"
                                class="w-full text-right tabular-nums" required>
                        </x-ui.field>
                    </div>
                </x-ui.section>

                @if ($editingLotId)
                    <x-ui.section step="2" title="Estado y notas" class="mt-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field label="Estado" :error="$errors->first('lotStatus')">
                                <select wire:model="lotStatus" class="w-full">
                                    <option value="pending">Pendiente</option>
                                    <option value="in_progress">En progreso</option>
                                    <option value="completed">Completado</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                            </x-ui.field>

                            <x-ui.field label="Descripción" optional :error="$errors->first('lotDescription')">
                                <input type="text" wire:model="lotDescription" class="w-full">
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Comentarios" optional class="mt-4" hint="Uso interno; no se imprime."
                            :error="$errors->first('lotComments')">
                            <textarea wire:model="lotComments" rows="2" class="w-full"></textarea>
                        </x-ui.field>
                    </x-ui.section>
                @endif
            </form>

            <x-slot:note>
                Al guardar, el lote aparece en la pestaña Lotes de esta orden.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeLotModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveLot" wire:loading.attr="disabled" wire:target="saveLot">
                    {{ $editingLotId ? 'Guardar cambios' : 'Crear lote' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Confirmación de borrado de lote --}}
    @if ($showDeleteLotConfirm)
        @php $lotToDelete = $editingLotId ? $workOrder->lots->find($editingLotId) : null; @endphp
        <x-ui-modal wire:key="modal-delete-lot" :title="'Eliminar el lote ' . ($lotToDelete?->lot_number ?? '')" subtitle="Esta acción no se puede deshacer."
            close="$set('showDeleteLotConfirm', false)" maxWidth="lg">

            <x-ui.section title="Qué va a pasar">
                {{-- Nombrar el registro dentro del propio aviso: el modal es
                     estrecho y una tira de contexto de cuatro celdas no cabe. --}}
                <x-ui.note tone="danger">
                    Se eliminará el lote <strong>{{ $lotToDelete?->lot_number ?? '—' }}</strong>
                    ({{ number_format($lotToDelete?->quantity ?? 0) }} pz) y también sus
                    <strong>{{ $lotToDelete?->weighings->count() ?? 0 }} pesada(s)</strong>.
                    Si el lote ya avanzó en el proceso, el sistema puede impedir el borrado.
                </x-ui.note>
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="$set('showDeleteLotConfirm', false)">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="deleteLot" wire:loading.attr="disabled"
                    wire:target="deleteLot">Eliminar lote</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Alta y edición de pesadas --}}
    @if ($showWeighingModal)
        @php $selectedLot = $weighingLotId ? $workOrder->lots->find($weighingLotId) : null; @endphp
        <x-ui-modal wire:key="modal-weighing-{{ $editingWeighingId ?? 'new' }}" :title="$editingWeighingId ? 'Editar pesada' : 'Registrar pesada'"
            subtitle="Cada pesada descuenta piezas del pendiente del lote." close="closeWeighingModal"
            maxWidth="3xl">

            <x-slot:context>
                <x-ui-modal.ctx label="WO" :value="$po?->wo ?? $workOrder->wo_number" />
                <x-ui-modal.ctx label="Parte" :value="$part?->number ?? '—'" />
                <x-ui-modal.ctx label="Lote" :value="$selectedLot?->lot_number ?? 'Sin elegir'" />
                <x-ui-modal.ctx label="Pendiente de pesar" :value="number_format($remainingPieces)" />
            </x-slot:context>

            <form wire:submit="saveWeighing">
                <x-ui.section step="1" title="Lote" hint="Elige el viajero al que pertenecen estas piezas.">
                    <x-ui.field label="Lote" required :error="$errors->first('weighingLotId')">
                        <select wire:model.live="weighingLotId" class="w-full" required>
                            <option value="">— Seleccionar lote —</option>
                            @foreach ($workOrder->lots as $lot)
                                <option value="{{ $lot->id }}">{{ $lot->lot_number }} —
                                    {{ number_format($lot->quantity) }} pz</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    @if ($weighingLotId)
                        <x-ui.stats cols="3" class="mt-4">
                            <x-ui.stat label="Cantidad del lote" :value="number_format($selectedLot?->quantity ?? 0)" />
                            <x-ui.stat label="Ya pesadas" :value="number_format(($selectedLot?->quantity ?? 0) - $remainingPieces)" tone="info" />
                            <x-ui.stat label="Pendiente" :value="number_format($remainingPieces)" :tone="$remainingPieces > 0 ? 'warn' : 'good'" />
                        </x-ui.stats>
                    @endif
                </x-ui.section>

                <x-ui.section step="2" title="Piezas pesadas" class="mt-5"
                    hint="La suma de buenas y malas no puede sobrepasar el pendiente del lote.">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.field label="Piezas buenas" required :error="$errors->first('goodPieces')">
                            <input type="number" wire:model="goodPieces" min="0"
                                class="w-full text-right font-bold tabular-nums" required>
                        </x-ui.field>

                        <x-ui.field label="Piezas malas" required :error="$errors->first('badPieces')">
                            <input type="number" wire:model="badPieces" min="0"
                                class="w-full text-right font-bold tabular-nums" required>
                        </x-ui.field>
                    </div>
                </x-ui.section>

                <x-ui.section step="3" title="Cuándo y notas" class="mt-5">
                    {{-- wire:ignore: el morph que dispara el selector de lote borraba la fecha. --}}
                    <x-ui.field label="Fecha y hora" required :error="$errors->first('weighedAt')">
                        <div wire:ignore>
                            <input type="datetime-local" wire:model="weighedAt" class="w-full" required>
                        </div>
                    </x-ui.field>

                    <x-ui.field label="Comentarios" optional class="mt-4"
                        hint="Contexto de la pesada: turno, incidencia, etc." :error="$errors->first('weighingComments')">
                        <textarea wire:model="weighingComments" rows="2" class="w-full"></textarea>
                    </x-ui.field>
                </x-ui.section>
            </form>

            <x-slot:note>
                Al guardar, la pesada aparece en la pestaña Pesadas y actualiza el avance del lote.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeWeighingModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveWeighing" wire:loading.attr="disabled"
                    wire:target="saveWeighing">
                    {{ $editingWeighingId ? 'Guardar cambios' : 'Registrar pesada' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Confirmación de borrado de pesada --}}
    @if ($showDeleteWeighingConfirm)
        @php $weighingToDelete = $editingWeighingId ? $weighings->find($editingWeighingId) : null; @endphp
        <x-ui-modal wire:key="modal-delete-weighing" title="Eliminar pesada"
            subtitle="Esta acción no se puede deshacer." close="$set('showDeleteWeighingConfirm', false)"
            maxWidth="lg">

            <x-ui.section title="Qué va a pasar">
                <x-ui.note tone="danger">
                    Se eliminará la pesada del lote
                    <strong>{{ $weighingToDelete?->lot?->lot_number ?? '—' }}</strong>
                    del {{ $weighingToDelete?->weighed_at?->format('d/m/Y H:i') ?? '—' }}
                    ({{ number_format($weighingToDelete?->good_pieces ?? 0) }} buenas ·
                    {{ number_format($weighingToDelete?->bad_pieces ?? 0) }} malas).
                    Sus piezas volverán al pendiente del lote.
                </x-ui.note>
            </x-ui.section>

            <x-slot:footer>
                <x-ui.btn variant="secondary"
                    wire:click="$set('showDeleteWeighingConfirm', false)">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="deleteWeighing" wire:loading.attr="disabled"
                    wire:target="deleteWeighing">Eliminar pesada</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Signature Modal Component - DESHABILITADO TEMPORALMENTE --}}
    {{-- <livewire:admin.signature-modal @signature-completed="refreshWorkOrder" /> --}}

    {{-- Historial completo de la orden. La sección "Historial de Estados" de
         arriba sólo muestra los últimos 5 cambios de estado; aquí está todo. --}}
    <div class="mt-6">
        <livewire:admin.history.history-explorer :entity-type="\App\Models\WorkOrder::class" :entity-id="$workOrder->id" :key="'historial-wo-' . $workOrder->id" />
    </div>

    {{-- Administración del catálogo de estados (colores incluidos) sin salir del WO.
         Se abre con 'open-statuses-wo-manager' y al guardar emite
         'statuses-wo-updated' para que esta vista recargue sus píldoras. --}}
    @can(\App\Livewire\Admin\StatusesWO\StatusWOManager::PERMISSION_VIEW)
        <livewire:admin.statuses-wo.status-wo-manager key="statuses-wo-manager" />
    @endcan
</x-ui.page>
