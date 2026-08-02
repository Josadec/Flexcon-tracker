@props([
    'tone' => 'neutral', // neutral | good | warn | bad | info | accent
    'dot'  => false,     // anteponer un punto de color (estados de proceso)
])

@php
    $tones = [
        'neutral' => ['chip' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',   'dot' => 'bg-slate-400'],
        'good'    => ['chip' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300', 'dot' => 'bg-green-500'],
        'warn'    => ['chip' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300', 'dot' => 'bg-amber-500'],
        'bad'     => ['chip' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',         'dot' => 'bg-red-500'],
        'info'    => ['chip' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',         'dot' => 'bg-sky-500'],
        'accent'  => ['chip' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',     'dot' => 'bg-cyan-500'],
    ];
    $t = $tones[$tone] ?? $tones['neutral'];
@endphp

{{-- Píldora de estado. Un solo tamaño en todo el admin. --}}
<span {{ $attributes->class('inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold '.$t['chip']) }}>
    @if ($dot)
        <span class="size-1.5 shrink-0 rounded-full {{ $t['dot'] }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
