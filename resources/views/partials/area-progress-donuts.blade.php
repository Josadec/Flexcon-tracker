{{-- Area Progress Donuts - Reusable partial
     Pass $areaStats array with keys: kit, inspeccion, produccion, calidad, empaque
     Each key has: green, yellow, gray, total
--}}
@php
    $donutAreas = [
        'kit'        => ['label' => 'Kit / Material', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        'inspeccion'  => ['label' => 'Inspección', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'produccion' => ['label' => 'Producción', 'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
        'calidad'    => ['label' => 'Calidad', 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
        'empaque'    => ['label' => 'Empaque', 'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
    ];
    $svgR = 36;
    $svgCirc = 2 * M_PI * $svgR;
@endphp

<section>
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
        </svg>
        Progreso por Área
    </h2>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        @foreach ($donutAreas as $dKey => $dArea)
            @php
                $dStats = $areaStats[$dKey] ?? ['green' => 0, 'yellow' => 0, 'gray' => 0, 'total' => 0];
                $dTotal = $dStats['total'] ?: 1;
                $dPctGreen = ($dStats['green'] / $dTotal) * 100;
                $dPctYellow = ($dStats['yellow'] / $dTotal) * 100;
                $dPctGray = 100 - $dPctGreen - $dPctYellow;

                $dLenGreen = ($dPctGreen / 100) * $svgCirc;
                $dLenYellow = ($dPctYellow / 100) * $svgCirc;
                $dLenGray = ($dPctGray / 100) * $svgCirc;

                $dDisplayPct = round($dPctGreen);
            @endphp
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4 flex flex-col items-center">
                <div class="flex items-center gap-1.5 mb-3">
                    <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $dArea['icon'] }}"/>
                    </svg>
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ $dArea['label'] }}</span>
                </div>
                <div class="relative" style="width:90px;height:90px;">
                    <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                        <circle cx="50" cy="50" r="{{ $svgR }}" fill="none" stroke="#e5e7eb" class="dark:stroke-gray-700" stroke-width="10" />
                        @if ($dLenGray > 0)
                            <circle cx="50" cy="50" r="{{ $svgR }}" fill="none"
                                stroke="#9ca3af" class="dark:stroke-gray-600" stroke-width="10"
                                stroke-dasharray="{{ $dLenGray }} {{ $svgCirc - $dLenGray }}"
                                stroke-dashoffset="{{ -($dLenGreen + $dLenYellow) }}"
                                stroke-linecap="butt" />
                        @endif
                        @if ($dLenYellow > 0)
                            <circle cx="50" cy="50" r="{{ $svgR }}" fill="none"
                                stroke="#facc15" stroke-width="10"
                                stroke-dasharray="{{ $dLenYellow }} {{ $svgCirc - $dLenYellow }}"
                                stroke-dashoffset="{{ -$dLenGreen }}"
                                stroke-linecap="butt" />
                        @endif
                        @if ($dLenGreen > 0)
                            <circle cx="50" cy="50" r="{{ $svgR }}" fill="none"
                                stroke="#22c55e" stroke-width="10"
                                stroke-dasharray="{{ $dLenGreen }} {{ $svgCirc - $dLenGreen }}"
                                stroke-dashoffset="0"
                                stroke-linecap="butt" />
                        @endif
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $dDisplayPct }}%</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-2 text-[11px]">
                    <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>{{ $dStats['green'] }}
                    </span>
                    <span class="flex items-center gap-1 text-yellow-600 dark:text-yellow-400">
                        <span class="w-2 h-2 rounded-full bg-yellow-400"></span>{{ $dStats['yellow'] }}
                    </span>
                    <span class="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                        <span class="w-2 h-2 rounded-full bg-gray-400 dark:bg-gray-600"></span>{{ $dStats['gray'] }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>
</section>
