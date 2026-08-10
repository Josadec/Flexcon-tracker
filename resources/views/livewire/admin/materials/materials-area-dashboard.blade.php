<x-ui.page eyebrow="Materiales" title="Gestión de materiales"
    subtitle="Tu mesa de trabajo: crear viajeros, liberar su material y atender lo que el flujo de CRIMP te regresa.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.materials.index') }}">Panel de Materiales</x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.sent-lists.display') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
            Ir al tablero
        </x-ui.btn>
    </x-slot:actions>

    {{-- Lo que importa para Materiales, no conteos generales del sistema --}}
    <x-ui.stats cols="5">
        <x-ui.stat label="Por liberar" :value="number_format($stats['por_liberar'])"
            :tone="$stats['por_liberar'] > 0 ? 'warn' : 'good'"
            :help="$stats['por_liberar_crimp'].' de ellos son de viajeros con CRIMP.'" />
        <x-ui.stat label="Viajeros con CRIMP" :value="number_format($stats['viajeros_crimp'])" tone="accent"
            help="Siguen el flujo de ocho pasos, con lotes de CRIMP y doble pesada." />
        <x-ui.stat label="CRIMP sin lotes" :value="number_format($stats['crimp_sin_lotes'])"
            :tone="$stats['crimp_sin_lotes'] > 0 ? 'warn' : 'neutral'"
            help="Viajeros CRIMP a los que todavía no les cargas lotes: Empaque no sabrá con qué trabajar." />
        <x-ui.stat label="Lotes de CRIMP" :value="number_format($stats['lotes_crimp'])" tone="info" />
        <x-ui.stat label="Piezas en CRIMP" :value="number_format($stats['piezas_crimp'])" tone="info" />
    </x-ui.stats>

    {{-- Listas de envío que están en Materiales --}}
    @include('livewire.admin.sent-lists.partials.pending-lists-panel', [
        'pendingSentLists' => $pendingSentLists,
        'deptLabel' => 'Materiales',
        'deptColor' => 'blue',
    ])

    {{-- Guía corta: los cuatro momentos del área --}}
    <x-ui.section title="Tus cuatro momentos en el flujo"
        hint="Dos se hacen aquí; los otros dos se toman en el tablero, con un clic desde la lista de pendientes.">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['2', 'Cargar lotes de CRIMP', 'Sólo en viajeros CRIMP. Se hace en el tablero.', 'accent'],
                ['3', 'Liberar el material', 'Aquí mismo. Desbloquea la inspección de Calidad.', 'good'],
                ['6', 'Tomar la decisión', 'Cerrar, completar o nuevo lote. En el tablero.', 'accent'],
                ['8', 'Recibir los sobrantes', 'Cierra el ciclo del viajero. En el tablero.', 'info'],
            ] as [$n, $titulo, $desc, $tono])
                @php
                    $clases = match ($tono) {
                        'good' => 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/30',
                        'accent' => 'border-cyan-300 bg-cyan-50 dark:border-cyan-800 dark:bg-cyan-950/30',
                        default => 'border-sky-300 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/30',
                    };
                @endphp
                <div class="flex gap-3 rounded-lg border px-4 py-3 {{ $clases }}">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white dark:bg-slate-200 dark:text-slate-900">{{ $n }}</span>
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ $titulo }}</span>
                        <span class="block text-xs leading-5 text-slate-600 dark:text-slate-300">{{ $desc }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </x-ui.section>

    {{-- Pestañas de trabajo --}}
    <div class="flex flex-wrap gap-2">
        @foreach ([
            'work-orders' => 'Órdenes y viajeros',
            'lots' => 'Viajeros (listado)',
        ] as $modo => $etiqueta)
            <button type="button" wire:click="switchView('{{ $modo }}')"
                @class([
                    'rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors',
                    'bg-sky-600 text-white' => $viewMode === $modo,
                    'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700' => $viewMode !== $modo,
                ])>
                {{ $etiqueta }}
            </button>
        @endforeach
    </div>

    @if ($viewMode === 'work-orders')
        <livewire:admin.materials.dynamic-sent-list-view :key="'work-orders-view'" />
    @else
        <livewire:admin.materials.lot-management :key="'lots-view'" />
    @endif
</x-ui.page>
