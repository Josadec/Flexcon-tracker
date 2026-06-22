<?php

namespace App\Services;

use App\Models\CrimpLot;
use App\Models\Kit;
use App\Models\Lot;
use App\Models\PackagingRecord;
use App\Models\PackingSlip;
use App\Models\QualityWeighing;
use App\Models\Weighing;
use App\Models\WorkOrder;

class ReportService
{
    // =========================================================
    // PRODUCCIÓN
    // Filtro: weighing.weighed_at entre start_date y end_date
    // =========================================================

    public function getProduccionReport(?string $startDate, ?string $endDate): array
    {
        $query = Weighing::with(['lot.workOrder', 'kit', 'weighedBy'])
            ->orderBy('weighed_at', 'desc');

        if ($startDate && $endDate) {
            $query->whereBetween('weighed_at', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ]);
        }

        $weighings = $query->get();

        $totalPiezas      = $weighings->sum('quantity');
        $totalBuenas      = $weighings->sum('good_pieces');
        $totalMalas       = $weighings->sum('bad_pieces');
        $totalRegistros   = $weighings->count();
        $lotesAfectados   = $weighings->pluck('lot_id')->unique()->count();

        return [
            'department'    => 'Producción',
            'weighings'     => $weighings,
            'stats' => [
                'total_registros'    => $totalRegistros,
                'total_piezas'       => $totalPiezas,
                'total_buenas'       => $totalBuenas,
                'total_malas'        => $totalMalas,
                'lotes_afectados'    => $lotesAfectados,
                'tasa_calidad'       => $totalPiezas > 0
                    ? round(($totalBuenas / $totalPiezas) * 100, 1)
                    : 0,
            ],
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'generated_at'  => now()->format('d/m/Y H:i'),
        ];
    }

    // =========================================================
    // MATERIALES
    // Filtro: lot.receipt_date (viajeros) y crimp_lots.created_at
    // =========================================================

    public function getMaterialesReport(?string $startDate, ?string $endDate): array
    {
        $lotsQuery = Lot::with(['workOrder'])
            ->orderBy('receipt_date', 'desc');

        // Flujo CRIMP: los lotes de CRIMP cuelgan del viajero (ya no se usan Kits).
        $crimpLotsQuery = CrimpLot::with(['lot.workOrder'])
            ->orderBy('created_at', 'desc');

        if ($startDate && $endDate) {
            $lotsQuery->whereBetween('receipt_date', [$startDate, $endDate]);
            $crimpLotsQuery->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ]);
        }

        $lots      = $lotsQuery->get();
        $crimpLots = $crimpLotsQuery->get();

        $lotsByMaterialStatus = $lots->groupBy('material_status');

        return [
            'department'        => 'Materiales',
            'lots'              => $lots,
            'crimp_lots'        => $crimpLots,
            'stats' => [
                'total_lotes'            => $lots->count(),
                'lotes_pendientes'       => $lotsByMaterialStatus->get('pending',  collect())->count(),
                'lotes_liberados'        => $lotsByMaterialStatus->get('released', collect())->count(),
                'lotes_rechazados'       => $lotsByMaterialStatus->get('rejected', collect())->count(),
                'total_crimp_lots'       => $crimpLots->count(),
                'total_crimp_piezas'     => (int) $crimpLots->sum('quantity'),
            ],
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'generated_at'      => now()->format('d/m/Y H:i'),
        ];
    }

    // =========================================================
    // CALIDAD
    // Filtro: quality_weighing.weighed_at
    // =========================================================

    public function getCalidadReport(?string $startDate, ?string $endDate): array
    {
        $query = QualityWeighing::with(['lot.workOrder', 'kit', 'weighedBy'])
            ->orderBy('weighed_at', 'desc');

        if ($startDate && $endDate) {
            $query->whereBetween('weighed_at', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ]);
        }

        $registros = $query->get();

        $totalBuenas    = $registros->sum('good_pieces');
        $totalMalas     = $registros->sum('bad_pieces');
        $totalInspeccion = $registros->sum(fn($r) => $r->good_pieces + $r->bad_pieces);

        $byDisposition = $registros->whereNotNull('disposition')->groupBy('disposition');
        $byRework      = $registros->whereNotNull('rework_status')->groupBy('rework_status');

        return [
            'department' => 'Calidad',
            'registros'  => $registros,
            'stats' => [
                'total_registros'       => $registros->count(),
                'total_inspeccionadas'  => $totalInspeccion,
                'total_buenas'          => $totalBuenas,
                'total_malas'           => $totalMalas,
                'lotes_afectados'       => $registros->pluck('lot_id')->unique()->count(),
                'tasa_aprobacion'       => $totalInspeccion > 0
                    ? round(($totalBuenas / $totalInspeccion) * 100, 1)
                    : 0,
                'para_scrap'            => $byDisposition->get('scrap',  collect())->sum('bad_pieces'),
                'para_rework'           => $byDisposition->get('rework', collect())->sum('bad_pieces'),
                'rework_pendiente'      => $byRework->get(QualityWeighing::REWORK_PENDING,     collect())->count(),
                'rework_en_proceso'     => $byRework->get(QualityWeighing::REWORK_IN_PROGRESS, collect())->count(),
                'rework_completado'     => $byRework->get(QualityWeighing::REWORK_COMPLETE,    collect())->count(),
            ],
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'generated_at'  => now()->format('d/m/Y H:i'),
        ];
    }

    // =========================================================
    // EMPAQUES
    // Filtro: packaging_record.packed_at
    //         packing_slip.document_date
    // =========================================================

    public function getEmpaquesReport(?string $startDate, ?string $endDate): array
    {
        $packingQuery = PackagingRecord::with(['lot.workOrder', 'kit', 'packedBy'])
            ->orderBy('packed_at', 'desc');

        $slipsQuery = PackingSlip::with(['items.lot', 'invoice'])
            ->orderBy('document_date', 'desc');

        if ($startDate && $endDate) {
            $packingQuery->whereBetween('packed_at', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ]);
            $slipsQuery->whereBetween('document_date', [$startDate, $endDate]);
        }

        $records = $packingQuery->get();
        $slips   = $slipsQuery->get();

        $slipsByStatus = $slips->groupBy('status');

        return [
            'department'    => 'Empaques',
            'records'       => $records,
            'slips'         => $slips,
            'stats' => [
                'total_registros'        => $records->count(),
                'total_disponibles'      => $records->sum('available_pieces'),
                'total_empacadas'        => $records->sum('packed_pieces'),
                'total_sobrante'         => $records->sum('surplus_pieces'),
                'lotes_procesados'       => $records->pluck('lot_id')->unique()->count(),
                'total_packing_slips'    => $slips->count(),
                'ps_borradores'          => $slipsByStatus->get(PackingSlip::STATUS_DRAFT,     collect())->count(),
                'ps_pendientes'          => $slipsByStatus->get(PackingSlip::STATUS_PENDING,   collect())->count(),
                'ps_despachados'         => $slipsByStatus->get(PackingSlip::STATUS_SHIPPED,   collect())->count(),
                'ps_cancelados'          => $slipsByStatus->get(PackingSlip::STATUS_CANCELLED, collect())->count(),
            ],
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'generated_at'  => now()->format('d/m/Y H:i'),
        ];
    }

    // =========================================================
    // GENERAL
    // Resumen de los 4 departamentos en el mismo período
    // =========================================================

    public function getGeneralReport(?string $startDate, ?string $endDate): array
    {
        return [
            'department'   => 'General',
            'produccion'   => $this->getProduccionReport($startDate, $endDate),
            'materiales'   => $this->getMaterialesReport($startDate, $endDate),
            'calidad'      => $this->getCalidadReport($startDate, $endDate),
            'empaques'     => $this->getEmpaquesReport($startDate, $endDate),
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }
}
