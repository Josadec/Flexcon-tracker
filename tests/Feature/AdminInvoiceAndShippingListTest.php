<?php

namespace Tests\Feature;

use App\Livewire\Admin\Invoices\InvoiceList;
use App\Livewire\Admin\PackingSlips\PackingSlipList;
use App\Models\Invoice;
use App\Models\PackingSlip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminInvoiceAndShippingListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name' => 'Invoice Admin',
            'email' => 'invoice-admin@test.com',
        ]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_admin_can_open_invoices_page(): void
    {
        $this->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee('Invoices');
    }

    public function test_invoice_list_displays_existing_invoice(): void
    {
        Invoice::create([
            'invoice_number' => '54321',
            'invoice_date' => '2026-05-21',
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_STANDALONE,
            'sold_to_address' => 'Invoice Sold Address',
            'shipped_to_address' => 'Invoice Shipping Address',
            'grand_total' => 1234.56,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(InvoiceList::class)
            ->assertSee('Invoice#54321')
            ->assertSee('Borrador')
            ->assertSee('$1,234.56');
    }

    public function test_admin_can_open_shipping_list_page(): void
    {
        $this->get(route('admin.shipping-list.index'))
            ->assertOk()
            ->assertSee('Shipping List')
            ->assertSee('WO Listos para SL');
    }

    public function test_shipping_list_tab_displays_existing_packing_slip(): void
    {
        PackingSlip::create([
            'ps_number' => 'PS-2026-9876',
            'created_by' => $this->admin->id,
            'status' => PackingSlip::STATUS_PENDING,
            'document_date' => '2026-05-21',
        ]);

        Livewire::test(PackingSlipList::class)
            ->call('setTab', 'list')
            ->assertSee('PS-2026-9876')
            ->assertSee('Pendiente');
    }
}
