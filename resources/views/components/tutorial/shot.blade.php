@props([
    'screen'  => null,   // nombre de la pantalla, a la derecha de la barra
    'path'    => null,   // ruta que se ve en la barra de dirección
    'blocks'  => [],     // renglones de la maqueta (ver x-tutorial.shot-blocks)
    'caption' => null,   // pie: qué está mirando la persona
])

{{--
    "Captura" esquemática de una pantalla del sistema.

    No es una imagen: se dibuja con HTML, así que se ve nítida en cualquier
    pantalla, respeta el modo oscuro y no se desactualiza cuando cambian los
    colores del sistema. La idea es que alguien que no lee todo el texto igual
    reconozca DÓNDE dar clic, por la forma y por el número del globo ámbar.

    <x-tutorial.shot screen="Tablero" path="/admin/sent-lists/display"
        :blocks="[['button', 'Empacar / Confirmar', 'mark' => 1]]"
        caption="Así se ve el botón en el renglón del viajero." />
--}}

<figure {{ $attributes }}>
    <div class="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-900">

        {{-- Barra superior: ubica a la persona ("esto es una pantalla del sistema") --}}
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-100 px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
            <span class="flex gap-1" aria-hidden="true">
                <span class="size-2.5 rounded-full bg-red-400"></span>
                <span class="size-2.5 rounded-full bg-amber-400"></span>
                <span class="size-2.5 rounded-full bg-green-400"></span>
            </span>
            <span class="ml-1 min-w-0 flex-1 truncate rounded-md bg-white px-2 py-1 font-mono text-[11px] text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                {{ $path ?? 'flexcon-tracker.la' }}
            </span>
            @if ($screen)
                <span class="hidden shrink-0 text-[11px] font-semibold text-slate-500 sm:block dark:text-slate-400">{{ $screen }}</span>
            @endif
        </div>

        <div class="space-y-3 bg-slate-50 p-3 dark:bg-slate-900/60">
            <x-tutorial.shot-blocks :blocks="$blocks" />
        </div>
    </div>

    @if ($caption)
        <figcaption class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $caption }}</figcaption>
    @endif
</figure>
