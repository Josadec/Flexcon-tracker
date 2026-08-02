@props([
    'accent' => 'blue',
    'title' => '',
    'subtitle' => '',
])

@php
    // Clases completas por acento (Tailwind necesita ver los literales — no construir dinámicamente).
    $map = [
        'blue'    => ['bar' => 'bg-blue-500',    'tileBg' => 'bg-blue-50 dark:bg-blue-900/30',    'tileText' => 'text-blue-600 dark:text-blue-300',    'ring' => 'ring-blue-500/20',    'glow' => 'from-blue-500/10'],
        'amber'   => ['bar' => 'bg-amber-500',   'tileBg' => 'bg-amber-50 dark:bg-amber-900/30',   'tileText' => 'text-amber-600 dark:text-amber-300',   'ring' => 'ring-amber-500/20',   'glow' => 'from-amber-500/10'],
        'emerald' => ['bar' => 'bg-emerald-500', 'tileBg' => 'bg-emerald-50 dark:bg-emerald-900/30', 'tileText' => 'text-emerald-600 dark:text-emerald-300', 'ring' => 'ring-emerald-500/20', 'glow' => 'from-emerald-500/10'],
        'teal'    => ['bar' => 'bg-teal-500',    'tileBg' => 'bg-teal-50 dark:bg-teal-900/30',    'tileText' => 'text-teal-600 dark:text-teal-300',    'ring' => 'ring-teal-500/20',    'glow' => 'from-teal-500/10'],
    ];
    $c = $map[$accent] ?? $map['blue'];
@endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm']) }}>
    {{-- Barra de acento --}}
    <div class="absolute inset-y-0 left-0 w-1.5 {{ $c['bar'] }}"></div>
    {{-- Resplandor sutil del acento --}}
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-r {{ $c['glow'] }} to-transparent"></div>

    <div class="relative flex flex-wrap items-center gap-4 px-6 py-5 pl-7">
        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl {{ $c['tileBg'] }} {{ $c['tileText'] }} ring-1 {{ $c['ring'] }} flex-shrink-0">
            {{ $icon }}
        </span>

        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex-shrink-0">{{ $actions }}</div>
        @endisset
    </div>
</div>
