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
            'lots.kits',
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

                // Kit info (for crimp)
                $kitsInfo = [];
                if ($isCrimp) {
                    foreach ($lot->kits as $kit) {
                        $kitsInfo[] = [
                            'kit_number' => $kit->kit_number,
                            'quantity' => $kit->quantity,
                            'status' => $kit->status,
                            'status_label' => ucfirst(str_replace('_', ' ', $kit->status)),
                        ];
                    }
                    $lotKit = $lot->kits->sortByDesc('created_at')->first();
                    $kitStatus = $lotKit?->status ?? 'none';
                    if ($kitStatus === 'released') { $kitGreen++; }
                    elseif ($kitStatus !== 'none') { $kitYellow++; }
                    else { $kitGray++; }
                } else {
                    $matStatus = $lot->material_status ?? 'pending';
                    if ($matStatus === 'released') { $kitGreen++; }
                    else { $kitGray++; }
                }

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

            $firstLot = $wo->lots->first();
            $woCards[] = [
                'wo' => $wo->purchaseOrder->wo ?? 'N/A',
                'item' => $part->item_number ?? 'N/A',
                'part_number' => $part->number ?? 'N/A',
                'description' => $firstLot->description ?? $part->description ?? 'N/A',
                'is_crimp' => $isCrimp,
                'lot_count' => $lotCount,
                'lots' => $lotsDetail,
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

        return view('livewire.admin.sent-lists.tv-display', [
            'woCards' => $woCards,
            'slides' => $slides,
            'areaStats' => $areaStats,
        ])->layout('components.layouts.tv');
    }
}
