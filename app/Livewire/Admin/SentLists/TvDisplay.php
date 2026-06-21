<?php

namespace App\Livewire\Admin\SentLists;

use App\Models\WorkOrder;
use Livewire\Component;
use Livewire\Attributes\On;

class TvDisplay extends Component
{
    public $refreshInterval = 30;
    public int $cardsPerSlide = 2;

    #[On('refresh-display')]
    public function refreshDisplay()
    {
        // Triggers re-render
    }

    public function render()
    {
        $workOrders = WorkOrder::with([
            'purchaseOrder.part',
            'lots.weighings',
            'lots.qualityWeighings',
            'lots.packagingRecords',
            'lots.crimpLots',
            'sentList',
        ])
        ->whereHas('lots', fn ($q) => $q->whereIn('status', ['pending', 'in_progress']))
        ->orderBy('wo_number')
        ->get();

        // Build WO cards with lot-level detail
        $woCards = [];
        $areaStats = [
            'kit'        => ['green' => 0, 'yellow' => 0, 'gray' => 0, 'total' => 0],
            'inspeccion'  => ['green' => 0, 'yellow' => 0, 'gray' => 0, 'total' => 0],
            'produccion' => ['green' => 0, 'yellow' => 0, 'gray' => 0, 'total' => 0],
            'calidad'    => ['green' => 0, 'yellow' => 0, 'gray' => 0, 'total' => 0],
            'empaque'    => ['green' => 0, 'yellow' => 0, 'gray' => 0, 'total' => 0],
        ];

        foreach ($workOrders as $wo) {
            $part = $wo->purchaseOrder->part ?? null;
            if (!$part) continue;

            $isCrimp = (bool) ($part->is_crimp ?? false);
            $lotCount = $wo->lots->count();
            $kitGreen = 0; $kitYellow = 0; $kitGray = 0;
            $inspGreen = 0; $inspYellow = 0; $inspGray = 0;
            $prodGreen = 0; $prodYellow = 0; $prodGray = 0;
            $qualGreen = 0; $qualYellow = 0; $qualGray = 0;
            $pkgGreen = 0; $pkgYellow = 0; $pkgGray = 0;

            $lotsDetail = [];

            foreach ($wo->lots as $lot) {
                // Production progress
                $prodWeighed = $lot->weighings->sum('good_pieces');
                $prodTarget = $lot->quantity;

                // Quality progress
                $qualGood = $lot->qualityWeighings->sum('good_pieces');
                $qualTarget = $prodWeighed; // quality checks production output

                // Packaging progress
                $packed = $lot->packagingRecords->sum('packed_pieces');
                $pkgTarget = $qualGood; // packaging uses quality approved

                // Lotes de CRIMP (para crimp). El indicador usa la liberación del
                // viajero (material_status); CRIMP ya no usa Kit.
                $kitsInfo = [];
                if ($isCrimp) {
                    $matReleased = ($lot->material_status ?? 'pending') === 'released';
                    foreach ($lot->crimpLots as $cl) {
                        $kitsInfo[] = [
                            'kit_number' => $cl->crimp_lot_number,
                            'quantity' => $cl->quantity,
                            'status' => $matReleased ? 'released' : 'preparing',
                            'status_label' => $matReleased ? 'Liberado' : 'Pendiente',
                        ];
                    }
                }
                $matStatus = $lot->material_status ?? 'pending';
                if ($matStatus === 'released') { $kitGreen++; }
                else { $kitGray++; }

                // Semaphore counts
                $inspStatus = $lot->inspection_status ?? 'pending';
                if ($inspStatus === 'approved') { $inspGreen++; }
                elseif ($inspStatus === 'rejected') { $inspYellow++; }
                else { $inspGray++; }

                $prodTotal = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
                if ($prodTotal > 0 && $prodTotal >= $prodTarget) { $prodGreen++; }
                elseif ($prodTotal > 0) { $prodYellow++; }
                else { $prodGray++; }

                if ($prodWeighed <= 0) {
                    $qualGray++;
                } else {
                    $qualAlready = $lot->qualityWeighings->sum('good_pieces') + $lot->qualityWeighings->sum('bad_pieces');
                    if ($qualAlready >= $prodWeighed) { $qualGreen++; }
                    else { $qualYellow++; }
                }

                $qualGoodPieces = $lot->qualityWeighings->sum('good_pieces');
                if ($qualGoodPieces <= 0) {
                    $pkgGray++;
                } elseif ($lot->surplus_received || in_array($lot->closure_decision, ['complete_lot', 'close_as_is', 'new_lot'])) {
                    $pkgGreen++;
                } elseif ($lot->viajero_received) {
                    $pkgYellow++;
                } elseif ($lot->packagingRecords->sum('packed_pieces') > 0) {
                    $pkgYellow++;
                } else {
                    $pkgGray++;
                }

                // Determine lot status color
                $lotStatusColor = match ($lot->status) {
                    'completed' => 'green',
                    'in_progress' => 'blue',
                    'cancelled' => 'red',
                    default => 'gray',
                };

                $lotsDetail[] = [
                    'lot_number' => $lot->lot_number,
                    'quantity' => $prodTarget,
                    'status' => $lot->status,
                    'status_color' => $lotStatusColor,
                    'completion_count' => $lot->completion_count ?? 0,
                    'prod_weighed' => $prodWeighed,
                    'prod_pct' => $prodTarget > 0 ? min(100, round(($prodWeighed / $prodTarget) * 100)) : 0,
                    'qual_good' => $qualGood,
                    'qual_target' => $qualTarget,
                    'qual_pct' => $qualTarget > 0 ? min(100, round(($qualGood / $qualTarget) * 100)) : 0,
                    'packed' => $packed,
                    'pkg_target' => $pkgTarget,
                    'pkg_pct' => $pkgTarget > 0 ? min(100, round(($packed / $pkgTarget) * 100)) : 0,
                    'kits' => $kitsInfo,
                ];
            }

            // Countdown a fecha de envío
            $sendDate = $wo->scheduled_send_date;
            $daysToSend = null;
            $sendColor = 'gray';
            $sendLabel = 'Sin fecha';
            if ($sendDate) {
                $daysToSend = now()->startOfDay()->diffInDays($sendDate->startOfDay(), false);
                if ($daysToSend < 0) {
                    $sendColor = 'red';
                    $sendLabel = abs((int) $daysToSend) . ' día(s) atrasado';
                } elseif ($daysToSend < 1) {
                    $sendColor = 'red';
                    $sendLabel = 'Hoy';
                } elseif ($daysToSend <= 3) {
                    $sendColor = 'yellow';
                    $sendLabel = ((int) $daysToSend) . ' día(s)';
                } else {
                    $sendColor = 'green';
                    $sendLabel = ((int) $daysToSend) . ' días';
                }
            }

            $firstLot = $wo->lots->first();
            $woCards[] = [
                'wo' => $wo->purchaseOrder->wo ?? 'N/A',
                'item' => $part->item_number ?? 'N/A',
                'part_number' => $part->number ?? 'N/A',
                'description' => $firstLot->description ?? $part->description ?? 'N/A',
                'is_crimp' => $isCrimp,
                'lot_count' => $lotCount,
                'lots' => $lotsDetail,
                'send_date' => $sendDate?->format('d/m/Y'),
                'days_to_send' => $daysToSend,
                'send_color' => $sendColor,
                'send_label' => $sendLabel,
                'wo_quantity' => $wo->original_quantity,
                'wo_sent' => $wo->sent_pieces,
                'kit' => ['green' => $kitGreen, 'yellow' => $kitYellow, 'gray' => $kitGray],
                'inspeccion' => ['green' => $inspGreen, 'yellow' => $inspYellow, 'gray' => $inspGray],
                'produccion' => ['green' => $prodGreen, 'yellow' => $prodYellow, 'gray' => $prodGray],
                'calidad' => ['green' => $qualGreen, 'yellow' => $qualYellow, 'gray' => $qualGray],
                'empaque' => ['green' => $pkgGreen, 'yellow' => $pkgYellow, 'gray' => $pkgGray],
            ];

            // Aggregate global stats
            $areaStats['kit']['green'] += $kitGreen;
            $areaStats['kit']['yellow'] += $kitYellow;
            $areaStats['kit']['gray'] += $kitGray;
            $areaStats['kit']['total'] += $lotCount;

            $areaStats['inspeccion']['green'] += $inspGreen;
            $areaStats['inspeccion']['yellow'] += $inspYellow;
            $areaStats['inspeccion']['gray'] += $inspGray;
            $areaStats['inspeccion']['total'] += $lotCount;

            $areaStats['produccion']['green'] += $prodGreen;
            $areaStats['produccion']['yellow'] += $prodYellow;
            $areaStats['produccion']['gray'] += $prodGray;
            $areaStats['produccion']['total'] += $lotCount;

            $areaStats['calidad']['green'] += $qualGreen;
            $areaStats['calidad']['yellow'] += $qualYellow;
            $areaStats['calidad']['gray'] += $qualGray;
            $areaStats['calidad']['total'] += $lotCount;

            $areaStats['empaque']['green'] += $pkgGreen;
            $areaStats['empaque']['yellow'] += $pkgYellow;
            $areaStats['empaque']['gray'] += $pkgGray;
            $areaStats['empaque']['total'] += $lotCount;
        }

        // Chunk WO cards into slides of N cards each
        $slides = array_chunk($woCards, $this->cardsPerSlide);

        // KPIs del día
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $packedTodayByStation = ['Mesa' => 0, 'Máquina' => 0, 'Semi-Automática' => 0, 'Sin Clasificar' => 0];
        $packedTodayTotal = 0;
        $totalTargetVisible = 0;
        $totalSentVisible = 0;

        foreach ($workOrders as $wo) {
            $totalTargetVisible += (int) ($wo->original_quantity ?? 0);
            $totalSentVisible   += (int) ($wo->sent_pieces ?? 0);

            // Resolver workstation (PO.workstation_type → fallback al Standard)
            $woType = $wo->purchaseOrder?->workstation_type ?? null;
            $mode = $woType ?: optional($wo->purchaseOrder?->part?->standards()->active()->first())->getAssemblyMode();
            $station = match($mode) {
                'manual', 'table' => 'Mesa',
                'machine'         => 'Máquina',
                'semi_automatic'  => 'Semi-Automática',
                default           => 'Sin Clasificar',
            };

            foreach ($wo->lots as $lot) {
                $todayPacked = $lot->packagingRecords
                    ->whereBetween('packed_at', [$todayStart, $todayEnd])
                    ->sum('packed_pieces');
                $packedTodayByStation[$station] = ($packedTodayByStation[$station] ?? 0) + (int) $todayPacked;
                $packedTodayTotal += (int) $todayPacked;
            }
        }

        $globalCompletion = $totalTargetVisible > 0
            ? min(100, round(($totalSentVisible / $totalTargetVisible) * 100, 1))
            : 0;

        $dayKpis = [
            'packed_today_total'  => $packedTodayTotal,
            'packed_today_by_station' => $packedTodayByStation,
            'total_target' => $totalTargetVisible,
            'total_sent'   => $totalSentVisible,
            'completion_pct' => $globalCompletion,
        ];

        return view('livewire.admin.sent-lists.tv-display', [
            'woCards' => $woCards,
            'slides' => $slides,
            'areaStats' => $areaStats,
            'dayKpis' => $dayKpis,
        ])->layout('components.layouts.tv');
    }
}
