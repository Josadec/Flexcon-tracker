@props([
    'variant' => 'secondary', // primary | secondary | success | warning | danger | accent | ghost
    'size'    => null,        // null (40px) | sm (32px)
    'block'   => false,
    'href'    => null,        // si viene, se renderiza como <a> (navegación) en vez de <button>
    'navigate'=> true,        // wire:navigate en los enlaces internos
])

@php
    $classes = 'ui-btn ui-btn--'.$variant.($size ? ' ui-btn--'.$size : '').($block ? ' ui-btn--block' : '');
@endphp

{{--
    Botón único del admin.

    <x-ui.btn variant="primary" wire:click="guardar">Guardar</x-ui.btn>
    <x-ui.btn variant="primary" href="{{ route('admin.parts.create') }}">Nueva parte</x-ui.btn>

    El estilo vive en app.css (.ui-btn), no en clases sueltas: así todos los
    botones de la aplicación cambian desde un solo lugar. Una acción que navega
    debe ser <a> (se puede abrir en otra pestaña); una que ejecuta, <button>.
--}}
@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
