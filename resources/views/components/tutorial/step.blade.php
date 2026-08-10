@props([
    'number'  => null,   // número grande del paso
    'title'   => '',
    'actor'   => null,   // quién lo hace: Materiales, Producción, Calidad, Empaque
    'where'   => null,   // en qué pantalla se hace
    'summary' => null,   // UNA frase: qué logras en este paso
    'does'    => [],     // pasos concretos, en orden ("da clic en...")
    'after'   => null,   // qué pasa cuando terminas
    'warning' => null,   // el error típico de este paso
    'tone'    => 'info', // info | accent
])

@php
    $actorTone = match ($actor) {
        'Materiales' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
        'Producción' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'Calidad'    => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        'Empaque'    => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        default      => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    };
@endphp

{{--
    Un paso del flujo. El orden es siempre el mismo para que se pueda leer en
    diagonal: número → quién → qué logras → qué haces → captura → qué sigue.
--}}
<section {{ $attributes->class('rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800/40') }}>

    <header class="flex flex-wrap items-start gap-3 border-b border-slate-200 px-4 py-4 dark:border-slate-700 sm:px-5">
        @if ($number)
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl text-lg font-black
                {{ $tone === 'accent' ? 'bg-amber-500 text-white' : 'bg-slate-900 text-white dark:bg-slate-200 dark:text-slate-900' }}">
                {{ $number }}
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <div class="mb-1 flex flex-wrap items-center gap-2">
                @if ($actor)
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold {{ $actorTone }}">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Lo hace {{ $actor }}
                    </span>
                @endif
                @if ($where)
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $where }}
                    </span>
                @endif
            </div>

            <h3 class="text-lg font-bold leading-6 text-slate-900 dark:text-white">{{ $title }}</h3>

            @if ($summary)
                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $summary }}</p>
            @endif
        </div>
    </header>

    <div class="grid grid-cols-1 gap-5 px-4 py-4 sm:px-5 lg:grid-cols-2">

        {{-- Izquierda: qué hacer, en pasos numerados que coinciden con los globos --}}
        <div class="space-y-4">
            @if (!empty($does))
                <ol class="space-y-2.5">
                    @foreach ($does as $i => $paso)
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-400 text-xs font-bold text-amber-950">
                                {{ $i + 1 }}
                            </span>
                            <span class="text-sm leading-6 text-slate-700 dark:text-slate-200">{!! $paso !!}</span>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($warning)
                <div class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 dark:border-amber-800 dark:bg-amber-950/40">
                    <svg class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <p class="text-xs leading-5 text-amber-900 dark:text-amber-100"><strong>Ojo:</strong> {!! $warning !!}</p>
                </div>
            @endif

            @if ($after)
                <div class="flex items-start gap-2 rounded-lg border border-green-300 bg-green-50 px-3 py-2.5 dark:border-green-800 dark:bg-green-950/40">
                    <svg class="mt-0.5 size-4 shrink-0 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs leading-5 text-green-900 dark:text-green-100"><strong>Al terminar:</strong> {!! $after !!}</p>
                </div>
            @endif
        </div>

        {{-- Derecha: la "captura" --}}
        <div class="min-w-0">
            {{ $slot }}
        </div>
    </div>
</section>
