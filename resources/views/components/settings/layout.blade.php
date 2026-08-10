@props([
    'heading' => '',
    'subheading' => null,
    // Pestaña activa: profile | password | appearance. Se pasa a mano porque
    // `request()->routeIs()` deja de valer dentro de una acción de Livewire —
    // esa petición va a /livewire/update, no a la ruta de la pantalla, así que
    // al guardar se apagaban todas las pestañas.
    'active' => null,
])

@php
    // Las tres pantallas de la cuenta comparten cabecera y pestañas: así se
    // navega entre ellas sin volver al menú y siempre se ve dónde estás.
    $tabs = [
        [
            'key' => 'profile',
            'route' => 'admin.settings.profile',
            'label' => 'Perfil',
            'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        ],
        [
            'key' => 'password',
            'route' => 'admin.settings.password',
            'label' => 'Contraseña',
            'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        ],
        [
            'key' => 'appearance',
            'route' => 'admin.settings.appearance',
            'label' => 'Apariencia',
            'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828L14.828 15',
        ],
    ];
@endphp

<x-ui.page eyebrow="Mi cuenta" :title="$heading" :subtitle="$subheading">
    <nav class="flex flex-wrap gap-2" aria-label="Ajustes de la cuenta">
        @foreach ($tabs as $tab)
            @php $activa = $active ? $active === $tab['key'] : request()->routeIs($tab['route']); @endphp
            <a href="{{ route($tab['route']) }}" wire:navigate
                @if ($activa) aria-current="page" @endif
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors',
                    'bg-sky-600 text-white' => $activa,
                    'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700' => ! $activa,
                ])>
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/>
                </svg>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    {{ $slot }}
</x-ui.page>
