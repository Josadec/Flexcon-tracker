<?php

namespace Tests\Feature;

use App\Livewire\Admin\CapacityWizard;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StatusWO;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de la migración de Kits → Lotes de CRIMP en el Capacity Wizard / Step 3.
 * El componente se maneja seteando sus propiedades públicas directamente (workOrderItems,
 * lotNumbers, crimpLots) y llamando a generateSentList(), que es la lógica de persistencia.
 */
class CapacityWizardCrimpLotTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un PO aprobado con su Work Order y devuelve el item del wizard listo para usar.
     */
    private function makeItem(bool $isCrimp): array
    {
        $part = Part::factory()->create([
            'is_crimp' => $isCrimp,
            'description' => 'Descripción de prueba',
        ]);

        $po = PurchaseOrder::factory()->approved()->create([
            'part_id' => $part->id,
            'quantity' => 1000,
            'wo' => 'WO-TEST-' . fake()->unique()->numerify('#####'),
        ]);

        WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id' => StatusWO::factory(),
            'sent_pieces' => 0,
        ]);

        return [
            'part_id'          => $part->id,
            'part_number'      => $part->number,
            'part_description' => $part->description,
            'is_crimp'         => $isCrimp,
            'quantity'         => 1000,
            'required_hours'   => 10.0,
            'po_id'            => $po->id,
            'po_number'        => $po->po_number,
            'wo'               => $po->wo,
            'is_carryover'     => false,
            'original_qty'     => 1000,
            'sent_pieces'      => 0,
            'configuration'    => ['id' => 1],
        ];
    }

    /**
     * CRIMP: genera una lista con una parte crimp, un viajero y N lotes de CRIMP.
     * Verifica que se crean filas en crimp_lots con el lot_id correcto del viajero,
     * con lote_fabricante persistido y también con lote_fabricante null (opcional).
     */
    public function test_crimp_genera_lotes_de_crimp_colgando_del_viajero(): void
    {
        $item = $this->makeItem(isCrimp: true);

        Livewire::test(CapacityWizard::class)
            ->set('selectedShifts', [])
            ->set('numPersons', 5)
            ->set('totalAvailableHours', 100)
            ->set('totalRequiredHours', 10)
            ->set('remainingHours', 90)
            ->set('scheduledShipDate', '2026-07-01')
            ->set('workOrderItems', [$item])
            ->set('lotNumbers', [
                0 => [['number' => 'L-100', 'quantity' => 1000]],
            ])
            ->set('crimpLots', [
                0 => [
                    ['lot_ref' => 'L-100', 'number' => 'CL-1', 'lote_fabricante' => 'F-22', 'quantity' => 400, 'comments' => 'primero'],
                    ['lot_ref' => 'L-100', 'number' => 'CL-2', 'lote_fabricante' => '', 'quantity' => 600, 'comments' => ''],
                ],
            ])
            ->call('generateSentList')
            ->assertSet('errorMessage', '');

        $viajero = Lot::where('lot_number', 'L-100')->firstOrFail();

        $this->assertSame(2, CrimpLot::where('lot_id', $viajero->id)->count());

        $cl1 = CrimpLot::where('crimp_lot_number', 'CL-1')->firstOrFail();
        $this->assertSame($viajero->id, $cl1->lot_id);
        $this->assertSame('F-22', $cl1->lote_fabricante);
        $this->assertSame(400, $cl1->quantity);

        $cl2 = CrimpLot::where('crimp_lot_number', 'CL-2')->firstOrFail();
        $this->assertSame($viajero->id, $cl2->lot_id);
        $this->assertNull($cl2->lote_fabricante, 'lote_fabricante debe ser opcional (null)');
        $this->assertSame(600, $cl2->quantity);
    }

    /**
     * NO-CRIMP (regresión): genera una lista con is_crimp = false.
     * No se debe crear ningún CrimpLot y el Lot se crea igual que antes.
     */
    public function test_no_crimp_no_crea_crimp_lots_y_crea_el_lote_normal(): void
    {
        $item = $this->makeItem(isCrimp: false);

        Livewire::test(CapacityWizard::class)
            ->set('selectedShifts', [])
            ->set('numPersons', 5)
            ->set('totalAvailableHours', 100)
            ->set('totalRequiredHours', 10)
            ->set('remainingHours', 90)
            ->set('scheduledShipDate', '2026-07-01')
            ->set('workOrderItems', [$item])
            ->set('lotNumbers', [
                0 => [['number' => 'L-200', 'quantity' => 1000]],
            ])
            // Aunque se pasen crimpLots, no deben persistirse porque is_crimp = false
            ->set('crimpLots', [
                0 => [['lot_ref' => 'L-200', 'number' => 'CL-X', 'lote_fabricante' => 'F-1', 'quantity' => 100, 'comments' => '']],
            ])
            ->call('generateSentList')
            ->assertSet('errorMessage', '');

        $this->assertSame(0, CrimpLot::count(), 'NO-CRIMP no debe crear lotes de CRIMP');
        $this->assertDatabaseHas('lots', ['lot_number' => 'L-200', 'quantity' => 1000]);
    }

    /**
     * Idempotencia: no se duplican crimp_lots con el mismo (lot_id, crimp_lot_number)
     * al generar la lista dos veces con el mismo número de lote de CRIMP.
     */
    public function test_idempotencia_no_duplica_crimp_lots(): void
    {
        $item = $this->makeItem(isCrimp: true);

        $state = [
            'selectedShifts'      => [],
            'numPersons'          => 5,
            'totalAvailableHours' => 100,
            'totalRequiredHours'  => 10,
            'remainingHours'      => 90,
            'scheduledShipDate'   => '2026-07-01',
            'workOrderItems'      => [$item],
            'lotNumbers'          => [0 => [['number' => 'L-300', 'quantity' => 1000]]],
            'crimpLots'           => [0 => [['lot_ref' => 'L-300', 'number' => 'CL-DUP', 'lote_fabricante' => 'F-9', 'quantity' => 500, 'comments' => '']]],
        ];

        // Primera generación
        Livewire::test(CapacityWizard::class)
            ->set($state)
            ->call('generateSentList')
            ->assertSet('errorMessage', '');

        // Segunda generación con los mismos datos
        Livewire::test(CapacityWizard::class)
            ->set($state)
            ->call('generateSentList')
            ->assertSet('errorMessage', '');

        $viajero = Lot::where('lot_number', 'L-300')->firstOrFail();
        $this->assertSame(
            1,
            CrimpLot::where('lot_id', $viajero->id)->where('crimp_lot_number', 'CL-DUP')->count(),
            'No deben duplicarse lotes de CRIMP con el mismo (lot_id, crimp_lot_number)'
        );
    }

    /**
     * Validación: validateLotCrimpQuantities() rechaza quantity < 1 en un lote de CRIMP.
     */
    public function test_validacion_rechaza_cantidad_menor_a_uno_en_lote_de_crimp(): void
    {
        $item = $this->makeItem(isCrimp: true);

        Livewire::test(CapacityWizard::class)
            ->set('selectedShifts', [])
            ->set('numPersons', 5)
            ->set('totalAvailableHours', 100)
            ->set('totalRequiredHours', 10)
            ->set('remainingHours', 90)
            ->set('scheduledShipDate', '2026-07-01')
            ->set('workOrderItems', [$item])
            ->set('lotNumbers', [
                0 => [['number' => 'L-400', 'quantity' => 1000]],
            ])
            ->set('crimpLots', [
                0 => [['lot_ref' => 'L-400', 'number' => 'CL-BAD', 'lote_fabricante' => '', 'quantity' => 0, 'comments' => '']],
            ])
            ->call('generateSentList')
            ->assertSet('errorMessage', function ($value) {
                return is_string($value) && str_contains($value, 'cada lote de CRIMP debe tener una cantidad mayor a 0');
            });

        $this->assertSame(0, CrimpLot::count(), 'No debe persistirse un lote de CRIMP inválido');
    }

    /**
     * Item mínimo para renderizar una fila del Step 3 (sin tocar BD).
     */
    private function renderItem(bool $isCrimp): array
    {
        return [
            'is_crimp'         => $isCrimp,
            'wo'               => 'WO-1',
            'po_number'        => 'PO-1',
            'part_number'      => '189-10492',
            'part_description' => 'Parte de prueba',
            'quantity'         => 1000,
            'required_hours'   => 10.0,
        ];
    }

    /**
     * Opción B: si la lista preliminar contiene una parte CRIMP, la columna de la unidad
     * principal (Lot) se rotula "Viajeros" y la celda vacía dice "Sin viajeros".
     * "Sin viajeros" solo se renderiza cuando $listHasCrimp = true, así que es señal inequívoca.
     */
    public function test_step3_usa_nomenclatura_viajeros_cuando_la_lista_tiene_crimp(): void
    {
        Livewire::test(CapacityWizard::class)
            ->set('currentStep', 3)
            ->set('workOrderItems', [$this->renderItem(isCrimp: true)])
            ->assertSee('Sin viajeros')
            ->assertDontSee('Sin lotes</span>');
    }

    /**
     * Regresión NO-CRIMP: sin partes CRIMP, la columna sigue siendo "Lotes" y la celda
     * vacía dice "Sin lotes"; nunca debe aparecer la nomenclatura de viajeros en esa celda.
     */
    public function test_step3_usa_nomenclatura_lotes_cuando_la_lista_no_tiene_crimp(): void
    {
        Livewire::test(CapacityWizard::class)
            ->set('currentStep', 3)
            ->set('workOrderItems', [$this->renderItem(isCrimp: false)])
            ->assertSee('Sin lotes')
            ->assertDontSee('Sin viajeros');
    }
}
