<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Lot;
use App\Models\PackagingCrimpWeighing;
use App\Models\PackagingPieceWeighing;
use App\Models\PackagingRecord;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\QualityWeighing;
use App\Models\SentList;
use App\Models\StatusWO;
use App\Models\User;
use App\Models\Weighing;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeder de PRUEBA del ciclo de vida: cierre, reapertura, historial y tablero.
 *
 * Arma tres listas de envío, cada una montada para ver funcionando una cosa
 * concreta de lo que se construyó en las fases 0–7. Es hermano de
 * CrimpFlowSeeder (que practica los pasos 5 y 6) y no lo pisa: usa su propio
 * prefijo de PO y su propia marca en `notes`.
 *
 * Es idempotente: borra su demo anterior antes de crear la nueva.
 *
 *   php artisan db:seed --class=CierreYReaperturaSeeder
 */
class CierreYReaperturaSeeder extends Seeder
{
    private const PO_PREFIX = 'CICLO-DEMO-';

    private const MARCA = 'CICLO_DEMO_SEED';

    private User $user;

    private int $statusAbierto;

    public function run(): void
    {
        $this->limpiarDemoAnterior();

        $this->user = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::query()->first()
            ?? User::factory()->create();

        // El seeder escribe historial: sin usuario autenticado, las entradas de
        // auditoría saldrían sin autor.
        Auth::login($this->user);

        $this->statusAbierto = StatusWO::where('name', StatusWO::OPEN)->value('id')
            ?? StatusWO::query()->value('id')
            ?? StatusWO::factory()->create(['name' => StatusWO::OPEN])->id;

        $listaCerrable = $this->escenarioListaCompleta();
        $listaIncompleta = $this->escenarioListaIncompleta();
        $listaFacturada = $this->escenarioFacturadoParaReabrir();

        $this->resumen($listaCerrable, $listaIncompleta, $listaFacturada);
    }

    /**
     * ESCENARIO 1 — Lista lista para cerrar.
     *
     * Dos viajeros, uno CRIMP y uno no-CRIMP, ambos con su empaque registrado
     * con la regla que le toca. En el tablero el botón «Cerrar lista» sale
     * verde y se puede pulsar.
     */
    private function escenarioListaCompleta(): SentList
    {
        $lista = $this->crearLista('A', 'Lista completa: se puede cerrar');

        // No-CRIMP: el empaque se mide con registros.
        $wo1 = $this->crearOrden($lista, 'A1', crimp: false, cantidad: 1000);
        $viajero1 = $this->crearViajero($wo1, 'DEMO-A1', 1000, produccion: 1000, calidad: 980);
        PackagingRecord::create([
            'lot_id' => $viajero1->id, 'available_pieces' => 980, 'packed_pieces' => 950,
            'surplus_pieces' => 30, 'packed_at' => now()->subHours(4), 'packed_by' => $this->user->id,
            'comments' => 'Empaque demo no-CRIMP.',
        ]);

        // CRIMP: el empaque se mide con pesadas, no con registros.
        $wo2 = $this->crearOrden($lista, 'A2', crimp: true, cantidad: 800);
        $viajero2 = $this->crearViajero($wo2, 'DEMO-A2', 800, produccion: 800, calidad: 800);
        PackagingPieceWeighing::create([
            'lot_id' => $viajero2->id, 'quantity' => 800, 'weight' => 0.560,
            'weighed_at' => now()->subHours(3), 'weighed_by' => $this->user->id,
        ]);
        PackagingCrimpWeighing::create([
            'lot_id' => $viajero2->id, 'quantity' => 800, 'weight' => 0.320,
            'weighed_at' => now()->subHours(3), 'weighed_by' => $this->user->id,
        ]);

        return $lista;
    }

