<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pantallas de la cuenta del admin (/admin/settings/*): perfil, contraseña y
 * apariencia comparten cabecera y pestañas.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $atributos = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Ana',
            'last_name' => 'López',
            'email_verified_at' => now(),
        ], $atributos));
    }

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $user = $this->usuario(['name' => 'Root']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_las_tres_pantallas_abren_con_sus_pestanas(): void
    {
        $user = $this->usuario();

        foreach (['profile', 'password', 'appearance'] as $pantalla) {
            $this->actingAs($user)
                ->get(route('admin.settings.'.$pantalla))
                ->assertOk()
                ->assertSee('Perfil')
                ->assertSee('Contraseña')
                ->assertSee('Apariencia');
        }
    }

    public function test_la_pestana_activa_sobrevive_a_una_accion(): void
    {
        $user = $this->usuario();

        // Tras guardar, la petición va a /livewire/update: si la pestaña activa
        // se dedujera de la ruta, se apagarían las tres.
        Volt::actingAs($user)->test('admin.settings.profile')
            ->assertSeeHtml('aria-current="page"')
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertSeeHtml('aria-current="page"');
    }

    public function test_el_perfil_guarda_nombre_y_apellido(): void
    {
        $user = $this->usuario();

        // El apellido existe en la base y se usa en toda la aplicación, pero la
        // pantalla de perfil no lo dejaba editar.
        Volt::actingAs($user)->test('admin.settings.profile')
            ->set('name', 'Ana María')
            ->set('last_name', 'López Ruiz')
            ->set('email', 'ana@flexcon.test')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Ana María', $user->name);
        $this->assertSame('López Ruiz', $user->last_name);
        $this->assertSame('ana@flexcon.test', $user->email);
    }

    public function test_cambiar_el_correo_lo_deja_sin_verificar(): void
    {
        $user = $this->usuario();

        Volt::actingAs($user)->test('admin.settings.profile')
            ->set('email', 'otro@flexcon.test')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_no_se_repite_el_correo_de_otra_persona(): void
    {
        $user = $this->usuario();
        $otro = $this->usuario(['email' => 'ocupado@flexcon.test']);

        Volt::actingAs($user)->test('admin.settings.profile')
            ->set('email', $otro->email)
            ->call('updateProfileInformation')
            ->assertHasErrors('email');
    }

    public function test_la_contrasena_actual_tiene_que_ser_correcta(): void
    {
        $user = $this->usuario();

        Volt::actingAs($user)->test('admin.settings.password')
            ->set('current_password', 'la-que-no-es')
            ->set('password', 'contrasena-nueva-larga')
            ->set('password_confirmation', 'contrasena-nueva-larga')
            ->call('updatePassword')
            ->assertHasErrors('current_password');
    }

    public function test_la_contrasena_se_cambia_con_la_actual_correcta(): void
    {
        $user = $this->usuario();

        Volt::actingAs($user)->test('admin.settings.password')
            ->set('current_password', 'password')
            ->set('password', 'contrasena-nueva-larga')
            ->set('password_confirmation', 'contrasena-nueva-larga')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('contrasena-nueva-larga', $user->refresh()->password));
    }

    public function test_el_unico_administrador_no_puede_borrar_su_cuenta(): void
    {
        $admin = $this->admin();

        Volt::actingAs($admin)->test('admin.settings.delete-user-form')
            ->call('confirmDelete')
            ->assertSet('showDeleteModal', false)
            ->call('deleteUser')
            ->assertHasErrors('password');

        $this->assertNotSoftDeleted($admin);
    }

    public function test_con_otro_administrador_si_se_puede_borrar_la_cuenta(): void
    {
        $admin = $this->admin();
        $otroAdmin = $this->usuario(['name' => 'Segundo']);
        $otroAdmin->assignRole('admin');

        Volt::actingAs($admin)->test('admin.settings.delete-user-form')
            ->call('confirmDelete')
            ->assertSet('showDeleteModal', true)
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertSoftDeleted($admin);
    }
}
