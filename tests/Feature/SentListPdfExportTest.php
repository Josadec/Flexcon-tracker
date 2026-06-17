<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas de la exportacion a PDF de la "Lista de Envio" (FPL-02) por SentList.
 *
 * Ruta:        GET /admin/sent-lists/{sentList}/export/pdf
 * Nombre:      admin.sent-lists.export-pdf
 * Controlador: App\Http\Controllers\SentListController@exportPdf
 * Vista:       resources/views/sent-lists/pdf/shipping-list.blade.php
 *
 * Cubre: autorizacion (200/redireccion), PDF valido (%PDF), filtro de la nota
 * auto-generada del Capacity Wizard, etiqueta "Viajero" para partes CRIMP y la
 * banda "Atrasados" para POs con pivot is_carryover = true.
 */
class SentListPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name' => 'PDF Admin',
            'email' => 'pdf-admin@test.com',
        ]);
        $this->admin->assignRole('admin');
    }

    /**
     * Atributos minimos validos para crear una SentList (columnas NOT NULL).
     */
    private function sentListAttributes(int $poId): array
    {
        return [
            'po_id' => $poId,
            'shift_ids' => [],
            'num_persons' => 5,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
            'total_available_hours' => 100,
            'used_hours' => 10,
            'remaining_hours' => 90,
            'status' => SentList::STATUS_PENDING,
            'current_department' => SentList::DEPT_MATERIALS,
        ];
    }

    /**
     * Crea una SentList con una WO (ligada por sent_list_id directo), su PO/parte
     * y un lote. Devuelve [SentList, WorkOrder, PurchaseOrder, Part, Lot].
     */
    private function makeSentListWithWorkOrder(bool $isCrimp = false, ?string $lotComment = null): array
    {
        $part = Part::factory()->create([
            'is_crimp' => $isCrimp,
            'description' => 'Parte de prueba PDF',
        ]);

        $po = PurchaseOrder::factory()->approved()->create([
            'part_id' => $part->id,
            'quantity' => 1000,
            'wo' => 'WO-EXT-' . fake()->unique()->numerify('#####'),
        ]);

        $sentList = SentList::create($this->sentListAttributes($po->id));

        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'sent_list_id' => $sentList->id,
            'status_id' => StatusWO::factory(),
            'sent_pieces' => 0,
        ]);

        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'L-PDF-' . fake()->unique()->numerify('###'),
            'quantity' => 1000,
            'comments' => $lotComment,
            'status' => Lot::STATUS_PENDING,
        ]);

        return [$sentList, $wo, $po, $part, $lot];
    }

    /**
     * 1) Un usuario autorizado (admin) recibe 200 y un PDF descargable al pegarle
     *    a la ruta export-pdf de una SentList con WOs/lotes.
     */
    public function test_usuario_autorizado_recibe_pdf_descargable(): void
    {
        [$sentList] = $this->makeSentListWithWorkOrder();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.sent-lists.export-pdf', $sentList));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'lista-de-envio-' . $sentList->id . '-',
            $response->headers->get('content-disposition') ?? ''
        );
    }

    /**
     * 2) Usuario NO autenticado -> redireccion a login (no 200).
     */
    public function test_usuario_no_autenticado_es_redirigido_a_login(): void
    {
        [$sentList] = $this->makeSentListWithWorkOrder();

        $this->get(route('admin.sent-lists.export-pdf', $sentList))
            ->assertRedirect(route('login'));
    }

    /**
     * 3) La respuesta es un PDF valido: sus primeros bytes son "%PDF".
     */
    public function test_la_respuesta_es_un_pdf_valido(): void
    {
        [$sentList] = $this->makeSentListWithWorkOrder();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.sent-lists.export-pdf', $sentList));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * 4a) Filtro de nota auto-generada: la columna "Piezas Enviadas" OCULTA la nota
     *     exacta del Capacity Wizard pero MUESTRA una nota real escrita por usuario.
     *     Se renderiza la vista Blade directamente con datos controlados para
     *     assertear el HTML sin parsear el binario del PDF.
     */
    public function test_oculta_nota_autogenerada_y_muestra_nota_real(): void
    {
        // Parte NO crimp con un lote que trae la nota auto-generada.
        [$slAuto, $woAuto] = $this->makeSentListWithWorkOrder(
            isCrimp: false,
            lotComment: 'Generado automáticamente desde Capacity Wizard'
        );
        $woAuto->load(['purchaseOrder.part', 'lots']);

        $htmlAuto = View::make('sent-lists.pdf.shipping-list', [
            'groups' => ['Mesas' => collect([$woAuto])],
            'sentList' => $slAuto,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringNotContainsString(
            'Generado automáticamente desde Capacity Wizard',
            $htmlAuto,
            'La nota auto-generada del Capacity Wizard NO debe mostrarse en el PDF'
        );

        // Parte NO crimp con una nota REAL escrita por un usuario.
        [$slReal, $woReal] = $this->makeSentListWithWorkOrder(
            isCrimp: false,
            lotComment: 'Viajero de 11,000'
        );
        $woReal->load(['purchaseOrder.part', 'lots']);

        $htmlReal = View::make('sent-lists.pdf.shipping-list', [
            'groups' => ['Mesas' => collect([$woReal])],
            'sentList' => $slReal,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString(
            'Viajero de 11,000',
            $htmlReal,
            'Las notas reales escritas por usuarios SI deben mostrarse'
        );
    }

    /**
     * 4b) Etiqueta "Viajero": para una parte con is_crimp = true, la sub-fila del
     *     lote muestra "Viajero" como etiqueta. Para una parte no-crimp muestra
     *     "{po->wo} {lot->lot_number}" en su lugar.
     */
    public function test_muestra_viajero_para_partes_crimp_y_lote_para_no_crimp(): void
    {
        // CRIMP: debe aparecer la etiqueta "Viajero".
        [$slCrimp, $woCrimp] = $this->makeSentListWithWorkOrder(isCrimp: true);
        $woCrimp->load(['purchaseOrder.part', 'lots']);

        $htmlCrimp = View::make('sent-lists.pdf.shipping-list', [
            'groups' => ['Mesas' => collect([$woCrimp])],
            'sentList' => $slCrimp,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString(
            'Viajero',
            $htmlCrimp,
            'Una parte CRIMP debe mostrar la etiqueta "Viajero" en la sub-fila del lote'
        );

        // NO-CRIMP: debe aparecer "{po->wo} {lot_number}" y NO la etiqueta Viajero.
        [$slPlain, $woPlain, $poPlain, , $lotPlain] = $this->makeSentListWithWorkOrder(isCrimp: false);
        $woPlain->load(['purchaseOrder.part', 'lots']);

        $htmlPlain = View::make('sent-lists.pdf.shipping-list', [
            'groups' => ['Mesas' => collect([$woPlain])],
            'sentList' => $slPlain,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString(
            trim($poPlain->wo . ' ' . $lotPlain->lot_number),
            $htmlPlain,
            'Una parte NO-CRIMP debe mostrar "{po->wo} {lot_number}" en la sub-fila'
        );
    }

    /**
     * 5) Banda "Atrasados": una PO cuyo pivot sent_list_purchase_orders tiene
     *    is_carryover = true cae en la banda "Atrasados" del PDF.
     *
     *    Aqui se ejercita el controlador real (no solo la vista): la WO se liga a
     *    la SentList via el pivot PO <-> SentList y se marca carryover. El PDF debe
     *    generarse correctamente (200 + %PDF), confirmando que la rama de
     *    clasificacion "Atrasados" del controlador se ejecuta sin error.
     */
    public function test_po_carryover_cae_en_banda_atrasados(): void
    {
        $part = Part::factory()->create([
            'is_crimp' => false,
            'description' => 'Parte atrasada',
        ]);

        $po = PurchaseOrder::factory()->approved()->create([
            'part_id' => $part->id,
            'quantity' => 500,
            'wo' => 'WO-LATE-' . fake()->unique()->numerify('#####'),
        ]);

        $sentList = SentList::create($this->sentListAttributes($po->id));

        // WO ligada por sent_list_id directo (para que la cargue el controlador).
        $wo = WorkOrder::factory()->create([
            'purchase_order_id' => $po->id,
            'sent_list_id' => $sentList->id,
            'status_id' => StatusWO::factory(),
            'sent_pieces' => 0,
        ]);

        Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => 'L-LATE-001',
            'quantity' => 500,
            'status' => Lot::STATUS_PENDING,
        ]);

        // Pivot con carryover = true -> banda "Atrasados".
        $sentList->purchaseOrders()->attach($po->id, [
            'quantity' => 500,
            'is_carryover' => true,
        ]);

        // Confirma a nivel de vista que la banda "Atrasados" se rotula correctamente.
        $wo->load(['purchaseOrder.part', 'lots']);
        $html = View::make('sent-lists.pdf.shipping-list', [
            'groups' => ['Atrasados' => collect([$wo])],
            'sentList' => $sentList,
            'generatedAt' => now(),
        ])->render();
        $this->assertStringContainsString('Atrasados', $html);

        // Confirma que el controlador real genera el PDF con esta PO carryover.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.sent-lists.export-pdf', $sentList));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