    /**
     * ESCENARIO 2 — Lista que NO se puede cerrar todavía, y lo demás del tablero.
     *
     * Un viajero empacado, otro sin empacar (el botón sale gris con el motivo),
     * un viajero ya terminado (que desaparece salvo «Ver terminados») y una
     * orden con piezas pendientes sin viajeros abiertos (aviso «Crear viajeros»).
     */
    private function escenarioListaIncompleta(): SentList
    {
        $lista = $this->crearLista('B', 'Lista incompleta: falta empacar');

        $wo1 = $this->crearOrden($lista, 'B1', crimp: false, cantidad: 600);
        $viajero1 = $this->crearViajero($wo1, 'DEMO-B1', 600, produccion: 600, calidad: 600);
        PackagingRecord::create([
            'lot_id' => $viajero1->id, 'available_pieces' => 600, 'packed_pieces' => 600,
            'surplus_pieces' => 0, 'packed_at' => now()->subHours(2), 'packed_by' => $this->user->id,
        ]);

        // Sin empaque: es el que bloquea el cierre.
        $wo2 = $this->crearOrden($lista, 'B2', crimp: false, cantidad: 400);
        $this->crearViajero($wo2, 'DEMO-B2', 400, produccion: 400, calidad: 400);

        // Terminado: NO debe verse en el tablero salvo con «Ver terminados».
        $wo3 = $this->crearOrden($lista, 'B3', crimp: false, cantidad: 300);
        $viajero3 = $this->crearViajero($wo3, 'DEMO-B3-TERMINADO', 300, produccion: 300, calidad: 300);
        PackagingRecord::create([
            'lot_id' => $viajero3->id, 'available_pieces' => 300, 'packed_pieces' => 300,
            'surplus_pieces' => 0, 'packed_at' => now()->subDay(), 'packed_by' => $this->user->id,
        ]);
        // La decisión de cierre dispara el observer → queda terminado.
        $viajero3->update([
            'closure_decision' => Lot::CLOSURE_CLOSE_AS_IS,
            'closure_decided_by' => $this->user->id,
            'closure_decided_at' => now()->subDay(),
        ]);

        // Orden con piezas pendientes y SIN viajeros abiertos: aviso «Crear viajeros».
        $wo4 = $this->crearOrden($lista, 'B4', crimp: false, cantidad: 5000);
        $wo4->update(['sent_pieces' => 1500]);

        return $lista;
    }

    /**
     * ESCENARIO 3 — Viajero cerrado, despachado y FACTURADO.
     *
     * Es el caso para probar la reapertura en cascada: al reabrir el viajero,
     * el sistema avisa de que también hay que devolver a borrador la factura y
     * el packing slip, y lo hace todo junto.
     */
    private function escenarioFacturadoParaReabrir(): SentList
    {
        $lista = $this->crearLista('C', 'Facturado: para probar la reapertura');

        $wo = $this->crearOrden($lista, 'C1', crimp: false, cantidad: 500);
        $viajero = $this->crearViajero($wo, 'DEMO-C1-FACTURADO', 500, produccion: 500, calidad: 500);

        PackagingRecord::create([
            'lot_id' => $viajero->id, 'available_pieces' => 500, 'packed_pieces' => 500,
            'surplus_pieces' => 0, 'packed_at' => now()->subDays(3), 'packed_by' => $this->user->id,
        ]);

        $viajero->update([
            'closure_decision' => Lot::CLOSURE_COMPLETE_LOT,
            'closure_decided_by' => $this->user->id,
            'closure_decided_at' => now()->subDays(3),
        ]);

        $ps = PackingSlip::create([
            'ps_number' => 'PS-DEMO-01',
            'created_by' => $this->user->id,
            'status' => PackingSlip::STATUS_SHIPPED,
            'document_date' => now()->subDays(2)->toDateString(),
            'shipped_at' => now()->subDays(2),
            'shipped_by' => $this->user->id,
            'notes' => self::MARCA,
        ]);

        PackingSlipItem::create([
            'packing_slip_id' => $ps->id,
            'lot_id' => $viajero->id,
            'quantity_packed' => 500,
        ]);

        $factura = Invoice::create([
            'invoice_number' => 'INV-DEMO1',
            'status' => Invoice::STATUS_ISSUED,
            'invoice_date' => now()->subDay()->toDateString(),
            'issued_at' => now()->subDay(),
            'issued_by' => $this->user->id,
            'packing_slip_id' => $ps->id,
            'sold_to_address' => 'Tecate, B.C.',
            'shipped_to_address' => 'Tecate, B.C.',
            'notes' => self::MARCA,
        ]);

        $ps->update(['invoice_id' => $factura->id]);

        return $lista;
    }

