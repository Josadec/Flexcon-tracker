<?php

namespace Tests\Feature;

use App\Livewire\Admin\History\HistoryExplorer;
use App\Models\AuditTrail;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 5 — el historial se puede consultar.
 *
 * `audit_trails` se escribía y no se leía desde ninguna parte: no había
 * pantalla, ni ruta, ni vista. Para conservar 5 años por ISO, poder demostrar
 * lo que pasó es la mitad del requisito.
 */
class HistorialConsultableTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol = 'Calidad'): User
    {
        Role::findOrCreate($rol);
        $user = User::factory()->create([
            'name' => 'Ana', 'last_name' => 'López', 'email_verified_at' => now(),
        ]);
        $user->assignRole($rol);

        return $user;
    }

    private function escenario(): array
    {
        $part = Part::factory()->create(['number' => 'P-55113', 'description' => 'Manguera azul']);
        $po = PurchaseOrder::factory()->approved()->create([
            'part_id' => $part->id, 'quantity' => 1000, 'wo' => 'WO-2053940',
        ]);
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(), 'sent_pieces' => 0,
        ]);
        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-0042', 'quantity' => 500,
            'status' => Lot::STATUS_PENDING,
        ]);

        return [$wo, $lot, $part];
    }

    public function test_todos_los_departamentos_pueden_ver_el_historial(): void
    {
        foreach (['Calidad', 'Materiales', 'Produccion', 'Empaques', 'admin'] as $rol) {
            $user = $this->usuario($rol);

            $this->actingAs($user)
                ->get(route('admin.history.index'))
                ->assertOk()
                ->assertSee('Historial');
        }
    }

    public function test_el_historial_se_busca_por_el_wo_que_se_ve_en_pantalla(): void
    {
        $user = $this->usuario();
        $this->actingAs($user);
        [$wo, $lot] = $this->escenario();

        Livewire::actingAs($user)->test(HistoryExplorer::class)
            ->set('search', 'WO-2053940')
            ->assertOk()
            ->assertSee('Viajero');

        // Un WO que no existe no debe traer nada.
        Livewire::actingAs($user)->test(HistoryExplorer::class)
            ->set('search', 'WO-INEXISTENTE')
            ->assertSee('Nada coincide con esos filtros');
    }

    public function test_el_historial_se_busca_por_numero_y_descripcion_de_parte(): void
    {
        $user = $this->usuario();
        $this->actingAs($user);
        $this->escenario();

        foreach (['P-55113', 'Manguera'] as $termino) {
            Livewire::actingAs($user)->test(HistoryExplorer::class)
                ->set('search', $termino)
                ->assertDontSee('Nada coincide con esos filtros');
        }
    }

    public function test_el_historial_se_busca_por_numero_de_viajero(): void
    {
        $user = $this->usuario();
        $this->actingAs($user);
        $this->escenario();

        Livewire::actingAs($user)->test(HistoryExplorer::class)
            ->set('search', 'V-0042')
            ->assertDontSee('Nada coincide con esos filtros');
    }

    public function test_el_historial_se_busca_por_quien_hizo_el_cambio(): void
    {
        $user = $this->usuario();
        $this->actingAs($user);
        $this->escenario();

        Livewire::actingAs($user)->test(HistoryExplorer::class)
            ->set('search', 'Ana López')
            ->assertSee('Ana López');
    }

    public function test_el_historial_siempre_esta_paginado(): void
    {
        $user = $this->usuario();
        $this->actingAs($user);
        $this->escenario();

        $entradas = Livewire::actingAs($user)->test(HistoryExplorer::class)->viewData('entries');

        // Nunca un ->get() suelto: esta tabla crece durante 5 años.
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $entradas);
    }

    public function test_la_ficha_de_un_viajero_muestra_solo_su_historial(): void
    {
        $user = $this->usuario();
        $this->actingAs($user);
        [$wo, $lot] = $this->escenario();

        $otroLote = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-9999', 'quantity' => 10,
            'status' => Lot::STATUS_PENDING,
        ]);

        $entradas = Livewire::actingAs($user)
            ->test(HistoryExplorer::class, ['entityType' => Lot::class, 'entityId' => $lot->id])
            ->assertSee('Historial')
            ->viewData('entries');

        $this->assertGreaterThan(0, $entradas->total());
        foreach ($entradas as $entrada) {
            $this->assertSame($lot->id, $entrada->auditable_id);
        }
    }

    public function test_el_comando_importa_los_estados_antiguos_de_una_orden(): void
    {
        $user = $this->usuario('admin');
        $this->actingAs($user);
        [$wo] = $this->escenario();

        $abierto = StatusWO::firstOrCreate(['name' => StatusWO::OPEN], ['color' => '#10B981']);
        $cerrado = StatusWO::firstOrCreate(['name' => StatusWO::COMPLETED], ['color' => '#000']);

        DB::table('wo_status_logs')->insert([
            'work_order_id' => $wo->id, 'from_status_id' => $abierto->id, 'to_status_id' => $cerrado->id,
            'user_id' => $user->id, 'comments' => 'Cerrada al terminar la corrida.',
            'created_at' => now()->subYear(), 'updated_at' => now()->subYear(),
        ]);

        $this->artisan('flexcon:historial-importar')->assertSuccessful();

        $entrada = AuditTrail::where('action', 'legacy.wo_status')->firstOrFail();
        $this->assertSame($wo->id, $entrada->auditable_id);
        $this->assertSame(StatusWO::COMPLETED, $entrada->new_values['status']);
        $this->assertSame('Cerrada al terminar la corrida.', $entrada->new_values['motivo']);

        // Idempotente: correrlo dos veces no duplica.
        $this->artisan('flexcon:historial-importar')->assertSuccessful();
        $this->assertSame(1, AuditTrail::where('action', 'legacy.wo_status')->count());
    }

    public function test_el_historial_aparece_en_el_buscador_global(): void
    {
        $user = $this->usuario('admin');

        $pantallas = collect(\App\Support\AdminNavigation::for($user))->pluck('route');

        $this->assertTrue($pantallas->contains('admin.history.index'));
    }
}
