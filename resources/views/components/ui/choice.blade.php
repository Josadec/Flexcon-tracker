@props([
    'tone'     => 'neutral', // neutral | good | bad | warn | info | accent
    'eyebrow'  => null,      // "Opción 1"
    'title'    => '',
    'desc'     => null,      // qué pasa si eliges esto
    'meta'     => null,      // dato duro a la derecha ("1,000 pz")
    'selected' => null,      // true/false cuando el estado vive en Livewire
])

{{--
    Tarjeta seleccionable grande. Se usa para elegir estado (aprobado /
    rechazado) y para elegir decisión (cerrar / completar / nuevo lote).

    La selección se comunica con aria-pressed, así el mismo componente sirve
    tanto con Livewire (`:selected="..."`) como con Alpine (`:aria-pressed="..."`),
    y de paso queda anunciada para lectores de pantalla.
--}}
<button type="button" data-tone="{{ $tone }}"
    @if (!is_null($selected)) aria-pressed="{{ $selected ? 'true' : 'false' }}" @endif
    {{ $attributes->class('ui-choice') }}>

    <span class="min-w-0 flex-1">
        @if ($eyebrow)
            <span class="ui-choice__eyebrow">{{ $eyebrow }}</span>
        @endif
        <span class="ui-choice__title">{{ $title }}</span>
        @if ($desc)
            <span class="ui-choice__desc">{{ $desc }}</span>
        @endif
        @isset($extra)
            <span class="mt-2 block">{{ $extra }}</span>
        @endisset
    </span>

    @if ($meta)
        <span class="shrink-0 text-sm font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ $meta }}</span>
    @endif

    <span class="ui-choice__check" aria-hidden="true">
        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
    </span>
</button>
