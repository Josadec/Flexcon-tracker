<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PackingSlip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InvoiceDeleteService
{
    /**
     * Elimina un Invoice con todas las validaciones de integridad referencial.
     *
     * Reglas de negocio:
     *   - Solo se pueden eliminar Invoices en estado 'draft' o 'cancelled'.
     *   - Los Invoices en estado 'issued' NO pueden eliminarse.
     *
     * Operaciones dentro de la transaccion (en orden):
     *   1. Nullear packing_slips.invoice_id donde invoice_id = $invoice->id
     *      (libera el PS para que pueda generar un nuevo Invoice en el futuro).
     *   2. Eliminar fisicamente los invoice_items del Invoice (no tienen SoftDeletes,
     *      son registros de detalle snapshot que no tienen valor historico independiente).
     *   3. Soft-delete del Invoice ($invoice->delete() usa el trait SoftDeletes del modelo).
     *
     * @param  Invoice $invoice
     * @return void
     *
     * @throws RuntimeException si el Invoice esta en estado 'issued'
     */
    public function delete(Invoice $invoice): void
    {
        // ----------------------------------------------------------------
        // Validacion previa: no se puede eliminar un Invoice emitido
        // ----------------------------------------------------------------
        if ($invoice->isIssued()) {
            throw new RuntimeException(
                "El Invoice #{$invoice->invoice_number} está en estado 'Emitido' y no puede ser eliminado. " .
                "Solo los Invoices en estado Borrador o Cancelado pueden eliminarse."
            );
        }

        // Capturar datos para el log antes de la transaccion
        $invoiceNumber  = $invoice->invoice_number;
        $invoiceId      = $invoice->id;
        $invoiceStatus  = $invoice->status;
        $packingSlipId  = $invoice->packing_slip_id;
        $deletedBy      = Auth::id();

        // ----------------------------------------------------------------
        // Transaccion atomica: las tres operaciones deben ejecutarse juntas
        // ----------------------------------------------------------------
        DB::transaction(function () use ($invoice, $packingSlipId) {

            // Paso 1: Nullear invoice_id en el Packing Slip asociado.
            // Esto libera el PS para que pueda generar un nuevo Invoice.
            // Se actualiza directamente via query para evitar cargar el modelo
            // y disparar eventos innecesarios.
            if ($packingSlipId !== null) {
                PackingSlip::where('invoice_id', $invoice->id)
                    ->update(['invoice_id' => null]);
            }

            // Paso 2: Eliminar fisicamente los InvoiceItems.
            // Los items son registros de detalle (snapshot) del Invoice.
            // No tienen SoftDeletes — se eliminan con delete() fisico.
            $invoice->items()->delete();

            // Paso 3: Soft-delete del Invoice.
            // El modelo Invoice usa el trait SoftDeletes, por lo que
            // $invoice->delete() setea deleted_at en lugar de borrar la fila.
            // Esto preserva el invoice_number en la secuencia (withTrashed en generateInvoiceNumber).
            $invoice->delete();
        });

        // ----------------------------------------------------------------
        // Log de auditoria (fuera de la transaccion, ya confirmada)
        // ----------------------------------------------------------------
        Log::info('Invoice eliminado', [
            'invoice_id'      => $invoiceId,
            'invoice_number'  => $invoiceNumber,
            'invoice_status'  => $invoiceStatus,
            'packing_slip_id' => $packingSlipId,
            'deleted_by'      => $deletedBy,
            'deleted_at'      => now()->toIso8601String(),
        ]);
    }
}
