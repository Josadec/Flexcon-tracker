@props([
    'cols' => 4, // 2 | 3 | 4 | 5 | 6
])

@php
    $grid = [
        2 => 'grid-cols-2',
        3 => 'grid-cols-2 sm:grid-cols-3',
        4 => 'grid-cols-2 sm:grid-cols-4',
        5 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        6 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
    ][(int) $cols] ?? 'grid-cols-2 sm:grid-cols-4';
@endphp

{{-- Fila de métricas de sólo lectura. Siempre va antes de los campos de captura. --}}
<div {{ $attributes->class('grid gap-3 '.$grid) }}>
    {{ $slot }}
</div>
