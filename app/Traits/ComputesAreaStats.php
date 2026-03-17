<?php

namespace App\Traits;

use App\Models\WorkOrder;

trait ComputesAreaStats
{
    /**
     * Compute area progress stats (kit, inspeccion, produccion, calidad, empaque)
     * across all active work orders with lots.
     *
     * Returns an array keyed by area name, each with green/yellow/gray/total counts.
     */
    protected function computeAreaStats(): array
    {
        $workOrders = WorkOrder::with([
            'purchaseOrder.part',
            'lots.weighings',
            'lots.qualityWeighings',
            'lots.packagingRecords',
            'lots.kits',
        ])
        ->whereHas('lots')
        ->get();

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

            foreach ($wo->lots as $lot) {
                $areaStats['kit']['total']++;
                $areaStats['inspeccion']['total']++;
                $areaStats['produccion']['total']++;
                $areaStats['calidad']['total']++;
                $areaStats['empaque']['total']++;

                // --- Kit ---
                if ($part->is_crimp) {
                    $lotKit = $lot->kits->sortByDesc('created_at')->first();
                    $kitStatus = $lotKit?->status ?? 'none';
                    if ($kitStatus === 'released') { $areaStats['kit']['green']++; }
                    elseif ($kitStatus !== 'none') { $areaStats['kit']['yellow']++; }
                    else { $areaStats['kit']['gray']++; }
                } else {
                    $matStatus = $lot->material_status ?? 'pending';
                    if ($matStatus === 'released') { $areaStats['kit']['green']++; }
                    else { $areaStats['kit']['gray']++; }
                }

                // --- Inspección ---
                $inspStatus = $lot->inspection_status ?? 'pending';
                if ($inspStatus === 'approved') { $areaStats['inspeccion']['green']++; }
                elseif ($inspStatus === 'rejected') { $areaStats['inspeccion']['yellow']++; }
                else { $areaStats['inspeccion']['gray']++; }

                // --- Producción ---
                $prodWeighed = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
                $prodTarget = $lot->quantity;
                if ($prodWeighed > 0 && $prodWeighed >= $prodTarget) { $areaStats['produccion']['green']++; }
                elseif ($prodWeighed > 0) { $areaStats['produccion']['yellow']++; }
                else { $areaStats['produccion']['gray']++; }

                // --- Calidad ---
                $qualSem = $lot->getQualitySemaphoreStatus();
                if ($qualSem === 'green') { $areaStats['calidad']['green']++; }
                elseif ($qualSem === 'yellow') { $areaStats['calidad']['yellow']++; }
                else { $areaStats['calidad']['gray']++; }

                // --- Empaque ---
                $pkgSem = $lot->getPackagingSemaphoreStatus();
                if ($pkgSem === 'green') { $areaStats['empaque']['green']++; }
                elseif (in_array($pkgSem, ['yellow', 'blue', 'orange'])) { $areaStats['empaque']['yellow']++; }
                else { $areaStats['empaque']['gray']++; }
            }
        }

        return $areaStats;
    }
}
