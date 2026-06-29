<?php

namespace App\Livewire\Admin\SentLists;

use App\Models\WorkOrder;
use Livewire\Component;

/**
 * Vista aislada de RESUMEN de un Work Order:
 * trae los viajeros abiertos, su cantidad, sus lotes de CRIMP asociados, y por cada
 * nivel: total · empacadas · sobrantes · faltantes · estado (completado / en proceso / no iniciado).
 */
class WoResume extends Component
{
    public WorkOrder $workOrder;

    public function mount(WorkOrder $workOrder): void
    {
        $this->workOrder = $workOrder;
    }

    /**
     * Estado de avance a partir del empaque.
     */
    private function estado(?string $closure, int $anyPacked, int $empacadas, int $total): string
    {
        if ($closure !== null) {
            return 'completado';
        }
        if ($total > 0 && $empacadas >= $total) {
            return 'completado';
        }
        if ($anyPacked > 0) {
            return 'en_proceso';
        }
        return 'no_iniciado';
    }

    public function render()
    {
        $this->workOrder->load([
            'purchaseOrder.part',
            'lots' => fn ($q) => $q->orderBy('lot_number'),
            'lots.crimpLots.packagingPieceWeighings',
            'lots.crimpLots.packagingCrimpWeighings',
        ]);

        $part    = $this->workOrder->purchaseOrder?->part;
        $isCrimp = (bool) ($part?->is_crimp ?? false);

        $viajeros = $this->workOrder->lots->map(function ($lot) {
            $total     = (int) $lot->quantity;
            $empacadas = $lot->getPackagedPiecesTotal();
            $crimpEmp  = $lot->getPackagedCrimpTotal();

            $crimpLots = $lot->crimpLots->map(function ($cl) {
                $t  = (int) $cl->quantity;
                $e  = $cl->getPackagedPiecesTotal();
                $ce = $cl->getPackagedCrimpTotal();

                return [
                    'number'    => $cl->crimp_lot_number,
                    'fab'       => $cl->lote_fabricante,
                    'total'     => $t,
                    'empacadas' => $e,
                    'crimp_emp' => $ce,
                    'sobrantes' => max(0, $e - $t),
                    'faltantes' => max(0, $t - $e),
                    'estado'    => $this->estado(null, $e + $ce, $e, $t),
                ];
            })->toArray();

            return [
                'number'     => $lot->lot_number,
                'total'      => $total,
                'empacadas'  => $empacadas,
                'crimp_obj'  => $lot->getCrimpTargetTotal(),
                'crimp_emp'  => $crimpEmp,
                'sobrantes'  => max(0, $empacadas - $total),
                'faltantes'  => max(0, $total - $empacadas),
                'estado'     => $this->estado($lot->closure_decision, $empacadas + $crimpEmp, $empacadas, $total),
                'crimp_lots' => $crimpLots,
            ];
        })->toArray();

        $totals = [
            'viajeros'  => count($viajeros),
            'total'     => array_sum(array_column($viajeros, 'total')),
            'empacadas' => array_sum(array_column($viajeros, 'empacadas')),
            'sobrantes' => array_sum(array_column($viajeros, 'sobrantes')),
            'faltantes' => array_sum(array_column($viajeros, 'faltantes')),
            'completados' => count(array_filter($viajeros, fn ($v) => $v['estado'] === 'completado')),
            'en_proceso'  => count(array_filter($viajeros, fn ($v) => $v['estado'] === 'en_proceso')),
            'no_iniciado' => count(array_filter($viajeros, fn ($v) => $v['estado'] === 'no_iniciado')),
        ];

        return view('livewire.admin.sent-lists.wo-resume', [
            'part'     => $part,
            'isCrimp'  => $isCrimp,
            'viajeros' => $viajeros,
            'totals'   => $totals,
            'woNum'    => $this->workOrder->purchaseOrder?->wo ?? $this->workOrder->wo_number ?? ('#' . $this->workOrder->id),
        ])->layout('components.layouts.app');
    }
}
