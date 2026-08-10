<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users\UserList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersListScopeTest extends TestCase
{
    // Usuarios y Empleados leen la misma tabla `users`. Empleados es
    // User::role('employee'); Usuarios debe ser justo el complemento, o cada
    // empleado de planta aparece duplicado en las dos pantallas.

    use RefreshDatabase;

    private function makeUsers(): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        Role::findOrCreate('Produccion');

        $admin = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'Beto', 'last_name' => 'Ruiz']);
        $staff->assignRole('Produccion');

        $emp = User::factory()->create(['name' => 'Ciro', 'last_name' => 'Vega']);
        $emp->assignRole('employee');

        $noRole = User::factory()->create(['name' => 'Delia', 'last_name' => 'Soto']);

        return compact('admin', 'staff', 'emp', 'noRole');
    }

    public function test_employees_are_excluded_by_default(): void
    {
        ['admin' => $admin] = $this->makeUsers();

        Livewire::actingAs($admin)->test(UserList::class)
            ->assertOk()
            ->assertSee('Beto Ruiz')
            ->assertSee('Delia Soto')
            ->assertDontSee('Ciro Vega')
            // Métricas del ámbito: 3 del sistema (admin + staff + sin rol), 1 empleado.
            ->assertViewHas('totalUsers', 3)
            ->assertViewHas('usersWithRole', 2)
            ->assertViewHas('usersWithoutRole', 1)
            ->assertViewHas('employeeCount', 1);
    }

    public function test_scope_can_show_employees_or_everyone(): void
    {
        ['admin' => $admin] = $this->makeUsers();

        Livewire::actingAs($admin)->test(UserList::class)
            ->set('typeFilter', 'employee')
            ->assertSee('Ciro Vega')
            ->assertDontSee('Beto Ruiz')
            ->assertSee('Estás viendo empleados de planta')
            ->set('typeFilter', 'all')
            ->assertSee('Ciro Vega')
            ->assertSee('Beto Ruiz')
            ->call('clearFilters')
            ->assertSet('typeFilter', 'staff')
            ->assertDontSee('Ciro Vega');
    }

    public function test_export_follows_the_visible_scope(): void
    {
        ['admin' => $admin] = $this->makeUsers();

        $csv = function ($component) {
            ob_start();
            $component->instance()->exportCsv()->sendContent();
            return ob_get_clean();
        };

        $component = Livewire::actingAs($admin)->test(UserList::class);
        $this->assertStringNotContainsString('Ciro', $csv($component));

        $component = Livewire::actingAs($admin)->test(UserList::class)->set('typeFilter', 'all');
        $this->assertStringContainsString('Ciro', $csv($component));
    }

    public function test_page_survives_missing_employee_role(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $admin->assignRole('admin');
        Role::where('name', 'employee')->delete();

        Livewire::actingAs($admin)->test(UserList::class)
            ->assertOk()
            ->assertViewHas('employeeCount', 0);
    }
}
