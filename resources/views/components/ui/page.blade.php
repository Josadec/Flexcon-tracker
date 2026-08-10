@props([
    'eyebrow'     => null,  // rótulo del módulo: "Catálogo", "Compras"... (pantallas raíz)
    'eyebrowHref' => null,  // destino del rótulo; si se omite se deduce del propio rótulo
    'title'       => '',
    'subtitle'    => null,  // una línea que explica para qué sirve la pantalla
    'back'        => null,  // URL de la pantalla anterior
    'backLabel'   => 'Volver',
])

@php
    // El rótulo era texto muerto encima del título. Ahora es el primer nivel de
    // navegación —el camino de vuelta al área— siempre que el destino exista y
    // el usuario pueda entrar: enlazar a un 403 sería peor que no enlazar.
    $eyebrowRoute = $eyebrowHref ? null : \App\Support\AdminNavigation::eyebrowRoute($eyebrow);
    $eyebrowUrl = $eyebrowHref
        ?: (\App\Support\AdminNavigation::canAccess($eyebrowRoute, auth()->user()) ? route($eyebrowRoute) : null);

    // Las migas de la barra superior dicen lo mismo que el rótulo, así que
    // donde ellas se ven, éste sobra. En móvil la barra las esconde por falta
    // de ancho: ahí el rótulo sigue siendo el único contexto de la pantalla.
    $hayMigas = count(\App\Support\AdminNavigation::breadcrumb(auth()->user())) > 1;
@endphp

{{--
    Cabecera y contenedor de CUALQUIER pantalla del admin.

    Pone la clase raíz `.ui-screen`, de la que cuelgan los valores por defecto
    de inputs, foco y botones definidos en app.css. Si una pantalla no usa este
    componente, no hereda el sistema.

    Volver: cuando hay pantalla anterior, "‹ Volver a precios" se dibuja como
    botón secundario a la DERECHA, como primera acción de la fila. Nada se
    apila encima del título: la columna izquierda es siempre rótulo → título →
    subtítulo, igual que en los listados, así la cabecera mide lo mismo en
    todas las vistas. Volver es la acción menos comprometida de la pantalla,
    por eso va al extremo izquierdo del grupo y la primaria queda al final.

    <x-ui.page eyebrow="Catálogo" title="Partes" subtitle="...">
        <x-slot:actions>
            <x-ui.btn variant="primary" href="...">Nueva parte</x-ui.btn>
        </x-slot:actions>
        ... secciones ...
    </x-ui.page>
--}}
<div {{ $attributes->class('ui-screen space-y-5 pt-1') }}>

    <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between lg:gap-6 dark:border-slate-700">
        <div class="min-w-0">
            @if ($eyebrow)
                <div @class([
                    'mb-1 text-xs font-bold uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300',
                    'sm:hidden' => $hayMigas,
                ])>
                    @if ($eyebrowUrl)
                        <a href="{{ $eyebrowUrl }}" wire:navigate
                            class="inline-flex items-center gap-1 rounded hover:text-sky-900 hover:underline underline-offset-4 dark:hover:text-sky-100">
                            {{ $eyebrow }}
                            <svg class="size-3 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @else
                        {{ $eyebrow }}
                    @endif
                </div>
            @endif

            <h1 class="truncate text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $title }}</h1>

            @if ($subtitle)
                <p class="mt-1 max-w-2xl text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>

        @if ($back || isset($actions))
            <div class="flex shrink-0 flex-wrap items-center gap-2 lg:justify-end">
                @if ($back)
                    <a href="{{ $back }}" wire:navigate class="ui-btn ui-btn--secondary group">
                        <svg class="transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                        </svg>
                        {{ $backLabel }}
                    </a>
                @endif
                @isset($actions){{ $actions }}@endisset
            </div>
        @endif
    </div>

    {{ $slot }}
</div>
