@props(['blocks' => []])

{{--
    Dibuja los renglones de una maqueta. Vive aparte de <x-tutorial.shot>
    porque un modal puede contener sus propios renglones, y así el mismo
    código sirve dentro y fuera. El mini-lenguaje está documentado en shot.
--}}

@php
    $marker = fn ($n) => $n
        ? '<span class="ml-2 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-amber-400 text-[11px] font-bold text-amber-950 ring-2 ring-amber-200 dark:ring-amber-900">' . $n . '</span>'
        : '';

    $highlight = 'ring-2 ring-amber-400 ring-offset-1 ring-offset-white dark:ring-offset-slate-900';
@endphp

@foreach ($blocks as $block)
    @php
        $kind = $block[0] ?? 'note';
        $data = $block[1] ?? null;
        $mark = $block['mark'] ?? null;
    @endphp

    @if ($kind === 'tabs')
        <div class="flex flex-wrap items-center gap-1">
            @foreach ((array) $data as $i => $tab)
                <span class="rounded-md px-2.5 py-1 text-xs font-semibold
                    {{ ($block['active'] ?? 0) === $i
                        ? 'bg-sky-600 text-white'
                        : 'bg-white text-slate-500 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700' }}
                    {{ $mark && ($block['active'] ?? 0) === $i ? $highlight : '' }}">
                    {{ $tab }}
                </span>
            @endforeach
            {!! $marker($mark) !!}
        </div>

    @elseif ($kind === 'toolbar')
        <div class="flex flex-wrap items-center gap-2">
            <span class="flex min-w-0 flex-1 items-center gap-2 rounded-md bg-white px-2.5 py-1.5 text-xs text-slate-400 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                {{ $data }}
            </span>
            @if (!empty($block['action']))
                <span class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white {{ $mark ? $highlight : '' }}">{{ $block['action'] }}</span>
            @endif
            {!! $marker($mark) !!}
        </div>

    @elseif ($kind === 'table')
        <div class="overflow-hidden rounded-md ring-1 ring-slate-200 dark:ring-slate-700">
            <div class="flex bg-slate-100 dark:bg-slate-800">
                @foreach ((array) $data as $col)
                    <span class="flex-1 truncate px-2 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $col }}</span>
                @endforeach
            </div>
            @for ($r = 1; $r <= ($block['rows'] ?? 3); $r++)
                <div class="flex items-center border-t border-slate-100 bg-white dark:border-slate-700 dark:bg-slate-900">
                    @foreach ((array) $data as $c => $col)
                        <span class="flex flex-1 items-center px-2 py-1.5">
                            @if ($mark && $r === 1 && ($block['markCol'] ?? -1) === $c)
                                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-200 {{ $highlight }}">
                                    {{ $block['markText'] ?? 'Acción' }}
                                </span>
                                {!! $marker($mark) !!}
                            @else
                                <span class="h-1.5 w-full max-w-[70%] rounded-full bg-slate-200 dark:bg-slate-700"></span>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endfor
        </div>

    @elseif ($kind === 'cards')
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach ((array) $data as $i => $card)
                <span class="flex items-center justify-between rounded-md bg-white px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700
                    {{ $mark && $i === ($block['markIndex'] ?? 0) ? $highlight : '' }}">
                    {{ $card }}
                    @if ($mark && $i === ($block['markIndex'] ?? 0)) {!! $marker($mark) !!} @endif
                </span>
            @endforeach
        </div>

    @elseif ($kind === 'fields')
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ((array) $data as $i => $field)
                <span class="block">
                    <span class="mb-1 block text-[10px] font-semibold text-slate-500 dark:text-slate-400">{{ $field }}</span>
                    <span class="flex h-7 items-center rounded-md bg-white px-2 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700
                        {{ $mark && $i === ($block['markIndex'] ?? 0) ? $highlight : '' }}">
                        @if ($mark && $i === ($block['markIndex'] ?? 0)) {!! $marker($mark) !!} @endif
                    </span>
                </span>
            @endforeach
        </div>

    @elseif ($kind === 'button')
        <div class="flex items-center">
            <span class="rounded-md bg-sky-600 px-4 py-2 text-xs font-bold text-white {{ $mark ? $highlight : '' }}">{{ $data }}</span>
            {!! $marker($mark) !!}
        </div>

    @elseif ($kind === 'lights')
        {{-- Semáforo por área: es LA metáfora del tablero. --}}
        <div class="flex flex-wrap items-center gap-3 rounded-md bg-white px-3 py-2 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            @foreach ((array) $data as $i => $light)
                @php
                    [$etiqueta, $estado] = $light;
                    $color = match ($estado) {
                        'done' => 'bg-green-500',
                        'pending' => 'bg-amber-400',
                        'blocked' => 'bg-red-500',
                        default => 'bg-slate-300 dark:bg-slate-600',
                    };
                @endphp
                <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-600 dark:text-slate-300
                    {{ $mark && $i === ($block['markIndex'] ?? -1) ? 'rounded px-1 ' . $highlight : '' }}">
                    <span class="size-2.5 rounded-full {{ $color }} {{ $estado === 'pending' ? 'animate-pulse' : '' }}"></span>
                    {{ $etiqueta }}
                    @if ($mark && $i === ($block['markIndex'] ?? -1)) {!! $marker($mark) !!} @endif
                </span>
            @endforeach
        </div>

    @elseif ($kind === 'modal')
        <div class="rounded-lg border border-slate-300 bg-white shadow-md dark:border-slate-600 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2 dark:border-slate-700">
                <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $data }}</span>
                <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">✕</span>
            </div>
            <div class="space-y-3 p-3">
                <x-tutorial.shot-blocks :blocks="$block['blocks'] ?? []" />
            </div>
        </div>

    @else
        <p class="rounded-md bg-sky-50 px-3 py-2 text-[11px] leading-4 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200">{{ $data }}</p>
    @endif
@endforeach
