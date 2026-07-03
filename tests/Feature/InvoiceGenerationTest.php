<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceChargeType;
use App\Models\InvoiceItem;
use App\Models\Lot;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\Price;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\InvoiceFromPackingSlipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FPL-12 — Generación de Invoice desde Packing Slip y cálculos financieros.
 *
 * Cubre lo que NO cubría AdminInvoiceAndShippingListTest (que solo verifica que
 * las pantallas cargan y listan): la lógica de negocio real que la corrida manual
 * va a ejercer — totales, precisión decimal, secuencia de invoice_number, cargos
 * fijos y la generación completa PS -> Invoice (happy path + guards).
 *
 * Cifras espejo del PDF FPL-12 Invoice #01019:
 *   - Línea producto STS H-M-3: 100,000 x 0.0958 = 9,580.00
 *   - Cargos fijos: Machine Maintenance 1,200 + Administration Fee 250 + Shipping 450 = 1,900.00
 */
class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'invoice-gen-admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    // =========================================================
    // Helpers de construcción
    // =========================================================

    /**
     * Crea la cadena Part -> Price(+tier) -> PO -> WO -> Lot -> PS(shipped) -> PSItem.
     * Devuelve el PackingSlip listo para generar Invoice.
     */
    private function makeShippedPackingSlip(array $overrides = []): PackingSlip
    {
        $isCrimp     = $overrides['is_crimp']       ?? false;
        $quantity    = $overrides['quantity']       ?? 100000;
        $tierPrice   = $overrides['tier_price']     ?? '0.0958';
        $workstation = $overrides['workstation']    ?? Price::WORKSTATION_TABLE;
        $description = $overrides['description']     ?? 'STS H-M-3';
        $itemNumber  = $overrides['item_number']    ?? '189-10179';

        $part = Part::factory()->create([
            'is_crimp'    => $isCrimp,
            'item_number' => $itemNumber,
            'description' => $description,
        ]);

        $price = Price::factory()->active()->create([
            'part_id'          => $part->id,
            'workstation_type' => $workstation,
            'sample_price'     => $tierPrice,
        ]);
        $price->tiers()->create([
            'min_quantity' => 1,
            'max_quantity' => null,
            'tier_price'   => $tierPrice,
        ]);

        $po = PurchaseOrder::factory()->approved()->create([
            'part_id'  => $part->id,
            'quantity' => $quantity,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id'  => $po->id,
            'status_id'          => StatusWO::factory(),
            'external_wo_number' => '2065668',
            'sent_pieces'        => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'V-INV-1',
            'quantity'      => $quantity,
            'status'        => Lot::STATUS_PENDING,
        ]);

        $ps = PackingSlip::create([
            'ps_number'     => $overrides['ps_number'] ?? 'PS-INV-1',
            'created_by'    => $this->admin->id,
            'status'        => PackingSlip::STATUS_SHIPPED,
            'document_date' => now(),
            'shipped_at'    => now(),
        ]);

        PackingSlipItem::create([
            'packing_slip_id' => $ps->id,
            'lot_id'          => $lot->id,
            'quantity_packed' => $quantity,
            'wo_number_ps'    => '2065668',
        ]);

        return $ps->fresh();
    }

    /**
     * Crea un Invoice standalone con los campos NOT NULL de snapshot de cliente.
     */
    private function makeStandaloneInvoice(array $overrides = []): Invoice
    {
        return Invoice::create($overrides + [
            'invoice_date'       => '2026-06-10',
            'status'             => Invoice::STATUS_DRAFT,
            'type'               => Invoice::TYPE_STANDALONE,
            'sold_to_address'    => 'S.E.I.P., Inc. / 915 Armorlite Dr.',
            'shipped_to_address' => 'S.E.I.P., Inc. / 915 Armorlite Dr.',
            'created_by'         => $this->admin->id,
        ]);
    }

    /**
     * Crea los 3 cargos fijos que aparecen en el PDF (always_include).
     */
    private function seedFixedCharges(): void
    {
        $charges = [
            ['code' => 'MACHINE_MAINT', 'label' => 'Machine Maintenance', 'default_amount' => '1200.00', 'sort_order' => 1],
            ['code' => 'ADMIN_FEE',     'label' => 'Administration Fee',  'default_amount' => '250.00',  'sort_order' => 2],
            ['code' => 'SHIPPING_COST', 'label' => 'SHIPPING COST',       'default_amount' => '450.00',  'sort_order' => 3],
        ];

        foreach ($charges as $c) {
            InvoiceChargeType::create($c + [
                'is_active'      => true,
                'always_include' => true,
                'created_by'     => $this->admin->id,
            ]);
        }
    }

    // =========================================================
    // 1. Cálculos financieros (Invoice::calculateTotals)
    // =========================================================

    public function test_calculate_totals_separa_productos_y_cargos_y_suma_grand_total(): void
    {
        $invoice = $this->makeStandaloneInvoice();

        // Dos líneas de producto
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'STS H-M-3', 'quantity' => 100000,
            'unit_cost' => '0.0958', 'line_total' => '9580.00', 'sort_order' => 1, 'is_fixed_charge' => false,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'STS H-M-2', 'quantity' => 79000,
            'unit_cost' => '0.0853', 'line_total' => '6738.70', 'sort_order' => 2, 'is_fixed_charge' => false,
        ]);

        // Tres cargos fijos
        foreach ([['Machine Maintenance', '1200.00'], ['Administration Fee', '250.00'], ['SHIPPING COST', '450.00']] as $i => $c) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id, 'description' => $c[0], 'quantity' => 1,
                'unit_cost' => $c[1], 'line_total' => $c[1], 'sort_order' => 10 + $i, 'is_fixed_charge' => true,
            ]);
        }

        $invoice->calculateTotals()->save();
        $invoice->refresh();

        $this->assertSame('16318.70', $invoice->subtotal_items);   // 9580.00 + 6738.70
        $this->assertSame('1900.00', $invoice->subtotal_charges);  // 1200 + 250 + 450
        $this->assertSame('18218.70', $invoice->grand_total);      // bcadd exacto
        $this->assertSame(179000, $invoice->total_quantity);       // 100000 + 79000 (solo productos)
    }

    public function test_line_total_usa_bcmul_y_evita_error_de_float(): void
    {
        // 108000 * 0.1796 con float PHP = 19396.800000000002 (error). bcmul + round = 19396.80.
        $invoice = $this->makeStandaloneInvoice();

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'STS', 'quantity' => 108000,
            'unit_cost' => '0.1796', 'line_total' => '0.00', 'sort_order' => 1, 'is_fixed_charge' => false,
        ]);

        $item->recalculateLineTotal();

        $this->assertSame('19396.80', $item->fresh()->line_total);
    }

    // =========================================================
    // 2. Secuencia de invoice_number (D-12-23)
    // =========================================================

    public function test_invoice_number_arranca_en_config_y_se_rellena_a_5_digitos(): void
    {
        $invoice = $this->makeStandaloneInvoice();

        $this->assertSame(
            str_pad((string) config('invoice.number_format.first_number', 1), 5, '0', STR_PAD_LEFT),
            $invoice->invoice_number
        );
    }

    public function test_invoice_number_incrementa_secuencialmente(): void
    {
        $this->makeStandaloneInvoice(['invoice_number' => '01018']);

        $second = $this->makeStandaloneInvoice();

        $this->assertSame('01019', $second->invoice_number); // #01019 del PDF
    }

    // =========================================================
    // 3. Catálogo de cargos fijos (InvoiceChargeType)
    // =========================================================

    public function test_get_active_types_for_new_invoice_solo_activos_always_include_ordenados(): void
    {
        $this->seedFixedCharges();

        // Uno inactivo y uno no-always → NO deben aparecer
        InvoiceChargeType::create(['code' => 'INACTIVE', 'label' => 'Inactivo', 'default_amount' => '99.00',
            'is_active' => false, 'always_include' => true, 'sort_order' => 4, 'created_by' => $this->admin->id]);
        InvoiceChargeType::create(['code' => 'OPTIONAL', 'label' => 'Opcional', 'default_amount' => '99.00',
            'is_active' => true, 'always_include' => false, 'sort_order' => 5, 'created_by' => $this->admin->id]);

        $types = InvoiceChargeType::getActiveTypesForNewInvoice();

        $this->assertCount(3, $types);
        $this->assertSame(
            ['Machine Maintenance', 'Administration Fee', 'SHIPPING COST'],
            $types->pluck('label')->all()
        );
    }

    // =========================================================
    // 4. Generación completa PS -> Invoice (happy path)
    // =========================================================

    public function test_genera_invoice_desde_packing_slip_con_producto_y_cargos_fijos(): void
    {
        $this->seedFixedCharges();
        $ps = $this->makeShippedPackingSlip();

        $invoice = app(InvoiceFromPackingSlipService::class)->createFromPackingSlip($ps);

        // Cabecera
        $this->assertSame(Invoice::TYPE_PRODUCT, $invoice->type);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame($ps->id, $invoice->packing_slip_id);

        // Línea de producto (espejo del PDF: 100,000 x 0.0958 = 9,580.00)
        $product = $invoice->productItems()->first();
        $this->assertSame(100000, $product->quantity);
        $this->assertSame('0.0958', $product->unit_cost);
        $this->assertSame('9580.00', $product->line_total);
        $this->assertSame('2065668', $product->wo_number);
        $this->assertFalse($product->is_fixed_charge);

        // Cargos fijos: 3 líneas
        $this->assertCount(3, $invoice->chargeItems);

        // Totales espejo del PDF
        $invoice->refresh();
        $this->assertSame('9580.00', $invoice->subtotal_items);
        $this->assertSame('1900.00', $invoice->subtotal_charges);
        $this->assertSame('11480.00', $invoice->grand_total);
        $this->assertSame(100000, $invoice->total_quantity);

        // El PS quedó ligado al Invoice (D-12-04)
        $this->assertSame($invoice->id, $ps->fresh()->invoice_id);
    }

    public function test_qty_del_invoice_toma_quantity_packed_del_packing_slip_item(): void
    {
        // La QTY del invoice debe ser exactamente la QTY empacada del PS (para CRIMP,
        // eso equivale a getPackagedPiecesTotal; aquí verificamos el paso-a-través 1:1).
        $this->seedFixedCharges();
        $ps = $this->makeShippedPackingSlip([
            'is_crimp' => true, 'quantity' => 60000, 'tier_price' => '0.1767',
            'description' => 'STS H-CR-436-37 CRIMP', 'item_number' => '189-10492',
        ]);

        $invoice = app(InvoiceFromPackingSlipService::class)->createFromPackingSlip($ps);
        $product = $invoice->productItems()->first();

        $this->assertSame(60000, $product->quantity);
        $this->assertSame('0.1767', $product->unit_cost);
        $this->assertSame('10602.00', $product->line_total); // 60,000 x 0.1767 (línea CRIMP del PDF)
        $this->assertStringContainsString('CRIMP', $product->description);
    }

    // =========================================================
    // 5. Guards del servicio
    // =========================================================

    public function test_no_genera_invoice_si_el_packing_slip_no_esta_shipped(): void
    {
        $ps = $this->makeShippedPackingSlip();
        $ps->update(['status' => PackingSlip::STATUS_PENDING]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("debe estar en estado 'shipped'");

        app(InvoiceFromPackingSlipService::class)->createFromPackingSlip($ps->fresh());
    }

    public function test_no_genera_segundo_invoice_si_el_packing_slip_ya_tiene_uno(): void
    {
        $this->seedFixedCharges();
        $ps = $this->makeShippedPackingSlip();

        app(InvoiceFromPackingSlipService::class)->createFromPackingSlip($ps);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya tiene un Invoice asociado');

        app(InvoiceFromPackingSlipService::class)->createFromPackingSlip($ps->fresh());
    }

    public function test_no_genera_invoice_si_el_packing_slip_no_tiene_items(): void
    {
        $ps = PackingSlip::create([
            'ps_number'     => 'PS-EMPTY',
            'created_by'    => $this->admin->id,
            'status'        => PackingSlip::STATUS_SHIPPED,
            'document_date' => now(),
            'shipped_at'    => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no tiene items');

        app(InvoiceFromPackingSlipService::class)->createFromPackingSlip($ps);
    }
}
