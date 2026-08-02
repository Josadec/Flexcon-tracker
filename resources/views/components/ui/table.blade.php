@props([
    'title' => null,
    'hint'  => null,
])

{{--
    Tarjeta que envuelve una tabla: cabecera opcional, scroll horizontal propio
    (la página nunca debe hacer scroll lateral) y pie para la paginación.

    <x-ui.table title="Partes" hint="...">
        <x-slot:head>
            <tr><x-ui.th>Nº parte</x-ui.th> ...</tr>
        </x-slot:head>
        <tr>...</tr>
        <x-slot:foot>{{ $parts->links() }}</x-slot:foot>
    </x-ui.table>
--}}
<div {{ $attributes->class('overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800') }}>
    @if ($title)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700 sm:px-5">
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
                @if ($hint)
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
                @endif
            </div>
            @isset($aside)
                <div class="shrink-0">{{ $aside }}</div>
            @endisset
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            @isset($head)
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                    {{ $head }}
                </thead>
            @endisset
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($foot)
        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50 sm:px-5">
            {{ $foot }}
        </div>
    @endisset
</div>
