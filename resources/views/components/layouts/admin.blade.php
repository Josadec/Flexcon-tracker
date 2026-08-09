<x-layouts.admin.sidebar :title="$title ?? null">
    <flux:main>
        @if(isset($header))
            <flux:header class="mb-6">
                {{ $header }}
            </flux:header>
        @endif
        
        {{-- Único punto donde se pinta el canal `flash.banner`. Va antes del
             contenido para que el aviso se lea antes que la pantalla. --}}
        <x-flash-banner />

        {{ $slot }}
    </flux:main>
</x-layouts.admin.sidebar>
