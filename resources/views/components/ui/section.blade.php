@props([
    'step'  => null,  // número de paso ("1", "2"...) cuando el modal es una secuencia
    'title' => null,
    'hint'  => null,  // una línea que explica QUÉ se hace aquí y POR QUÉ
    'tone'  => 'neutral', // neutral | accent — accent marca el paso donde se decide
])

{{--
    Bloque temático dentro de un modal. Todo el contenido de los modales va
    dentro de una sección: es lo que hace que todos se lean igual.

    <x-ui.section step="1" title="Revisa las cantidades"
                  hint="Compara lo empacado contra el total antes de decidir.">
        ...
    </x-ui.section>
--}}
<section {{ $attributes->class('rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800/40') }}>
    @if ($title)
        <header class="flex items-start gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700 sm:px-5">
            @if ($step)
                <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold
                    {{ $tone === 'accent' ? 'bg-amber-500 text-white' : 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' }}">
                    {{ $step }}
                </span>
            @endif
            <div class="min-w-0">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white sm:text-base">{{ $title }}</h4>
                @if ($hint)
                    <p class="mt-0.5 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $hint }}</p>
                @endif
            </div>
            @isset($aside)
                <div class="ml-auto shrink-0">{{ $aside }}</div>
            @endisset
        </header>
    @endif

    <div class="px-4 py-4 sm:px-5 {{ $title ? '' : 'pt-4' }}">
        {{ $slot }}
    </div>
</section>
