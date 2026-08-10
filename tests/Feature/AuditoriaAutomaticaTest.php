<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4 — la auditoría deja de depender de que alguien se acuerde.
 *
 * Antes había que llamar al servicio a mano en cada componente, y por eso
 * cubría 2 modelos de 41: ni órdenes, ni listas de envío, ni packing slips, ni
 * facturas dejaban rastro. Ahora es un trait: auditar un modelo es una línea.
 */
class AuditoriaAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $this->actingAs($user);

        return $user;
    }

    private function orden(): WorkOrder
    {
        $part = Part::factory()->create(['number' => 'P-100']);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        return WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);
    }

    public function test_crear_una_orden_de_trabajo_deja_rastro(): void
    {
        $user = $this->usuario();
        $wo = $this->orden();

        $entrada = AuditTrail::where('auditable_type', WorkOrder::class)
            ->where('auditable_id', $wo->id)
            ->where('action', 'create')
            ->firstOrFail();

        $this->assertSame($user->id, $entrada->user_id);
        $this->assertSame('Ana López', $entrada->user_name);
    }

    public function test_la_auditoria_guarda_lo_de_antes_y_lo_de_despues(): void
    {
        $this->usuario();
        $wo = $this->orden();

        $wo->update(['comments' => 'Se adelanta a la semana 32.']);

        $entrada = AuditTrail::where('auditable_type', WorkOrder::class)
            ->where('action', 'update')->latest('id')->firstOrFail();

        $this->assertArrayHasKey('comments', $entrada->new_values);
        $this->assertSame('Se adelanta a la semana 32.', $entrada->new_values['comments']);
    }

    public function test_un_guardado_que_no_cambia_nada_no_ensucia_el_historial(): void
    {
        $this->usuario();
        $wo = $this->orden();

        $antes = AuditTrail::where('action', 'update')->count();
        $wo->update(['comments' => $wo->comments]); // mismo valor

        $this->assertSame($antes, AuditTrail::where('action', 'update')->count());
    }

    public function test_la_auditoria_no_guarda_las_marcas_de_tiempo(): void
    {
        $this->usuario();
        $wo = $this->orden();

        $entrada = AuditTrail::where('auditable_type', WorkOrder::class)->firstOrFail();

        // Ruido puro: cada update las cambia y no cuentan nada.
        $this->assertArrayNotHasKey('updated_at', $entrada->new_values);
        $this->assertArrayNotHasKey('created_at', $entrada->new_values);
    }

    public function test_la_auditoria_resuelve_la_orden_y_la_parte_del_registro(): void
    {
        $this->usuario();
        $wo = $this->orden();

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'L-1', 'quantity' => 500,
            'status' => Lot::STATUS_PENDING,
        ]);

        $entrada = AuditTrail::where('auditable_type', Lot::class)
            ->where('auditable_id', $lot->id)->firstOrFail();

        // Es lo que hará posible buscar el historial «por WO» o «por parte»
        // sin recorrer relaciones desde una tabla polimórfica.
        $this->assertSame($lot->id, $entrada->lot_id);
        $this->assertSame($wo->id, $entrada->work_order_id);
        $this->assertSame($wo->purchase_order_id, $entrada->purchase_order_id);
        $this->assertSame($wo->purchaseOrder->part_id, $entrada->part_id);
    }

    public function test_borrar_un_viajero_deja_rastro(): void
    {
        $this->usuario();
        $wo = $this->orden();
        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'L-2', 'quantity' => 100,
            'status' => Lot::STATUS_PENDING,
        ]);

        $lot->delete();

        $this->assertDatabaseHas('audit_trails', [
            'auditable_type' => Lot::class,
            'auditable_id' => $lot->id,
            'action' => 'delete',
        ]);
    }

    public function test_el_historial_de_una_entidad_se_lee_desde_el_modelo(): void
    {
        $this->usuario();
        $wo = $this->orden();
        $wo->update(['comments' => 'Primera corrección de la orden.']);

        // create + update
        $this->assertGreaterThanOrEqual(2, $wo->auditTrails()->count());
    }
}
