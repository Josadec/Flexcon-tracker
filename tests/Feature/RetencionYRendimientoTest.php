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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fases 6 y 7 — retención de 5 años y coste del tablero.
 *
 * La retención es sobre todo NO borrar antes de tiempo (eso lo cerró la Fase 0);
 * aquí se comprueban las herramientas y, sobre todo, que nadie haya añadido una
 * tarea que borre datos sola.
 *
 * El tablero se refresca entero cada 30 segundos: si el coste de cada refresco
 * crece sin tope, la pantalla de piso se cae sola con el tiempo.
 */
class RetencionYRendimientoTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        Role::findOrCreate('Produccion');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Produccion');

        return $user;
    }

    private function orden(string $sufijo = ''): WorkOrder
    {
        StatusWO::firstOrCreate(['name' => StatusWO::COMPLETED], ['color' => '#000']);
        StatusWO::firstOrCreate(['name' => StatusWO::CANCELLED], ['color' => '#000']);
        $abierto = StatusWO::firstOrCreate(['name' => StatusWO::OPEN], ['color' => '#10B981']);

        $part = Part::factory()->create();
        $po = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        return WorkOrder::factory()->create([
            'purchase_order_id' => $po->id, 'status_id' => $abierto->id, 'sent_pieces' => 0,
        ]);
    }

    // ===============================================
    // RETENCIÓN
    // ===============================================

    public function test_el_reporte_de_retencion_no_borra_nada(): void
    {
        $this->operador();
        $wo = $this->orden();
        Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'L-1', 'quantity' => 100,
            'status' => Lot::STATUS_PENDING, 'created_at' => now()->subYears(7),
        ]);

        $antes = Lot::count();

        $this->artisan('flexcon:retencion-reporte')->assertSuccessful();

        $this->assertSame($antes, Lot::count());
    }

    public function test_el_archivado_exporta_a_fichero_sin_borrar(): void
    {
        Storage::fake('local');
        $this->operador();
        $wo = $this->orden();

        $anio = now()->subYears(6)->year;
        $lot = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'L-VIEJO', 'quantity' => 100,
            'status' => Lot::STATUS_PENDING,
        ]);

        // Eloquent pisa `created_at` al crear: hay que envejecerlo a mano.
        DB::table('lots')->where('id', $lot->id)->update(['created_at' => now()->subYears(6)]);

        $this->artisan('flexcon:archivar', ['anio' => $anio])->assertSuccessful();

        Storage::assertExists("archivo/{$anio}/lots.json");
        Storage::assertExists("archivo/{$anio}/_archivo.txt");

        // Archivar es una copia: la base no se toca.
        $this->assertSame(1, Lot::where('lot_number', 'L-VIEJO')->count());
    }

    public function test_no_existe_ninguna_tarea_programada_que_borre_datos(): void
    {
        $programadas = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e) => $e->description ?? '')
            ->implode(' ');

        // Prueba-candado: `lots.work_order_id` borra en cascada, así que una
        // poda automática arrastraría pesadas e historial en silencio.
        foreach (['prune', 'purge', 'archivar', 'retencion-borrar'] as $peligrosa) {
            $this->assertStringNotContainsString($peligrosa, $programadas);
        }

        $this->assertFalse(config('retencion.borrado_automatico'));
    }

    public function test_la_politica_de_retencion_esta_escrita(): void
    {
        // El auditor pide el documento, no el código.
        $this->assertFileExists(base_path('docs/RETENCION.md'));
        $this->assertStringContainsString('5 años', file_get_contents(base_path('docs/RETENCION.md')));
    }

    // ===============================================
    // RENDIMIENTO DEL TABLERO
    // ===============================================

    public function test_el_tablero_no_crece_sin_tope(): void
    {
        $user = $this->operador();

        foreach (range(1, 65) as $i) {
            $wo = $this->orden();
            Lot::create([
                'work_order_id' => $wo->id, 'lot_number' => 'L-'.$i, 'quantity' => 10,
                'status' => Lot::STATUS_IN_PROGRESS,
            ]);
        }

        $componente = Livewire::actingAs($user)->test(ShippingListDisplay::class);
        $mostradas = collect($componente->viewData('workOrdersGrouped'))->flatten(1)->count();

        $this->assertLessThanOrEqual(60, $mostradas);
        $componente->assertSee('Se están mostrando las primeras');
    }

    public function test_el_tablero_no_dispara_una_consulta_por_renglon(): void
    {
        $user = $this->operador();

        foreach (range(1, 10) as $i) {
            $wo = $this->orden();
            foreach (range(1, 3) as $j) {
                Lot::create([
                    'work_order_id' => $wo->id, 'lot_number' => "L-{$i}-{$j}", 'quantity' => 10,
                    'status' => Lot::STATUS_IN_PROGRESS,
                ]);
            }
        }

        DB::enableQueryLog();
        Livewire::actingAs($user)->test(ShippingListDisplay::class)->assertOk();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Candado, no ideal. Con 10 órdenes y 30 viajeros el tablero venía
        // haciendo 1,016 consultas: los getters del modelo (`getQualityGoodPieces`
        // y compañía) consultaban de nuevo aunque la relación ya estuviera
        // cargada. Ahora usan la colección y bajó a ~226.
        //
        // Las que quedan son métodos del modelo que todavía consultan por su
        // cuenta dentro del bucle de la vista. Bajarlas más pide partir la
        // vista de 3,000 líneas, que es otro trabajo. El umbral está para que
        // una regresión nueva se note: si alguien vuelve a meter un N+1, esto
        // se dispara muy por encima de 300.
        $this->assertLessThan(300, $consultas, "El tablero ejecutó {$consultas} consultas.");
    }
}
