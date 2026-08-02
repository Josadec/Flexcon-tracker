@props([
    'badge'    => null,    // p.ej. "Paso 6" (píldora ámbar)
    'title'    => null,
    'subtitle' => null,
    'close'    => null,    // nombre del método Livewire para cerrar (string)
    'maxWidth' => '4xl',   // md | lg | xl | 2xl | 3xl | 4xl | 5xl | 6xl | 7xl
    'bodyClass'=> 'space-y-5 px-5 py-5 sm:px-6 sm:py-6',
])
@php
    $mw = [
        'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl', '3xl' => 'max-w-3xl', '4xl' => 'max-w-4xl', '5xl' => 'max-w-5xl',
        '6xl' => 'max-w-6xl', '7xl' => 'max-w-7xl',
    ][$maxWidth] ?? 'max-w-4xl';
@endphp

{{--
    ANATOMÍA ÚNICA DE MODAL — todos los modales de la Lista de envío se arman
    con estas cinco zonas, siempre en el mismo orden:

      1. Encabezado  — píldora de paso + título + subtítulo + cerrar
      2. Contexto    — tira de 4 datos que identifican el registro (WO, parte…)
      3. Cuerpo      — una o varias <x-ui.section>, nunca contenido suelto
      4. Nota de pie — QUÉ va a pasar al guardar (siempre visible, es la guía)
      5. Acciones    — a la derecha en escritorio, apiladas en móvil

    <x-ui-modal badge="Paso 6" title="Resumen + toma de decisión"
                subtitle="..." close="closeDecisionModal" maxWidth="2xl">
        <x-slot:context>
            <x-ui-modal.ctx label="Descripción" :value="$desc" />
        </x-slot:context>

        <x-ui.section title="..."> ... </x-ui.section>

        <x-slot:note>El resultado actualizará el semáforo de Calidad.</x-slot:note>
        <x-slot:footer>
            <x-ui.btn variant="secondary" wire:click="cerrar">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" wire:click="guardar">Guardar</x-ui.btn>
        </x-slot:footer>
    </x-ui-modal>
--}}
<div {{ $attributes->class('shipping-flow-modal fixed inset-0 z-50 overflow-y-auto') }} role="dialog" aria-modal="true"
    aria-label="{{ $title ?? 'Ventana de diálogo' }}"
    @if($close) wire:keydown.escape.window="{{ $close }}" @endif>
    <div class="flex min-h-full items-start justify-center p-3 sm:items-center sm:p-6">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-[2px]" aria-hidden="true"
            @if($close) wire:click="{{ $close }}" @endif></div>

        <div class="relative z-10 my-3 flex max-h-[calc(100vh-1.5rem)] w-full {{ $mw }} flex-col overflow-hidden rounded-xl bg-white shadow-[0_28px_80px_-22px_rgba(15,23,42,0.58)] dark:bg-slate-800 sm:my-6 sm:max-h-[calc(100vh-3rem)]">

            {{-- 1. Encabezado --}}
            @if($title || $badge || isset($header))
                <div class="flex shrink-0 items-start justify-between gap-6 border-b border-slate-200 px-5 py-4 dark:border-slate-700 sm:px-6 sm:py-5">
                    <div class="min-w-0">
                        @if($badge)
                            <span class="inline-block rounded bg-amber-300 px-2.5 py-0.5 text-xs font-bold text-amber-900">{{ $badge }}</span>
                        @endif
                        @if($title)
                            <h3 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white sm:text-xl {{ $badge ? 'mt-1.5' : '' }}">{{ $title }}</h3>
                        @endif
                        @if($subtitle)
                            <p class="mt-1 max-w-3xl text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                        @endif
                        @isset($header){{ $header }}@endisset
                    </div>
                    @if($close)
                        <button wire:click="{{ $close }}" type="button" aria-label="Cerrar ventana"
                            class="ui-icon-btn shrink-0 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-white">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
            @endif

            {{-- 2. Tira de contexto --}}
            @isset($context)
                <div class="grid shrink-0 grid-cols-1 gap-px border-b border-slate-200 bg-slate-200 sm:grid-cols-2 md:grid-cols-4 dark:border-slate-700 dark:bg-slate-700">
                    {{ $context }}
                </div>
            @endisset

            {{-- 3. Cuerpo --}}
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-50 dark:bg-slate-900/30 {{ $bodyClass }}">
                {{ $slot }}
            </div>

            {{-- 4 + 5. Pie: nota a la izquierda, acciones a la derecha --}}
            @if(isset($footer) || isset($note))
                <div class="ui-modal-footer shrink-0 border-t border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-800 sm:px-6">
                    <p class="ui-modal-footer__note">
                        @isset($note){{ $note }}@endisset
                    </p>
                    @isset($footer)
                        <div class="ui-modal-footer__actions">{{ $footer }}</div>
                    @endisset
                </div>
            @endif
        </div>
    </div>
</div>
