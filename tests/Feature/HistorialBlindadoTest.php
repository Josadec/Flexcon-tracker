<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users\UserList;
use App\Livewire\Admin\WorkOrders\WOList;
use App\Models\AuditTrail;
use App\Models\Lot;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use App\Services\AuditTrailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — el historial deja de poder destruirse desde la interfaz.
 *
 * El cliente tiene que conservar 5 años de datos por requisito del ISO. Hasta
 * ahora había tres caminos que borraban evidencia FÍSICAMENTE y sin aviso:
 * borrar una orden de trabajo, borrar un usuario, y editar la auditoría.
 * Lo que se borraba no se podía reconstruir.
 */
class HistorialBlindadoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['name' => 'Root', 'last_name' => 'Sistema', 'email_verified_at' => now()]);
        $user->assignRole('admin');

        return $user;
    }

    private function ordenConLote(string $numero = 'L-1'): array
    {
        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 500,
            'status' => Lot::STATUS_PENDING,
        ]);

        return [$wo, $lot];
    }

    // ===============================================
    // AUDITORÍA
    // ===============================================

    public function test_borrar_usuario_no_borra_su_auditoria(): void
    {
        $admin = $this->admin();
        $operador = User::factory()->create(['name' => 'Beto', 'last_name' => 'Ruiz']);
        [$wo, $lot] = $this->ordenConLote();

        app(AuditTrailService::class)->recordCreate($lot, $operador);
        $suyas = AuditTrail::where('user_id', $operador->id)->count();
        $this->assertGreaterThan(0, $suyas);

        Livewire::actingAs($admin)->test(UserList::class)
            ->call('deleteUser', $operador->id);

        // La cuenta se da de baja, el rastro se queda.
        $this->assertSoftDeleted($operador);
        $this->assertSame($suyas, AuditTrail::where('user_id', $operador->id)->count());
    }

    public function test_la_auditoria_conserva_el_nombre_de_un_usuario_borrado(): void
    {
        $operador = User::factory()->create(['name' => 'Carla', 'last_name' => 'Díaz']);
        [$wo, $lot] = $this->ordenConLote();

        $entrada = app(AuditTrailService::class)->recordCreate($lot, $operador);

        // Borrado físico: el caso extremo. La FK pone NULL en vez de arrastrar
        // la fila, y el nombre sigue ahí gracias a la copia.
        $operador->forceDelete();

        $entrada = $entrada->fresh();
        $this->assertNull($entrada->user_id);
        $this->assertSame('Carla Díaz', $entrada->user_name);
        $this->assertSame('Carla Díaz', $entrada->actor_name);
    }

    public function test_una_entrada_de_auditoria_no_se_puede_modificar_ni_borrar(): void
    {
        $operador = User::factory()->create();
        [$wo, $lot] = $this->ordenConLote();

        $entrada = app(AuditTrailService::class)->recordCreate($lot, $operador);

        // Si una entrada se puede corregir después, deja de probar nada.
        try {
            $entrada->update(['action' => 'otra-cosa']);
            $this->fail('Se pudo modificar una entrada de auditoría.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no se puede modificar', $e->getMessage());
        }

        try {
            $entrada->delete();
            $this->fail('Se pudo borrar una entrada de auditoría.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no se puede eliminar', $e->getMessage());
        }

        $this->assertSame('create', $entrada->fresh()->action);
    }

    // ===============================================
    // BORRADO DE ÓRDENES DE TRABAJO
    // ===============================================

    public function test_no_se_puede_borrar_una_wo_con_pesadas_registradas(): void
    {
        $admin = $this->admin();
        [$wo, $lot] = $this->ordenConLote();

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 200, 'good_pieces' => 200,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $admin->id,
        ]);

        $this->assertNotNull($wo->getDeleteBlockReason());

        Livewire::actingAs($admin)->test(WOList::class)
            ->call('deleteWorkOrder', $wo->id);

        $this->assertNotSoftDeleted($wo);
        $this->assertDatabaseHas('weighings', ['lot_id' => $lot->id]);
    }

    public function test_no_se_puede_borrar_una_wo_con_packing_slip(): void
    {
        $admin = $this->admin();
        [$wo, $lot] = $this->ordenConLote();

        $ps = PackingSlip::create([
            'ps_number' => 'PS-001', 'created_by' => $admin->id,
            'status' => 'draft', 'document_date' => now()->toDateString(),
        ]);
        PackingSlipItem::create([
            'packing_slip_id' => $ps->id, 'lot_id' => $lot->id, 'quantity_packed' => 100,
        ]);

        $this->assertStringContainsString('packing slip', $wo->getDeleteBlockReason());

        Livewire::actingAs($admin)->test(WOList::class)
            ->call('deleteWorkOrder', $wo->id);

        $this->assertNotSoftDeleted($wo);
    }

    public function test_el_borrado_fisico_se_bloquea_aunque_se_llame_directo_al_modelo(): void
    {
        $admin = $this->admin();
        [$wo, $lot] = $this->ordenConLote();

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => 200, 'good_pieces' => 200,
            'bad_pieces' => 0, 'weighed_at' => now(), 'weighed_by' => $admin->id,
        ]);

        // El guard vive en el modelo, no sólo en la pantalla: ningún llamador
        // futuro puede saltárselo sin darse cuenta.
        $this->expectException(\RuntimeException::class);
        $wo->forceDeleteWithRelations();
    }

    public function test_una_wo_sin_evidencia_si_se_puede_borrar(): void
    {
        $admin = $this->admin();
        [$wo, $lot] = $this->ordenConLote();

        $this->assertNull($wo->getDeleteBlockReason());

        Livewire::actingAs($admin)->test(WOList::class)
            ->call('deleteWorkOrder', $wo->id);

        // Baja lógica: desaparece de las pantallas y se puede recuperar.
        $this->assertSoftDeleted($wo);
        $this->assertSoftDeleted($lot);
    }

    // ===============================================
    // REPORTES QUE ESTABAN ROTOS
    // ===============================================

    public function test_el_excel_de_materiales_se_genera_sin_error(): void
    {
        $admin = $this->admin();
        $this->ordenConLote();

        // El flujo migró de Kits a lotes de CRIMP y esta hoja se quedó leyendo
        // claves que ya no existen: reventaba con "Undefined array key".
        $this->actingAs($admin)
            ->get(route('admin.reports.materiales.excel'))
            ->assertOk();
    }

    public function test_el_excel_general_se_genera_sin_error(): void
    {
        $admin = $this->admin();
        $this->ordenConLote();

        $this->actingAs($admin)
            ->get(route('admin.reports.general.excel'))
            ->assertOk();
    }
}
