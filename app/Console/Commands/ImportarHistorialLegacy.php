<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use App\Models\Lot;
use App\Models\SentList;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Trae al historial lo que ya estaba registrado y no se veía.
 *
 * Antes de la auditoría automática, el sistema sí guardaba cosas: los cambios
 * de estado de las órdenes, los ciclos de cierre de los viajeros y los rechazos
 * entre departamentos. Pero cada uno en su tabla, y sólo uno de ellos tenía
 * pantalla (los últimos 5 cambios de estado de una WO).
 *
 * Sin esta importación, el historial "empieza" el día que se desplegó la
 * auditoría, y eso no sirve para una retención de 5 años por ISO.
 *
 * Es idempotente: se puede correr las veces que haga falta.
 */
class ImportarHistorialLegacy extends Command
{
    protected $signature = 'flexcon:historial-importar {--dry-run : Sólo informa, no escribe}';

    protected $description = 'Importa al historial los registros antiguos (estados de WO, ciclos de viajero y rechazos)';

    private bool $simulacion = false;

    private int $importadas = 0;

    public function handle(): int
    {
        $this->simulacion = (bool) $this->option('dry-run');

        if ($this->simulacion) {
            $this->warn('Simulación: no se escribirá nada.');
        }

        $this->importarEstadosDeOrden();
        $this->importarCiclosDeViajero();
        $this->importarRechazosDeLista();

        $this->newLine();
        $this->info($this->simulacion
            ? "Se importarían {$this->importadas} entradas."
            : "Listo: {$this->importadas} entradas importadas al historial.");

        return self::SUCCESS;
    }

    /** `wo_status_logs` → historial de la orden de trabajo. */
    private function importarEstadosDeOrden(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('wo_status_logs')) {
            return;
        }

        $estados = DB::table('statuses_wo')->pluck('name', 'id');

        DB::table('wo_status_logs')->orderBy('id')->chunk(200, function ($logs) use ($estados) {
            foreach ($logs as $log) {
                $wo = WorkOrder::with('purchaseOrder')->find($log->work_order_id);

                $this->guardar(
                    accion: 'legacy.wo_status',
                    tipo: WorkOrder::class,
                    id: $log->work_order_id,
                    userId: $log->user_id,
                    antes: ['status' => $estados[$log->from_status_id] ?? $log->from_status_id],
                    despues: [
                        'status' => $estados[$log->to_status_id] ?? $log->to_status_id,
                        'motivo' => $log->comments ?: null,
                    ],
                    fecha: $log->created_at,
                    contexto: [
                        'work_order_id' => $log->work_order_id,
                        'purchase_order_id' => $wo?->purchase_order_id,
                        'part_id' => $wo?->purchaseOrder?->part_id,
                    ],
                );
            }
        });

        $this->line('  · Estados de orden de trabajo revisados.');
    }

    /** `lot_completion_logs` → ciclos de cierre del viajero. */
    private function importarCiclosDeViajero(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('lot_completion_logs')) {
            return;
        }

        DB::table('lot_completion_logs')->orderBy('id')->chunk(200, function ($logs) {
            foreach ($logs as $log) {
                $lot = Lot::withTrashed()->with('workOrder.purchaseOrder')->find($log->lot_id);

                $this->guardar(
                    accion: 'legacy.lot_cycle',
                    tipo: Lot::class,
                    id: $log->lot_id,
                    userId: $log->completed_by,
                    antes: null,
                    despues: [
                        'ciclo' => $log->cycle_number,
                        'cantidad_original' => $log->original_quantity,
                        'empacadas' => $log->packed_pieces,
                        'sobrantes' => $log->surplus_pieces,
                        'faltantes' => $log->missing_pieces,
                    ],
                    fecha: $log->completed_at ?? $log->created_at,
                    contexto: [
                        'lot_id' => $log->lot_id,
                        'work_order_id' => $lot?->work_order_id,
                        'purchase_order_id' => $lot?->workOrder?->purchase_order_id,
                        'part_id' => $lot?->workOrder?->purchaseOrder?->part_id,
                    ],
                );
            }
        });

        $this->line('  · Ciclos de viajero revisados.');
    }

    /** `sent_list_rejections` → rechazos entre departamentos. */
    private function importarRechazosDeLista(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('sent_list_rejections')) {
            return;
        }

        $departamentos = SentList::getDepartments();

        DB::table('sent_list_rejections')->orderBy('id')->chunk(200, function ($rechazos) use ($departamentos) {
            foreach ($rechazos as $rechazo) {
                $this->guardar(
                    accion: 'legacy.sent_list_rejection',
                    tipo: SentList::class,
                    id: $rechazo->sent_list_id,
                    userId: $rechazo->rejected_by,
                    antes: ['departamento' => $departamentos[$rechazo->from_department] ?? $rechazo->from_department],
                    despues: [
                        'departamento' => $departamentos[$rechazo->to_department] ?? $rechazo->to_department,
                        'motivo' => $rechazo->reason,
                    ],
                    fecha: $rechazo->created_at,
                    contexto: ['lot_id' => $rechazo->lot_id],
                );
            }
        });

        $this->line('  · Rechazos de listas revisados.');
    }

    /** Escribe la entrada si no existe ya (el comando se puede repetir). */
    private function guardar(
        string $accion,
        string $tipo,
        ?int $id,
        ?int $userId,
        ?array $antes,
        array $despues,
        $fecha,
        array $contexto,
    ): void {
        if (! $id) {
            return;
        }

        $yaEsta = AuditTrail::where('action', $accion)
            ->where('auditable_type', $tipo)
            ->where('auditable_id', $id)
            ->where('created_at', $fecha)
            ->exists();

        if ($yaEsta) {
            return;
        }

        $this->importadas++;

        if ($this->simulacion) {
            return;
        }

        $usuario = $userId ? DB::table('users')->find($userId) : null;

        AuditTrail::create(array_merge([
            'user_id' => $usuario?->id,
            'user_name' => $usuario ? trim($usuario->name.' '.($usuario->last_name ?? '')) : null,
            'user_email' => $usuario?->email,
            'auditable_type' => $tipo,
            'auditable_id' => $id,
            'action' => $accion,
            'old_values' => $antes,
            'new_values' => $despues,
            'created_at' => $fecha,
        ], $contexto));
    }
}
