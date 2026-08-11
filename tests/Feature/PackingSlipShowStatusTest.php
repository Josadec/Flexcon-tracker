<?php

namespace Tests\Feature;

use App\Livewire\Admin\PackingSlips\PackingSlipShow;
use App\Models\PackingSlip;
use App\Models\User;
use App\Services\ReopeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FPL-10 — Transiciones de estado del Packing Slip (PackingSlipShow::updateStatus).
 *
 * Marcar shipped fija shipped_at/shipped_by (paso previo obligatorio para generar
 * el Invoice FPL-12); revertir desde shipped limpia esos campos.
 */
class PackingSlipShowStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Sacar un packing slip de `shipped` es una reapertura y ReopeningService
        // la reserva a quien tenga este permiso. El rol de prueba se crea vacío,
        // así que hay que otorgarlo o el revert se bloquea antes de tocar la BD.
        $rol->givePermissionTo(Permission::firstOrCreate([
            'name'       => ReopeningService::PERMISSION,
            'guard_name' => 'web',
        ]));

        $this->admin = User::factory()->create(['email' => 'ps-status-admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function makePackingSlip(string $status, bool $shipped = false): PackingSlip
    {
        return PackingSlip::create([
            'ps_number'     => 'PS-STATUS-1',
            'created_by'    => $this->admin->id,
            'status'        => $status,
            'document_date' => now(),
            'shipped_at'    => $shipped ? now() : null,
            'shipped_by'    => $shipped ? $this->admin->id : null,
        ]);
    }

    public function test_marcar_shipped_fija_shipped_at_y_shipped_by(): void
    {
        $ps = $this->makePackingSlip(PackingSlip::STATUS_PENDING);

        Livewire::test(PackingSlipShow::class, ['packingSlip' => $ps])
            ->set('selectedStatus', PackingSlip::STATUS_SHIPPED)
            ->call('updateStatus');

        $fresh = $ps->fresh();
        $this->assertSame(PackingSlip::STATUS_SHIPPED, $fresh->status);
        $this->assertNotNull($fresh->shipped_at);
        $this->assertSame($this->admin->id, $fresh->shipped_by);
    }

    public function test_revertir_desde_shipped_limpia_shipped_at_y_shipped_by(): void
    {
        $ps = $this->makePackingSlip(PackingSlip::STATUS_SHIPPED, shipped: true);

        Livewire::test(PackingSlipShow::class, ['packingSlip' => $ps])
            ->set('selectedStatus', PackingSlip::STATUS_PENDING)
            ->call('updateStatus');

        $fresh = $ps->fresh();
        $this->assertSame(PackingSlip::STATUS_PENDING, $fresh->status);
        $this->assertNull($fresh->shipped_at);
        $this->assertNull($fresh->shipped_by);
    }

    public function test_estado_invalido_no_cambia_nada(): void
    {
        $ps = $this->makePackingSlip(PackingSlip::STATUS_PENDING);

        Livewire::test(PackingSlipShow::class, ['packingSlip' => $ps])
            ->set('selectedStatus', 'estado-inexistente')
            ->call('updateStatus');

        $this->assertSame(PackingSlip::STATUS_PENDING, $ps->fresh()->status);
    }
}
