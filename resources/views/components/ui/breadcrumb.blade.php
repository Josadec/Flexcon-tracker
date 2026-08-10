@php
    // Se deducen de la ruta actual, no se declaran pantalla por pantalla.
    // El layout sólo se renderiza en cargas de página completas, así que aquí
    // `request()->route()` siempre es la ruta de la pantalla.
    $migas = \App\Support\AdminNavigation::breadcrumb(auth()->user());
@endphp

@if (count($migas) > 1)
    <nav {{ $attributes->class('min-w-0') }} aria-label="Ruta de navegación">
        <ol class="flex items-center gap-1.5 text-sm">
            @foreach ($migas as $i => $miga)
                @php $ultima = $i === count($migas) - 1; @endphp

                @if ($i > 0)
                    <li aria-hidden="true" class="shrink-0 text-zinc-300 dark:text-zinc-600">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </li>
                @endif

                <li class="{{ $ultima ? 'min-w-0' : 'shrink-0' }}">
                    @if ($miga['url'] && ! $ultima)
                        <a href="{{ $miga['url'] }}" wire:navigate
                            class="rounded text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                            {{ $miga['label'] }}
                        </a>
                    @else
                        <span @if ($ultima) aria-current="page" @endif
                            class="block truncate {{ $ultima ? 'font-semibold text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400' }}">
                            {{ $miga['label'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
