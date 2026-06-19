<?php

namespace Tests\Feature;

use App\Livewire\Admin\CapacityWizard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas del comentario opcional al crear un Lote/Viajero en el Capacity Wizard
 * (paso 3, modal de Lote). El comentario viaja en tempLots[*]['comment'] y al
 * guardar (saveLots) se conserva en lotNumbers[$index], desde donde se persiste
 * en lots.comments al ejecutar el wizard.
 */
class CapacityWizardLotCommentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * saveLots conserva el comentario capturado en el modal dentro de lotNumbers.
     */
    public function test_savelots_preserva_comentario_del_lote(): void
    {
        Livewire::test(CapacityWizard::class)
            ->set('workOrderItems', [['quantity' => 1000]])
            ->set('currentLotIndex', 0)
            ->set('tempLots', [
                ['number' => 'L-1', 'quantity' => 500, 'comment' => 'Mi comentario'],
            ])
            ->call('saveLots')
            ->assertSet('lotNumbers.0.0.comment', 'Mi comentario')
            ->assertSet('lotNumbers.0.0.number', 'L-1');
    }

    /**
     * addLotInput y openLotModal inicializan la fila con la clave 'comment' vacía,
     * para que el wire:model del input exista desde el inicio.
     */
    public function test_filas_nuevas_incluyen_clave_comment(): void
    {
        $component = Livewire::test(CapacityWizard::class)
            ->call('addLotInput');

        $tempLots = $component->get('tempLots');
        $last = end($tempLots);

        $this->assertArrayHasKey('comment', $last);
        $this->assertSame('', $last['comment']);
    }
}