    // ===============================================
    // HELPERS
    // ===============================================

    private function crearLista(string $sufijo, string $nota): SentList
    {
        $parte = $this->parte(false);
        $po = $this->crearPo($parte, self::PO_PREFIX.'LISTA-'.$sufijo, '90'.ord($sufijo), 1000);

        return SentList::create([
            'po_id' => $po->id,
            'status' => SentList::STATUS_PENDING,
            'current_department' => SentList::DEPT_SHIPPING, // en Empaque
            'shift_ids' => [],
            'num_persons' => 2,
            'start_date' => now()->startOfWeek()->toDateString(),
            'end_date' => now()->endOfWeek()->toDateString(),
            'total_available_hours' => 80,
            'used_hours' => 40,
            'remaining_hours' => 40,
            'materials_approved_at' => now()->subDays(4),
            'materials_approved_by' => $this->user->id,
            'inspection_approved_at' => now()->subDays(3),
            'inspection_approved_by' => $this->user->id,
            'production_approved_at' => now()->subDays(2),
            'production_approved_by' => $this->user->id,
            'quality_approved_at' => now()->subDay(),
            'quality_approved_by' => $this->user->id,
            'notes' => self::MARCA.' — '.$nota,
        ]);
    }

    private function crearOrden(SentList $lista, string $sufijo, bool $crimp, int $cantidad): WorkOrder
    {
        $parte = $this->parte($crimp);
        $po = $this->crearPo($parte, self::PO_PREFIX.$sufijo, '905'.ord($sufijo), $cantidad);

        return WorkOrder::create([
            'wo_number' => WorkOrder::generateWONumber(),
            'purchase_order_id' => $po->id,
            'sent_list_id' => $lista->id,
            'status_id' => $this->statusAbierto,
            'sent_pieces' => 0,
            'opened_date' => now()->subDays(5),
        ]);
    }

    /** Viajero con la cadena completa: material liberado → inspección → producción → calidad. */
    private function crearViajero(WorkOrder $wo, string $numero, int $cantidad, int $produccion, int $calidad): Lot
    {
        $lot = Lot::create([
            'work_order_id' => $wo->id,
            'lot_number' => $numero,
            'description' => $wo->purchaseOrder->part->description ?? 'Demo ciclo de vida',
            'quantity' => $cantidad,
            'status' => Lot::STATUS_IN_PROGRESS,
            'material_status' => 'released',
            'inspection_status' => Lot::INSPECTION_APPROVED,
            'inspection_completed_at' => now()->subDays(3),
            'inspection_completed_by' => $this->user->id,
        ]);

        Weighing::create([
            'lot_id' => $lot->id, 'kit_id' => null, 'quantity' => $produccion,
            'good_pieces' => $produccion, 'bad_pieces' => 0,
            'weighed_at' => now()->subDays(2), 'weighed_by' => $this->user->id,
        ]);

        QualityWeighing::create([
            'lot_id' => $lot->id, 'production_good_pieces' => $produccion,
            'good_pieces' => $calidad, 'bad_pieces' => max(0, $produccion - $calidad),
            'weighed_at' => now()->subDay(), 'weighed_by' => $this->user->id,
        ]);

        return $lot->refresh();
    }

