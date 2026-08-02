@props([
    'sort'      => null,  // nombre del campo si la columna es ordenable
    'field'     => null,  // $sortField actual
    'direction' => 'asc', // $sortDirection actual
    'align'     => 'left', // left | right | center
])

@php
    $alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align] ?? 'text-left';
    $justify    = ['left' => 'justify-start', 'right' => 'justify-end', 'center' => 'justify-center'][$align] ?? 'justify-start';
    $isActive   = $sort && $field === $sort;
@endphp

{{-- Celda de encabezado. Si recibe `sort`, se vuelve botón de ordenamiento. --}}
<th {{ $attributes->class('px-4 py-2.5 '.$alignClass.' text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300') }}
    @if ($isActive) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
    @if ($sort)
        <button type="button" wire:click="sortBy('{{ $sort }}')"
            class="inline-flex items-center gap-1.5 {{ $justify }} hover:text-slate-900 dark:hover:text-white"
            title="Ordenar por {{ strip_tags($slot) }}">
            <span>{{ $slot }}</span>
            <svg class="size-3.5 shrink-0 transition-transform {{ $isActive ? ($direction === 'asc' ? '' : 'rotate-180') : 'opacity-25' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
            </svg>
        </button>
    @else
        {{ $slot }}
    @endif
</th>
