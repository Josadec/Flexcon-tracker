<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\PackingSlip;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reabrir lo que ya se cerró, en un solo sitio.
 *
 * El cliente fue explícito: se tiene que poder corregir todo, «desde una PO
 * hasta una Invoice», incluso con la factura ya emitida, porque hay errores de
 * comunicación con el cliente. Pero sólo Administración, y con motivo escrito.
 *
 * Antes esto vivía repartido en seis implementaciones que ya divergían: una
 * limpiaba la cola de despacho y otra no (dejando «viajeros fantasma» listos
 * para facturar), sólo una escribía auditoría, y ninguna pedía un motivo.
 *
 * La reapertura es EN CASCADA, no bloqueante: si el viajero está en una factura
 * emitida, no se responde «no se puede», se responde «esto implica reabrir
 * también la factura y el packing slip» y se ejecuta todo o nada.
 */
class ReopeningService
{
    /** Permiso que hace falta para cualquier reapertura. */
    public const PERMISSION = 'administracion.reopen-records';

    /** Longitud mínima del motivo: «error» no le sirve a nadie dentro de un año. */
    public const MIN_REASON = 10;

    public function __construct(private readonly AuditTrailService $auditTrail) {}

    /**
     * Qué se va a reabrir además del viajero, en orden de arriba a abajo.
     *
     * Se calcula ANTES de tocar nada para poder enseñárselo a quien decide.
     *
     * @return array<int, string>
     */
    public function cascadeFor(Lot $lot): array
    {
        $cadena = [];

        $packingSlip = $lot->packingSlipItem?->packingSlip;

        if (! $packingSlip) {
            return $cadena;
        }

        $invoice = $this->invoiceOf($packingSlip);

        if ($invoice && ! $invoice->isDraft()) {
            $cadena[] = 'la factura '.($invoice->invoice_number ?? '#'.$invoice->id)
                .' ('.$invoice->status.' → borrador)';
        }

        if ($packingSlip->status !== PackingSlip::STATUS_DRAFT) {
            $cadena[] = 'el packing slip '.($packingSlip->ps_number ?? '#'.$packingSlip->id)
                .' ('.$packingSlip->status.' → borrador)';
        }

        $cadena[] = 'la salida del viajero '.$lot->lot_number.' del packing slip';

        return $cadena;
    }

    /**
     * Reabre un viajero y, si hace falta, su packing slip y su factura.
     *
     * @throws \RuntimeException si falta permiso, motivo o el viajero no se puede reabrir
     */
    public function reopenLot(Lot $lot, string $reason, ?User $actor = null): void
    {
        $actor = $actor ?: Auth::user();

        $this->assertAllowed($actor);
        $reason = $this->assertReason($reason);

        if ($motivo = $lot->getReopenBlockReason()) {
            throw new \RuntimeException($motivo);
        }

        $cascada = $this->cascadeFor($lot);
        $antes = $this->snapshotLot($lot);

        DB::transaction(function () use ($lot, $actor, $reason) {
            $item = $lot->packingSlipItem;

            if ($item) {
                $packingSlip = $item->packingSlip;
                $invoice = $packingSlip?->invoice;

                // De arriba hacia abajo: primero la factura, luego el packing
                // slip, y al final se saca el viajero. Al revés dejaría una
                // factura apuntando a un documento que ya cambió.
                if ($invoice && ! $invoice->isDraft()) {
                    $this->reopenInvoice($invoice, $reason, $actor, cascade: true);
                }

                if ($packingSlip && $packingSlip->status !== PackingSlip::STATUS_DRAFT) {
                    $this->reopenPackingSlip($packingSlip, $reason, $actor, cascade: true);
                }

                $item->delete();
            }

            // `completed_at` no se toca a mano: lo limpia el propio modelo al
            // apagarse las señales de cierre (ver Lot::syncCompletedAt()).
            $lot->update([
                'closure_decision' => null,
                'closure_decided_by' => null,
                'closure_decided_at' => null,
                'surplus_delivered' => false,
                'surplus_delivered_at' => null,
                'surplus_delivered_by' => null,
                'surplus_received' => false,
                'surplus_received_at' => null,
                'surplus_received_by' => null,
                'ready_for_shipping' => false,
                'ready_for_shipping_at' => null,
                'quantity_packed_final' => null,
                'closed_by_type' => null,
                // El viajero vuelve a Empaque: si se quedara «recibido», el
                // paso 7 aparecería hecho sobre un viajero que se va a volver
                // a trabajar.
                'viajero_received' => false,
                'viajero_received_at' => null,
                'viajero_received_by' => null,
                'status' => Lot::STATUS_IN_PROGRESS,
                'packaging_status' => 'pending',
            ]);
        });

        $this->record($lot, $actor, $reason, $antes, $this->snapshotLot($lot->fresh()), $cascada);
    }

