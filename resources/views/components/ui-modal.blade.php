@props([
    'badge'    => null,    // p.ej. "Paso 6" (píldora ámbar)
    'title'    => null,
    'subtitle' => null,
    'close'    => null,    // nombre del método Livewire para cerrar (string)
    'maxWidth' => '2xl',   // md | lg | xl | 2xl | 3xl | 4xl
    'bodyClass'=> 'px-6 py-5 space-y-6 max-h-[65vh] overflow-y-auto',
])
@php
    $mw = [
        'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl', '3xl' => 'max-w-3xl', '4xl' => 'max-w-4xl', '5xl' => 'max-w-5xl',
    ][$maxWidth] ?? 'max-w-2xl';
@endphp

{{--
    Modal con el diseño "Paso 5": panel blanco redondeado, header con píldora +
    título + subtítulo + cerrar, tira de contexto opcional, cuerpo desplazable y
    footer opcional.

    Uso:
    <x-ui-modal badge="Paso 6" title="Resumen + toma de decisión"
                subtitle="..." close="closeDecisionModal" maxWidth="2xl">
        <x-slot:context>
            <x-ui-modal.ctx label="Descripción" :value="$desc" />
            ...
        </x-slot:context>

        ... cuerpo ...

        <x-slot:footer>
            <span class="text-xs text-gray-500 dark:text-gray-400">...</span>
            <div class="flex items-center gap-2"> ...botones... </div>
        </x-slot:footer>
    </x-ui-modal>
--}}
<div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-start sm:items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-900/70" @if($close) wire:click="{{ $close }}" @endif></div>

        <div class="relative z-10 w-full {{ $mw }} bg-white dark:bg-gray-800 rounded-2xl shadow-2xl my-8">

            {{-- Header --}}
            @if($title || $badge || isset($header))
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        @if($badge)
                            <span class="inline-block px-2.5 py-0.5 text-xs font-bold bg-amber-300 text-amber-900 rounded-full">{{ $badge }}</span>
                        @endif
                        @if($title)
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white {{ $badge ? 'mt-1.5' : '' }}">{{ $title }}</h3>
                        @endif
                        @if($subtitle)
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
                        @endif
                        @isset($header){{ $header }}@endisset
                    </div>
                    @if($close)
                        <button wire:click="{{ $close }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
            @endif

            {{-- Tira de contexto (opcional) --}}
            @isset($context)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700">
                    {{ $context }}
                </div>
            @endisset

            {{-- Cuerpo --}}
            <div class="{{ $bodyClass }}">
                {{ $slot }}
            </div>

            {{-- Footer (opcional) --}}
            @isset($footer)
                <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 rounded-b-2xl">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
