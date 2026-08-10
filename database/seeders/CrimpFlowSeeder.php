<?php

namespace Database\Seeders;

use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PackagingRecord;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

/**
 * Seeder de PRÁCTICA para el flujo CRIMP (Sent List → Empaque → toma de decisión).
 *
 * Crea una Lista de envío demo, en el departamento de Empaque, con varios viajeros
 * en distintos estados para practicar los modales Paso 5 (confirmación) y Paso 6
 * (toma de decisión), tanto en la vista de Empaque (/admin/sent-lists/{id}, tab
 * "Empaque") como en el tablero (/admin/sent-lists/display).
 *
 * Usa números de parte REALES de la base de datos. Es idempotente: borra su propia
 * data demo anterior (marcada con po_number "CRIMP-DEMO-*" y notes "CRIMP_DEMO_SEED").
 *
 *   php artisan db:seed --class=CrimpFlowSeeder
 */
class CrimpFlowSeeder extends Seeder
{
    public function run(): void
    {
        $this->cleanPrevious();

        $user      = User::query()->first() ?? User::factory()->create();
        $statusId  = StatusWO::where('name', 'In Progress')->value('id')
            ?? StatusWO::query()->value('id')
            ?? StatusWO::factory()->create()->id;

        // Números de parte reales (con fallback por si no existen en esta BD).
        $crimpParts = $this->pickParts(true,  ['189-10466', '189-10467', '189-10468'], 3);
        $plainPart  = $this->pickParts(false, ['189-10004'], 1)->first();

        // ── Lista de envío demo, ya en Empaque ──────────────────────────────
        $mainPo   = $this->makePo($crimpParts[0], 'CRIMP-DEMO-MAIN', '2053940', 20000);
        $sentList = SentList::create([
            'po_id'                 => $mainPo->id,
            'status'                => SentList::STATUS_PENDING,
            'current_department'    => SentList::DEPT_SHIPPING, // Empaque
            'shift_ids'             => [],
            'num_persons'           => 2,
            'start_date'            => now()->startOfWeek()->toDateString(),
            'end_date'              => now()->endOfWeek()->toDateString(),
            'total_available_hours' => 80,
            'used_hours'            => 40,
            'remaining_hours'       => 40,
            'materials_approved_at' => now()->subDays(4),
            'materials_approved_by' => $user->id,
            'inspection_approved_at'=> now()->subDays(3),
            'inspection_approved_by'=> $user->id,
            'production_approved_at'=> now()->subDays(2),
            'production_approved_by'=> $user->id,
            'quality_approved_at'   => now()->subDay(),
            'quality_approved_by'   => $user->id,
            'notes'                 => 'CRIMP_DEMO_SEED',
        ]);

        // ── Escenario A — CRIMP listo para EMPACAR (practica Paso 5) ────────
        // Viajero con 2 lotes de CRIMP (muestra "División del lote"); calidad
        // disponible; SIN pesadas de empaque todavía.
        $woA = $this->makeWo($mainPo, $sentList, $statusId);
        $vA  = $this->makeViajero($woA, $crimpParts[0], 'V-A1', 20000, $user, qualityGood: 1840);
        CrimpLot::create(['lot_id' => $vA->id, 'crimp_lot_number' => '009', 'lote_fabricante' => 'LF-2231', 'quantity' => 18800]);
        CrimpLot::create(['lot_id' => $vA->id, 'crimp_lot_number' => '010', 'lote_fabricante' => 'LF-2232', 'quantity' => 1200]);

        // ── Escenario B — CRIMP empacado con SOBRANTE y FALTANTE (practica Paso 6) ──
        // disponibles 900, empacadas 600 → sobrante piezas 300, faltante 100.
        // CRIMP objetivo 1000, empacado 800 → sobrante CRIMP 200.
        // Completar CRIMP = 300 − 200 = 100.
        $poB = $this->makePo($crimpParts[1], 'CRIMP-DEMO-B', '2053941', 1000);
        $woB = $this->makeWo($poB, $sentList, $statusId);
        $vB  = $this->makeViajero($woB, $crimpParts[1], 'V-B1', 1000, $user, qualityGood: 900);
        $clB1 = CrimpLot::create(['lot_id' => $vB->id, 'crimp_lot_number' => '021', 'lote_fabricante' => 'LF-3001', 'quantity' => 600]);
        $clB2 = CrimpLot::create(['lot_id' => $vB->id, 'crimp_lot_number' => '022', 'lote_fabricante' => 'LF-3002', 'quantity' => 400]);
        // Pesadas de piezas ("manguitas") = 600
        $this->pieceWeighing($vB, $clB1, 350, 0.420, $user);
        $this->pieceWeighing($vB, $clB1, 250, 0.300, $user);
        // Pesadas de CRIMP = 800 (450 + 350)
        $this->crimpWeighing($vB, $clB1, 450, 0.180, $user);
        $this->crimpWeighing($vB, $clB2, 350, 0.140, $user);

        // ── Escenario C — FLUJO COMPLETO (cuadrado, cerrado y notificado) ──
        $poC = $this->makePo($crimpParts[2], 'CRIMP-DEMO-C', '2053942', 1000);
        $woC = $this->makeWo($poC, $sentList, $statusId);
        $vC  = $this->makeViajero($woC, $crimpParts[2], 'V-C1', 1000, $user, qualityGood: 1000, extra: [
            'closure_decision'      => Lot::CLOSURE_CLOSE_AS_IS,
            'closure_decided_by'    => $user->id,
            'closure_decided_at'    => now()->subHours(2),
            'viajero_received'      => true,
            'viajero_received_at'   => now()->subHours(1),
            'viajero_received_by'   => $user->id,
            'packaging_status'      => 'approved',
            'packaging_label_count' => 12,
            'packaging_notified_at' => now()->subHours(1),
            'packaging_notified_by' => $user->id,
            'status'                => Lot::STATUS_COMPLETED,
        ]);
        $clC = CrimpLot::create(['lot_id' => $vC->id, 'crimp_lot_number' => '030', 'lote_fabricante' => 'LF-4000', 'quantity' => 1000]);
        $this->pieceWeighing($vC, $clC, 1000, 0.700, $user);
        $this->crimpWeighing($vC, $clC, 1000, 0.450, $user);

        // ── Escenario D — NO CRIMP (contraste, empaque por registro) ────────
        if ($plainPart) {
            $poD = $this->makePo($plainPart, 'CRIMP-DEMO-D', '2053943', 500);
            $woD = $this->makeWo($poD, $sentList, $statusId);
            $vD  = $this->makeViajero($woD, $plainPart, 'V-D1', 500, $user, qualityGood: 500);
            PackagingRecord::create([
                'lot_id'           => $vD->id,
                'available_pieces' => 500,
                'packed_pieces'    => 480,
                'surplus_pieces'   => 20,
                'packed_at'        => now()->subHours(3),
                'packed_by'        => $user->id,
                'comments'         => 'Empaque demo no-CRIMP.',
            ]);
        }

        $this->command?->info('CRIMP demo creado. Lista de envío #'.$sentList->id.' (Empaque).');
        $this->command?->info('  • Vista Empaque:  /admin/sent-lists/'.$sentList->id.'  (tab "Empaque")');
        $this->command?->info('  • Tablero (Mesa): /admin/sent-lists/display/sl/'.$sentList->id);
        $this->command?->info('  A=listo para empacar (Paso 5) · B=para decidir (Paso 6) · C=flujo completo · D=no-CRIMP.');
    }