    /** Devuelve una factura emitida a borrador para poder corregirla. */
    public function reopenInvoice(Invoice $invoice, string $reason, ?User $actor = null, bool $cascade = false): void
    {
        $actor = $actor ?: Auth::user();

        if (! $cascade) {
            $this->assertAllowed($actor);
            $reason = $this->assertReason($reason);
        }

        $antes = ['status' => $invoice->status, 'issued_at' => (string) $invoice->issued_at];

        $invoice->update([
            'status' => Invoice::STATUS_DRAFT,
            'issued_at' => null,
            'issued_by' => null,
        ]);

        $this->record($invoice, $actor, $reason, $antes, ['status' => Invoice::STATUS_DRAFT], []);
    }

    /** Devuelve un packing slip despachado a borrador. */
    public function reopenPackingSlip(PackingSlip $packingSlip, string $reason, ?User $actor = null, bool $cascade = false): void
    {
        $actor = $actor ?: Auth::user();

        if (! $cascade) {
            $this->assertAllowed($actor);
            $reason = $this->assertReason($reason);
        }

        $antes = ['status' => $packingSlip->status, 'shipped_at' => (string) $packingSlip->shipped_at];

        $packingSlip->update([
            'status' => PackingSlip::STATUS_DRAFT,
            'shipped_at' => null,
            'shipped_by' => null,
        ]);

        $this->record($packingSlip, $actor, $reason, $antes, ['status' => PackingSlip::STATUS_DRAFT], []);
    }

    // ===============================================
    // GUARDS
    // ===============================================

    public function allows(?User $actor): bool
    {
        return (bool) $actor?->can(self::PERMISSION);
    }

    private function assertAllowed(?User $actor): void
    {
        if (! $this->allows($actor)) {
            throw new \RuntimeException(
                'Sólo Administración puede reabrir un documento cerrado. Pide el cambio a quien tenga ese permiso.'
            );
        }
    }

    private function assertReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON) {
            throw new \RuntimeException(
                'Escribe por qué se reabre (mínimo '.self::MIN_REASON.' caracteres). '
                .'Ese texto es lo que explicará el cambio dentro de un año.'
            );
        }

        return $reason;
    }

    // ===============================================
    // AUDITORÍA
    // ===============================================

    /**
     * Toda reapertura deja rastro. Es la mitad del valor de tener el servicio:
     * antes sólo una de las seis implementaciones escribía auditoría.
     */
    private function record(
        \Illuminate\Database\Eloquent\Model $model,
        ?User $actor,
        string $reason,
        array $antes,
        array $despues,
        array $cascada,
    ): void {
        AuditTrail::create([
            'user_id' => $actor?->id,
            'user_name' => $actor ? trim($actor->name.' '.($actor->last_name ?? '')) : null,
            'user_email' => $actor?->email,
            'auditable_type' => $model::class,
            'auditable_id' => $model->id,
            'action' => 'reopen',
            'old_values' => $antes,
            'new_values' => $despues + ['motivo' => $reason, 'cascada' => $cascada],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * La factura de un packing slip.
     *
     * Hay dos caminos entre estas dos tablas (`invoices.packing_slip_id` y
     * `packing_slips.invoice_id`) y no siempre están los dos rellenos. Se
     * comprueban ambos para no dejar una factura emitida colgando.
     */
    private function invoiceOf(PackingSlip $packingSlip): ?Invoice
    {
        return $packingSlip->invoice
            ?? ($packingSlip->invoice_id ? Invoice::find($packingSlip->invoice_id) : null);
    }

    private function snapshotLot(Lot $lot): array
    {
        return [
            'status' => $lot->status,
            'closure_decision' => $lot->closure_decision,
            'ready_for_shipping' => (bool) $lot->ready_for_shipping,
            'completed_at' => (string) $lot->completed_at,
        ];
    }
}
