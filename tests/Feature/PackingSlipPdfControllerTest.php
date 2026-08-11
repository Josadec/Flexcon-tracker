<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FPL-10 — PackingSlipPdfController (rutas del PDF del Packing Slip).
 *
 * Equivalente HTTP a lo que SentListPdfExportTest cubre para la Lista de Envio:
 * un usuario autorizado obtiene un PDF valido; un anonimo es redirigido a login.
 */
class PackingSlipPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'ps-pdf-admin@test.com']);
        $this->admin->assignRole('admin');
    }

    private function makePackingSlipWithItem(): PackingSlip
    {
        $part = Part::factory()->create(['is_crimp' => false, 'item_number' => 'IT-PDF']);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id'  => $po->id,
            'status_id'          => StatusWO::factory(),
            'external_wo_number' => '2065668',
            'sent_pieces'        => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => '001',
            'quantity'      => 1000,
            'status'        => Lot::STATUS_PENDING,
        ]);

        $ps = PackingSlip::create([
            'ps_number'     => 'PS-PDF-1',
            'created_by'    => $this->admin->id,
            'status'        => PackingSlip::STATUS_PENDING,
            'document_date' => now(),
        ]);

        PackingSlipItem::create([
            'packing_slip_id' => $ps->id,
            'lot_id'          => $lot->id,
            'quantity_packed' => 1000,
            'wo_number_ps'    => 'W02065668001',
        ]);

        return $ps->fresh();
    }

    public function test_usuario_autorizado_recibe_pdf_en_stream(): void
    {
        $ps = $this->makePackingSlipWithItem();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.shipping-list.pdf', $ps));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_usuario_autorizado_puede_descargar_el_pdf(): void
    {
        $ps = $this->makePackingSlipWithItem();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.shipping-list.pdf.download', $ps));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_usuario_no_autenticado_es_redirigido_a_login(): void
    {
        $ps = $this->makePackingSlipWithItem();

        $this->get(route('admin.shipping-list.pdf', $ps))
            ->assertRedirect(route('login'));
    }
}