    /** Borra la data demo previa de este seeder. */
    private function cleanPrevious(): void
    {
        $poIds  = PurchaseOrder::withTrashed()->where('po_number', 'like', 'CRIMP-DEMO-%')->pluck('id');
        $woIds  = WorkOrder::withTrashed()->whereIn('purchase_order_id', $poIds)->pluck('id');
        $lotIds = Lot::withTrashed()->whereIn('work_order_id', $woIds)->pluck('id');

        CrimpLot::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        PackagingPieceWeighing::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        PackagingCrimpWeighing::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        PackagingRecord::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        QualityWeighing::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        Lot::withTrashed()->whereIn('id', $lotIds)->forceDelete();
        SentList::withTrashed()->where('notes', 'CRIMP_DEMO_SEED')->forceDelete();
        WorkOrder::withTrashed()->whereIn('id', $woIds)->forceDelete();
        PurchaseOrder::withTrashed()->whereIn('id', $poIds)->forceDelete();
    }

    /** Toma partes reales por número; si faltan, cae a las primeras del tipo pedido. */
    private function pickParts(bool $isCrimp, array $numbers, int $count)
    {
        $parts = Part::whereIn('number', $numbers)->where('is_crimp', $isCrimp)->get();
        if ($parts->count() < $count) {
            $parts = Part::where('is_crimp', $isCrimp)->orderBy('id')->limit($count)->get();
        }
        if ($parts->isEmpty()) {
            $parts = collect([Part::factory()->create(['is_crimp' => $isCrimp])]);
        }
        return $parts->values();
    }

