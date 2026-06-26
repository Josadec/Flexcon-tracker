<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reordenamiento del lifecycle post-calidad (Lot::getPostQualityLifecycle).
 *
 * CRIMP:    Empaque → Decisión (Materiales) → Entrega de viajero (Empaque) → Sobrantes.
 * NO-CRIMP: Empaque → Entrega de viajero (Empaque) → Decisión (Materiales) → Sobrantes (flujo ORIGINAL).
 *
 * El punto crítico es QUÉ fase queda "pending" primero tras el empaque: en CRIMP decide
 * Materiales antes de que Empaque entregue el viajero; en NO-CRIMP es al revés. Esta regresión
 * bloquea cualquier cambio que vuelva a igualar ambos órdenes.
 */
class CrimpLifecycleOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un viajero/lote. Con $availablePieces > 0 simula empaque disponible
     * (piezas aprobadas por Calidad), que es lo que abre la primera fase del lifecycle.
     */
    private function makeLot(bool $isCrimp, int $availablePieces = 0, array $attrs = []): Lot
    {
        $part = Part::factory()->create(['is_crimp' => $isCrimp]);

        $po = PurchaseOrder::factory()->approved()->create([
            'part_id'  => $part->id,
            'quantity' => 1000,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);

        $lot = Lot::create(array_merge([
            'work_order_id' => $wo->id,
            'lot_number'    => 'L-'.fake()->unique()->numerify('#####'),
            'quantity'      => 1000,
            'status'        => Lot::STATUS_PENDING,
        ], $attrs));

        if ($availablePieces > 0) {
            QualityWeighing::create([
                'lot_id'                 => $lot->id,
                'production_good_pieces' => $availablePieces,
                'good_pieces'            => $availablePieces,
                'bad_pieces'             => 0,
                'weighed_at'             => now(),
                'weighed_by'             => User::factory()->create()->id,
            ]);
        }

        return $lot->refresh();
    }

    /**
     * CRIMP: tras el empaque, la primera acción pendiente es la DECISIÓN de Materiales;
     * la entrega del viajero queda en espera de esa decisión.
     */
    public function test_crimp_decision_precedes_viajero_delivery(): void
    {
        $lot = $this->makeLot(isCrimp: true, availablePieces: 800);

        $lifecycle = $lot->getPostQualityLifecycle();

        $this->assertSame('pending', $lifecycle['decision']['state']);
        $this->assertSame('Materiales', $lifecycle['decision']['actor']);
        $this->assertSame('idle', $lifecycle['viajero']['state']);

        $next = $lot->getNextPendingAction();
        $this->assertSame('decision', $next['phase']);
        $this->assertSame('Materiales', $next['actor']);
    }

    /**
     * Regresión NO-CRIMP: tras el empaque, la primera acción pendiente sigue siendo la
     * ENTREGA del viajero por Empaque; la decisión espera a esa entrega (orden original).
     */
    public function test_non_crimp_viajero_delivery_precedes_decision(): void
    {
        $lot = $this->makeLot(isCrimp: false, availablePieces: 800);

        $lifecycle = $lot->getPostQualityLifecycle();

        $this->assertSame('pending', $lifecycle['viajero']['state']);
        $this->assertSame('Empaque', $lifecycle['viajero']['actor']);
        $this->assertSame('idle', $lifecycle['decision']['state']);

        $next = $lot->getNextPendingAction();
        $this->assertSame('viajero', $next['phase']);
        $this->assertSame('Empaque', $next['actor']);
    }

    /**
     * CRIMP: una vez tomada la decisión, la fase de viajero pasa a pendiente (Empaque
     * debe entregar), confirmando que en CRIMP la entrega va DESPUÉS de la decisión.
     */
    public function test_crimp_viajero_delivery_pending_after_decision(): void
    {
        $lot = $this->makeLot(isCrimp: true, availablePieces: 800, attrs: [
            'closure_decision' => Lot::CLOSURE_COMPLETE_LOT,
        ]);

        $lifecycle = $lot->getPostQualityLifecycle();

        $this->assertSame('done', $lifecycle['decision']['state']);
        $this->assertSame('pending', $lifecycle['viajero']['state']);
        $this->assertSame('Empaque', $lifecycle['viajero']['actor']);
    }

    /**
     * Regresión NO-CRIMP: recibido el viajero y sin decisión, la decisión queda pendiente
     * para Materiales (orden original: entrega → decisión).
     */
    public function test_non_crimp_decision_pending_after_viajero_received(): void
    {
        $lot = $this->makeLot(isCrimp: false, availablePieces: 800, attrs: [
            'viajero_received' => true,
        ]);

        $lifecycle = $lot->getPostQualityLifecycle();

        $this->assertSame('done', $lifecycle['viajero']['state']);
        $this->assertSame('pending', $lifecycle['decision']['state']);
        $this->assertSame('Materiales', $lifecycle['decision']['actor']);
    }
}
