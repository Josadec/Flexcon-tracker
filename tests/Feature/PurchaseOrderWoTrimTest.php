<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El mutator de PurchaseOrder::wo normaliza el valor al guardar (trim), para
 * evitar que un espacio sucio (" 2040057") contamine el codigo "W0..." que se
 * arma con getEffectiveWoNumber() en la cola de envio y en los Packing Slips.
 */
class PurchaseOrderWoTrimTest extends TestCase
{
    use RefreshDatabase;

    public function test_wo_is_trimmed_on_create(): void
    {
        $po = PurchaseOrder::factory()->create(['wo' => ' 2040057 ']);

        $this->assertSame('2040057', $po->fresh()->wo);
    }

    public function test_wo_is_trimmed_on_update(): void
    {
        $po = PurchaseOrder::factory()->create(['wo' => '2040057']);

        $po->update(['wo' => "  2040057\t"]);

        $this->assertSame('2040057', $po->fresh()->wo);
    }

    public function test_wo_null_remains_null(): void
    {
        $po = PurchaseOrder::factory()->create(['wo' => null]);

        $this->assertNull($po->fresh()->wo);
    }
}
