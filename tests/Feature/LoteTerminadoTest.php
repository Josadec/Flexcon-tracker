<?php

namespace Tests\Feature;

use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackagingRecord;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 1 — una sola definición de «viajero terminado».
 *
 * Antes convivían tres nociones sueltas (`status`, `closure_decision` y
 * `ready_for_shipping`), escritas en trece sitios distintos, y un lote podía
 * estar en cualquier combinación de las tres. Ahora se resumen en
 * `completed_at`, que se mantiene solo desde el modelo.
 *
 * La regla es la que dio el cliente: «un lote se termina cuando se termina de
 * empacar, ya está listo para el shipping list».
 */
class LoteTerminadoTest extends TestCase
{
    use RefreshDatabase;

    private function viajero(bool $crimp = false, string $numero = 'L-1'): Lot
    {
        $part = Part::factory()->create(['is_crimp' => $crimp]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 500,
            'status' => Lot::STATUS_IN_PROGRESS, 'material_status' => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
        ]);

        if ($crimp) {
            CrimpLot::create([
                'lot_id' => $lot->id, 'crimp_lot_number' => 'CL-'.$numero, 'quantity' => 300,
            ]);
        }

        return $lot;
    }

    public function test_un_viajero_nuevo_no_esta_terminado(): void
    {
        $lot = $this->viajero();

        $this->assertFalse($lot->isFinished());
        $this->assertNull($lot->completed_at);
    }

    public function test_la_decision_de_cierre_de_empaque_lo_da_por_terminado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $lot = $this->viajero();
        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 480,
            'surplus_pieces' => 20, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);

        // D1: el observer de empaque lo manda a la cola de despacho.
        $lot->update(['closure_decision' => Lot::CLOSURE_CLOSE_AS_IS]);

        $lot->refresh();
        $this->assertTrue($lot->ready_for_shipping);
        $this->assertTrue($lot->isFinished(), 'Listo para el shipping list = terminado.');
        $this->assertSame($user->id, $lot->completed_by);
    }

    public function test_marcar_el_status_como_completado_tambien_lo_da_por_terminado(): void
    {
        $lot = $this->viajero();

        $lot->update(['status' => Lot::STATUS_COMPLETED]);

        $this->assertTrue($lot->fresh()->isFinished());
    }

    public function test_reabrir_un_viajero_borra_su_fecha_de_terminado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $lot = $this->viajero();
        PackagingRecord::create([
            'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 480,
            'surplus_pieces' => 20, 'packed_at' => now(), 'packed_by' => $user->id,
        ]);
        $lot->update(['closure_decision' => Lot::CLOSURE_CLOSE_AS_IS]);
        $this->assertTrue($lot->fresh()->isFinished());

        // Reapertura: se apagan las dos señales y el viajero vuelve al tablero.
        $lot->fresh()->update([
            'closure_decision' => null,
            'ready_for_shipping' => false,
            'ready_for_shipping_at' => null,
            'status' => Lot::STATUS_IN_PROGRESS,
        ]);

        $lot->refresh();
        $this->assertFalse($lot->isFinished());
        $this->assertNull($lot->completed_at);
        $this->assertNull($lot->completed_by);
    }

    public function test_no_hay_viajeros_en_la_cola_de_despacho_sin_fecha_de_terminado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['A', 'B', 'C'] as $n) {
            $lot = $this->viajero(false, 'L-'.$n);
            PackagingRecord::create([
                'lot_id' => $lot->id, 'available_pieces' => 500, 'packed_pieces' => 500,
                'surplus_pieces' => 0, 'packed_at' => now(), 'packed_by' => $user->id,
            ]);
            $lot->update(['closure_decision' => Lot::CLOSURE_COMPLETE_LOT]);
        }

        // Invariante sobre toda la tabla: si está listo para despacho, tiene
        // fecha de terminado. Es lo que impide que vuelvan a divergir.
        $incoherentes = Lot::where('ready_for_shipping', true)->whereNull('completed_at')->count();

        $this->assertSame(0, $incoherentes);
        $this->assertSame(3, Lot::finished()->count());
        $this->assertSame(0, Lot::open()->count());
    }

    public function test_los_scopes_separan_lo_abierto_de_lo_terminado(): void
    {
        $abierto = $this->viajero(false, 'L-ABIERTO');
        $cerrado = $this->viajero(false, 'L-CERRADO');
        $cerrado->update(['status' => Lot::STATUS_COMPLETED]);

        $this->assertSame(['L-ABIERTO'], Lot::open()->pluck('lot_number')->all());
        $this->assertSame(['L-CERRADO'], Lot::finished()->pluck('lot_number')->all());
    }

    public function test_los_estados_cerrados_de_wo_se_leen_del_catalogo(): void
    {
        StatusWO::factory()->create(['name' => StatusWO::COMPLETED]);
        StatusWO::factory()->create(['name' => StatusWO::CANCELLED]);
        $abierto = StatusWO::factory()->create(['name' => StatusWO::OPEN]);

        $cerrados = StatusWO::closedIds();

        $this->assertCount(2, $cerrados);
        $this->assertNotContains($abierto->id, $cerrados);
    }
}
