@props([
    'title' => '',
    'hint'  => null,   // una línea que dice qué hay dentro sin tener que abrirlo
    'open'  => false,  // cerrado por defecto: lo de dentro es ayuda, no trabajo
    'icon'  => 'info', // info | book
])

@php
    $paths = [
        'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'book' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    ];
@endphp

{{--
    Bloque plegable. Se usa para la ayuda de pantalla: está a mano cuando hace
    falta y no ocupa sitio el resto del tiempo.

    Es <details> nativo a propósito: funciona sin JavaScript, con teclado y con
    lector de pantalla, y no se pierde en los re-renders de Livewire.

    <x-ui.disclosure title="Cómo usar este tablero" hint="Guía y leyenda">
        ...
    </x-ui.disclosure>
--}}
<details @if ($open) open @endif
    {{ $attributes->class('group overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800') }}>

    <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/40 [&::-webkit-details-marker]:hidden">
        <svg class="size-4 shrink-0 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $paths[$icon] ?? $paths['info'] }}"/>
        </svg>

        <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">{{ $title }}</span>

        @if ($hint)
            <span class="hidden truncate text-xs text-slate-400 sm:block dark:text-slate-500">{{ $hint }}</span>
        @endif

        {{-- Resumen que se ve con el bloque cerrado: un plegable que esconde
             trabajo pendiente sin dejar rastro sería peor que no plegarlo. --}}
        @isset($aside)
            <span class="shrink-0">{{ $aside }}</span>
        @endisset

        <span class="ml-auto flex shrink-0 items-center gap-1.5 text-xs font-semibold text-slate-400 dark:text-slate-500">
            <span class="hidden sm:inline group-open:hidden">Ver</span>
            <span class="hidden group-open:sm:inline">Ocultar</span>
            <svg class="size-4 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </summary>

    <div class="border-t border-slate-200 dark:border-slate-700">
        {{ $slot }}
    </div>
</details>
