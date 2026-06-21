<?php

namespace App\Livewire\Public;

use App\Models\WorkOrder;
use Livewire\Component;
use Livewire\Attributes\On;

class TvMonitor extends Component
{
    public $refreshInterval = 30;

    #[On('refresh-display')]
    public function refreshDisplay()
    {
        // Triggers re-render
    }

    public function render()
    {
        // Solo WOs con al menos un lote activo (pending o in_progress)
        // para no arrastrar historial completo al monitor
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
                // --- Kit / Material (a nivel viajero) ---
                // CRIMP ya no usa Kit: liberación por material_status, igual que NO-CRIMP.
                $matStatus = $lot->material_status ?? 'pending';
                if ($matStatus === 'released') { $kitGreen++; }
                else { $kitGray++; }

                // --- Inspección (columna directa, sin query) ---
                $inspStatus = $lot->inspection_status ?? 'pending';
                if ($inspStatus === 'approved') { $inspGreen++; }
                elseif ($inspStatus === 'rejected') { $inspYellow++; }
                else { $inspGray++; }

                // --- Producción (usa colección ya cargada) ---
                $prodWeighed = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
                $prodTarget = $lot->quantity;
                if ($prodWeighed > 0 && $prodWeighed >= $prodTarget) { $prodGreen++; }
                elseif ($prodWeighed > 0) { $prodYellow++; }
                else { $prodGray++; }

                // --- Calidad (desde colección, evita N+1 de getQualitySemaphoreStatus) ---
                $prodGoodPieces = $lot->weighings->sum('good_pieces');
                if ($prodGoodPieces <= 0) {
                    $qualGray++;
                } else {
                    $qualAlreadyWeighed = $lot->qualityWeighings->sum('good_pieces')
                                        + $lot->qualityWeighings->sum('bad_pieces');
                    if ($qualAlreadyWeighed >= $prodGoodPieces) { $qualGreen++; }
                    else { $qualYellow++; }
                }

                // --- Empaque (desde colección, evita N+1 de getPackagingSemaphoreStatus) ---
                $qualGoodPieces = $lot->qualityWeighings->sum('good_pieces');
                if ($qualGoodPieces <= 0) {
                    $pkgGray++;
                } elseif ($lot->surplus_received || ! empty($lot->closure_decision)) {
                    $pkgGreen++;
                } elseif ($lot->viajero_received) {
                    $pkgYellow++; // blue → yellow en semáforo TV
                } elseif ($lot->packagingRecords->sum('packed_pieces') > 0) {
                    $pkgYellow++;
                } else {
                    $pkgGray++;
                }
            }

            $firstLot = $wo->lots->first();
            $rows[] = [
                'wo'          => $wo->purchaseOrder->wo ?? 'N/A',
                'item'        => $part->item_number ?? 'N/A',
                'part_number' => $part->number ?? 'N/A',
                'description' => $firstLot->description ?? $part->description ?? 'N/A',
                'lot_count'   => $lotCount,
                'kit'         => ['green' => $kitGreen,  'yellow' => $kitYellow,  'gray' => $kitGray],
                'inspeccion'  => ['green' => $inspGreen, 'yellow' => $inspYellow, 'gray' => $inspGray],
                'produccion'  => ['green' => $prodGreen, 'yellow' => $prodYellow, 'gray' => $prodGray],
                'calidad'     => ['green' => $qualGreen, 'yellow' => $qualYellow, 'gray' => $qualGray],
                'empaque'     => ['green' => $pkgGreen,  'yellow' => $pkgYellow,  'gray' => $pkgGray],
            ];

            $areaStats['kit']['green']        += $kitGreen;
            $areaStats['kit']['yellow']       += $kitYellow;
            $areaStats['kit']['gray']         += $kitGray;
            $areaStats['kit']['total']        += $lotCount;

            $areaStats['inspeccion']['green']  += $inspGreen;
            $areaStats['inspeccion']['yellow'] += $inspYellow;
            $areaStats['inspeccion']['gray']   += $inspGray;
            $areaStats['inspeccion']['total']  += $lotCount;

            $areaStats['produccion']['green']  += $prodGreen;
            $areaStats['produccion']['yellow'] += $prodYellow;
            $areaStats['produccion']['gray']   += $prodGray;
            $areaStats['produccion']['total']  += $lotCount;

            $areaStats['calidad']['green']     += $qualGreen;
            $areaStats['calidad']['yellow']    += $qualYellow;
            $areaStats['calidad']['gray']      += $qualGray;
            $areaStats['calidad']['total']     += $lotCount;

            $areaStats['empaque']['green']     += $pkgGreen;
            $areaStats['empaque']['yellow']    += $pkgYellow;
            $areaStats['empaque']['gray']      += $pkgGray;
            $areaStats['empaque']['total']     += $lotCount;
        }

        return view('livewire.admin.sent-lists.tv-display', [
            'rows'      => $rows,
            'areaStats' => $areaStats,
        ])->layout('components.layouts.tv');
    }
}
