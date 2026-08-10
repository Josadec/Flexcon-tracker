{{--
    Paleta de búsqueda global. Se abre con Ctrl+K / ⌘K desde cualquier pantalla
    o con el botón de la barra superior (que dispara `abrir-buscador`).

    El abrir/cerrar vive en Alpine, no en Livewire: así la ventana aparece al
    instante y sólo el texto tecleado viaja al servidor.

    OJO — «está abierto» vive en `$store.buscador`, NO en el `x-data` de este
    componente. Cada tecla dispara un re-render de Livewire y el morph
    reinicializa el scope de Alpine del elemento raíz: con el estado ahí dentro,
    la ventana se cerraba sola al escribir (y al reabrirla aparecían los
    resultados, porque `q` sí sobrevive en Livewire). El store vive fuera del
    DOM y no se entera de los re-renders. El store se registra en el layout.

    OJO 2 — el panel NO lleva la clase `ui-screen`. Esa clase aplica los estilos
    de campo del sistema a todo lo que tenga dentro; heredarlos obligaba a
    pelearlos con `!important` y dejaba un doble borde con anillo azul encima.
--}}
<div
    x-data="{
        abrir() {
            $store.buscador.abierto = true;
            this.$nextTick(() => this.$refs.campo?.focus());
        },
        cerrar() {
            $store.buscador.abierto = false;
            $wire.clear();
        },
        mover(dir) {
            const items = [...this.$refs.panel.querySelectorAll('[data-resultado]')];
            if (! items.length) return;
            const actual = items.indexOf(document.activeElement);
            const siguiente = actual === -1
                ? (dir > 0 ? 0 : items.length - 1)
                : (actual + dir + items.length) % items.length;
            items[siguiente].focus();
        },
        primero() {
            this.$refs.panel.querySelector('[data-resultado]')?.click();
        },
    }"
    x-on:abrir-buscador.window="abrir()"
    x-on:keydown.window.ctrl.k.prevent="abrir()"
    x-on:keydown.window.meta.k.prevent="abrir()"
>
    <div x-show="$store.buscador.abierto" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" role="dialog" aria-modal="true"
        aria-label="Buscar en Flexcon Tracker" x-on:keydown.escape.window="cerrar()">

        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-[2px]" aria-hidden="true" x-on:click="cerrar()"></div>

        <div class="relative z-10 mx-auto flex min-h-full max-w-2xl items-start justify-center p-3 sm:p-6 sm:pt-[12vh]">
            <div class="flex max-h-[min(30rem,calc(100vh-8rem))] w-full flex-col overflow-hidden rounded-xl bg-white shadow-[0_28px_80px_-22px_rgba(15,23,42,0.58)] dark:bg-slate-800">

                {{-- Campo de búsqueda.
                     Va en su propia caja con aire alrededor: pegado a los bordes
                     del panel se leía como un trozo de cromo, no como un campo. --}}
                <div class="flex shrink-0 items-center gap-2 border-b border-slate-200 p-3 dark:border-slate-700 sm:p-4">
                    <div class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg border border-slate-300 bg-white px-3 transition focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/20 dark:border-slate-600 dark:bg-slate-900/40">
                        <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>

                        <input x-ref="campo" type="text" wire:model.live.debounce.250ms="q"
                            placeholder="Viajero, orden, parte o pantalla..."
                            autocomplete="off" spellcheck="false"
                            x-on:keydown.arrow-down.prevent="mover(1)"
                            x-on:keydown.arrow-up.prevent="mover(-1)"
                            x-on:keydown.enter.prevent="primero()"
                            class="h-11 w-full min-w-0 border-0 bg-transparent p-0 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:text-white dark:placeholder:text-slate-500">

                        <div wire:loading.delay wire:target="q" class="shrink-0">
                            <svg class="size-4 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                            </svg>
                        </div>
                    </div>

                    <button type="button" x-on:click="cerrar()" aria-label="Cerrar el buscador"
                        class="ui-icon-btn shrink-0 bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Resultados --}}
                <div x-ref="panel" class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-50 dark:bg-slate-900/30">
                    @if (strlen(trim($q)) < 2)
                        <x-ui.empty icon="search" title="¿Qué estás buscando?"
                            hint="El número de un viajero, una orden, una parte o el nombre de una pantalla. Con dos letras basta." />
                    @elseif ($this->total === 0)
                        <x-ui.empty icon="search" title="Nada coincide con «{{ $q }}»"
                            hint="Revisa el número o prueba con menos caracteres. Sólo se busca en lo que tu rol te deja ver." />
                    @else
                        <div class="space-y-2 p-2">
                            @foreach ($this->groups as $grupo)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                                    <header class="flex items-center gap-2 border-b border-slate-200 px-3 py-2 dark:border-slate-700">
                                        <svg class="size-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $grupo['icon'] }}"/>
                                        </svg>
                                        <h3 class="text-[11px] font-bold uppercase leading-4 tracking-wide text-slate-400 dark:text-slate-500">
                                            {{ $grupo['title'] }}
                                        </h3>
                                        <span class="ml-auto text-[11px] font-semibold tabular-nums text-slate-300 dark:text-slate-600">
                                            {{ count($grupo['items']) }}
                                        </span>
                                    </header>

                                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                        @foreach ($grupo['items'] as $item)
                                            <a href="{{ $item['url'] }}" wire:navigate data-resultado
                                                x-on:keydown.arrow-down.prevent="mover(1)"
                                                x-on:keydown.arrow-up.prevent="mover(-1)"
                                                x-on:click="$store.buscador.abierto = false"
                                                class="group flex items-center justify-between gap-3 border-s-2 border-transparent px-3 py-2.5 transition-colors hover:border-sky-500 hover:bg-sky-50/70 focus:border-sky-500 focus:bg-sky-50 focus:outline-none dark:hover:bg-slate-700/40 dark:focus:bg-slate-700/60">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ $item['label'] }}</span>
                                                    <span class="block truncate text-xs leading-4 text-slate-500 dark:text-slate-400">{{ $item['meta'] }}</span>
                                                </span>
                                                <svg class="size-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-sky-600 group-focus:text-sky-600 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Atajos --}}
                <div class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-200 bg-white px-4 py-2.5 text-[11px] text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    @foreach ([['↑↓', 'moverse'], ['Enter', 'abrir'], ['Esc', 'cerrar']] as [$tecla, $que])
                        <span class="inline-flex items-center gap-1.5">
                            <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-sans text-[10px] font-semibold text-slate-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-400">{{ $tecla }}</kbd>
                            {{ $que }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
