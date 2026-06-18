<?php

namespace Tests\Feature;

use App\Livewire\Admin\CapacityWizard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas del buscador live de la tabla resumen (Step 3) del Capacity Wizard.
 *
 * El computed filteredWorkOrderItems filtra workOrderItems EN MEMORIA por WO, PO,
 * número de parte o descripción (case-insensitive, coincidencia parcial), preservando
 * el índice original de cada item y SIN mutar workOrderItems ni la selección.
 */
class CapacityWizardSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dos items con valores distinguibles en cada uno de los 4 campos buscables.
     */
    private function items(): array
    {
        return [
            [
                'po_id'            => 500,
                'po_number'        => 'PO-500',
                'wo'               => 'WO-100',
                'part_number'      => 'PN-AAA',
                'part_description' => 'Widget azul',
            ],
            [
                'po_id'            => 600,
                'po_number'        => 'PO-600',
                'wo'               => 'WO-200',
                'part_number'      => 'PN-BBB',
                'part_description' => 'Gizmo rojo',
            ],
        ];
    }

    public function test_filtra_por_wo(): void
    {
        $component = Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', $this->items())
            ->set('itemSearchTerm', 'WO-100');

        $filtered = $component->instance()->filteredWorkOrderItems;

        $this->assertCount(1, $filtered);
        $this->assertSame('PO-500', reset($filtered)['po_number']);
    }

    public function test_filtra_por_po(): void
    {
        $component = Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', $this->items())
            ->set('itemSearchTerm', 'PO-600');

        $filtered = $component->instance()->filteredWorkOrderItems;

        $this->assertCount(1, $filtered);
        $this->assertSame('WO-200', reset($filtered)['wo']);
    }

    public function test_filtra_por_numero_de_parte(): void
    {
        $component = Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', $this->items())
            ->set('itemSearchTerm', 'PN-AAA');

        $filtered = $component->instance()->filteredWorkOrderItems;

        $this->assertCount(1, $filtered);
        $this->assertSame('PO-500', reset($filtered)['po_number']);
    }

    public function test_filtra_por_descripcion(): void
    {
        $component = Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', $this->items())
            ->set('itemSearchTerm', 'rojo');

        $filtered = $component->instance()->filteredWorkOrderItems;

        $this->assertCount(1, $filtered);
        $this->assertSame('PO-600', reset($filtered)['po_number']);
    }

    public function test_busqueda_es_case_insensitive(): void
    {
        $component = Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', $this->items())
            ->set('itemSearchTerm', 'wo-100');

        $this->assertCount(1, $component->instance()->filteredWorkOrderItems);
    }

    public function test_filtro_vacio_muestra_todos_los_items(): void
    {
        $component = Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', $this->items())
            ->set('itemSearchTerm', '');

        $this->assertCount(2, $component->instance()->filteredWorkOrderItems);
    }

    /**
     * El filtro preserva el índice original de cada item (clave para que las acciones
     * por fila —removeWorkOrderItem, openLotModal, openCrimpModal— reciban el índice correcto).
     */
    public function test_el_filtro_preserva_el_indice_original(): void
    {
        $component = Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', $this->items())
            ->set('itemSearchTerm', 'PO-600');

        $filtered = $component->instance()->filteredWorkOrderItems;

        // El segundo item (índice 1) es el único que coincide y conserva su key original.
        $this->assertSame([1], array_keys($filtered));
    }

    /**
     * El paso 3 renderiza sin error al filtrar (reproduce el render real del live search).
     */
    public function test_el_paso_3_renderiza_al_filtrar(): void
    {
        $item = [
            'part_id'          => 1,
            'part_number'      => 'PN-AAA',
            'part_description' => 'Widget azul',
            'is_crimp'         => false,
            'quantity'         => 100,
            'required_hours'   => 12.5,
            'po_id'            => 500,
            'po_number'        => 'PO-500',
            'wo'               => 'WO-100',
            'configuration'    => ['id' => 9],
        ];

        Livewire::test(CapacityWizard::class)
            ->set('currentStep', 3)
            ->set('startDate', '2026-06-15')
            ->set('endDate', '2026-06-19')
            ->set('workOrderItems', [$item])
            ->set('itemSearchTerm', 'WO-100')
            ->assertOk()
            ->assertSee('PN-AAA')
            ->set('itemSearchTerm', 'NO-EXISTE')
            ->assertOk()
            ->assertSee('No se encontraron POs');
    }

    /**
     * Filtrar NO debe mutar workOrderItems ni la selección de POs.
     */
    public function test_filtrar_no_altera_los_datos_ni_la_seleccion(): void
    {
        $items = $this->items();

        Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', $items)
            ->set('selectedPOs', [500, 600])
            ->set('poConfigurations', [500 => 5, 600 => 7])
            ->set('itemSearchTerm', 'WO-100')
            ->assertSet('workOrderItems', $items)
            ->assertSet('selectedPOs', [500, 600])
            ->assertSet('poConfigurations', [500 => 5, 600 => 7]);
    }
}
