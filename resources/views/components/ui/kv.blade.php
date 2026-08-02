@props([
    'label' => '',
    'value' => null,
    'tone'  => 'neutral', // neutral | good | warn | bad | info
    'help'  => null,
])

@php
    $tones = [
        'neutral' => 'text-slate-900 dark:text-white',
        'good'    => 'text-green-700 dark:text-green-300',
        'warn'    => 'text-orange-700 dark:text-orange-300',
        'bad'     => 'text-red-700 dark:text-red-300',
        'info'    => 'text-sky-800 dark:text-sky-200',
    ];
@endphp

{{-- Renglón etiqueta → valor. Para resúmenes de lectura dentro de un modal. --}}
<div {{ $attributes->class('flex items-baseline justify-between gap-4 px-4 py-2.5') }}>
    <dt class="text-sm text-slate-600 dark:text-slate-300">
        {{ $label }}
        @if ($help)
            <span class="block text-[11px] leading-4 text-slate-400 dark:text-slate-500">{{ $help }}</span>
        @endif
    </dt>
    <dd class="shrink-0 text-right text-sm font-bold tabular-nums {{ $tones[$tone] ?? $tones['neutral'] }}">{{ $value ?? $slot }}</dd>
</div>
