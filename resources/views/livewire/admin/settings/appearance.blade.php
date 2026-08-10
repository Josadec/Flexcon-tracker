<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<x-settings.layout active="appearance" heading="Apariencia"
    subheading="Elige cómo se ve el sistema en este dispositivo. El ajuste se guarda en el navegador, así que puedes tener el taller en oscuro y la oficina en claro.">

    <x-ui.section title="Tema de la pantalla"
        hint="«Automático» sigue lo que tenga configurado el sistema operativo del equipo.">

        {{-- La selección se comunica con aria-pressed, así el mismo componente
             sirve con Livewire y con Alpine. El tema vive en $flux.appearance. --}}
        <div x-data class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-ui.choice tone="neutral" title="Claro"
                desc="Fondo blanco. Se lee mejor con mucha luz ambiental."
                x-bind:aria-pressed="$flux.appearance === 'light'"
                x-on:click="$flux.appearance = 'light'" />

            <x-ui.choice tone="info" title="Oscuro"
                desc="Fondo oscuro. Cansa menos la vista en turnos de noche."
                x-bind:aria-pressed="$flux.appearance === 'dark'"
                x-on:click="$flux.appearance = 'dark'" />

            <x-ui.choice tone="accent" title="Automático"
                desc="Sigue el tema del equipo: claro de día, oscuro de noche."
                x-bind:aria-pressed="$flux.appearance === 'system'"
                x-on:click="$flux.appearance = 'system'" />
        </div>

        <x-ui.note tone="muted" class="mt-4">
            El cambio se aplica al instante y sólo afecta a este navegador. Si entras desde otra
            computadora o desde el celular, ahí se elige por separado.
        </x-ui.note>
    </x-ui.section>
</x-settings.layout>
