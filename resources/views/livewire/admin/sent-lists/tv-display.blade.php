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
            <h1 class="text-lg font-bold text-gray-800 tracking-wide">FLEXCON — Monitor de Producción</h1>
            <span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">{{ count($woCards) }} WOs</span>
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-500">
            @if (count($slides) > 1)
                <span x-text="(currentSlide + 1) + '/{{ count($slides) }}'"></span>
            @endif
            <span class="font-mono" id="tv-clock"></span>
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
                            {{-- Card Header: compact --}}
                            <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-sm font-bold text-indigo-600">{{ $card['wo'] }}</span>
                                        <span class="text-xs text-gray-400">{{ $card['item'] }}</span>
                                        <span class="text-xs font-semibold text-gray-800">#{{ $card['part_number'] }}</span>
                                        @if ($card['is_crimp'])
                                            <span class="px-1 py-0.5 text-[9px] font-bold bg-purple-100 text-purple-700 rounded leading-none">CRIMP</span>
                                        @endif
                                        <span class="text-[10px] text-gray-400 truncate max-w-[200px]">{{ $card['description'] }}</span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 flex-shrink-0">{{ $card['lot_count'] }} lotes</span>
                                </div>
                            </div>

                            {{-- Lots table: compact rows --}}
                            <div class="flex-1 overflow-y-auto min-h-0">
                                <table class="w-full text-[11px]">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-gray-100 text-gray-500 uppercase tracking-wider">
                                            <th class="px-2 py-1.5 text-left font-medium">Lote</th>
                                            <th class="px-2 py-1.5 text-right font-medium">Piezas</th>
                                            @if ($card['is_crimp'])
                                                <th class="px-2 py-1.5 text-left font-medium">Kit</th>
                                            @endif
                                            <th class="px-2 py-1.5 text-center font-medium" style="width:28%">Producción</th>
                                            <th class="px-2 py-1.5 text-center font-medium" style="width:28%">Calidad</th>
                                            <th class="px-2 py-1.5 text-center font-medium" style="width:28%">Empaque</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($card['lots'] as $li => $lot)
                                            <tr class="{{ $li % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }}">
                                                {{-- Lot number --}}
                                                <td class="px-2 py-1.5 font-semibold text-gray-800 whitespace-nowrap">
                                                    {{ $lot['lot_number'] }}
                                                    @if ($lot['completion_count'] > 0)
                                                        <span class="text-[9px] text-amber-600 font-normal ml-0.5">C{{ $lot['completion_count'] }}</span>
                                                    @endif
                                                </td>
                                                {{-- Pieces target --}}
                                                <td class="px-2 py-1.5 text-right text-gray-500 font-mono whitespace-nowrap">{{ number_format($lot['quantity']) }}</td>
                                                {{-- Kit (if crimp) --}}
                                                @if ($card['is_crimp'])
                                                    <td class="px-2 py-1.5 whitespace-nowrap">
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
                                                                <span class="inline-flex items-center gap-0.5 mr-1">
                                                                    <span class="w-1.5 h-1.5 rounded-full {{ $kDot }}"></span>
                                                                    <span class="text-gray-600">{{ $kit['kit_number'] }}</span>
                                                                </span>
                                                            @endforeach
                                                        @else
                                                            <span class="text-gray-300">—</span>
                                                        @endif
                                                    </td>
                                                @endif
                                                {{-- Production mini bar --}}
                                                <td class="px-2 py-1.5">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                            <div class="h-full rounded-full {{ $lot['prod_pct'] >= 100 ? 'bg-green-500' : ($lot['prod_pct'] > 0 ? 'bg-yellow-400' : 'bg-gray-300') }}"
                                                                style="width:{{ max($lot['prod_pct'], 2) }}%"></div>
                                                        </div>
                                                        <span class="font-mono text-[10px] {{ $lot['prod_pct'] >= 100 ? 'text-green-600' : 'text-gray-500' }} whitespace-nowrap w-16 text-right">{{ number_format($lot['prod_weighed']) }}/{{ number_format($lot['quantity']) }}</span>
                                                    </div>
                                                </td>
                                                {{-- Quality mini bar --}}
                                                <td class="px-2 py-1.5">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                            <div class="h-full rounded-full {{ $lot['qual_pct'] >= 100 ? 'bg-green-500' : ($lot['qual_pct'] > 0 ? 'bg-blue-400' : 'bg-gray-300') }}"
                                                                style="width:{{ max($lot['qual_pct'], 2) }}%"></div>
                                                        </div>
                                                        <span class="font-mono text-[10px] {{ $lot['qual_pct'] >= 100 ? 'text-green-600' : 'text-gray-500' }} whitespace-nowrap w-16 text-right">{{ number_format($lot['qual_good']) }}/{{ number_format($lot['qual_target']) }}</span>
                                                    </div>
                                                </td>
                                                {{-- Packaging mini bar --}}
                                                <td class="px-2 py-1.5">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                            <div class="h-full rounded-full {{ $lot['pkg_pct'] >= 100 ? 'bg-green-500' : ($lot['pkg_pct'] > 0 ? 'bg-orange-400' : 'bg-gray-300') }}"
                                                                style="width:{{ max($lot['pkg_pct'], 2) }}%"></div>
                                                        </div>
                                                        <span class="font-mono text-[10px] {{ $lot['pkg_pct'] >= 100 ? 'text-green-600' : 'text-gray-500' }} whitespace-nowrap w-16 text-right">{{ number_format($lot['packed']) }}/{{ number_format($lot['pkg_target']) }}</span>
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
                <p class="text-gray-400 text-lg">No hay Work Orders activas</p>
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
