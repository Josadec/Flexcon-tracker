<?php

namespace Tests\Feature;

use App\Livewire\Admin\Production\WeighingManagement;
use App\Livewire\Admin\Quality\QualityWeighings;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M4 / M5 — Pesadas de Producción y Calidad: para CRIMP ya no se selecciona Kit.
 * Las pesadas nuevas se registran a nivel viajero con kit_id = null.
 */
class CrimpWeighingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCrimpLot(): Lot
    {
        $part = Part::factory()->create(['is_crimp' => true]);

        $po = PurchaseOrder::factory()->approved()->create([
            'part_id'  => $part->id,
            'quantity' => 1000,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);

        // El viajero llega a Producción con material liberado e inspección
        // aprobada: es el único estado en el que se puede pesar.
        return Lot::create([
            'work_order_id'     => $wo->id,
            'lot_number'        => 'V-'.fake()->unique()->numerify('#####'),
            'quantity'          => 1000,
            'status'            => Lot::STATUS_PENDING,
            'material_status'   => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
        ]);
    }

    /** M4: una pesada de producción nueva sobre un viajero CRIMP se guarda con kit_id null. */
    public function test_production_weighing_for_crimp_has_null_kit(): void
    {
        $this->actingAs(User::factory()->create());
        $lot = $this->makeCrimpLot();

        Livewire::test(WeighingManagement::class)
            ->call('openCreateModal')
            ->set('selectedLotId', $lot->id)
            ->set('formWeighedPieces', 250)
            ->set('formWeighedAt', now()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $weighing = Weighing::where('lot_id', $lot->id)->firstOrFail();
        $this->assertNull($weighing->kit_id, 'La pesada de producción CRIMP no debe llevar kit_id');
        $this->assertSame(250, $weighing->good_pieces);
    }

    /** M5: una pesada de calidad nueva sobre un viajero CRIMP se guarda con kit_id null. */
    public function test_quality_weighing_for_crimp_has_null_kit(): void
    {
        $this->actingAs(User::factory()->create());
        $lot = $this->makeCrimpLot();

        // Calidad requiere una pesada de producción previa (define las piezas pendientes).
        Weighing::create([
            'lot_id'      => $lot->id,
            'kit_id'      => null,
            'quantity'    => 1000,
            'good_pieces' => 300,
            'bad_pieces'  => 0,
            'weighed_at'  => now(),
            'weighed_by'  => auth()->id(),
        ]);

        Livewire::test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('openWeighingModal')
            ->set('qualGoodPieces', 300)
            ->set('qualBadPieces', 0)
            ->set('qualWeighedAt', now()->format('Y-m-d\TH:i'))
            ->call('saveQualityWeighing')
            ->assertHasNoErrors();

        $qw = QualityWeighing::where('lot_id', $lot->id)->firstOrFail();
        $this->assertNull($qw->kit_id, 'La pesada de calidad CRIMP no debe llevar kit_id');
        $this->assertSame(300, $qw->good_pieces);
    }
}
