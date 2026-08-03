{{--
    TABLERO DE MATERIALES

    Materiales toca el flujo en cuatro momentos: liberar el material, capturar
    los lotes de CRIMP, decidir el cierre y recibir los sobrantes. El tablero se
    lee en ese orden y todo lo pendiente sale de App\Support\PendingActions.
--}}
<x-ui.page eyebrow="Área · Materiales" title="Materiales"
    subtitle="Lo que Materiales tiene que liberar, decidir y recibir para que los lotes avancen.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.sent-lists.index') }}">Listas de envío</x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.sent-lists.display') }}">Abrir tablero de piso</x-ui.btn>
    </x-slot:actions>

    {{-- Carga del área --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Pendientes de Materiales" :value="$totalPending"
            :tone="$totalPending > 0 ? 'warn' : 'good'"
            help="Suma de liberaciones, decisiones y recepciones de sobrantes." />
        <x-ui.stat label="Por liberar material" :value="$byPhase['release']"
            :tone="$byPhase['release'] > 0 ? 'warn' : 'good'"
            help="Sin liberar, el lote no puede inspeccionarse." />
        <x-ui.stat label="Esperan decisión" :value="$byPhase['decision']"
            :tone="$byPhase['decision'] > 0 ? 'info' : 'good'"
            help="Ya se empacaron; falta decidir si se cierran, se completan o se abre otro lote." />
        <x-ui.stat label="Sobrantes por recibir" :value="$byPhase['surplus']"
            :tone="$byPhase['surplus'] > 0 ? 'info' : 'good'"
            :help="$surplusInTransit > 0 ? number_format($surplusInTransit).' piezas ya entregadas por Empaque' : 'Nada en tránsito'" />
    </x-ui.stats>

    {{-- Bloqueos que no son una etapa del flujo pero detienen a otras áreas --}}
    @if ($advisories->isNotEmpty())
        <x-ui.section title="Bloqueos por atender"
            hint="No son una etapa del flujo, pero detienen a otra área hasta que Materiales los resuelva.">
            <div class="space-y-2">
                @foreach ($advisories as $adv)
                    @php $lot = $adv['lot']; $wo = $lot->workOrder; @endphp
                    <div wire:key="adv-{{ $lot->id }}"
                        class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-amber-800 dark:bg-amber-950/30">
                        <div class="flex min-w-0 items-start gap-3">
                            <svg class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-amber-900 dark:text-amber-100">
                                    {{ $adv['title'] }} — {{ $lot->lot_number }}
                                </p>
                                <p class="mt-0.5 text-xs leading-4 text-amber-800 dark:text-amber-200">{{ $adv['detail'] }}</p>
                            </div>
                        </div>
                        <x-ui.btn variant="warning" size="sm"
                            href="{{ route('admin.sent-lists.display.wo', $wo?->id) }}">Capturar lotes</x-ui.btn>
                    </div>
                @endforeach
            </div>
        </x-ui.section>
    @endif

    {{-- Cola de trabajo --}}
    <x-ui.section title="Cola de trabajo de Materiales"
        hint="Cada renglón es un lote esperando que Materiales registre algo.">
        <x-ui.pending-table :items="$mine"
            emptyTitle="Materiales está al día"
            emptyHint="Ningún lote está esperando una liberación, una decisión ni una recepción de sobrantes." />
    </x-ui.section>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        {{-- Inventario CRIMP --}}
        <x-ui.section title="Inventario de CRIMP"
            hint="Los lotes de CRIMP se capturan aquí y son requisito para que Empaque registre el Paso 5.">
            <x-ui.stats cols="3">
                <x-ui.stat label="Viajeros con CRIMP" :value="number_format($crimpViajeros)" tone="accent" />
                <x-ui.stat label="Lotes de CRIMP" :value="number_format($crimpLotsTotal)" tone="accent" />
                <x-ui.stat label="Piezas en CRIMP" :value="number_format($crimpPieces)" />
            </x-ui.stats>
        </x-ui.section>

        {{-- Listas paradas aquí --}}
        <x-ui.section title="Listas de envío en Materiales"
            hint="Listas pendientes cuyo departamento actual es Materiales.">
            @if ($sentListsHere->isEmpty())
                <x-ui.empty icon="doc" title="Ninguna lista parada aquí"
                    hint="Todas las listas pendientes están en otro departamento." />
            @else
                <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    @foreach ($sentListsHere as $sl)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">Lista #{{ $sl->id }}</span>
                                <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $sl->workOrders->count() }} {{ Str::plural('orden', $sl->workOrders->count()) }} ·
                                    {{ $sl->created_at->format('d/m/Y') }}
                                </span>
                            </span>
                            <x-ui.btn variant="secondary" size="sm"
                                href="{{ route('admin.sent-lists.show', $sl->id) }}">Ver</x-ui.btn>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.section>
    </div>
</x-ui.page>
