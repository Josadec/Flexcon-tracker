@php
    $pasos = match ($activeSection) {
        'crimp' => $crimpSteps,
        'estandar' => $standardSteps,
        default => [],
    };
@endphp

<x-ui.page eyebrow="Guía" :title="$section['title']" :subtitle="$section['description']">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.sent-lists.display') }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
            Abrir el tablero
        </x-ui.btn>
    </x-slot:actions>

    {{-- Navegación de la guía --}}
    <div class="flex flex-wrap gap-2">
        @foreach ($sections as $key => $tab)
            <button type="button" wire:click="goTo('{{ $key }}')"
                @class([
                    'rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors',
                    'bg-sky-600 text-white' => $activeSection === $key,
                    'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700' => $activeSection !== $key,
                ])>
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Cómo se lee el semáforo. Va arriba de todo porque es lo que se usa
         en todas las pantallas, y es lo primero que hay que reconocer. --}}
    <x-ui.section title="Antes de empezar: los colores"
        hint="Es el lenguaje de todo el sistema. Si reconoces estos cuatro colores, ya sabes cuándo te toca.">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($legend as [$estado, $nombre, $significa])
                @php
                    $color = match ($estado) {
                        'done' => 'bg-green-500',
                        'pending' => 'bg-amber-400',
                        'blocked' => 'bg-red-500',
                        default => 'bg-slate-300 dark:bg-slate-600',
                    };
                @endphp
                <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                    <span class="mt-1 size-4 shrink-0 rounded-full {{ $color }} {{ $estado === 'pending' ? 'animate-pulse' : '' }}" aria-hidden="true"></span>
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ $nombre }}</span>
                        <span class="block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $significa }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </x-ui.section>

    {{-- ── Flujos paso a paso ─────────────────────────────────────── --}}
    @if (!empty($pasos))

        {{-- Mapa del flujo: quién toca qué, de un vistazo --}}
        <x-ui.section title="El flujo de un vistazo"
            hint="Los pasos en orden. El color dice de qué área es cada uno.">
            <div class="flex flex-wrap items-stretch gap-2">
                @foreach ($pasos as $i => $paso)
                    @php
                        $tono = match ($paso['actor']) {
                            'Materiales' => 'border-violet-300 bg-violet-50 text-violet-900 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
                            'Producción' => 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
                            'Calidad' => 'border-green-300 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950/40 dark:text-green-200',
                            'Empaque' => 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
                            default => 'border-slate-300 bg-slate-50 text-slate-800 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200',
                        };
                    @endphp
                    <a href="#paso-{{ $paso['n'] }}"
                        class="flex min-w-[8.5rem] flex-1 flex-col rounded-lg border px-3 py-2.5 transition-transform hover:-translate-y-0.5 {{ $tono }}">
                        <span class="text-[11px] font-bold uppercase tracking-wide opacity-70">Paso {{ $paso['n'] }} · {{ $paso['actor'] }}</span>
                        <span class="mt-0.5 text-sm font-bold leading-5">{{ $paso['title'] }}</span>
                    </a>
                    @if (!$loop->last)
                        <span class="hidden self-center text-slate-300 dark:text-slate-600 lg:block" aria-hidden="true">→</span>
                    @endif
                @endforeach
            </div>
        </x-ui.section>

        {{-- Los pasos, con su maqueta --}}
        @foreach ($pasos as $paso)
            <x-tutorial.step id="paso-{{ $paso['n'] }}" wire:key="paso-{{ $activeSection }}-{{ $paso['n'] }}"
                :number="$paso['n']"
                :title="$paso['title']"
                :actor="$paso['actor']"
                :where="$paso['where']"
                :summary="$paso['summary']"
                :does="$paso['does']"
                :after="$paso['after'] ?? null"
                :warning="$paso['warning'] ?? null"
                :tone="in_array($paso['n'], [5, 6], true) && $activeSection === 'crimp' ? 'accent' : 'info'">

                <x-tutorial.shot
                    :screen="$paso['shot']['screen']"
                    :path="$paso['shot']['path']"
                    :blocks="$paso['shot']['blocks']"
                    :caption="$paso['shot']['caption'] ?? null" />
            </x-tutorial.step>
        @endforeach

        @if ($activeSection === 'crimp')
            <x-ui.note tone="info">
                <strong>¿Y si la parte no es CRIMP?</strong> El viajero sigue el flujo de siempre: sin lotes de CRIMP,
                sin doble pesada y con tres decisiones al cerrar. Está en la pestaña
                <button type="button" wire:click="goTo('estandar')" class="font-bold underline">Flujo estándar</button>.
            </x-ui.note>
        @else
            <x-ui.note tone="info">
                <strong>¿La parte es CRIMP?</strong> Entonces el flujo tiene ocho pasos y dos pesadas por viajero.
                Está en la pestaña
                <button type="button" wire:click="goTo('crimp')" class="font-bold underline">Flujo CRIMP</button>.
            </x-ui.note>
        @endif
    @endif

    {{-- ── Guía por área ──────────────────────────────────────────── --}}
    @if ($roleGuide)
        <x-ui.section title="Qué te toca a ti" :hint="$roleGuide['intro']">
            <div class="flex flex-wrap gap-2">
                @foreach ($roleGuide['steps'] as $n => $que)
                    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <span class="flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white dark:bg-slate-200 dark:text-slate-900">{{ $n }}</span>
                        {{ $que }}
                    </span>
                @endforeach
            </div>

            @if ($activeSection !== 'admin')
                <x-ui.note tone="muted" class="mt-4">
                    Los pasos numerados son los del <button type="button" wire:click="goTo('crimp')" class="font-bold underline">flujo CRIMP</button>.
                    Ahí está cada uno explicado con su pantalla.
                </x-ui.note>
            @endif
        </x-ui.section>

        <x-ui.section title="Tus pantallas" hint="Los accesos que vas a usar todos los días.">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($roleGuide['links'] as [$titulo, $desc, $ruta])
                    <a href="{{ route($ruta) }}" wire:navigate
                        class="group rounded-lg border border-slate-200 bg-white px-4 py-3 transition-colors hover:border-sky-400 hover:bg-sky-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-sky-600 dark:hover:bg-sky-950/40">
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $titulo }}</span>
                            <svg class="size-4 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                        <span class="mt-0.5 block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $desc }}</span>
                    </a>
                @endforeach
            </div>
        </x-ui.section>
    @endif

    {{-- Cierre --}}
    <x-ui.section title="Si algo no te deja avanzar"
        hint="Casi siempre es una de estas tres, y las tres se resuelven mirando el semáforo.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="El botón no abre nada"
                value="Ese paso es de otra área"
                help="Cada acción está limitada al área que le corresponde. Revisa la etiqueta «Lo hace…» del paso." />
            <x-ui.kv label="La inspección aparece bloqueada"
                value="Falta liberar el material"
                help="Es el paso 3. Sin material liberado, Calidad no puede avanzar." />
            <x-ui.kv label="No veo lotes de CRIMP"
                value="La parte no está marcada como CRIMP"
                help="Se marca en el catálogo de Partes; sin esa marca el viajero va por el flujo estándar." />
        </dl>
    </x-ui.section>
</x-ui.page>
