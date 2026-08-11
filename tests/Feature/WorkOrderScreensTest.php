<?php

namespace Tests\Feature;

use App\Livewire\Admin\StatusesWO\StatusWOManager;
use App\Livewire\Admin\WorkOrders\WOEdit;
use App\Livewire\Admin\WorkOrders\WOList;
use App\Livewire\Admin\WorkOrders\WOShow;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pantallas de Manage PO (Work Orders) después de alinearlas con el diseño de
 * Partes: listado, ficha (con sus tres pestañas) y edición.
 *
 * Lo que se protege aquí es que el rediseño no se llevó por delante nada del
 * dominio: los conteos por estado, el CRUD de lotes y de pesadas, y el cambio
 * de estado con su registro en el historial.
 */
class WorkOrderScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            StatusWOManager::PERMISSION_VIEW,
            StatusWOManager::PERMISSION_CREATE,
            StatusWOManager::PERMISSION_EDIT,
            StatusWOManager::PERMISSION_DELETE,
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create(['email' => 'wo-screens-admin@test.com']);
        $this->admin->assignRole('admin');

        $this->actingAs($this->admin);
    }

    private function makeWorkOrder(?StatusWO $status = null, int $quantity = 1000): WorkOrder
    {
        $status ??= StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);
        $part = Part::factory()->create();
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => $quantity]);

        // original_quantity no es columna: sale de la PO (accessor del modelo).
        return WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => $status->id,
            'sent_pieces'       => 0,
        ]);
    }

    // ===============================================
    // LISTADO
    // ===============================================

    public function test_the_list_shows_the_work_order_with_its_status(): void
    {
        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);
        $wo     = $this->makeWorkOrder($status);

        Livewire::test(WOList::class)
            ->assertOk()
            ->assertSee('Órdenes de trabajo')
            ->assertSee($wo->purchaseOrder->wo)
            ->assertSee('Open')
            ->assertSee('background-color: #3B82F6', escape: false);
    }

    public function test_the_summary_counts_work_orders_per_status(): void
    {
        $open      = StatusWO::factory()->create(['name' => 'Open']);
        $completed = StatusWO::factory()->create(['name' => 'Completed']);

        $this->makeWorkOrder($open);
        $this->makeWorkOrder($open);
        $this->makeWorkOrder($completed);

        $component = Livewire::test(WOList::class)->assertOk();

        $counts = $component->viewData('statusCounts');

        $this->assertSame(2, (int) $counts[$open->id]);
        $this->assertSame(1, (int) $counts[$completed->id]);
        $this->assertSame(3, $component->viewData('totalWOs'));
    }

    public function test_the_empty_state_explains_where_work_orders_come_from(): void
    {
        Livewire::test(WOList::class)
            ->assertOk()
            ->assertSee('No se encontraron órdenes de trabajo');
    }

    public function test_filtering_by_status_hides_the_other_work_orders(): void
    {
        $open      = StatusWO::factory()->create(['name' => 'Open']);
        $completed = StatusWO::factory()->create(['name' => 'Completed']);

        $visible = $this->makeWorkOrder($open);
        $hidden  = $this->makeWorkOrder($completed);

        $rows = Livewire::test(WOList::class)
            ->set('filterStatus', $open->id)
            ->viewData('workOrders')
            ->pluck('id')
            ->all();

        $this->assertContains($visible->id, $rows);
        $this->assertNotContains($hidden->id, $rows);
    }

    // ===============================================
    // FICHA: PESTAÑAS
    // ===============================================

    public function test_the_show_screen_offers_the_three_tabs(): void
    {
        $wo = $this->makeWorkOrder();

        Livewire::test(WOShow::class, ['workOrder' => $wo])
            ->assertOk()
            ->assertSee('General')
            ->assertSee('Lotes')
            ->assertSee('Pesadas')
            ->assertSet('activeTab', 'general');
    }

    public function test_the_weighings_tab_is_reachable(): void
    {
        $wo = $this->makeWorkOrder();

        Livewire::test(WOShow::class, ['workOrder' => $wo])
            ->call('setTab', 'weighings')
            ->assertSet('activeTab', 'weighings')
            ->assertSee('Pesadas de esta orden de trabajo');
    }

    /**
     * Las pestañas son un tablist ARIA, no una barra de enlaces: cada botón es
     * `role="tab"` y apunta con `aria-controls` al panel que muestra.
     */
    public function test_the_tabs_are_wired_to_their_panels(): void
    {
        $wo = $this->makeWorkOrder();

        $component = Livewire::test(WOShow::class, ['workOrder' => $wo])->assertOk();

        foreach (['general', 'lots', 'weighings'] as $tab) {
            $component->assertSee('id="wo-tab-'.$tab.'"', escape: false)
                ->assertSee('aria-controls="wo-panel-'.$tab.'"', escape: false);
        }

        // Sólo la pestaña abierta está seleccionada y su panel existe.
        $component->assertSee('id="wo-panel-general"', escape: false)
            ->assertSee('role="tabpanel"', escape: false)
            ->assertDontSee('id="wo-panel-lots"', escape: false);

        $component->call('setTab', 'lots')
            ->assertSee('id="wo-panel-lots"', escape: false)
            ->assertDontSee('id="wo-panel-general"', escape: false);
    }

    public function test_the_show_screen_keeps_the_purchase_order_data(): void
    {
        $wo = $this->makeWorkOrder();

        Livewire::test(WOShow::class, ['workOrder' => $wo])
            ->assertSee($wo->purchaseOrder->po_number)
            ->assertSee($wo->purchaseOrder->part->number)
            ->assertSee($wo->wo_number);
    }

    // ===============================================
    // FICHA: LOTES
    // ===============================================

    public function test_a_lot_can_be_created_from_the_lots_tab(): void
    {
        $wo = $this->makeWorkOrder(quantity: 500);

        Livewire::test(WOShow::class, ['workOrder' => $wo])
            ->call('setTab', 'lots')
            ->call('openCreateLotModal')
            ->assertSet('showLotModal', true)
            ->set('lotNumber', 'L-001')
            ->set('lotQuantity', 200)
            ->call('saveLot')
            ->assertSet('showLotModal', false);

        $this->assertDatabaseHas('lots', [
            'work_order_id' => $wo->id,
            'lot_number'    => 'L-001',
            'quantity'      => 200,
        ]);
    }

    public function test_lots_cannot_add_up_to_more_than_the_work_order_quantity(): void
    {
        $wo = $this->makeWorkOrder(quantity: 100);

        Livewire::test(WOShow::class, ['workOrder' => $wo])
            ->call('openCreateLotModal')
            ->set('lotNumber', 'L-001')
            ->set('lotQuantity', 250)
            ->call('saveLot')
            ->assertHasErrors('lotQuantity');

        $this->assertDatabaseCount('lots', 0);
    }

    public function test_a_lot_is_deleted_through_the_confirmation_modal(): void
    {
        $wo  = $this->makeWorkOrder(quantity: 500);
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'L-009',
            'quantity'      => 50,
            'status'        => 'pending',
        ]);

        Livewire::test(WOShow::class, ['workOrder' => $wo->fresh()])
            ->call('confirmDeleteLot', $lot->id)
            ->assertSet('showDeleteLotConfirm', true)
            ->call('deleteLot')
            ->assertSet('showDeleteLotConfirm', false);

        $this->assertSoftDeleted('lots', ['id' => $lot->id]);
    }

    /**
     * El modal de borrado tiene que decir QUÉ se borra: sin el número de lote
     * el operador confirma a ciegas.
     */
    public function test_the_delete_lot_modal_names_the_lot_and_its_weighings(): void
    {
        $wo  = $this->makeWorkOrder(quantity: 500);
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'L-777',
            'quantity'      => 60,
            'status'        => 'pending',
        ]);
        Weighing::create([
            'lot_id'      => $lot->id,
            'kit_id'      => null,
            'quantity'    => $lot->quantity,
            'good_pieces' => 5,
            'bad_pieces'  => 0,
            'weighed_at'  => now(),
            'weighed_by'  => $this->admin->id,
        ]);

        Livewire::test(WOShow::class, ['workOrder' => $wo->fresh()])
            ->call('confirmDeleteLot', $lot->id)
            ->assertSee('Eliminar el lote L-777')
            ->assertSee('1 pesada(s)');
    }

    // ===============================================
    // FICHA: PESADAS
    // ===============================================

    /**
     * Regresión: `weighings.quantity` es NOT NULL sin valor por defecto. El
     * componente no lo enviaba y el INSERT reventaba en cuanto la pestaña de
     * Pesadas quedó accesible desde la ficha.
     */
    public function test_registering_a_weighing_stores_the_lot_quantity(): void
    {
        $wo  = $this->makeWorkOrder(quantity: 500);
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'L-010',
            'quantity'      => 40,
            'status'        => 'pending',
        ]);

        Livewire::test(WOShow::class, ['workOrder' => $wo->fresh()])
            ->call('setTab', 'weighings')
            ->call('openCreateWeighingModal')
            ->set('weighingLotId', $lot->id)
            ->set('goodPieces', 30)
            ->set('badPieces', 2)
            ->set('weighedAt', now()->format('Y-m-d\TH:i'))
            ->call('saveWeighing')
            ->assertHasNoErrors()
            ->assertSet('showWeighingModal', false);

        $this->assertDatabaseHas('weighings', [
            'lot_id'      => $lot->id,
            'quantity'    => 40,
            'good_pieces' => 30,
            'bad_pieces'  => 2,
        ]);
    }

    public function test_a_weighing_cannot_exceed_the_pending_pieces_of_the_lot(): void
    {
        $wo  = $this->makeWorkOrder(quantity: 500);
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'L-011',
            'quantity'      => 10,
            'status'        => 'pending',
        ]);

        Livewire::test(WOShow::class, ['workOrder' => $wo->fresh()])
            ->call('openCreateWeighingModal')
            ->set('weighingLotId', $lot->id)
            ->set('goodPieces', 50)
            ->set('badPieces', 0)
            ->set('weighedAt', now()->format('Y-m-d\TH:i'))
            ->call('saveWeighing')
            ->assertHasErrors('goodPieces');

        $this->assertDatabaseCount('weighings', 0);
    }

    public function test_a_weighing_is_deleted_through_the_confirmation_modal(): void
    {
        $wo  = $this->makeWorkOrder(quantity: 500);
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number'    => 'L-012',
            'quantity'      => 20,
            'status'        => 'pending',
        ]);
        $weighing = Weighing::create([
            'lot_id'      => $lot->id,
            'kit_id'      => null,
            'quantity'    => $lot->quantity,
            'good_pieces' => 5,
            'bad_pieces'  => 0,
            'weighed_at'  => now(),
            'weighed_by'  => $this->admin->id,
        ]);

        Livewire::test(WOShow::class, ['workOrder' => $wo->fresh()])
            ->call('setTab', 'weighings')
            ->call('confirmDeleteWeighing', $weighing->id)
            ->assertSet('showDeleteWeighingConfirm', true)
            ->call('deleteWeighing')
            ->assertSet('showDeleteWeighingConfirm', false);

        $this->assertSoftDeleted('weighings', ['id' => $weighing->id]);
    }

    // ===============================================
    // EDICIÓN
    // ===============================================

    public function test_the_edit_screen_shows_the_order_context_and_saves(): void
    {
        $open       = StatusWO::factory()->create(['name' => 'Open']);
        $inProgress = StatusWO::factory()->create(['name' => 'In Progress']);
        $wo         = $this->makeWorkOrder($open);

        Livewire::test(WOEdit::class, ['workOrder' => $wo])
            ->assertOk()
            ->assertSee($wo->purchaseOrder->po_number)
            ->assertSee($wo->purchaseOrder->part->number)
            ->set('status_id', $inProgress->id)
            ->set('status_change_comments', 'Arranca producción')
            ->set('eq', 'EQ-7')
            ->call('save');

        $wo->refresh();

        $this->assertSame($inProgress->id, $wo->status_id);
        $this->assertSame('EQ-7', $wo->eq);
    }

    /**
     * Guardar tiene que dejar un mensaje que la ficha SEPA pintar. El canal
     * `flash.banner` que heredamos de Jetstream no lo renderiza ninguna vista
     * del proyecto, así que un guardado exitoso se veía como si no pasara nada.
     */
    public function test_saving_leaves_a_success_message_the_show_screen_can_render(): void
    {
        $open       = StatusWO::factory()->create(['name' => 'Open']);
        $inProgress = StatusWO::factory()->create(['name' => 'In Progress']);
        $wo         = $this->makeWorkOrder($open);

        Livewire::test(WOEdit::class, ['workOrder' => $wo])
            ->set('status_id', $inProgress->id)
            ->call('save');

        $this->assertNotNull(session('success'));

        Livewire::test(WOShow::class, ['workOrder' => $wo->fresh()])
            ->assertSee(session('success'));
    }

    /**
     * La cabecera de edición muestra el estado guardado (de dónde parte el
     * cambio), con el color del catálogo.
     */
    public function test_the_edit_screen_shows_the_saved_status_in_the_header(): void
    {
        $open = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);
        $wo   = $this->makeWorkOrder($open);

        Livewire::test(WOEdit::class, ['workOrder' => $wo])
            ->assertSee('Estado actual')
            ->assertSee('background-color: #3B82F6', escape: false);
    }

    public function test_the_edit_screen_warns_before_changing_the_status(): void
    {
        $open       = StatusWO::factory()->create(['name' => 'Open']);
        $inProgress = StatusWO::factory()->create(['name' => 'In Progress']);
        $wo         = $this->makeWorkOrder($open);

        Livewire::test(WOEdit::class, ['workOrder' => $wo])
            ->assertDontSee('Vas a cambiar el estado de la orden')
            ->set('status_id', $inProgress->id)
            ->assertSee('Vas a cambiar el estado de la orden');
    }
}
