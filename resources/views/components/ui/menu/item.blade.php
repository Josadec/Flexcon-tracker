@props([
    'href' => null,
    'navigate' => true,
    'tone' => 'neutral', // neutral | danger
])

@php
    $classes = 'flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm font-medium transition-colors '
        .($tone === 'danger'
            ? 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30'
            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60');
@endphp

{{-- Renglón de <x-ui.menu>. Navega con <a> y ejecuta con <button>, igual que x-ui.btn. --}}
@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif role="menuitem" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button role="menuitem" {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
