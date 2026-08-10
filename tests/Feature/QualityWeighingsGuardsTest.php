<?php

namespace Tests\Feature;

use App\Livewire\Admin\Quality\QualityWeighings;
use App\Models\Lot;
use App\Models\PackagingRecord;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pesadas de Calidad (paso 5): lo aprobado aquí es lo único que llega a
 * Empaque, así que no puede pasar de lo que Producción pesó ni bajar de lo que
 * Empaque ya consumió.
 */
class QualityWeighingsGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function inspector(): User
    {
        Role::findOrCreate('Calidad');
        $user = User::factory()->create(['name' => 'Carla Díaz']);
        $user->assignRole('Calidad');

        return $user;
    }

    /** Viajero con inspección aprobada y piezas ya pesadas por Producción. */
    private function viajeroProducido(User $user, int $producidas = 500, string $numero = 'V-1'): Lot
    {
        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 1000,
            'status' => Lot::STATUS_IN_PROGRESS, 'material_status' => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
        ]);

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => $producidas, 'good_pieces' => $producidas,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        return $lot;
    }

    public function test_la_pantalla_lista_lo_que_espera_verificacion(): void
    {
        $user = $this->inspector();
        $this->viajeroProducido($user, 500, 'V-9');

        $this->actingAs($user)->get(route('admin.quality.weighings'))->assertOk();

        $stats = Livewire::actingAs($user)->test(QualityWeighings::class)
            ->assertOk()
            ->assertSee('Pesadas de Calidad')
            ->assertSee('Lo que te toca ahora')
            ->assertSee('V-9')
            ->viewData('stats');

        $this->assertSame(1, $stats['pending']);
    }

    public function test_no_se_verifican_mas_piezas_de_las_que_produccion_peso(): void
    {
        $user = $this->inspector();
        $lot = $this->viajeroProducido($user, 500);

        // El tope se recalcula en el servidor: ya no vive en una propiedad que
        // el navegador pueda cambiar.
        Livewire::actingAs($user)->test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('openWeighingModal')
            ->set('qualGoodPieces', 400)
            ->set('qualBadPieces', 200)
            ->set('qualWeighedAt', now()->format('Y-m-d\TH:i'))
            ->call('saveQualityWeighing')
            ->assertHasErrors('qualGoodPieces');

        $this->assertSame(0, $lot->qualityWeighings()->count());
    }

    public function test_la_fecha_de_la_pesada_no_puede_ser_futura(): void
    {
        $user = $this->inspector();
        $lot = $this->viajeroProducido($user, 500);

        Livewire::actingAs($user)->test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('openWeighingModal')
            ->set('qualGoodPieces', 100)
            ->set('qualWeighedAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('saveQualityWeighing')
            ->assertHasErrors('qualWeighedAt');
    }

    public function test_no_se_registra_calidad_si_la_inspeccion_no_esta_aprobada(): void
    {
        $user = $this->inspector();
        $lot = $this->viajeroProducido($user, 500);
        $lot->update(['inspection_status' => Lot::INSPECTION_PENDING]);

        Livewire::actingAs($user)->test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('openWeighingModal')
            ->assertSet('showWeighingModal', false);

        $this->assertSame(0, $lot->qualityWeighings()->count());
    }

    public function test_no_se_baja_lo_aprobado_por_debajo_de_lo_que_empaque_ya_uso(): void
    {
        $user = $this->inspector();
        $lot = $this->viajeroProducido($user, 500);

        $qw = QualityWeighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'production_good_pieces' => 500,
            'good_pieces' => 400, 'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 400, 'packed_pieces' => 300,
            'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('editQualityWeighing', $qw->id)
            ->set('qualGoodPieces', 200)
            ->call('saveQualityWeighing')
            ->assertHasErrors('qualGoodPieces');

        $this->assertSame(400, $qw->fresh()->good_pieces);
    }

    public function test_no_se_borra_una_pesada_que_empaque_ya_consumio(): void
    {
        $user = $this->inspector();
        $lot = $this->viajeroProducido($user, 500);

        $qw = QualityWeighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'production_good_pieces' => 500,
            'good_pieces' => 400, 'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 400, 'packed_pieces' => 300,
            'surplus_pieces' => 50, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(QualityWeighings::class)
            ->call('openDetailModal', $lot->id)
            ->call('deleteQualityWeighing', $qw->id);

        $this->assertNotSoftDeleted('quality_weighings', ['id' => $qw->id]);
    }

    public function test_el_orden_solo_acepta_columnas_conocidas(): void
    {
        $user = $this->inspector();
        $this->viajeroProducido($user);

        Livewire::actingAs($user)->test(QualityWeighings::class)
            ->call('sortBy', 'qual_good_sum')
            ->assertSet('sortField', 'qual_good_sum')
            ->call('sortBy', 'lots.id; DROP TABLE lots')
            ->assertSet('sortField', 'qual_good_sum')
            ->assertOk();
    }

    public function test_el_listado_no_dispara_una_consulta_por_renglon(): void
    {
        $user = $this->inspector();

        foreach (range(1, 6) as $i) {
            $lot = $this->viajeroProducido($user, 500, 'V-'.$i);
            QualityWeighing::create([
                'lot_id' => $lot->id, 'kit_id' => null, 'production_good_pieces' => 500,
                'good_pieces' => 200, 'bad_pieces' => 10, 'weighed_at' => now(), 'weighed_by' => $user->id,
            ]);
        }

        DB::enableQueryLog();
        Livewire::actingAs($user)->test(QualityWeighings::class)->assertOk();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Las sumas viajan en la propia consulta (withSum). Antes cada renglón
        // llamaba a cinco métodos del modelo y cada uno lanzaba su propio SUM.
        $this->assertLessThan(30, $consultas, "El listado ejecutó {$consultas} consultas: volvió el N+1.");
    }
}
