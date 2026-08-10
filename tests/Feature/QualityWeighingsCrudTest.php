<?php

namespace Tests\Feature;

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
 * Área de Calidad (/admin/quality/weighings): editar y eliminar pesadas
 * de calidad desde el modal de detalle del lote.
 */
class QualityWeighingsCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeLotWithWeighings(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $part = Part::factory()->create(['is_crimp' => false]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 100]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id'     => $wo->id,
            'lot_number'        => 'L-001',
            'quantity'          => 100,
            'status'            => Lot::STATUS_IN_PROGRESS,
            'material_status'   => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
        ]);

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 100,
            'good_pieces' => 100, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        $qw = QualityWeighing::create([
            'lot_id' => $lot->id, 'kit_id' => null,
            'production_good_pieces' => 100,
            'good_pieces' => 40, 'bad_pieces' => 0,
            'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        return [$lot, $qw];
    }

    public function test_editar_pesada_de_calidad_abre_el_modal_con_los_datos(): void
    {
        [$lot, $qw] = $this->makeLotWithWeighings();

        Livewire::test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->assertSet('showDetailModal', true)
            ->call('editQualityWeighing', $qw->id)
            ->assertSet('showWeighingModal', true)
            ->assertSet('editingQualityWeighingId', $qw->id)
            ->assertSet('qualGoodPieces', 40);
    }

    public function test_guardar_edicion_actualiza_la_pesada(): void
    {
        [$lot, $qw] = $this->makeLotWithWeighings();

        Livewire::test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('editQualityWeighing', $qw->id)
            ->set('qualGoodPieces', 55)
            ->call('saveQualityWeighing')
            ->assertHasNoErrors();

        $this->assertSame(55, $qw->fresh()->good_pieces);
    }

    public function test_eliminar_pesada_de_calidad(): void
    {
        [$lot, $qw] = $this->makeLotWithWeighings();

        Livewire::test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('deleteQualityWeighing', $qw->id);

        $this->assertSoftDeleted('quality_weighings', ['id' => $qw->id]);
    }

    public function test_eliminar_cierra_el_formulario_si_se_estaba_editando_esa_pesada(): void
    {
        [$lot, $qw] = $this->makeLotWithWeighings();

        Livewire::test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('editQualityWeighing', $qw->id)
            ->assertSet('showWeighingModal', true)
            ->call('deleteQualityWeighing', $qw->id)
            ->assertSet('showWeighingModal', false)
            ->assertSet('editingQualityWeighingId', null);
    }

    public function test_no_se_puede_tocar_una_pesada_de_otro_lote(): void
    {
        [$lotA, $qwA] = $this->makeLotWithWeighings();
        [$lotB, $qwB] = $this->makeLotWithWeighings();

        // Con el lote A abierto, un id del lote B no debe editarse ni borrarse.
        Livewire::test(QualityWeighings::class)
            ->call('openDetailModal', $lotA->id)
            ->call('editQualityWeighing', $qwB->id)
            ->assertSet('showWeighingModal', false)
            ->call('deleteQualityWeighing', $qwB->id);

        $this->assertNotSoftDeleted('quality_weighings', ['id' => $qwB->id]);
    }

    public function test_pesada_inexistente_avisa_en_vez_de_quedarse_muda(): void
    {
        [$lot, $qw] = $this->makeLotWithWeighings();
        $qw->delete();

        Livewire::test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('editQualityWeighing', $qw->id)
            ->assertSet('showWeighingModal', false)
            // El aviso se pinta en el mismo render, no se queda mudo.
            ->assertSee('Esa pesada de calidad ya no existe');
    }
}
