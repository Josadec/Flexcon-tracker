<?php

namespace Tests\Feature;

use App\Livewire\Admin\CapacityWizard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de persistencia de la selección de POs en el modal "Cargar desde WOs" (Step 2).
 *
 * Regla de negocio: mientras NO se recargue la página, cerrar el modal (X / overlay /
 * Cancelar) debe CONSERVAR los POs ya marcados como borrador. El reset real solo ocurre
 * tras "Agregar Seleccionados" (addSelectedPOs) o al usar resetWizard().
 *
 * La lógica de selección (toggle/open/close) opera sobre propiedades públicas y no toca BD,
 * por lo que estas pruebas se centran en el estado del componente.
 */
class CapacityWizardPoSelectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Item mínimo de workOrderItems con un PO ya confirmado en la tabla.
     */
    private function confirmedItem(int $poId, int $configId): array
    {
        return [
            'po_id'         => $poId,
            'po_number'     => 'PO-' . $poId,
            'configuration' => ['id' => $configId],
        ];
    }

    /**
     * Cerrar el modal conserva la selección, y reabrirlo no la pisa (idempotente).
     */
    public function test_cerrar_el_modal_conserva_los_pos_seleccionados(): void
    {
        Livewire::test(CapacityWizard::class)
            ->call('openPOModal')
            ->assertSet('selectedPOs', [])
            // El usuario marca dos POs en el modal
            ->call('togglePOSelection', 101)
            ->call('togglePOSelection', 102)
            ->assertSet('selectedPOs', [101, 102])
            // Cierra con la X / overlay / Cancelar (todos llaman closePOModal)
            ->call('closePOModal')
            ->assertSet('showPOModal', false)
            // La selección sobrevive al cierre
            ->assertSet('selectedPOs', [101, 102])
            // Y al reabrir NO se pierde ni se duplica
            ->call('openPOModal')
            ->assertSet('showPOModal', true)
            ->assertSet('selectedPOs', [101, 102]);
    }

    /**
     * Al abrir, se pre-seleccionan los POs ya confirmados (workOrderItems) FUSIONÁNDOLOS
     * con el borrador del usuario, sin pisar lo que ya tenía marcado.
     */
    public function test_abrir_el_modal_fusiona_workorderitems_sin_pisar_el_borrador(): void
    {
        Livewire::test(CapacityWizard::class)
            // PO ya confirmado en la tabla con su configuración
            ->set('workOrderItems', [$this->confirmedItem(200, 9)])
            // Borrador previo del usuario (marcó el 101 y le eligió config 5)
            ->set('selectedPOs', [101])
            ->set('poConfigurations', [101 => 5])
            ->call('openPOModal')
            // El borrador se conserva y se le suma el PO confirmado
            ->assertSet('selectedPOs', [101, 200])
            // La config del borrador se respeta y se completa la del PO confirmado
            ->assertSet('poConfigurations', [101 => 5, 200 => 9]);
    }

    /**
     * Destildar un PO lo quita de la selección y borra su configuración asociada.
     */
    public function test_destildar_un_po_lo_quita_de_la_seleccion(): void
    {
        Livewire::test(CapacityWizard::class)
            ->set('selectedPOs', [101, 102])
            ->set('poConfigurations', [101 => 5, 102 => 7])
            ->call('togglePOSelection', 101)
            ->assertSet('selectedPOs', [102])
            ->assertSet('poConfigurations', [102 => 7]);
    }

    /**
     * resetWizard() ("empezar de cero") sí limpia el borrador de selección.
     */
    public function test_reset_wizard_limpia_la_seleccion(): void
    {
        Livewire::test(CapacityWizard::class)
            ->set('selectedPOs', [101, 102])
            ->set('poConfigurations', [101 => 5, 102 => 7])
            ->call('resetWizard')
            ->assertSet('selectedPOs', [])
            ->assertSet('poConfigurations', []);
    }
}
