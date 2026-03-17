<div
    x-data="{
        scrollContainer: null,
        scrollSpeed: 0.5,
        paused: false,
        init() {
            this.scrollContainer = this.$refs.tableBody;
            this.autoScroll();
        },
        autoScroll() {
            const step = () => {
                if (!this.paused && this.scrollContainer) {
                    this.scrollContainer.scrollTop += this.scrollSpeed;
                    if (this.scrollContainer.scrollTop >= this.scrollContainer.scrollHeight - this.scrollContainer.clientHeight) {
                        this.scrollContainer.scrollTop = 0;
                    }
                }
                requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        }
    }"
    wire:poll.{{ $refreshInterval }}s="refreshDisplay"
    class="min-h-screen bg-gray-950 text-white p-4 flex flex-col"
    @mouseenter="paused = true"
    @mouseleave="paused = false"
>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 flex-shrink-0">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold text-white tracking-wide">FLEXCON — Monitor de Producción</h1>
            <span class="text-xs text-gray-500 bg-gray-800 px-2 py-1 rounded">Auto-refresh {{ $refreshInterval }}s</span>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-500"></span> Completo</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-yellow-400"></span> En Proceso</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-gray-600"></span> Pendiente</span>
            <span class="text-gray-500 ml-4" id="tv-clock"></span>
        </div>
    </div>

    {{-- Donut Charts (pure SVG — no JS needed) --}}
    <div class="grid grid-cols-5 gap-4 mb-4 flex-shrink-0">
        @php
            $areas = [
                'kit' => ['label' => 'Kit / Material'],
                'inspeccion' => ['label' => 'Inspección'],
                'produccion' => ['label' => 'Producción'],
                'calidad' => ['label' => 'Calidad'],
                'empaque' => ['label' => 'Empaque'],
            ];
            // SVG donut config: radius=40, circumference=2*PI*40=251.327
            $svgR = 40;
            $svgCirc = 2 * M_PI * $svgR; // ~251.327
        @endphp
        @foreach ($areas as $key => $area)
            @php
                $stats = $areaStats[$key];
                $total = $stats['total'] ?: 1;
                $pctGreen = ($stats['green'] / $total) * 100;
                $pctYellow = ($stats['yellow'] / $total) * 100;
                $pctGray = 100 - $pctGreen - $pctYellow;

                // SVG stroke-dasharray segments
                $lenGreen = ($pctGreen / 100) * $svgCirc;
                $lenYellow = ($pctYellow / 100) * $svgCirc;
                $lenGray = ($pctGray / 100) * $svgCirc;

                // SVG stroke-dashoffset (cumulative rotation)
                $offsetGreen = 0;
                $offsetYellow = $svgCirc - $lenGreen;
                $offsetGray = $svgCirc - $lenGreen - $lenYellow;

                $displayPct = round($pctGreen);
            @endphp
            <div class="bg-gray-900 rounded-xl p-4 flex flex-col items-center border border-gray-800">
                <h3 class="text-sm font-semibold text-gray-300 mb-2">{{ $area['label'] }}</h3>
                <div class="relative" style="width:110px;height:110px;">
                    <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                        {{-- Background ring --}}
                        <circle cx="50" cy="50" r="{{ $svgR }}" fill="none" stroke="#374151" stroke-width="12" />
                        {{-- Gray segment --}}
                        @if ($lenGray > 0)
                            <circle cx="50" cy="50" r="{{ $svgR }}" fill="none"
                                stroke="#4b5563" stroke-width="12"
                                stroke-dasharray="{{ $lenGray }} {{ $svgCirc - $lenGray }}"
                                stroke-dashoffset="{{ -($lenGreen + $lenYellow) }}"
                                stroke-linecap="butt" />
                        @endif
                        {{-- Yellow segment --}}
                        @if ($lenYellow > 0)
                            <circle cx="50" cy="50" r="{{ $svgR }}" fill="none"
                                stroke="#facc15" stroke-width="12"
                                stroke-dasharray="{{ $lenYellow }} {{ $svgCirc - $lenYellow }}"
                                stroke-dashoffset="{{ -$lenGreen }}"
                                stroke-linecap="butt" />
                        @endif
                        {{-- Green segment (drawn last = on top) --}}
                        @if ($lenGreen > 0)
                            <circle cx="50" cy="50" r="{{ $svgR }}" fill="none"
                                stroke="#22c55e" stroke-width="12"
                                stroke-dasharray="{{ $lenGreen }} {{ $svgCirc - $lenGreen }}"
                                stroke-dashoffset="0"
                                stroke-linecap="butt" />
                        @endif
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-2xl font-bold text-white">{{ $displayPct }}%</span>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-2 text-[11px] text-gray-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span>{{ $stats['green'] }}</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-400"></span>{{ $stats['yellow'] }}</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-600"></span>{{ $stats['gray'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="flex-1 bg-gray-900 rounded-xl border border-gray-800 overflow-hidden flex flex-col min-h-0">
        {{-- Table Header --}}
        <div class="flex-shrink-0">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-800/80 text-gray-300 text-sm uppercase tracking-wider">
                        <th class="px-5 py-4 text-left w-[140px]">WO</th>
                        <th class="px-5 py-4 text-left w-[90px]">Item</th>
                        <th class="px-5 py-4 text-left w-[140px]"># Parte</th>
                        <th class="px-5 py-4 text-left">Descripción</th>
                        <th class="px-4 py-4 text-center w-[70px]">Lotes</th>
                        <th class="px-3 py-4 text-center w-[110px]">Kit</th>
                        <th class="px-3 py-4 text-center w-[110px]">Insp</th>
                        <th class="px-3 py-4 text-center w-[110px]">Prod</th>
                        <th class="px-3 py-4 text-center w-[110px]">Cal</th>
                        <th class="px-3 py-4 text-center w-[110px]">Emp</th>
                    </tr>
                </thead>
            </table>
        </div>

        {{-- Table Body (scrollable) --}}
        <div class="flex-1 overflow-y-auto min-h-0" x-ref="tableBody">
            <table class="w-full">
                <tbody class="divide-y divide-gray-800/50">
                    @forelse ($rows as $i => $row)
                        <tr class="{{ $i % 2 === 0 ? 'bg-gray-900' : 'bg-gray-900/50' }} hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-4 text-base font-semibold text-indigo-400 w-[140px]">{{ $row['wo'] }}</td>
                            <td class="px-5 py-4 text-base text-gray-300 w-[90px]">{{ $row['item'] }}</td>
                            <td class="px-5 py-4 text-base font-medium text-white w-[140px]">{{ $row['part_number'] }}</td>
                            <td class="px-5 py-4 text-base text-gray-400 truncate max-w-[400px]" title="{{ $row['description'] }}">{{ $row['description'] }}</td>
                            <td class="px-4 py-4 text-center w-[70px]">
                                <span class="text-sm font-bold text-gray-300 bg-gray-800 px-2.5 py-1 rounded">{{ $row['lot_count'] }}</span>
                            </td>
                            @foreach (['kit', 'inspeccion', 'produccion', 'calidad', 'empaque'] as $areaKey)
                                <td class="px-3 py-3 text-center w-[110px]">
                                    @php
                                        $a = $row[$areaKey];
                                        $aTotal = $a['green'] + $a['yellow'] + $a['gray'];
                                        $miniR = 15;
                                        $miniCirc = 2 * M_PI * $miniR;
                                        if ($aTotal > 0) {
                                            $mPctG = ($a['green'] / $aTotal) * 100;
                                            $mPctY = ($a['yellow'] / $aTotal) * 100;
                                            $mPctGr = 100 - $mPctG - $mPctY;
                                            $mLenG = ($mPctG / 100) * $miniCirc;
                                            $mLenY = ($mPctY / 100) * $miniCirc;
                                            $mLenGr = ($mPctGr / 100) * $miniCirc;
                                            $mDisplayPct = round($mPctG);
                                        }
                                    @endphp
                                    @if ($aTotal > 0)
                                        <div class="flex items-center justify-center gap-1.5">
                                            <div class="relative" style="width:50px;height:50px;">
                                                <svg viewBox="0 0 40 40" class="w-full h-full -rotate-90">
                                                    <circle cx="20" cy="20" r="{{ $miniR }}" fill="none" stroke="#374151" stroke-width="5" />
                                                    @if ($mLenGr > 0)
                                                        <circle cx="20" cy="20" r="{{ $miniR }}" fill="none"
                                                            stroke="#4b5563" stroke-width="5"
                                                            stroke-dasharray="{{ $mLenGr }} {{ $miniCirc - $mLenGr }}"
                                                            stroke-dashoffset="{{ -($mLenG + $mLenY) }}"
                                                            stroke-linecap="butt" />
                                                    @endif
                                                    @if ($mLenY > 0)
                                                        <circle cx="20" cy="20" r="{{ $miniR }}" fill="none"
                                                            stroke="#facc15" stroke-width="5"
                                                            stroke-dasharray="{{ $mLenY }} {{ $miniCirc - $mLenY }}"
                                                            stroke-dashoffset="{{ -$mLenG }}"
                                                            stroke-linecap="butt" />
                                                    @endif
                                                    @if ($mLenG > 0)
                                                        <circle cx="20" cy="20" r="{{ $miniR }}" fill="none"
                                                            stroke="#22c55e" stroke-width="5"
                                                            stroke-dasharray="{{ $mLenG }} {{ $miniCirc - $mLenG }}"
                                                            stroke-dashoffset="0"
                                                            stroke-linecap="butt" />
                                                    @endif
                                                </svg>
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="text-[10px] font-bold text-white">{{ $mDisplayPct }}%</span>
                                                </div>
                                            </div>
                                            <span class="text-xs text-gray-500">{{ $a['green'] }}/{{ $aTotal }}</span>
                                        </div>
                                    @else
                                        <span class="text-gray-700">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-gray-500 text-lg">
                                No hay Work Orders activas
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @script
    <script>
        function updateClock() {
            const el = document.getElementById('tv-clock');
            if (el) {
                const now = new Date();
                el.textContent = now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }
        updateClock();
        setInterval(updateClock, 1000);
    </script>
    @endscript
</div>
