<?php

namespace Tests\Feature;

use App\Livewire\Admin\PackingSlips\PackingSlipShow;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FPL-10 — Desglose CRIMP en la vista de detalle (pantalla) del Packing Slip.
 *
 * Componente: App\Livewire\Admin\PackingSlips\PackingSlipShow
 * Vista:      resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php
 *
 * Regla de oro: rótulo "Viajero" y sub-filas viajero -> lotes de CRIMP solo para
 * partes is_crimp; NO-CRIMP queda idéntico (sin rótulo ni desglose).
 */
class PackingSlipCrimpScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'ps-screen-admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    /**
     * Crea un Packing Slip con un item ligado a un viajero de una parte crimp/no-crimp.
     */
    private function makePackingSlipWithItem(bool $isCrimp): array
    {
        $part = Part::factory()->create(['is_crimp' => $isCrimp, 'item_number' => 'IT-100']);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
        $wo   = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'V-PS-777',
            'quantity'      => 1000,
            'status'        => Lot::STATUS_PENDING,
        ]);

        $ps = PackingSlip::create([
            'ps_number'     => 'PS-SCREEN-1',
            'created_by'    => auth()->id(),
            'status'        => PackingSlip::STATUS_PENDING,
            'document_date' => now(),
        ]);

        PackingSlipItem::create([
            'packing_slip_id' => $ps->id,
            'lot_id'          => $lot->id,
            'quantity_packed' => 1000,
            'wo_number_ps'    => 'WO-PS-1',
        ]);

        return [$ps, $lot];
    }

    public function test_crimp_screen_muestra_rotulo_viajero_y_desglose(): void
    {
        [$ps, $lot] = $this->makePackingSlipWithItem(isCrimp: true);

        CrimpLot::create([
            'lot_id' => $lot->id, 'crimp_lot_number' => 'CL-001',
            'lote_fabricante' => 'PROV-AAA', 'quantity' => 600,
        ]);
        CrimpLot::create([
            'lot_id' => $lot->id, 'crimp_lot_number' => 'CL-002',
            'lote_fabricante' => 'PROV-BBB', 'quantity' => 400,
        ]);

        Livewire::test(PackingSlipShow::class, ['packingSlip' => $ps])
            ->assertSee('Viajero')          // rótulo
            ->assertSee('V-PS-777')         // viajero en la sub-fila
            ->assertSee('CL-001')
            ->assertSee('CL-002')
            ->assertSee('PROV-AAA')
            ->assertSee('PROV-BBB');
    }

    public function test_no_crimp_screen_no_muestra_desglose(): void
    {
        [$ps, $lot] = $this->makePackingSlipWithItem(isCrimp: false);

        // Datos CRIMP en BD pero parte NO-crimp: no deben aparecer.
        CrimpLot::create([
            'lot_id' => $lot->id, 'crimp_lot_number' => 'CL-OCULTO',
            'lote_fabricante' => 'PROV-OCULTO', 'quantity' => 600,
        ]);

        Livewire::test(PackingSlipShow::class, ['packingSlip' => $ps])
            ->assertDontSee('CL-OCULTO')
            ->assertDontSee('PROV-OCULTO');
    }
}
