<?php

namespace Tests\Feature;

use App\Livewire\Admin\Tutorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La guía documenta el flujo CRIMP de 8 pasos del diagrama del proceso.
 * Estas pruebas cuidan la estructura (pasos completos, cada rol entra a lo
 * suyo, las maquetas se dibujan), no la redacción: el texto puede cambiar.
 */
class TutorialFlowTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $user->assignRole($role);

        return $user;
    }

    public function test_el_flujo_crimp_documenta_los_ocho_pasos_en_orden(): void
    {
        $pasos = (new Tutorial)->crimpSteps();

        $this->assertCount(8, $pasos);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], array_column($pasos, 'n'));

        foreach ($pasos as $paso) {
            $this->assertNotEmpty($paso['actor'], 'Cada paso dice quién lo hace.');
            $this->assertNotEmpty($paso['where'], 'Cada paso dice en qué pantalla se hace.');
            $this->assertNotEmpty($paso['does'], 'Cada paso trae instrucciones concretas.');
            $this->assertNotEmpty($paso['shot']['blocks'], 'Cada paso trae su maqueta de pantalla.');
        }
    }

    public function test_cada_rol_entra_directo_a_su_seccion(): void
    {
        $esperado = [
            'admin' => 'crimp',
            'Materiales' => 'materiales',
            'Produccion' => 'produccion',
            'Calidad' => 'calidad',
            'Empaques' => 'empaques',
        ];

        foreach ($esperado as $rol => $seccion) {
            Livewire::actingAs($this->userWithRole($rol))->test(Tutorial::class)
                ->assertOk()
                ->assertSet('activeSection', $seccion);
        }
    }

    public function test_se_cambia_de_seccion_y_se_ignoran_las_inexistentes(): void
    {
        Livewire::actingAs($this->userWithRole('admin'))->test(Tutorial::class)
            ->call('goTo', 'estandar')
            ->assertSet('activeSection', 'estandar')
            ->call('goTo', 'inventada')
            ->assertSet('activeSection', 'estandar');
    }

    public function test_las_maquetas_se_dibujan_dentro_de_la_pagina(): void
    {
        $html = Livewire::actingAs($this->userWithRole('admin'))->test(Tutorial::class)->html();

        $this->assertStringContainsString('/admin/sent-lists/display', $html);
        $this->assertStringContainsString('Confirmación de empaque', $html);
    }
}
