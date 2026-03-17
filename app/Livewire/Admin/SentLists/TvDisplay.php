<?php

namespace App\Livewire\Admin\SentLists;

use App\Models\WorkOrder;
use App\Models\SentList;
use Livewire\Component;
use Livewire\Attributes\On;

class TvDisplay extends Component
{
    public $refreshInterval = 30;

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
        ->whereHas('lots')
        ->orderBy('wo_number')
        ->get();

        // Build rows and aggregate area stats
        $rows = [];
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

            $lotCount = $wo->lots->count();
            $kitGreen = 0; $kitYellow = 0; $kitGray = 0;
            $inspGreen = 0; $inspYellow = 0; $inspGray = 0;
            $prodGreen = 0; $prodYellow = 0; $prodGray = 0;
            $qualGreen = 0; $qualYellow = 0; $qualGray = 0;
            $pkgGreen = 0; $pkgYellow = 0; $pkgGray = 0;

            foreach ($wo->lots as $lot) {
                // --- Kit ---
                if ($part->is_crimp) {
                    $lotKit = $lot->kits->sortByDesc('created_at')->first();
                    $kitStatus = $lotKit?->status ?? 'none';
                    if ($kitStatus === 'released') { $kitGreen++; }
                    elseif ($kitStatus !== 'none') { $kitYellow++; }
                    else { $kitGray++; }
                } else {
                    $matStatus = $lot->material_status ?? 'pending';
                    if ($matStatus === 'released') { $kitGreen++; }
                    elseif ($matStatus === 'rejected') { $kitGray++; }
                    else { $kitGray++; }
                }

                // --- Inspección ---
                $inspStatus = $lot->inspection_status ?? 'pending';
                if ($inspStatus === 'approved') { $inspGreen++; }
                elseif ($inspStatus === 'rejected') { $inspYellow++; }
                else { $inspGray++; }

                // --- Producción ---
                $prodWeighed = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
                $prodTarget = $lot->quantity;
                if ($prodWeighed > 0 && $prodWeighed >= $prodTarget) { $prodGreen++; }
                elseif ($prodWeighed > 0) { $prodYellow++; }
                else { $prodGray++; }

                // --- Calidad ---
                $qualSem = $lot->getQualitySemaphoreStatus();
                if ($qualSem === 'green') { $qualGreen++; }
                elseif ($qualSem === 'yellow') { $qualYellow++; }
                else { $qualGray++; }

                // --- Empaque ---
                $pkgSem = $lot->getPackagingSemaphoreStatus();
                if (in_array($pkgSem, ['green'])) { $pkgGreen++; }
                elseif (in_array($pkgSem, ['yellow', 'blue', 'orange'])) { $pkgYellow++; }
                else { $pkgGray++; }
            }

            $firstLot = $wo->lots->first();
            $rows[] = [
                'wo' => $wo->purchaseOrder->wo ?? 'N/A',
                'item' => $part->item_number ?? 'N/A',
                'part_number' => $part->number ?? 'N/A',
                'description' => $firstLot->description ?? $part->description ?? 'N/A',
                'lot_count' => $lotCount,
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

        return view('livewire.admin.sent-lists.tv-display', [
            'rows' => $rows,
            'areaStats' => $areaStats,
        ])->layout('components.layouts.app');
    }
}
