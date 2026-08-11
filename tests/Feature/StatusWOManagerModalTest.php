<?php

namespace Tests\Feature;

use App\Livewire\Admin\StatusesWO\StatusWOManager;
use App\Livewire\Admin\WorkOrders\WOShow;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Administración de estados de WO (colores incluidos) desde la vista del WO.
 *
 * Cubre: apertura del modal, cambio de color y persistencia, validación del
 * hexadecimal, alta/baja de estados, autorización, y que la pantalla
 * standalone (/admin/statuses-wo) y su entrada de menú ya no existan.
 */
class StatusWOManagerModalTest extends TestCase
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

        $this->admin = User::factory()->create(['email' => 'status-admin@test.com']);
        $this->admin->assignRole('admin');
    }

    private function makeWorkOrder(StatusWO $status): WorkOrder
    {
        $part = Part::factory()->create();
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        return WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id' => $status->id,
            'sent_pieces' => 0,
        ]);
    }

    // ===============================================
    // APERTURA DESDE LA VISTA DEL WO
    // ===============================================

    public function test_wo_show_offers_the_button_that_opens_the_status_manager(): void
    {
        $this->actingAs($this->admin);

        $wo = $this->makeWorkOrder(StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']));

        Livewire::test(WOShow::class, ['workOrder' => $wo])
            ->assertOk()
            ->assertSee('Administrar estados')
            ->assertSee('open-statuses-wo-manager', escape: false)
            // El botón no sirve de nada si el manager no está montado en la
            // página: el evento se despacharía al vacío (ya pasó en un merge).
            ->assertSeeLivewire('admin.statuses-wo.status-wo-manager');
    }

    public function test_modal_is_hidden_until_the_open_event_arrives(): void
    {
        $this->actingAs($this->admin);

        StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->assertSet('show', false)
            ->assertDontSee('Estados de Work Order')
            ->call('open')
            ->assertSet('show', true)
            ->assertSee('Estados de Work Order')
            ->assertSee('Open');
    }

    public function test_open_loads_every_status_with_its_current_color(): void
    {
        $this->actingAs($this->admin);

        StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);
        StatusWO::factory()->create(['name' => 'Completed', 'color' => '#10B981']);

        $component = Livewire::test(StatusWOManager::class)->call('open');

        $rows = collect($component->get('rows'));

        $this->assertCount(2, $rows);
        // Se cargan ordenados por nombre.
        $this->assertSame('Completed', $rows[0]['name']);
        $this->assertSame('#10B981', $rows[0]['color']);
        $this->assertSame('Open', $rows[1]['name']);
        $this->assertSame('#3B82F6', $rows[1]['color']);
    }

    // ===============================================
    // CAMBIO DE COLOR Y PERSISTENCIA
    // ===============================================

    public function test_admin_can_change_a_status_color_and_it_persists(): void
    {
        $this->actingAs($this->admin);

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->set('rows.0.color', '#FF00AA')
            ->call('saveAll')
            ->assertHasNoErrors()
            ->assertDispatched('statuses-wo-updated');

        $this->assertSame('#FF00AA', $status->fresh()->color);
    }

    public function test_lowercase_and_short_hex_values_are_normalized_before_saving(): void
    {
        $this->actingAs($this->admin);

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->set('rows.0.color', 'abc')
            ->call('saveAll')
            ->assertHasNoErrors();

        $this->assertSame('#AABBCC', $status->fresh()->color);
    }

    public function test_name_and_comments_can_also_be_edited_from_the_modal(): void
    {
        $this->actingAs($this->admin);

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6', 'comments' => null]);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->set('rows.0.name', 'Abierta')
            ->set('rows.0.comments', 'Lista para producción')
            ->call('saveAll')
            ->assertHasNoErrors();

        $status->refresh();
        $this->assertSame('Abierta', $status->name);
        $this->assertSame('Lista para producción', $status->comments);
    }

    public function test_saving_without_changes_does_not_announce_an_update(): void
    {
        $this->actingAs($this->admin);

        StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->call('saveAll')
            ->assertHasNoErrors()
            ->assertNotDispatched('statuses-wo-updated')
            ->assertSet('feedback', 'No había cambios que guardar.');
    }

    public function test_the_new_color_reaches_the_badge_of_the_work_order(): void
    {
        $this->actingAs($this->admin);

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);
        $wo = $this->makeWorkOrder($status);

        $component = Livewire::test(WOShow::class, ['workOrder' => $wo])
            ->assertSee('background-color: #3B82F6', escape: false);

        // El modal guarda y avisa; la vista del WO escucha y se recarga.
        $status->update(['color' => '#FF00AA']);

        $component->dispatch('statuses-wo-updated')
            ->assertSee('background-color: #FF00AA', escape: false)
            ->assertDontSee('background-color: #3B82F6', escape: false);
    }

    // ===============================================
    // VALIDACIÓN
    // ===============================================

    public function test_an_invalid_color_is_rejected_with_a_friendly_message(): void
    {
        $this->actingAs($this->admin);

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->set('rows.0.color', 'rojo intenso')
            ->call('saveAll')
            ->assertHasErrors('rows.0.color')
            ->assertSee('El color debe ser hexadecimal, por ejemplo #3B82F6.');

        $this->assertSame('#3B82F6', $status->fresh()->color);
    }

    public function test_an_empty_name_is_rejected(): void
    {
        $this->actingAs($this->admin);

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->set('rows.0.name', '')
            ->call('saveAll')
            ->assertHasErrors('rows.0.name');

        $this->assertSame('Open', $status->fresh()->name);
    }

    public function test_two_rows_cannot_end_up_with_the_same_name(): void
    {
        $this->actingAs($this->admin);

        $open = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);
        $completed = StatusWO::factory()->create(['name' => 'Completed', 'color' => '#10B981']);

        // rows[0] = Completed, rows[1] = Open (orden alfabético).
        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->set('rows.1.name', 'Completed')
            ->call('saveAll')
            ->assertHasErrors('rows.1.name');

        $this->assertSame('Open', $open->fresh()->name);
        $this->assertSame('Completed', $completed->fresh()->name);
    }

    // ===============================================
    // ALTA Y BAJA
    // ===============================================

    public function test_a_new_status_can_be_created_from_the_modal(): void
    {
        $this->actingAs($this->admin);

        StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->call('toggleNewForm')
            ->set('newName', 'En revisión')
            ->set('newColor', '#123456')
            ->call('createStatus')
            ->assertHasNoErrors()
            ->assertDispatched('statuses-wo-updated');

        $this->assertDatabaseHas('statuses_wo', ['name' => 'En revisión', 'color' => '#123456']);
    }

    public function test_an_unused_status_can_be_deleted(): void
    {
        $this->actingAs($this->admin);

        $unused = StatusWO::factory()->create(['name' => 'On Hold', 'color' => '#6B7280']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->call('deleteStatus', $unused->id)
            ->assertDispatched('statuses-wo-updated');

        $this->assertDatabaseMissing('statuses_wo', ['id' => $unused->id]);
    }

    public function test_a_status_used_by_a_work_order_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin);

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);
        $this->makeWorkOrder($status);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->call('deleteStatus', $status->id)
            ->assertNotDispatched('statuses-wo-updated')
            ->assertSee('No se puede eliminar');

        $this->assertDatabaseHas('statuses_wo', ['id' => $status->id]);
    }

    // ===============================================
    // AUTORIZACIÓN
    // ===============================================

    public function test_a_user_without_permission_cannot_open_the_manager(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'sin-permiso@test.com']));

        StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->assertForbidden();
    }

    public function test_a_user_without_permission_cannot_change_a_color(): void
    {
        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        // Se abre con el admin para tener las filas cargadas…
        $this->actingAs($this->admin);
        $component = Livewire::test(StatusWOManager::class)->call('open');

        // …y se intenta guardar como usuario sin permisos.
        $this->actingAs(User::factory()->create(['email' => 'intruso@test.com']));

        $component->set('rows.0.color', '#FF00AA')
            ->call('saveAll')
            ->assertForbidden();

        $this->assertSame('#3B82F6', $status->fresh()->color);
    }

    public function test_a_user_without_permission_cannot_create_or_delete_statuses(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'intruso2@test.com']));

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('createStatus')
            ->assertForbidden();

        Livewire::test(StatusWOManager::class)
            ->call('deleteStatus', $status->id)
            ->assertForbidden();

        $this->assertDatabaseHas('statuses_wo', ['id' => $status->id]);
    }

    public function test_the_status_manager_button_is_hidden_for_a_user_without_permission(): void
    {
        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);
        $wo = $this->makeWorkOrder($status);

        $this->actingAs(User::factory()->create(['email' => 'mirón@test.com']));

        Livewire::test(WOShow::class, ['workOrder' => $wo])
            ->assertOk()
            ->assertDontSee('Administrar estados');
    }

    // ===============================================
    // LA PANTALLA STANDALONE YA NO EXISTE
    // ===============================================

    public function test_the_standalone_statuses_screen_is_gone(): void
    {
        $this->actingAs($this->admin);

        $status = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        $this->get('/admin/statuses-wo')->assertNotFound();
        $this->get('/admin/statuses-wo/create')->assertNotFound();
        $this->get('/admin/statuses-wo/'.$status->id.'/edit')->assertNotFound();
    }

    /**
     * Los nombres de ruta desaparecieron: cualquier `route('admin.statuses-wo.*')`
     * que sobreviva en un blade reventaría con RouteNotFoundException en runtime.
     */
    public function test_the_statuses_route_names_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.statuses-wo.index'));
        $this->assertFalse(Route::has('admin.statuses-wo.create'));
        $this->assertFalse(Route::has('admin.statuses-wo.edit'));
    }

    /**
     * El menú se renderiza sin la entrada "Estados" y la vista del WO sigue
     * ofreciendo el modal. Si alguien reintrodujera el ítem del sidebar sin la
     * ruta, esta página devolvería 500 en vez de 200.
     */
    public function test_the_menu_no_longer_offers_the_statuses_screen(): void
    {
        $this->actingAs($this->admin);

        $wo = $this->makeWorkOrder(StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']));

        $this->get(route('admin.work-orders.show', $wo))
            ->assertOk()
            ->assertDontSee('/admin/statuses-wo')
            ->assertSee('Administrar estados');
    }

    /**
     * El catálogo sigue siendo administrable end-to-end pese a no tener pantalla:
     * el modal es ahora el único camino.
     */
    public function test_the_catalog_is_still_fully_manageable_through_the_modal(): void
    {
        $this->actingAs($this->admin);

        $open = StatusWO::factory()->create(['name' => 'Open', 'color' => '#3B82F6']);

        Livewire::test(StatusWOManager::class)
            ->call('open')
            // Editar
            ->set('rows.0.color', '#111111')
            ->call('saveAll')
            ->assertHasNoErrors()
            // Crear
            ->call('toggleNewForm')
            ->set('newName', 'Bloqueado')
            ->set('newColor', '#222222')
            ->call('createStatus')
            ->assertHasNoErrors();

        $this->assertSame('#111111', $open->fresh()->color);
        $this->assertDatabaseHas('statuses_wo', ['name' => 'Bloqueado', 'color' => '#222222']);

        // Borrar (el recién creado no lo usa ninguna WO)
        $created = StatusWO::where('name', 'Bloqueado')->firstOrFail();

        Livewire::test(StatusWOManager::class)
            ->call('open')
            ->call('deleteStatus', $created->id);

        $this->assertDatabaseMissing('statuses_wo', ['id' => $created->id]);
    }
}
