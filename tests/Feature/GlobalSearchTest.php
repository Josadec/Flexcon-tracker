<?php

namespace Tests\Feature;

use App\Livewire\Admin\GlobalSearch;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Buscador global (Ctrl+K): salta a un registro o a una pantalla del menú sin
 * tener que adivinar en qué módulo vive.
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        Role::findOrCreate($rol);
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'López']);
        $user->assignRole($rol);

        return $user;
    }

    private function viajero(string $numero = 'V-0042'): Lot
    {
        $part = Part::factory()->create(['is_crimp' => false, 'number' => 'P-'.fake()->unique()->numerify('#####')]);
        $po = PurchaseOrder::factory()->approved()->create([
            'part_id' => $part->id, 'quantity' => 1000, 'wo' => 'WO-'.fake()->unique()->numerify('#####'),
        ]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);

        return Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 500,
            'status' => Lot::STATUS_PENDING,
        ]);
    }

    public function test_con_menos_de_dos_letras_no_busca_nada(): void
    {
        $user = $this->usuario('admin');
        $this->viajero();

        Livewire::actingAs($user)->test(GlobalSearch::class)
            ->set('q', 'V')
            ->assertOk()
            ->assertSee('¿Qué estás buscando?');
    }

    public function test_encuentra_un_viajero_por_su_numero(): void
    {
        $user = $this->usuario('admin');
        $lot = $this->viajero('V-0042');

        Livewire::actingAs($user)->test(GlobalSearch::class)
            ->set('q', '0042')
            ->assertSee('Viajero V-0042')
            ->assertSeeHtml(route('admin.lots.show', $lot));
    }

    public function test_encuentra_pantallas_del_menu_sin_acentos(): void
    {
        $user = $this->usuario('admin');

        Livewire::actingAs($user)->test(GlobalSearch::class)
            // "inspeccion" sin acento tiene que encontrar "Inspección".
            ->set('q', 'inspeccion')
            ->assertSee('Inspección')
            ->assertSeeHtml(route('admin.quality.inspection'));
    }

    public function test_encuentra_una_parte_por_su_descripcion(): void
    {
        $user = $this->usuario('admin');
        Part::factory()->create(['number' => 'X-900', 'description' => 'Manguera reforzada azul']);

        Livewire::actingAs($user)->test(GlobalSearch::class)
            ->set('q', 'reforzada')
            ->assertSee('Parte X-900');
    }

    public function test_solo_ofrece_lo_que_el_rol_deja_ver(): void
    {
        $empaques = $this->usuario('Empaques');
        $this->viajero('V-0042');

        $componente = Livewire::actingAs($empaques)->test(GlobalSearch::class)->set('q', '0042');

        // Los viajeros viven en una ruta de sólo admin: ofrecerlos a Empaques
        // sería mandarlos a un 403.
        $grupos = collect($componente->instance()->groups)->pluck('title');
        $this->assertFalse($grupos->contains('Viajeros'));
        $this->assertFalse($grupos->contains('Usuarios'));
    }

    public function test_el_menu_de_un_area_no_muestra_pantallas_de_otra(): void
    {
        $calidad = $this->usuario('Calidad');

        $pantallas = collect(AdminNavigation::for($calidad))->pluck('route');

        $this->assertTrue($pantallas->contains('admin.quality.inspection'));
        $this->assertFalse($pantallas->contains('admin.users.index'), 'Calidad no administra usuarios.');
        $this->assertFalse($pantallas->contains('admin.packaging.manage'), 'Calidad no gestiona empaque.');
    }

    public function test_todas_las_pantallas_del_catalogo_existen(): void
    {
        // Si alguien renombra o borra una ruta, el catálogo tiene que enterarse
        // aquí y no en la cara del usuario con un error 500.
        foreach (AdminNavigation::screens() as $pantalla) {
            $this->assertNotNull(
                Route::getRoutes()->getByName($pantalla['route']),
                "La ruta {$pantalla['route']} del catálogo de navegación no existe."
            );
        }
    }

    public function test_el_rotulo_de_area_lleva_al_panel_del_area(): void
    {
        $user = $this->usuario('admin');

        $this->assertSame('admin.quality.index', AdminNavigation::eyebrowRoute('Calidad'));
        $this->assertSame('admin.quality.index', AdminNavigation::eyebrowRoute('Área · Calidad'));
        $this->assertSame('admin.dashboard', AdminNavigation::eyebrowRoute('Administración'));
        // Los rótulos de las maquetas del tutorial no llevan a ningún lado.
        $this->assertNull(AdminNavigation::eyebrowRoute('Opción 1'));

        $this->assertTrue(AdminNavigation::canAccess('admin.quality.index', $user));
    }

    public function test_el_rotulo_se_dibuja_como_enlace_en_la_pantalla(): void
    {
        $user = $this->usuario('admin');

        $this->actingAs($user)->get(route('admin.users.index'))
            ->assertOk()
            // "ADMINISTRACIÓN" deja de ser texto muerto: vuelve al panel.
            ->assertSeeHtml('href="'.route('admin.dashboard').'"');
    }

    public function test_las_migas_se_deducen_de_la_ruta(): void
    {
        $user = $this->usuario('admin');

        $migas = fn (string $ruta) => collect(AdminNavigation::breadcrumb($user, $ruta))->pluck('label')->all();

        $this->assertSame(['Inicio'], $migas('admin.dashboard'));
        $this->assertSame(['Inicio', 'Administración', 'Usuarios'], $migas('admin.users.index'));
        // Una subpantalla que no está en el catálogo cae en su familia y añade
        // el paso: así las 89 pantallas tienen migas sin declararlas.
        $this->assertSame(['Inicio', 'Administración', 'Usuarios', 'Nuevo'], $migas('admin.users.create'));
        $this->assertSame(['Inicio', 'Administración', 'Usuarios', 'Editar'], $migas('admin.users.edit'));
        $this->assertSame(['Inicio', 'Calidad', 'Inspección'], $migas('admin.quality.inspection'));
    }

    public function test_las_migas_no_enlazan_a_donde_el_rol_no_entra(): void
    {
        $calidad = $this->usuario('Calidad');

        $migas = AdminNavigation::breadcrumb($calidad, 'admin.quality.inspection');

        // Calidad no entra al dashboard general: no debe aparecer "Inicio".
        $this->assertNotContains('Inicio', collect($migas)->pluck('label')->all());
        $this->assertSame(['Calidad', 'Inspección'], collect($migas)->pluck('label')->all());
    }

    public function test_la_barra_superior_va_al_lado_del_sidebar_no_encima(): void
    {
        $user = $this->usuario('admin');

        $html = $this->actingAs($user)->get(route('admin.users.index'))->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $sidebar = $xpath->query('//*[@data-flux-sidebar]')->item(0);
        $this->assertNotNull($sidebar, 'No se encontró el sidebar en la página.');

        $siguiente = $sidebar->nextSibling;
        while ($siguiente && $siguiente->nodeType !== XML_ELEMENT_NODE) {
            $siguiente = $siguiente->nextSibling;
        }

        // Flux sólo coloca el header en la columna de al lado con el selector
        // `[data-flux-sidebar]+[data-flux-header]`. Si algo se cuela entre los
        // dos, el header se monta ENCIMA del sidebar a todo el ancho — falla
        // en silencio, sólo se nota mirando la pantalla. De ahí este test.
        $this->assertNotNull($siguiente, 'El sidebar no tiene ningún hermano después.');
        $this->assertTrue(
            $siguiente->hasAttribute('data-flux-header'),
            'El header debe ser el hermano inmediato del sidebar; se coló un <'.$siguiente->nodeName.'> en medio.'
        );
    }

    public function test_la_barra_superior_trae_migas_buscador_y_perfil(): void
    {
        $user = $this->usuario('admin');

        $this->actingAs($user)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Ruta de navegación')     // el nav de migas
            ->assertSee('Ctrl K')                 // el botón del buscador
            ->assertSee('Cerrar sesión');         // el menú de perfil
    }
}