    private function makePo(Part $part, string $poNumber, string $woNumber, int $qty): PurchaseOrder
    {
        // firstOrCreate y no create(): el po_number es único entre POs activos,
        // así que reejecutar el seeder sin limpiar antes reventaría el insert.
        return PurchaseOrder::firstOrCreate(
            ['po_number' => $poNumber],
            [
                'wo'         => $woNumber,
                'part_id'    => $part->id,
                'po_date'    => now()->subDays(10),
                'due_date'   => now()->addDays(20),
                'quantity'   => $qty,
                'unit_price' => 1.50,
                'status'     => PurchaseOrder::STATUS_APPROVED,
                'comments'   => 'Demo CRIMP seeder.',
            ]
        );
    }

    private function makeWo(PurchaseOrder $po, SentList $sentList, int $statusId): WorkOrder
    {
        return WorkOrder::create([
            'wo_number'         => WorkOrder::generateWONumber(),
            'purchase_order_id' => $po->id,
            'sent_list_id'      => $sentList->id,
            'status_id'         => $statusId,
            'sent_pieces'       => 0,
            'opened_date'       => now()->subDays(5),
        ]);
    }

    /** Crea un viajero (Lot) liberado, inspeccionado y con disponible de calidad. */
    private function makeViajero(WorkOrder $wo, Part $part, string $lotNumber, int $qty, User $user, int $qualityGood, array $extra = []): Lot
    {
        $lot = Lot::create(array_merge([
            'work_order_id'           => $wo->id,
            'lot_number'              => $lotNumber,
            'description'             => $part->description,
            'quantity'                => $qty,
            'status'                  => Lot::STATUS_IN_PROGRESS,
            'material_status'         => 'released',
            'inspection_status'       => Lot::INSPECTION_APPROVED,
            'inspection_completed_at' => now()->subDays(3),
            'inspection_completed_by' => $user->id,
        ], $extra));

        if ($qualityGood > 0) {
            QualityWeighing::create([
                'lot_id'                 => $lot->id,
                'production_good_pieces' => $qualityGood,
                'good_pieces'            => $qualityGood,
                'bad_pieces'             => 0,
                'weighed_at'             => now()->subDays(2),
                'weighed_by'             => $user->id,
            ]);
        }

        return $lot->refresh();
    }

    private function pieceWeighing(Lot $lot, ?CrimpLot $cl, int $qty, float $weight, User $user): void
    {
        PackagingPieceWeighing::create([
            'lot_id'       => $lot->id,
            'crimp_lot_id' => $cl?->id,
            'quantity'     => $qty,
            'weight'       => $weight,
            'weighed_at'   => now()->subHour(),
            'weighed_by'   => $user->id,
        ]);
    }

    private function crimpWeighing(Lot $lot, ?CrimpLot $cl, int $qty, float $weight, User $user): void
    {
        PackagingCrimpWeighing::create([
            'lot_id'       => $lot->id,
            'crimp_lot_id' => $cl?->id,
            'quantity'     => $qty,
            'weight'       => $weight,
            'weighed_at'   => now()->subHour(),
            'weighed_by'   => $user->id,
        ]);
    }
}
