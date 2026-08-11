<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El canal `flash.banner` lo escriben 79 llamadas repartidas en 59 ficheros de
 * app/Livewire/Admin y no lo pintaba NINGUNA vista: los mensajes se perdían.
 * Ahora lo pinta el layout admin en un único punto (<x-flash-banner>).
 *
 * ALCANCE DE ESTOS TESTS: inyectan la clave con withSession(), así que prueban
 * que el layout pinta lo que hay en sesión — no que un componente consiga
 * dejarlo ahí. Eso último sólo ocurre cuando la acción redirige: en un update
 * AJAX sin redirect, Livewire borra el valor a propósito (ver el comentario de
 * resources/views/components/flash-banner.blade.php).
 */
class FlashBannerLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['email' => 'flash-banner-admin@test.com']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_the_layout_paints_the_banner_channel(): void
    {
        $this->actingAs($this->admin())
            ->withSession([
                'flash.banner'      => 'Área eliminada correctamente.',
                'flash.bannerStyle' => 'success',
            ])
            ->get(route('admin.work-orders.index'))
            ->assertOk()
            ->assertSee('Área eliminada correctamente.')
            ->assertSee('role="status"', escape: false);
    }

    public function test_a_danger_banner_is_announced_as_an_alert(): void
    {
        $this->actingAs($this->admin())
            ->withSession([
                'flash.banner'      => 'No se puede eliminar esta área porque tiene equipos asociados.',
                'flash.bannerStyle' => 'danger',
            ])
            ->get(route('admin.work-orders.index'))
            ->assertOk()
            ->assertSee('No se puede eliminar esta área porque tiene equipos asociados.')
            ->assertSee('role="alert"', escape: false);
    }

    public function test_without_a_flash_there_is_no_banner_at_all(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.work-orders.index'))
            ->assertOk()
            ->assertDontSee('Cerrar el aviso');
    }

    /**
     * Las pantallas de Órdenes de trabajo ya pintan sus avisos en línea con
     * <x-ui.note>. Por eso sus componentes dejaron de escribir en `flash.banner`:
     * si escribieran en los dos canales, el usuario vería el mismo texto dos
     * veces (una en el banner del layout y otra dentro de la pantalla).
     */
    public function test_the_work_order_screens_do_not_write_to_both_channels(): void
    {
        foreach ([
            app_path('Livewire/Admin/WorkOrders/WOList.php'),
            app_path('Livewire/Admin/WorkOrders/WOShow.php'),
            app_path('Livewire/Admin/WorkOrders/WOEdit.php'),
        ] as $file) {
            $this->assertStringNotContainsString(
                "session()->flash('flash.banner'",
                file_get_contents($file),
                basename($file).' no debe escribir en flash.banner: duplicaría el aviso en línea.'
            );
        }
    }
}
