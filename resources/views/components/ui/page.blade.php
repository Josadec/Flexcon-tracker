@props([
    'eyebrow'   => null,  // rótulo del módulo: "Catálogo", "Compras"... (pantallas raíz)
    'title'     => '',
    'subtitle'  => null,  // una línea que explica para qué sirve la pantalla
    'back'      => null,  // URL de la pantalla anterior
    'backLabel' => 'Volver',
])

{{--
    Cabecera y contenedor de CUALQUIER pantalla del admin.

    Pone la clase raíz `.ui-screen`, de la que cuelgan los valores por defecto
    de inputs, foco y botones definidos en app.css. Si una pantalla no usa este
    componente, no hereda el sistema.

    Sobre el renglón superior: cuando hay pantalla anterior se muestra un enlace
    de texto ("‹ Volver a precios"), NO un botón con icono. El botón flotante
    junto al título metía un hueco a la izquierda y competía visualmente con las
    acciones reales de la pantalla. El enlace ocupa el mismo renglón que ocuparía
    el rótulo del módulo, así que la cabecera mide lo mismo en todas las vistas.

    <x-ui.page eyebrow="Catálogo" title="Partes" subtitle="...">
        <x-slot:actions>
            <x-ui.btn variant="primary" href="...">Nueva parte</x-ui.btn>
        </x-slot:actions>
        ... secciones ...
    </x-ui.page>
--}}
<div {{ $attributes->class('ui-screen space-y-5') }}>

    <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between lg:gap-6 dark:border-slate-700">
        <div class="min-w-0">
            @if ($back)
                <a href="{{ $back }}" wire:navigate
                    class="group -ml-1 mb-1 inline-flex items-center gap-1 rounded px-1 py-0.5 text-xs font-bold uppercase tracking-[0.12em] text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">
                    <svg class="size-3.5 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ $backLabel }}
                </a>
            @elseif ($eyebrow)
                <div class="mb-1 text-xs font-bold uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300">{{ $eyebrow }}</div>
            @endif

            <h1 class="truncate text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $title }}</h1>

            @if ($subtitle)
                <p class="mt-1 max-w-2xl text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-2 lg:justify-end">{{ $actions }}</div>
        @endisset
    </div>

    {{ $slot }}
</div>
