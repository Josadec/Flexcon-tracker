@php
    // Canal heredado de Jetstream. 79 llamadas repartidas en 59 ficheros de
    // app/Livewire/Admin escriben en él con session()->flash('flash.banner', ...)
    // + 'flash.bannerStyle', y hasta ahora NINGUNA vista lo pintaba: los mensajes
    // se perdían. Se resuelve en un solo punto (el layout admin) en lugar de
    // tocar los 59 ficheros.
    //
    // LÍMITE CONOCIDO — sólo cubre las ~40 llamadas que redirigen. Livewire borra
    // a propósito el valor de todo flash de una petición en la que ningún
    // componente redirigió (SupportRedirects::on('response') hace
    // session()->forget(session()->get('_flash.new'))), así que una acción que se
    // queda en la pantalla por AJAX nunca llegará hasta aquí: la clave sobrevive
    // en _flash.old, el valor no. Ésas necesitan x-ui.note dentro de la propia
    // vista, como hacen las pantallas de Órdenes de trabajo.
    $bannerMessage = session('flash.banner');
    $bannerStyle   = session('flash.bannerStyle', 'success');

    $bannerTones = [
        'success' => ['box' => 'border-green-300 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950/40 dark:text-green-100', 'ico' => 'text-green-600 dark:text-green-400', 'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'warning' => ['box' => 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100', 'ico' => 'text-amber-600 dark:text-amber-400', 'path' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
        'danger'  => ['box' => 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-100',           'ico' => 'text-red-600 dark:text-red-400',     'path' => 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    $bannerTone = $bannerTones[$bannerStyle] ?? $bannerTones['success'];
@endphp

@if ($bannerMessage)
    {{-- role="alert" sólo en lo que exige atención inmediata; un guardado
         correcto se anuncia con role="status" para no interrumpir al lector. --}}
    <div x-data="{ shown: true }" x-show="shown" x-cloak
        role="{{ $bannerStyle === 'success' ? 'status' : 'alert' }}"
        aria-live="{{ $bannerStyle === 'success' ? 'polite' : 'assertive' }}"
        class="mb-5 flex items-start gap-3 rounded-lg border px-4 py-3 {{ $bannerTone['box'] }}">
        <svg class="mt-0.5 size-5 shrink-0 {{ $bannerTone['ico'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $bannerTone['path'] }}"/>
        </svg>

        <p class="min-w-0 flex-1 text-sm font-medium leading-5">{{ $bannerMessage }}</p>

        <button type="button" @click="shown = false" @keydown.escape.window="shown = false"
            class="-mr-1 -mt-1 shrink-0 rounded p-1 opacity-70 transition hover:bg-black/5 hover:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current dark:hover:bg-white/10"
            aria-label="Cerrar el aviso">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
@endif
