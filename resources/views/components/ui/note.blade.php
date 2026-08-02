@props([
    'tone'  => 'info', // info | success | warn | danger | muted
    'title' => null,
])

@php
    $tones = [
        'info'    => ['box' => 'border-sky-300 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/40',       'ico' => 'text-sky-600 dark:text-sky-400',       'txt' => 'text-sky-900 dark:text-sky-100',       'path' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'success' => ['box' => 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/40','ico' => 'text-green-600 dark:text-green-400',   'txt' => 'text-green-900 dark:text-green-100',   'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'warn'    => ['box' => 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40','ico' => 'text-amber-600 dark:text-amber-400',   'txt' => 'text-amber-900 dark:text-amber-100',   'path' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
        'danger'  => ['box' => 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/40',        'ico' => 'text-red-600 dark:text-red-400',       'txt' => 'text-red-900 dark:text-red-100',       'path' => 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'muted'   => ['box' => 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40','ico' => 'text-slate-400',                       'txt' => 'text-slate-600 dark:text-slate-300',   'path' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    $t = $tones[$tone] ?? $tones['info'];
@endphp

{{--
    Nota / guía. Es el componente con el que se explica al operador qué va a
    pasar, qué falta o por qué algo está bloqueado.
--}}
<div {{ $attributes->class('flex items-start gap-3 rounded-lg border px-4 py-3 '.$t['box']) }}>
    <svg class="mt-0.5 size-5 shrink-0 {{ $t['ico'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $t['path'] }}"/>
    </svg>
    <div class="min-w-0 text-sm leading-5 {{ $t['txt'] }}">
        @if ($title)
            <p class="font-bold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
</div>
