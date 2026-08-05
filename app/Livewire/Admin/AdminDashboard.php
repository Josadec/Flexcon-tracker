<?php

namespace App\Livewire\Admin;

use App\Models\CrimpLot;
use App\Models\Lot;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\WorkOrder;
use App\Support\PendingActions;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AdminDashboard extends Component
{
    /**
     * Tablero de control de producción.
     *
     * Criterio: el tablero responde "¿qué hay que mover hoy y quién lo mueve?",
     * no "¿cuántos registros hay en la base?". Los totales de catálogo (partes,
     * usuarios) no son accionables y bajaron al pie; arriba va el trabajo
     * pendiente por área, que es lo que hace que un lote avance o se atore.
     *
     * El cálculo de pendientes es el mismo que usa la Lista de envío, para que
     * los dos números coincidan siempre.
     */
    public function render()
    {
        // ── Trabajo pendiente ────────────────────────────────────────────
        // Todo sale de App\Support\PendingActions, la única fuente de verdad.
        // La Lista de envío y los cuatro tableros de área consumen lo mismo,
        // así que las cifras no se pueden desincronizar.
        $actions = PendingActions::make();

        $pending      = $actions->countsByPhase();
        $byArea       = $actions->countsByActor();
        $totalPending = $actions->total();

        $lotsLive        = $actions->lots();
        $piecesPending   = $actions->piecesPendingProduction();
        $lotsDone        = $lotsLive->filter(fn ($l) => $l->hasPackagingRecords() && $l->isSurplusReceived())->count();
        $piecesCompleted = (int) $lotsLive->sum(fn ($l) => $l->getTotalCompletedPieces());

        // ── Órdenes de compra que esperan una decisión ───────────────────
        $poPending    = PurchaseOrder::pending()->count();
        $poCorrection = PurchaseOrder::pendingCorrection()->count();

        // ── Salud de los datos ───────────────────────────────────────────
        // Lotes donde Calidad reporta más piezas de las que Producción
        // registró. Sus cifras de empaque y decisión salen de ahí, así que no
        // son confiables. Se reporta aquí y no en el tablero de piso porque
        // un operador no puede corregirlo.
        $inconsistentLots = $lotsLive
            ->filter(fn ($l) => ! $l->hasConsistentQualityData())
            ->sortByDesc(fn ($l) => $l->getOrphanQualityPieces())
            ->values();

        // ── Entregas: lo que ya se pasó de fecha ─────────────────────────
        $overdueWOs = WorkOrder::with(['purchaseOrder.part', 'status'])
            ->whereNotNull('scheduled_send_date')
            ->whereNull('actual_send_date')
            ->whereDate('scheduled_send_date', '<', now()->toDateString())
            ->orderBy('scheduled_send_date')
            ->take(6)
            ->get();

        $overdueCount = WorkOrder::whereNotNull('scheduled_send_date')
            ->whereNull('actual_send_date')
            ->whereDate('scheduled_send_date', '<', now()->toDateString())
            ->count();

        $dueSoonCount = WorkOrder::whereNotNull('scheduled_send_date')
            ->whereNull('actual_send_date')
            ->whereBetween('scheduled_send_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->count();

        // ── Listas de envío por departamento ─────────────────────────────
        $sentListsByDept = SentList::selectRaw('current_department, count(*) as total')
            ->where('status', SentList::STATUS_PENDING)
            ->groupBy('current_department')
            ->pluck('total', 'current_department');

        $pipeline = [
            SentList::DEPT_MATERIALS  => ['label' => 'Materiales', 'tone' => 'sky'],
            SentList::DEPT_INSPECTION => ['label' => 'Inspección', 'tone' => 'emerald'],
            SentList::DEPT_PRODUCTION => ['label' => 'Producción', 'tone' => 'indigo'],
            SentList::DEPT_QUALITY    => ['label' => 'Calidad',    'tone' => 'teal'],
            SentList::DEPT_SHIPPING   => ['label' => 'Empaque',    'tone' => 'orange'],
        ];
        foreach ($pipeline as $dept => &$info) {
            $info['count'] = (int) ($sentListsByDept[$dept] ?? 0);
        }
        unset($info);

        // ── Actividad reciente ───────────────────────────────────────────
        $recentWorkOrders = WorkOrder::with(['purchaseOrder.part', 'status'])
            ->latest()
            ->take(6)
            ->get();

        return view('livewire.admin.admin-dashboard', [
            'pending'          => $pending,
            'byArea'           => $byArea,
            'totalPending'     => $totalPending,
            'lotsTotal'        => $lotsLive->count(),
            'lotsDone'         => $lotsDone,
            'piecesPending'    => $piecesPending,
            'piecesCompleted'  => $piecesCompleted,
            'poPending'        => $poPending,
            'poCorrection'     => $poCorrection,
            'inconsistentLots' => $inconsistentLots,
            'overdueWOs'       => $overdueWOs,
            'overdueCount'     => $overdueCount,
            'dueSoonCount'     => $dueSoonCount,
            'pipeline'         => $pipeline,
            'recentWorkOrders' => $recentWorkOrders,
            'totalWO'          => WorkOrder::count(),
            'totalPO'          => PurchaseOrder::count(),
            'totalParts'       => Part::count(),
            'crimpViajeros'    => Lot::has('crimpLots')->count(),
            'crimpLots'        => CrimpLot::count(),
        ]);
    }
}
