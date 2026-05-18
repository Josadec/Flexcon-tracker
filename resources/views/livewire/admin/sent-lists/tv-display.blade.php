<div
    x-data="{
        currentSlide: 0,
        totalSlides: {{ count($slides) ?: 1 }},
        slideInterval: 10000,
        timer: null,
        paused: false,
        init() {
            this.startTimer();
        },
        startTimer() {
            this.timer = setInterval(() => {
                if (!this.paused) this.next();
            }, this.slideInterval);
        },
        next() {
            this.currentSlide = (this.currentSlide + 1) % this.totalSlides;
        },
        prev() {
            this.currentSlide = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
        },
        goTo(i) {
            this.currentSlide = i;
            clearInterval(this.timer);
            this.startTimer();
        }
    }"
    wire:poll.{{ $refreshInterval }}s="refreshDisplay"
    class="h-screen bg-white text-gray-900 p-4 flex flex-col overflow-hidden"
    @mouseenter="paused = true"
    @mouseleave="paused = false"
>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-3 flex-shrink-0">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-800 tracking-wide">FLEXCON — Monitor de Producción</h1>
            <span class="text-sm text-gray-600 bg-gray-100 px-2.5 py-1 rounded font-semibold">{{ count($woCards) }} WOs</span>
        </div>
        <div class="flex items-center gap-4 text-base text-gray-600">
            @if (count($slides) > 1)
                <span class="font-semibold" x-text="(currentSlide + 1) + '/{{ count($slides) }}'"></span>
            @endif
            <span class="font-mono text-xl font-bold text-gray-800"
                x-data="{ time: '' }"
                x-init="
                    const fmt = () => new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    time = fmt();
                    setInterval(() => { time = fmt(); }, 1000);
                "
                x-text="time"></span>
        </div>
    </div>

    {{-- Banner KPIs del día --}}
    <div class="flex items-stretch gap-3 mb-3 flex-shrink-0">
        {{-- % Cumplimiento global --}}
        @php
            $pct = $dayKpis['completion_pct'] ?? 0;
            $pctColor = $pct >= 90 ? 'green' : ($pct >= 60 ? 'yellow' : 'red');
            $pctBg = $pct >= 90 ? 'bg-green-50 border-green-300' : ($pct >= 60 ? 'bg-yellow-50 border-yellow-300' : 'bg-red-50 border-red-300');
            $pctText = $pct >= 90 ? 'text-green-700' : ($pct >= 60 ? 'text-yellow-700' : 'text-red-700');
            $pctBar = $pct >= 90 ? 'bg-green-500' : ($pct >= 60 ? 'bg-yellow-400' : 'bg-red-500');
        @endphp
        <div class="flex-1 border-2 {{ $pctBg }} rounded-lg px-4 py-2 flex flex-col justify-center">
            <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-bold uppercase tracking-wider text-gray-600">% Cumplimiento global</span>
                <span class="text-3xl font-extrabold {{ $pctText }}">{{ number_format($pct, 1) }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                <div class="h-full {{ $pctBar }} rounded-full transition-all" style="width: {{ max($pct, 1) }}%"></div>
            </div>
            <div class="text-xs text-gray-600 mt-1 font-mono">
                {{ number_format($dayKpis['total_sent']) }} / {{ number_format($dayKpis['total_target']) }} pz
            </div>
        </div>

        {{-- Piezas empacadas hoy --}}
        <div class="flex-1 border-2 border-indigo-300 bg-indigo-50 rounded-lg px-4 py-2 flex flex-col justify-center">
            <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-bold uppercase tracking-wider text-gray-600">Empacadas Hoy</span>
                <span class="text-3xl font-extrabold text-indigo-700">{{ number_format($dayKpis['packed_today_total']) }}</span>
            </div>
            <div class="flex items-center gap-3 text-xs font-semibold text-gray-700 mt-1">
                @foreach (['Mesa' => 'bg-blue-500', 'Máquina' => 'bg-green-500', 'Semi-Automática' => 'bg-purple-500'] as $st => $colorClass)
                    @php $val = $dayKpis['packed_today_by_station'][$st] ?? 0; @endphp
                    <span class="inline-flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full {{ $colorClass }}"></span>
                        {{ $st }}: <span class="font-mono">{{ number_format($val) }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Slideshow --}}
    <div class="flex-1 flex flex-col min-h-0">
        @if (count($slides) > 0)
            @foreach ($slides as $slideIndex => $slideCards)
                <div x-show="currentSlide === {{ $slideIndex }}" x-cloak
                    x-transition:enter="transition ease-out duration-400"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="flex-1 grid grid-cols-1 gap-3 min-h-0" style="grid-template-rows: repeat({{ count($slideCards) }}, minmax(0, 1fr))"
                >
                    @foreach ($slideCards as $card)
                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden flex flex-col min-h-0">
                            {{-- Card Header --}}
                            <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex-shrink-0">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="text-xl font-bold text-indigo-600">{{ $card['wo'] }}</span>
                                        <span class="text-sm text-gray-500">{{ $card['item'] }}</span>
                                        <span class="text-sm font-bold text-gray-800">#{{ $card['part_number'] }}</span>
                                        @if ($card['is_crimp'])
                                            <span class="px-1.5 py-0.5 text-xs font-bold bg-purple-100 text-purple-700 rounded">CRIMP</span>
                                        @endif
                                        <span class="text-sm text-gray-500 truncate max-w-[280px]">{{ $card['description'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        {{-- Countdown a fecha de envío --}}
                                        @php
                                            $sc = $card['send_color'];
                                            $countdownClasses = match ($sc) {
                                                'red'    => 'bg-red-100 text-red-700 border-red-400 animate-pulse',
                                                'yellow' => 'bg-yellow-100 text-yellow-700 border-yellow-400',
                                                'green'  => 'bg-green-100 text-green-700 border-green-400',
                                                default  => 'bg-gray-100 text-gray-500 border-gray-300',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-sm font-bold border-2 {{ $countdownClasses }}"
                                              title="Fecha de envío: {{ $card['send_date'] ?? '—' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span>{{ $card['send_label'] }}</span>
                                            @if ($card['send_date'])
                                                <span class="text-xs opacity-75">{{ $card['send_date'] }}</span>
                                            @endif
                                        </span>
                                        <span class="text-sm font-semibold text-gray-600 bg-white px-2 py-1 rounded border border-gray-200">{{ $card['lot_count'] }} lotes</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Lots table --}}
                            <div class="flex-1 overflow-y-auto min-h-0">
                                <table class="w-full text-sm">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-gray-100 text-gray-700 uppercase tracking-wider">
                                            <th class="px-3 py-2 text-left text-sm font-bold">Lote</th>
                                            <th class="px-3 py-2 text-right text-sm font-bold">Piezas</th>
                                            @if ($card['is_crimp'])
                                                <th class="px-3 py-2 text-left text-sm font-bold">Kit</th>
                                            @endif
                                            <th class="px-3 py-2 text-center text-sm font-bold" style="width:28%">Producción</th>
                                            <th class="px-3 py-2 text-center text-sm font-bold" style="width:28%">Calidad</th>
                                            <th class="px-3 py-2 text-center text-sm font-bold" style="width:28%">Empaque</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($card['lots'] as $li => $lot)
                                            <tr class="{{ $li % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }}">
                                                {{-- Lot number --}}
                                                <td class="px-3 py-2 font-bold text-base text-gray-800 whitespace-nowrap">
                                                    {{ $lot['lot_number'] }}
                                                    @if ($lot['completion_count'] > 0)
                                                        <span class="text-xs text-amber-600 font-semibold ml-1">C{{ $lot['completion_count'] }}</span>
                                                    @endif
                                                </td>
                                                {{-- Pieces target --}}
                                                <td class="px-3 py-2 text-right text-gray-700 font-mono font-semibold text-base whitespace-nowrap">{{ number_format($lot['quantity']) }}</td>
                                                {{-- Kit (if crimp) --}}
                                                @if ($card['is_crimp'])
                                                    <td class="px-3 py-2 whitespace-nowrap text-sm">
                                                        @if (!empty($lot['kits']))
                                                            @foreach ($lot['kits'] as $kit)
                                                                @php
                                                                    $kDot = match($kit['status']) {
                                                                        'released' => 'bg-green-500',
                                                                        'preparing' => 'bg-yellow-400',
                                                                        'pending_approval' => 'bg-blue-400',
                                                                        default => 'bg-gray-600',
                                                                    };
                                                                @endphp
                                                                <span class="inline-flex items-center gap-1 mr-2">
                                                                    <span class="w-2 h-2 rounded-full {{ $kDot }}"></span>
                                                                    <span class="text-gray-700 font-semibold">{{ $kit['kit_number'] }}</span>
                                                                </span>
                                                            @endforeach
                                                        @else
                                                            <span class="text-gray-300">—</span>
                                                        @endif
                                                    </td>
                                                @endif
                                                {{-- Production mini bar --}}
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                                            <div class="h-full rounded-full {{ $lot['prod_pct'] >= 100 ? 'bg-green-500' : ($lot['prod_pct'] > 0 ? 'bg-yellow-400' : 'bg-gray-300') }}"
                                                                style="width:{{ max($lot['prod_pct'], 2) }}%"></div>
                                                        </div>
                                                        <span class="font-mono text-sm font-semibold {{ $lot['prod_pct'] >= 100 ? 'text-green-600' : 'text-gray-700' }} whitespace-nowrap w-24 text-right">{{ number_format($lot['prod_weighed']) }}/{{ number_format($lot['quantity']) }}</span>
                                                    </div>
                                                </td>
                                                {{-- Quality mini bar --}}
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                                            <div class="h-full rounded-full {{ $lot['qual_pct'] >= 100 ? 'bg-green-500' : ($lot['qual_pct'] > 0 ? 'bg-blue-400' : 'bg-gray-300') }}"
                                                                style="width:{{ max($lot['qual_pct'], 2) }}%"></div>
                                                        </div>
                                                        <span class="font-mono text-sm font-semibold {{ $lot['qual_pct'] >= 100 ? 'text-green-600' : 'text-gray-700' }} whitespace-nowrap w-24 text-right">{{ number_format($lot['qual_good']) }}/{{ number_format($lot['qual_target']) }}</span>
                                                    </div>
                                                </td>
                                                {{-- Packaging mini bar --}}
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                                            <div class="h-full rounded-full {{ $lot['pkg_pct'] >= 100 ? 'bg-green-500' : ($lot['pkg_pct'] > 0 ? 'bg-orange-400' : 'bg-gray-300') }}"
                                                                style="width:{{ max($lot['pkg_pct'], 2) }}%"></div>
                                                        </div>
                                                        <span class="font-mono text-sm font-semibold {{ $lot['pkg_pct'] >= 100 ? 'text-green-600' : 'text-gray-700' }} whitespace-nowrap w-24 text-right">{{ number_format($lot['packed']) }}/{{ number_format($lot['pkg_target']) }}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="flex-1 flex items-center justify-center">
                <p class="text-gray-400 text-2xl font-semibold">No hay Work Orders activas</p>
            </div>
        @endif

        {{-- Slide dots --}}
        @if (count($slides) > 1)
            <div class="flex items-center justify-center gap-2 mt-2 flex-shrink-0">
                @foreach ($slides as $si => $s)
                    <button @click="goTo({{ $si }})"
                        :class="currentSlide === {{ $si }} ? 'bg-indigo-500 w-6' : 'bg-gray-300 w-2 hover:bg-gray-400'"
                        class="h-2 rounded-full transition-all duration-300"></button>
                @endforeach
            </div>
        @endif
    </div>

</div>
