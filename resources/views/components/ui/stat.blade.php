@props([
    'label' => '',
    'value' => null,
    'unit'  => null,
    'tone'  => 'neutral', // neutral | good | warn | bad | info | accent
    'help'  => null,      // explicación al pasar el cursor; también se lee como title
])

@php
    $tones = [
        'neutral' => ['box' => 'bg-slate-50 dark:bg-slate-900/40',   'num' => 'text-slate-900 dark:text-white'],
        'good'    => ['box' => 'bg-green-50 dark:bg-green-900/20',   'num' => 'text-green-700 dark:text-green-300'],
        'warn'    => ['box' => 'bg-orange-50 dark:bg-orange-900/20', 'num' => 'text-orange-700 dark:text-orange-300'],
        'bad'     => ['box' => 'bg-red-50 dark:bg-red-900/20',       'num' => 'text-red-700 dark:text-red-300'],
        'info'    => ['box' => 'bg-sky-50 dark:bg-sky-900/20',       'num' => 'text-sky-800 dark:text-sky-200'],
        'accent'  => ['box' => 'bg-cyan-50 dark:bg-cyan-900/20',     'num' => 'text-cyan-700 dark:text-cyan-300'],
    ];
    $t = $tones[$tone] ?? $tones['neutral'];
@endphp

{{-- Una métrica. Etiqueta arriba en minúscula ancha, número grande abajo. --}}
<div {{ $attributes->class('rounded-lg px-3 py-3 text-center '.$t['box']) }} @if($help) title="{{ $help }}" @endif>
    <div class="flex items-center justify-center gap-1 text-[11px] font-semibold uppercase leading-tight tracking-wide text-slate-500 dark:text-slate-400">
        <span>{{ $label }}</span>
        @if ($help)
            <svg class="size-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        @endif
    </div>
    <div class="mt-1 text-xl font-bold tabular-nums {{ $t['num'] }}">
        {{ $value ?? $slot }}@if ($unit)<span class="ml-1 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $unit }}</span>@endif
    </div>
</div>
