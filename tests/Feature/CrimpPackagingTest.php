<?php

namespace Tests\Feature;

use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6 — Empaque CRIMP: pesadas separadas de piezas y CRIMP a nivel viajero.
 * Verifica los cálculos de completadas y sobrantes (decisión B.4: calculados).
 */
class CrimpPackagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_crimp_packaging_totals_and_surplus(): void
    {
        $user = User::factory()->create();
        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);

        $viajero = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'V-1',
            'quantity'      => 1000,
            'status'        => Lot::STATUS_PENDING,
        ]);

        // Objetivo CRIMP = suma de lotes de CRIMP = 400 + 600 = 1000
        CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 400]);
        CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-2', 'quantity' => 600]);

        // Disponibles (Calidad) = 800
        QualityWeighing::create([
            'lot_id' => $viajero->id, 'production_good_pieces' => 800, 'good_pieces' => 800, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        // Piezas empacadas = 300 + 200 = 500
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'quantity' => 300, 'weighed_at' => now(), 'weighed_by' => $user->id]);
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'quantity' => 200, 'weighed_at' => now(), 'weighed_by' => $user->id]);

        // CRIMP empacados = 700
        PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'quantity' => 700, 'weighed_at' => now(), 'weighed_by' => $user->id]);

        $viajero->refresh();

        $this->assertSame(500, $viajero->getPackagedPiecesTotal());
        $this->assertSame(700, $viajero->getPackagedCrimpTotal());
        $this->assertSame(1000, $viajero->getCrimpTargetTotal());
        // Sobrante piezas = disponibles 800 − empacadas 500 = 300
        $this->assertSame(300, $viajero->getPackagedPiecesSurplus());
        // Sobrante CRIMP = objetivo 1000 − empacados 700 = 300
        $this->assertSame(300, $viajero->getPackagedCrimpSurplus());
    }

    public function test_crimp_shortfall_totals_use_viajero_objective(): void
    {
        $user = User::factory()->create();
        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);

        $viajero = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'V-3',
            'quantity'      => 1000,
            'status'        => Lot::STATUS_PENDING,
        ]);

        // Objetivo del viajero = suma de lotes de CRIMP = 400 + 600 = 1000
        CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 400]);
        CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-2', 'quantity' => 600]);

        // Disponibles (Calidad) = 800 — NO es la referencia del faltante.
        QualityWeighing::create([
            'lot_id' => $viajero->id, 'production_good_pieces' => 800, 'good_pieces' => 800, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        // Empacadas: manguitas 300+200 = 500 ; CRIMP 700
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'quantity' => 300, 'weighed_at' => now(), 'weighed_by' => $user->id]);
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'quantity' => 200, 'weighed_at' => now(), 'weighed_by' => $user->id]);
        PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'quantity' => 700, 'weighed_at' => now(), 'weighed_by' => $user->id]);

        $viajero->refresh();

        // Faltante manguitas = objetivo 1000 − empacadas 500 = 500
        // (difiere del sobrante 300, que usa el disponible de Calidad 800)
        $this->assertSame(500, $viajero->getPiecesShortfallTotal());
        // Faltante CRIMP = objetivo 1000 − empacados 700 = 300
        $this->assertSame(300, $viajero->getCrimpShortfallTotal());
    }

    public function test_packed_totals_aggregate_across_all_crimp_lots(): void
    {
        $user = User::factory()->create();
        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);
        $viajero = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-5', 'quantity' => 1000, 'status' => Lot::STATUS_PENDING,
        ]);

        $cl1 = CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 400]);
        $cl2 = CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-2', 'quantity' => 600]);

        // Manguitas: 300 en CL-1 + 200 en CL-2 -> el total del viajero debe ser 500 (no 300 ni 200).
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 300, 'weighed_at' => now(), 'weighed_by' => $user->id]);
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'crimp_lot_id' => $cl2->id, 'quantity' => 200, 'weighed_at' => now(), 'weighed_by' => $user->id]);
        // CRIMP: 150 en CL-1 + 250 en CL-2 -> total viajero 400.
        PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'crimp_lot_id' => $cl1->id, 'quantity' => 150, 'weighed_at' => now(), 'weighed_by' => $user->id]);
        PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'crimp_lot_id' => $cl2->id, 'quantity' => 250, 'weighed_at' => now(), 'weighed_by' => $user->id]);

        $viajero->refresh();

        // Los stats del Paso 3 usan estos helpers a nivel viajero.
        $this->assertSame(500, $viajero->getPackagedPiecesTotal());
        $this->assertSame(400, $viajero->getPackagedCrimpTotal());
        // Faltante coherente: objetivo 1000 − 500 / 1000 − 400.
        $this->assertSame(500, $viajero->getPiecesShortfallTotal());
        $this->assertSame(600, $viajero->getCrimpShortfallTotal());
    }

    public function test_shortfall_totals_never_go_negative_when_overpacked(): void
    {
        $user = User::factory()->create();
        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 500]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);
        $viajero = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-4', 'quantity' => 500, 'status' => Lot::STATUS_PENDING,
        ]);

        CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 500]);

        // Empacado por encima del objetivo -> faltante = 0, nunca negativo.
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'quantity' => 620, 'weighed_at' => now(), 'weighed_by' => $user->id]);
        PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'quantity' => 500, 'weighed_at' => now(), 'weighed_by' => $user->id]);

        $viajero->refresh();

        $this->assertSame(0, $viajero->getPiecesShortfallTotal());
        $this->assertSame(0, $viajero->getCrimpShortfallTotal());
    }

    public function test_piece_and_crimp_weighings_belong_to_viajero(): void
    {
        $user = User::factory()->create();
        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 500]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);
        $viajero = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-2', 'quantity' => 500, 'status' => Lot::STATUS_PENDING,
        ]);

        $pw = PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'quantity' => 100, 'weight' => 12.5, 'weighed_at' => now(), 'weighed_by' => $user->id]);
        $cw = PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'quantity' => 100, 'weighed_at' => now(), 'weighed_by' => $user->id]);

        $this->assertTrue($viajero->packagingPieceWeighings->contains($pw));
        $this->assertTrue($viajero->packagingCrimpWeighings->contains($cw));
        $this->assertSame($viajero->id, $pw->lot->id);
        $this->assertSame('12.500', (string) $pw->weight);
        $this->assertNull($cw->weight);
    }
}
