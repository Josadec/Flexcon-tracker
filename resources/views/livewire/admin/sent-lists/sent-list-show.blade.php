{{--
    DETALLE DE UNA LISTA PRELIMINAR

    Orden de lectura: en qué punto del flujo va → recursos y capacidad →
    la vista del departamento. La pestaña activa vive en la URL (?dept=...),
    así que el enlace se puede compartir.
--}}
@php
    $statusTone = match ($sentList->status) {
        'confirmed' => 'good',
        'pending'   => 'warn',
        'canceled'  => 'bad',
        default     => 'neutral',
    };
    $util    = $sentList->capacity_utilization;
    $utilBar = $util >= 100 ? 'bg-red-500' : ($util >= 80 ? 'bg-amber-500' : 'bg-green-500');
@endphp

<x-ui.page :title="'Lista preliminar #'.$sentList->id"
    :subtitle="$sentList->start_date && $sentList->end_date
        ? 'Semana '.$sentList->start_date->weekOfYear.' de '.$sentList->start_date->year.' · '.$sentList->start_date->format('d/m/Y').' – '.$sentList->end_date->format('d/m/Y')
        : 'Sin período de planificación registrado.'"
    back="{{ route('admin.sent-lists.index') }}" backLabel="Volver a listas preliminares">

    <x-slot:actions>
        <x-ui.badge :tone="$statusTone" dot>{{ $sentList->status_label }}</x-ui.badge>

        @if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('Materiales'))
            <x-ui.btn variant="secondary" wire:click="openStatusModal">Cambiar estado</x-ui.btn>
        @endif

        <x-ui.btn variant="secondary" :href="route('admin.sent-lists.export-pdf', $sentList->id)" :navigate="false">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            PDF
        </x-ui.btn>

        <x-ui.btn variant="primary" href="{{ route('admin.sent-lists.display.sl', $sentList->id) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M5 5h6a2 2 0 012 2v2"/></svg>
            Tablero de piso
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Rechazos sin resolver: lo que detiene la lista --}}
    @if ($sentList->unresolvedRejections->isNotEmpty())
        <x-ui.section title="Rechazos sin resolver"
            hint="La lista no puede cerrarse mientras existan rechazos pendientes.">
            <ul class="divide-y divide-slate-200 rounded-lg border border-red-300 dark:divide-slate-700 dark:border-red-800">
                @foreach ($sentList->unresolvedRejections as $rej)
                    <li class="bg-red-50 px-4 py-3 dark:bg-red-950/30">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-red-900 dark:text-red-100">
                                    Lote {{ $rej->lot->lot_number ?? '—' }}
                                    <span class="font-normal">· rechazado por {{ $rej->rejectedBy->name ?? 'sin usuario' }}</span>
                                </p>
                                <p class="mt-0.5 text-xs text-red-800 dark:text-red-200">
                                    {{ $rej->reason ?? $rej->comments ?? 'Sin motivo capturado' }}
                                </p>
                            </div>
                            <span class="shrink-0 text-xs text-red-700 dark:text-red-300">
                                {{ $rej->created_at?->format('d/m/Y H:i') }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.section>
    @endif

    {{-- Avance por el flujo de departamentos --}}
    <x-ui.section title="Avance por departamentos"
        :hint="$doneStages.' de '.count($stages).' etapas aprobadas · departamento actual: '.$sentList->department_label">

        <ol class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($stages as $key => $stage)
                @php
                    $isDone    = ! is_null($stage['at']);
                    $isCurrent = $key === $sentList->current_department;
                @endphp
                <li class="rounded-lg border px-4 py-3
                    {{ $isDone ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/30'
                        : ($isCurrent ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
                        : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800') }}">
                    <div class="flex items-center gap-2">
                        @if ($isDone)
                            <svg class="size-4 shrink-0 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @elseif ($isCurrent)
                            <span class="size-2.5 shrink-0 animate-pulse rounded-full bg-amber-500"></span>
                        @else
                            <span class="size-2.5 shrink-0 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                        @endif
                        <span class="truncate text-sm font-bold
                            {{ $isDone ? 'text-green-900 dark:text-green-100'
                                : ($isCurrent ? 'text-amber-900 dark:text-amber-100'
                                : 'text-slate-500 dark:text-slate-400') }}">
                            {{ $stage['label'] }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs
                        {{ $isDone ? 'text-green-700 dark:text-green-300'
                            : ($isCurrent ? 'text-amber-700 dark:text-amber-300'
                            : 'text-slate-400 dark:text-slate-500') }}">
                        {{ $isDone ? 'Aprobado '.$stage['at']->format('d/m/Y') : ($isCurrent ? 'En curso' : 'Pendiente') }}
                    </p>
                </li>
            @endforeach
        </ol>
    </x-ui.section>

    {{-- Recursos y capacidad --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <x-ui.section title="Recursos asignados" hint="Lo que se planeó para esta semana.">
            <x-ui.stats cols="2">
                <x-ui.stat label="Personas" :value="$sentList->num_persons" />
                <x-ui.stat label="Órdenes" :value="$workOrders->count()" tone="info" />
            </x-ui.stats>

            <div class="mt-4">
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Turnos</p>
                @if ($sentList->shifts->isEmpty())
                    <p class="text-sm text-slate-400">Sin turnos asignados</p>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($sentList->shifts as $shift)
                            <x-ui.badge tone="info">{{ $shift->name }}</x-ui.badge>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-ui.section>

        <x-ui.section class="xl:col-span-2" title="Capacidad de la semana"
            hint="Horas disponibles contra horas comprometidas por las órdenes de la lista.">
            <x-ui.stats cols="3">
                <x-ui.stat label="Horas disponibles" :value="number_format($sentList->total_available_hours, 1)" unit="h" tone="info" />
                <x-ui.stat label="Horas usadas" :value="number_format($sentList->used_hours, 1)" unit="h" tone="warn" />
                <x-ui.stat label="Horas restantes" :value="number_format($sentList->remaining_hours, 1)" unit="h"
                    :tone="$sentList->remaining_hours >= 0 ? 'good' : 'bad'" />
            </x-ui.stats>

            <div class="mt-4">
                <div class="mb-1.5 flex items-baseline justify-between text-xs">
                    <span class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Utilización</span>
                    <span class="font-bold tabular-nums {{ $util >= 100 ? 'text-red-700 dark:text-red-400' : 'text-slate-700 dark:text-slate-200' }}">
                        {{ number_format($util, 1) }}%
                    </span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                    <div class="h-full rounded-full transition-all {{ $utilBar }}" style="width: {{ min($util, 100) }}%"></div>
                </div>
            </div>

            @if ($sentList->remaining_hours < 0)
                <x-ui.note tone="danger" class="mt-4" title="Capacidad sobrepasada">
                    Las órdenes de esta lista piden {{ number_format(abs($sentList->remaining_hours), 1) }} horas más
                    de las disponibles. Habrá que mover órdenes a otra semana o sumar personal.
                </x-ui.note>
            @elseif ($util >= 80)
                <x-ui.note tone="warn" class="mt-4">
                    La semana va al {{ number_format($util, 0) }}% de su capacidad: queda poco margen para imprevistos.
                </x-ui.note>
            @endif
        </x-ui.section>
    </div>

    {{-- Vista del departamento --}}
    <x-ui.section title="Vista por departamento"
        hint="Cada pestaña muestra la pantalla del área. Sólo ves los departamentos de tu rol.">

        @if (empty($allowedTabs))
            <x-ui.note tone="muted" title="Sin acceso a departamentos">
                Tu rol no tiene permiso para ver el detalle de ningún departamento de esta lista.
            </x-ui.note>
        @else
            {{-- Pestañas --}}
            <div class="border-b border-slate-200 dark:border-slate-700">
                <nav class="-mb-px flex flex-wrap gap-x-6" aria-label="Departamento">
                    @foreach ($departments as $key => $label)
                        @php
                            $canOpen   = in_array($key, $allowedTabs, true);
                            $isDone    = ! is_null($stages[$key]['at'] ?? null);
                            $isCurrent = $key === $sentList->current_department;
                            $isActive  = $key === $tab;
                        @endphp
                        @if ($canOpen)
                            <button type="button" wire:click="selectTab('{{ $key }}')"
                                @class([
                                    'inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition-colors',
                                    'border-sky-600 text-sky-700 dark:text-sky-300' => $isActive,
                                    'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => ! $isActive,
                                ])>
                                @if ($isDone)
                                    <svg class="size-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @elseif ($isCurrent)
                                    <span class="size-2 rounded-full bg-amber-500"></span>
                                @else
                                    <span class="size-2 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                                @endif
                                {{ $label }}
                            </button>
                        @else
                            <span class="inline-flex cursor-not-allowed items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-1 py-3 text-sm font-semibold text-slate-300 dark:text-slate-600"
                                title="Tu rol no tiene acceso a {{ $label }}">
                                <span class="size-2 rounded-full bg-slate-200 dark:bg-slate-700"></span>
                                {{ $label }}
                            </span>
                        @endif
                    @endforeach
                </nav>
            </div>

            {{-- Contenido: sólo se monta la pestaña activa, no las cinco --}}
            <div class="pt-4">
                @switch($tab)
                    @case('materiales')
                        @livewire('admin.sent-lists.sent-list-materials-view', ['sentList' => $sentList], key('dept-materiales-'.$sentList->id))
                        @break
                    @case('inspeccion')
                        @livewire('admin.sent-lists.sent-list-inspection-view', ['sentList' => $sentList], key('dept-inspeccion-'.$sentList->id))
                        @break
                    @case('produccion')
                        @livewire('admin.sent-lists.sent-list-production-view', ['sentList' => $sentList], key('dept-produccion-'.$sentList->id))
                        @break
                    @case('calidad')
                        @livewire('admin.sent-lists.sent-list-quality-view', ['sentList' => $sentList], key('dept-calidad-'.$sentList->id))
                        @break
                    @case('envios')
                        @livewire('admin.sent-lists.sent-list-packaging-view', ['sentList' => $sentList], key('dept-envios-'.$sentList->id))
                        @break
                    @default
                        <x-ui.empty icon="doc" title="Selecciona un departamento"
                            hint="Elige una pestaña para ver su detalle." />
                @endswitch
            </div>
        @endif
    </x-ui.section>

    {{-- Cambio de estado --}}
    @if ($showStatusModal)
        <x-ui-modal wire:key="modal-status-show"
            title="Cambiar estado de la lista #{{ $sentList->id }}"
            subtitle="El estado define si la lista sigue en planeación o ya se cerró."
            close="closeStatusModal" maxWidth="2xl">

            <x-slot:context>
                <x-ui-modal.ctx label="Lista" :value="'#'.$sentList->id" />
                <x-ui-modal.ctx label="Departamento" :value="$sentList->department_label" />
                <x-ui-modal.ctx label="Órdenes" :value="$workOrders->count().' work orders'" />
                <x-ui-modal.ctx label="Estado actual" :value="$sentList->status_label" />
            </x-slot:context>

            @php
                // Se calcula al abrir el modal para poder avisar ANTES de guardar
                // si cancelar va a borrar la lista o sólo marcarla.
                $runningWOs = $sentList->getRunningWorkOrders();
            @endphp

            <x-ui.section title="Nuevo estado" hint="Puedes moverla en cualquier dirección, incluido regresarla a Pendiente.">
                <div class="grid grid-cols-1 gap-3">
                    <x-ui.choice tone="warn" title="Pendiente"
                        desc="Sigue en planeación. Es el único estado que permite editarla."
                        :selected="$newStatus === 'pending'"
                        wire:click="$set('newStatus', 'pending')" />
                    <x-ui.choice tone="good" title="Confirmada"
                        desc="La lista queda cerrada. Puedes regresarla a Pendiente cuando quieras."
                        :selected="$newStatus === 'confirmed'"
                        wire:click="$set('newStatus', 'confirmed')" />
                    <x-ui.choice tone="bad" title="Cancelada"
                        :desc="$runningWOs->isEmpty()
                            ? 'La lista se descarta Y SE ELIMINA: ninguna de sus órdenes ha empezado.'
                            : 'La lista se descarta pero NO se elimina: ya hay órdenes corriendo.'"
                        :selected="$newStatus === 'canceled'"
                        wire:click="$set('newStatus', 'canceled')" />
                </div>

                @error('newStatus')
                    <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
                @enderror

                {{-- Aviso explícito de lo que va a pasar al cancelar --}}
                @if ($newStatus === 'canceled')
                    @if ($runningWOs->isEmpty())
                        <x-ui.note tone="danger" class="mt-4" title="Esta lista se va a ELIMINAR">
                            Ninguna de sus órdenes tiene trabajo registrado, así que al cancelarla se borra.
                            Las Work Orders y sus lotes <strong>no</strong> se borran; sólo desaparece la lista
                            de planeación, sus turnos, sus órdenes asociadas y su historial de rechazos.
                            <strong>Esta acción no se puede deshacer.</strong>
                        </x-ui.note>
                    @else
                        <x-ui.note tone="warn" class="mt-4"
                            title="La lista se conserva: {{ $runningWOs->count() }} {{ Str::plural('orden', $runningWOs->count()) }} ya {{ $runningWOs->count() === 1 ? 'está' : 'están' }} corriendo">
                            <span class="mb-2 block">Se marcará como cancelada, pero no se elimina porque ya hay trabajo registrado en:</span>
                            <span class="flex flex-wrap gap-1.5">
                                @foreach ($runningWOs->take(6) as $rwo)
                                    <x-ui.badge tone="warn">{{ $rwo->purchaseOrder->wo ?? $rwo->wo_number }}</x-ui.badge>
                                @endforeach
                                @if ($runningWOs->count() > 6)
                                    <x-ui.badge tone="neutral">+{{ $runningWOs->count() - 6 }} más</x-ui.badge>
                                @endif
                            </span>
                        </x-ui.note>
                    @endif
                @endif
            </x-ui.section>

            <x-slot:note>
                Cancelar elimina la lista sólo si ninguna de sus órdenes empezó a trabajarse.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeStatusModal">Cancelar</x-ui.btn>

                @if ($newStatus === 'canceled' && $runningWOs->isEmpty())
                    {{-- Va a borrar: botón rojo y confirmación extra. --}}
                    <x-ui.btn variant="danger" wire:click="saveStatus"
                        wire:confirm="Se va a ELIMINAR la lista #{{ $sentList->id }}. Esta acción no se puede deshacer. ¿Continuar?"
                        wire:loading.attr="disabled" wire:target="saveStatus">Cancelar y eliminar lista</x-ui.btn>
                @else
                    <x-ui.btn variant="primary" wire:click="saveStatus"
                        wire:loading.attr="disabled" wire:target="saveStatus">Guardar estado</x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