    private function crearPo(Part $parte, string $numero, string $wo, int $cantidad): PurchaseOrder
    {
        return PurchaseOrder::firstOrCreate(
            ['po_number' => $numero],
            [
                'wo' => $wo,
                'part_id' => $parte->id,
                'po_date' => now()->subDays(10),
                'due_date' => now()->addDays(20),
                'quantity' => $cantidad,
                'unit_price' => 1.50,
                'status' => PurchaseOrder::STATUS_APPROVED,
                'comments' => self::MARCA,
            ]
        );
    }

    private function parte(bool $crimp): Part
    {
        return Part::where('is_crimp', $crimp)->inRandomOrder()->first()
            ?? Part::factory()->create(['is_crimp' => $crimp]);
    }

    /** Borra la demo anterior de este seeder, sin tocar la de CrimpFlowSeeder. */
    private function limpiarDemoAnterior(): void
    {
        $poIds = PurchaseOrder::withTrashed()->where('po_number', 'like', self::PO_PREFIX.'%')->pluck('id');
        $woIds = WorkOrder::withTrashed()->whereIn('purchase_order_id', $poIds)->pluck('id');
        $lotIds = Lot::withTrashed()->whereIn('work_order_id', $woIds)->pluck('id');

        Invoice::withTrashed()->where('notes', self::MARCA)->forceDelete();
        PackingSlipItem::whereIn('lot_id', $lotIds)->delete();
        PackingSlip::withTrashed()->where('notes', self::MARCA)->forceDelete();

        PackagingPieceWeighing::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        PackagingCrimpWeighing::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        PackagingRecord::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        QualityWeighing::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();
        Weighing::withTrashed()->whereIn('lot_id', $lotIds)->forceDelete();

        Lot::withTrashed()->whereIn('id', $lotIds)->forceDelete();
        SentList::withTrashed()->where('notes', 'like', self::MARCA.'%')->forceDelete();
        WorkOrder::withTrashed()->whereIn('id', $woIds)->forceDelete();
        PurchaseOrder::withTrashed()->whereIn('id', $poIds)->forceDelete();
    }

    private function resumen(SentList $a, SentList $b, SentList $c): void
    {
        $this->command?->newLine();
        $this->command?->info('Demo del ciclo de vida creada. Qué probar y dónde:');
        $this->command?->newLine();

        $this->command?->line("  <fg=green>1) CERRAR UNA LISTA</> — Lista #{$a->id}");
        $this->command?->line('     /admin/sent-lists/display');
        $this->command?->line('     Arriba, junto al título: el botón «Cerrar lista» de la #'.$a->id.' está VERDE (2/2).');
        $this->command?->line('     Lleva un viajero no-CRIMP (por registro) y uno CRIMP (por pesadas).');
        $this->command?->newLine();

        $this->command?->line("  <fg=yellow>2) LISTA QUE NO SE PUEDE CERRAR</> — Lista #{$b->id}");
        $this->command?->line('     El botón de la #'.$b->id.' sale GRIS: «faltan 1 por empacar» (DEMO-B2).');
        $this->command?->line('     Además: DEMO-B3-TERMINADO no se ve hasta pulsar «Ver terminados».');
        $this->command?->line('     Y la orden B4 avisa «Faltan 3,500 pz — crear viajeros».');
        $this->command?->newLine();

        $this->command?->line("  <fg=cyan>3) REABRIR ALGO FACTURADO</> — Lista #{$c->id}");
        $this->command?->line('     Viajero DEMO-C1-FACTURADO, con PS-DEMO-01 (despachado) e INV-DEMO1 (emitida).');
        $this->command?->line('     Abre su decisión en el tablero → «Reabrir»: avisa que también');
        $this->command?->line('     reabrirá la factura y el packing slip. Requiere rol admin y motivo.');
        $this->command?->newLine();

        $this->command?->line('  <fg=magenta>4) HISTORIAL</> — /admin/historial');
        $this->command?->line('     Busca «DEMO-» o el número de parte: ahí está todo lo que acaba de crear el seeder.');
        $this->command?->newLine();
    }
}
