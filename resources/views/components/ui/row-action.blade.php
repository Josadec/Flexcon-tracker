@props([
    'state'     => 'idle',  // idle | pending | active | done | error
    'label'     => '—',     // verbo si hay acción ("Liberar"), estado si no ("Liberado")
    'hint'      => null,    // frase completa para el tooltip
    'clickable' => null,    // sólo para la leyenda: forzar la apariencia sin acción real
])

@php
    // Es accionable si quien lo usa le pasó un wire:click (o lo forzó para la leyenda).
    $clickable ??= $attributes->whereStartsWith('wire:click')->isNotEmpty();

    // Una acción pendiente y accionable es lo único que se resalta: es lo que
    // el operador tiene que atender ahora.
    $modifier = match (true) {
        $clickable && $state === 'pending' => 'ui-row-action--pending',
        $clickable                         => 'ui-row-action--clickable',
        default                            => 'ui-row-action--static',
    };

    $stateText = [
        'idle'    => 'no disponible',
        'pending' => 'pendiente',
        'active'  => 'en proceso',
        'done'    => 'completado',
        'error'   => 'rechazado',
    ][$state] ?? $state;

    $tooltip = $hint ?: $label.' — '.$stateText;
@endphp

{{--
    Una sola celda del semáforo = un solo control.

    El punto de color dice CÓMO va; el texto dice QUÉ hacer. Si no hay acción
    disponible se pinta como texto plano (sin borde ni cursor), de modo que
    "se ve como botón" ⇔ "se puede presionar".

    <x-ui.row-action state="pending" label="Liberar" hint="Materiales debe liberar el material"
                     wire:click="openMaterialModal({{ $lot->id }})" />
--}}
<{{ $clickable ? 'button' : 'span' }}
    @if ($clickable) type="button" @endif
    {{ $attributes->merge(['class' => 'ui-row-action '.$modifier, 'title' => $tooltip]) }}>
    <span class="ui-dot ui-dot--{{ $state }}" aria-hidden="true"></span>
    <span class="truncate">{{ $label }}</span>
    <span class="sr-only"> — {{ $stateText }}</span>
</{{ $clickable ? 'button' : 'span' }}>
