@props([
    'title' => 'No hay registros',
    'hint'  => null,   // qué puede hacer el usuario para que aparezca algo
    'icon'  => 'box',  // box | search | doc
])

@php
    $paths = [
        'box'    => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
        'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        'doc'    => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    ];
@endphp

{{-- Estado vacío. Nunca dejar una tabla o panel en blanco sin explicar por qué. --}}
<div {{ $attributes->class('px-6 py-12 text-center') }}>
    <svg class="mx-auto size-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $paths[$icon] ?? $paths['box'] }}"/>
    </svg>
    <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $title }}</p>
    @if ($hint)
        <p class="mx-auto mt-1 max-w-sm text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
    @isset($action)
        <div class="mt-4 flex justify-center">{{ $action }}</div>
    @endisset
</div>
