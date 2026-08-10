@props([
    'label' => 'Más acciones',
    'align' => 'end', // start | end
])

{{--
    Menú de acciones secundarias de una cabecera.

    Cuando una pantalla tiene cuatro botones del mismo peso (Plantilla,
    Importar, Exportar, Nuevo), ninguno destaca y la acción principal se pierde.
    Lo secundario se recoge aquí y sólo la acción principal queda como botón.

    <x-ui.menu>
        <x-ui.menu.item href="...">Exportar CSV</x-ui.menu.item>
        <x-ui.menu.item wire:click="algo">Importar CSV</x-ui.menu.item>
    </x-ui.menu>
--}}
<div x-data="{ abierto: false }" x-on:keydown.escape.window="abierto = false" class="relative">
    <button type="button" x-on:click="abierto = ! abierto"
        x-bind:aria-expanded="abierto ? 'true' : 'false'"
        aria-haspopup="menu" :title="'{{ $label }}'"
        {{ $attributes->class('ui-btn ui-btn--secondary') }}>
        <span class="sr-only">{{ $label }}</span>
        <svg class="size-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM18 10a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
    </button>

    <div x-show="abierto" x-cloak x-on:click.outside="abierto = false" x-on:click="abierto = false"
        role="menu" aria-label="{{ $label }}"
        class="absolute z-30 mt-1 min-w-56 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800
            {{ $align === 'end' ? 'end-0' : 'start-0' }}">
        {{ $slot }}
    </div>
</div>
