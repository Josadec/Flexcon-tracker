<?php

namespace Tests\Feature;

use App\Livewire\Admin\SentLists\SentListPackagingView;
use App\Mail\EmpaqueTerminadoCrimpViajero;
use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * M9 — Correo "Empaque terminado CRIMP Viajero": disparo manual, destinatarios por
 * rol (Empaques + Materiales), historial en el viajero y contenido del Mailable.
 */
class CrimpPackagingNotifyTest extends TestCase
{
    use RefreshDatabase;

    private function makeViajeroWithPackaging(User $packer): array
    {
        $part = Part::factory()->create(['is_crimp' => true]);
        $po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);

        $sentList = SentList::create([
            'po_id'                 => $po->id,
            'status'                => SentList::STATUS_PENDING,
            'shift_ids'             => [],
            'num_persons'           => 1,
            'start_date'            => now()->toDateString(),
            'end_date'              => now()->toDateString(),
            'total_available_hours' => 0,
            'used_hours'            => 0,
            'remaining_hours'       => 0,
        ]);

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'status_id'         => StatusWO::factory(),
            'sent_pieces'       => 0,
            'sent_list_id'      => $sentList->id,
        ]);

        $viajero = Lot::create([
            'work_order_id' => $wo->id, 'lot_number' => 'V-9', 'quantity' => 1000, 'status' => Lot::STATUS_PENDING,
        ]);

        CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 1000]);
        PackagingPieceWeighing::create(['lot_id' => $viajero->id, 'quantity' => 600, 'weighed_at' => now(), 'weighed_by' => $packer->id]);
        PackagingCrimpWeighing::create(['lot_id' => $viajero->id, 'quantity' => 580, 'weighed_at' => now(), 'weighed_by' => $packer->id]);

        return [$sentList, $viajero];
    }

    public function test_confirm_and_notify_sends_mail_and_records_history(): void
    {
        Mail::fake();

        Role::firstOrCreate(['name' => 'Empaques', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Materiales', 'guard_name' => 'web']);

        $empaques = User::factory()->create(['email' => 'empaques@flexcon.test']);
        $empaques->assignRole('Empaques');
        $materiales = User::factory()->create(['email' => 'materiales@flexcon.test']);
        $materiales->assignRole('Materiales');

        $packer = User::factory()->create(['email' => 'packer@flexcon.test']);
        $this->actingAs($packer);

        [$sentList, $viajero] = $this->makeViajeroWithPackaging($packer);

        Livewire::test(SentListPackagingView::class, ['sentList' => $sentList])
            ->call('openNotifyModal', $viajero->id)
            ->set('notifyLabelCount', 24)
            ->set('notifyComments', 'Empaque OK')
            ->call('confirmAndNotify')
            ->assertHasNoErrors();

        // Historial registrado en el viajero
        $viajero->refresh();
        $this->assertNotNull($viajero->packaging_notified_at);
        $this->assertSame(24, $viajero->packaging_label_count);
        $this->assertSame($packer->id, $viajero->packaging_notified_by);

        // Correo enviado a Empaques + Materiales + empacadora
        Mail::assertSent(EmpaqueTerminadoCrimpViajero::class, function ($mail) {
            return $mail->hasTo('empaques@flexcon.test')
                && $mail->hasTo('materiales@flexcon.test')
                && $mail->hasTo('packer@flexcon.test');
        });
    }

    public function test_mailable_renders_summary(): void
    {
        $packer = User::factory()->create();
        [, $viajero] = $this->makeViajeroWithPackaging($packer);

        $mailable = new EmpaqueTerminadoCrimpViajero(
            viajero: $viajero,
            labelCount: 24,
            extraComments: 'Empaque OK',
            packerName: 'Empacadora Uno',
        );

        $mailable->assertHasSubject('Empaque terminado CRIMP — Viajero V-9');
        $mailable->assertSeeInHtml('Empacadora Uno');
        $mailable->assertSeeInHtml('Empaque OK');
        $mailable->assertSeeInHtml('600'); // piezas completadas
        $mailable->assertSeeInHtml('580'); // CRIMP completados
    }
}
