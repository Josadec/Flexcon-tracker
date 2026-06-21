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
