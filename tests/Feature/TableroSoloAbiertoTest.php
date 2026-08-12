<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\ShippingListDisplay;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 — el tablero de piso enseña lo que falta por hacer, no el archivo.
 *
 * Regla del cliente: «se abrió una PO y WO por 100,000 pero la semana pasada
 * mandamos 31,000 con 3 lotes; la siguiente semana debería seguir activo ese WO
 * pero con 69,000, sin mostrar los lotes de la semana pasada».
 */
class TableroSoloAbiertoTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        Role::findOrCreate('Produccion');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Produccion');

        return $user;
    }

    /** Una orden de 100,000 piezas, como el ejemplo del cliente. */
    private function orden(int $cantidad = 100000): WorkOrder
    {
        StatusWO::firstOrCreate(['name' => StatusWO::COMPLETED], ['color' => '#000']);
        StatusWO::firstOrCreate(['name' => StatusWO::CANCELLED], ['color' => '#000']);
        $abierto = StatusWO::firstOrCreate(['name' => StatusWO::OPEN], ['color' => '#10B981']);

        $part = Part::factory()->create(['is_crimp' => false]);
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => $cantidad]);

        return WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => $abierto->id, 'sent_pieces' => 0,
        ]);
    }

    private function viajero(WorkOrder $wo, string $numero, bool $terminado = false): Lot
    {
        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => $numero, 'quantity' => 10000,
            'status' => Lot::STATUS_IN_PROGRESS,
        ]);

        if ($terminado) {
            $lot->update(['status' => Lot::STATUS_COMPLETED]);
        }

        return $lot;
    }

    private function woIdsVisibles($componente): array
    {
        return collect($componente->viewData('workOrdersGrouped'))
            ->flatten(1)->pluck('id')->all();
    }

    public function test_un_viajero_terminado_desaparece_aunque_su_wo_siga_abierta(): void
    {
        $user = $this->operador();
        $wo = $this->orden();
        $this->viajero($wo, 'V-SEMANA-PASADA', terminado: true);
        $this->viajero($wo, 'V-ESTA-SEMANA');

        Livewire::actingAs($user)->test(ShippingListDisplay::class)
            ->assertOk()
            ->assertSee('V-ESTA-SEMANA')
            ->assertDontSee('V-SEMANA-PASADA');
    }

    public function test_el_boton_ver_terminados_los_devuelve_a_la_vista(): void
    {
        $user = $this->operador();
        $wo = $this->orden();
        $this->viajero($wo, 'V-CERRADO', terminado: true);
        $this->viajero($wo, 'V-ABIERTO');

        Livewire::actingAs($user)->test(ShippingListDisplay::class)
            ->assertDontSee('V-CERRADO')
            ->call('toggleFinished')
            ->assertSet('showFinished', true)
            ->assertSee('V-CERRADO')
            ->assertSee('V-ABIERTO');
    }

    public function test_el_tablero_avisa_cuantos_terminados_esconde(): void
    {
        $user = $this->operador();
        $wo = $this->orden();
        $this->viajero($wo, 'V-1', terminado: true);
        $this->viajero($wo, 'V-2', terminado: true);
        $this->viajero($wo, 'V-3');

        $componente = Livewire::actingAs($user)->test(ShippingListDisplay::class);

        $this->assertSame(2, $componente->viewData('totalFinishedHidden'));
        $componente->assertSee('Ver terminados');
    }

    public function test_una_wo_cancelada_no_aparece_en_el_tablero(): void
    {
        $user = $this->operador();
        $wo = $this->orden();
        $this->viajero($wo, 'V-OK');

        $cancelada = $this->orden();
        $this->viajero($cancelada, 'V-CANCELADA');
        $cancelada->update(['status_id' => StatusWO::where('name', StatusWO::CANCELLED)->first()->id]);

        // Antes sólo se excluían las "Completed": las canceladas seguían
        // ocupando sitio en el tablero para siempre.
        $visibles = $this->woIdsVisibles(Livewire::actingAs($user)->test(ShippingListDisplay::class));

        $this->assertContains($wo->id, $visibles);
        $this->assertNotContains($cancelada->id, $visibles);
    }

    public function test_una_wo_con_piezas_pendientes_sigue_visible_sin_viajeros_abiertos(): void
    {
        $user = $this->operador();
        $wo = $this->orden(100000);

        // Se mandaron 31,000 en 3 viajeros ya cerrados. Quedan 69,000.
        foreach (['V-1', 'V-2', 'V-3'] as $numero) {
            $this->viajero($wo, $numero, terminado: true);
        }
        $wo->update(['sent_pieces' => 31000]);

        $componente = Livewire::actingAs($user)->test(ShippingListDisplay::class);

        // El lunes por la mañana la orden sigue viva: si desapareciera, nadie
        // sabría que hay que abrir los viajeros de la semana.
        $this->assertContains($wo->id, $this->woIdsVisibles($componente));
        $componente->assertSee('69,000')->assertSee('Crear viajeros');
    }

    public function test_una_wo_sin_piezas_pendientes_ni_viajeros_abiertos_desaparece(): void
    {
        $user = $this->operador();
        $wo = $this->orden(10000);
        $this->viajero($wo, 'V-UNICO', terminado: true);
        $wo->update(['sent_pieces' => 10000]);

        $visibles = $this->woIdsVisibles(Livewire::actingAs($user)->test(ShippingListDisplay::class));

        $this->assertNotContains($wo->id, $visibles);
    }

    /**
     * El caso que dejaba el tablero muerto: la ÚNICA orden con trabajo termina
     * de empacarse y ya no le faltan piezas. La orden sale de la vista (eso está
     * bien) pero el contador de escondidos se calculaba sólo sobre las órdenes
     * visibles —ninguna—, así que daba cero: ni barra, ni botón, ni pista. La
     * pantalla decía «No hay lotes» y no había forma de volver.
     */
    public function test_cuando_todo_esta_terminado_el_tablero_lo_anuncia(): void
    {
        $user = $this->operador();
        $wo = $this->orden(10000);
        $this->viajero($wo, 'V-UNICO', terminado: true);
        $wo->update(['sent_pieces' => 10000]);

        $componente = Livewire::actingAs($user)->test(ShippingListDisplay::class);

        $this->assertSame([], $this->woIdsVisibles($componente));
        $this->assertSame(1, $componente->viewData('totalFinishedHidden'));
        $componente->assertSee('Ver terminados');
    }

    /** Y el botón tiene que devolverla de verdad, no sólo prometerlo. */
    public function test_ver_terminados_recupera_una_wo_totalmente_terminada(): void
    {
        $user = $this->operador();
        $wo = $this->orden(10000);
        $this->viajero($wo, 'V-UNICO', terminado: true);
        $wo->update(['sent_pieces' => 10000]);

        $componente = Livewire::actingAs($user)->test(ShippingListDisplay::class)
            ->assertDontSee('V-UNICO')
            ->call('toggleFinished');

        $this->assertContains($wo->id, $this->woIdsVisibles($componente));
        $componente->assertSee('V-UNICO');
    }

    public function test_la_vista_enfocada_por_wo_sigue_mostrando_todo(): void
    {
        $user = $this->operador();
        $wo = $this->orden();
        $this->viajero($wo, 'V-CERRADO', terminado: true);

        // Al entrar a una orden concreta se quiere el expediente completo.
        // Se pasa el id, no el modelo: `mount()` hace `WorkOrder::find()`, y un
        // modelo es Arrayable, así que find() resolvería findMany() y devolvería
        // una colección. La ruta real pasa el id de la URL.
        Livewire::actingAs($user)->test(ShippingListDisplay::class, ['workOrder' => $wo->id])
            ->assertSee('V-CERRADO');
    }

    public function test_los_pendientes_no_cuentan_viajeros_terminados(): void
    {
        $user = $this->operador();
        $wo = $this->orden();
        $this->viajero($wo, 'V-CERRADO', terminado: true);

        $resumen = Livewire::actingAs($user)->test(ShippingListDisplay::class)
            ->viewData('lifecycleSummary');

        // Un viajero terminado no le debe trabajo a nadie.
        $this->assertSame(0, array_sum($resumen));
    }
}
