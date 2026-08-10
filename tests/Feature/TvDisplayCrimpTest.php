<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\TvDisplay;
use App\Models\Lot;
use App\Models\PackagingPieceWeighing;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El monitor sumaba el empaque sólo desde `packagingRecords`, que es la tabla
 * del flujo NO-CRIMP: los viajeros CRIMP —que empacan con pesadas— salían en
 * cero. Y el semáforo de empaque se ponía verde con una lista fija de
 * decisiones de cierre, sin las de CRIMP.
 */
class TvDisplayCrimpTest extends TestCase
{
    use RefreshDatabase;

    private function crimpLotConEmpaque(int $piezas, ?string $closure = null): Lot
    {
        $user = User::factory()->create();
        $part = Part::factory()->create(['is_crimp' => true]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'V-' . fake()->unique()->numerify('#####'),
            'quantity' => 1000,
            'status' => Lot::STATUS_IN_PROGRESS,
            'material_status' => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
        ]);

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 1000, 'good_pieces' => 600,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        QualityWeighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'production_good_pieces' => 600,
            'good_pieces' => 600, 'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        PackagingPieceWeighing::create([
            'lot_id' => $lot->id, 'weight' => 12.5, 'quantity' => $piezas,
            'weighed_at' => now(), 'weighed_by' => $user->id,
        ]);

        if ($closure) {
            $lot->update([
                'closure_decision' => $closure,
                'closure_decided_by' => $user->id,
                'closure_decided_at' => now(),
            ]);
        }

        return $lot->fresh();
    }

    private function tarjetas(): array
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return Livewire::actingAs($admin)->test(TvDisplay::class)->viewData('woCards');
    }

    public function test_el_empaque_crimp_se_cuenta_en_el_monitor(): void
    {
        $this->crimpLotConEmpaque(400);

        $tarjeta = $this->tarjetas()[0];

        $this->assertTrue($tarjeta['is_crimp']);
        $this->assertSame(400, (int) $tarjeta['lots'][0]['packed'], 'Las piezas empacadas de CRIMP deben contarse.');
        // Con empaque en curso el semáforo va en ámbar, no en gris.
        $this->assertSame(1, $tarjeta['empaque']['yellow']);
        $this->assertSame(0, $tarjeta['empaque']['gray']);
    }

    public function test_una_decision_de_crimp_pone_el_empaque_en_verde(): void
    {
        $this->crimpLotConEmpaque(400, Lot::CLOSURE_COMPLETE_CRIMP);

        $tarjeta = $this->tarjetas()[0];

        $this->assertSame(1, $tarjeta['empaque']['green'], 'Completar CRIMP es una decisión de cierre válida.');
    }

    public function test_el_kpi_del_dia_incluye_lo_empacado_de_crimp(): void
    {
        $this->crimpLotConEmpaque(400);

        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $kpis = Livewire::actingAs($admin)->test(TvDisplay::class)->viewData('dayKpis');

        $this->assertSame(400, (int) $kpis['packed_today_total']);
    }
}
