@props([
    'label' => '',
    'hint'  => null,  // qué implica activarlo
])

{{--
    Casilla con etiqueta. Toda la fila es clicable (área táctil grande) y la
    ayuda va debajo, no dentro de la etiqueta, para que se lea igual de rápido.

    <x-ui.check label="Parte activa" hint="Las inactivas no aparecen al crear órdenes.">
        <input type="checkbox" wire:model="active">
    </x-ui.check>
--}}
<label {{ $attributes->class('flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700/50') }}>
    <span class="mt-0.5 flex shrink-0">{{ $slot }}</span>
    <span class="min-w-0">
        <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</span>
        @if ($hint)
            <span class="mt-0.5 block text-xs leading-4 text-slate-500 dark:text-slate-400">{{ $hint }}</span>
        @endif
    </span>
</label>
