<?php

namespace App\Support;

use App\Models\Lot;
use Illuminate\Support\Collection;

/**
 * Fuente única de verdad de "qué falta hacer" en el piso.
 *
 * Antes cada tablero (el de administración, el de piso y los cuatro de área)
 * recalculaba los pendientes por su cuenta y con criterios distintos, así que
 * mostraban cifras que no cuadraban entre sí. Aquí se calcula una sola vez y
 * todos consumen lo mismo.
 *
 * Reglas:
 *  - El universo son los lotes de Work Orders que no están cerradas: el mismo
 *    que usa la Lista de envío.
 *  - Las FASES son las nueve del flujo y no se tocan sin actualizar los seis
 *    tableros a la vez.
 *  - Los AVISOS (`advisories`) son señales útiles que NO son una etapa del
 *    flujo (p.ej. un viajero CRIMP sin lotes capturados). Van aparte para que
 *    no inflen el total de pendientes.
 */
final class PendingActions
{
    /**
     * Definición de cada fase: área responsable, verbo y ruta a donde ir.
     *
     * @var array<string, array{actor: string, action: string, route: ?string}>
     */
    public const PHASES = [
        'material_release' => ['actor' => 'Materiales', 'action' => 'Liberar material',               'route' => 'admin.materials.index'],
        'crimp_release'    => ['actor' => 'Materiales', 'action' => 'Liberar material del viajero',   'route' => 'admin.materials.index'],
        'inspection'       => ['actor' => 'Calidad',    'action' => 'Inspeccionar el lote',           'route' => 'admin.quality.index'],
        'production'       => ['actor' => 'Producción', 'action' => 'Registrar pesada de producción', 'route' => 'admin.production.index'],
        'quality'          => ['actor' => 'Calidad',    'action' => 'Verificar piezas',               'route' => 'admin.quality.index'],
        'viajero'          => ['actor' => 'Empaque',    'action' => 'Entregar el viajero',            'route' => 'admin.packaging.index'],
        'decision'         => ['actor' => 'Materiales', 'action' => 'Tomar la decisión del lote',     'route' => 'admin.materials.index'],
        'surplus_deliver'  => ['actor' => 'Empaque',    'action' => 'Entregar los sobrantes',         'route' => 'admin.packaging.index'],
        'surplus_receive'  => ['actor' => 'Materiales', 'action' => 'Recibir los sobrantes',          'route' => 'admin.materials.index'],
    ];

    /** Áreas en el orden en que aparecen en el flujo. */
    public const ACTORS = ['Materiales', 'Calidad', 'Producción', 'Empaque'];

    private ?Collection $items = null;

    private ?Collection $advisories = null;

    private ?Collection $lots = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * Lotes vivos con todo lo que hace falta para evaluar sus fases.
     */
    public function lots(): Collection
    {
        return $this->lots ??= Lot::query()
            ->with([
                'workOrder.purchaseOrder.part',
                'weighings',
                'qualityWeighings',
                'crimpLots',
                'packagingRecords',
                'packagingPieceWeighings',
                'packagingCrimpWeighings',
            ])
            ->whereHas('workOrder', function ($q) {
                $q->whereDoesntHave('status', fn ($s) => $s->where('name', 'Completed'));
            })
            ->get();
    }

    /**
     * Una entrada por acción pendiente.
     *
     * @return Collection<int, array{lot: Lot, phase: string, actor: string, action: string, detail: string, route: ?string}>
     */
    public function items(): Collection
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $this->items      = collect();
        $this->advisories = collect();

        foreach ($this->lots() as $lot) {
            $isCrimp = (bool) ($lot->workOrder?->purchaseOrder?->part?->is_crimp ?? false);

            // ── Material a nivel viajero/lote ────────────────────────────
            if (($lot->material_status ?? 'pending') === 'pending') {
                $this->push($lot, $isCrimp ? 'crimp_release' : 'material_release',
                    'El lote no avanza hasta que Materiales lo libere.');
            } else {
                // Aviso: un viajero CRIMP liberado pero sin lotes capturados no
                // se puede empacar; no es una fase, pero sí un bloqueo real.
                if ($isCrimp && $lot->crimpLots->isEmpty()) {
                    $this->advisories->push([
                        'lot'    => $lot,
                        'actor'  => 'Materiales',
                        'title'  => 'Viajero CRIMP sin lotes capturados',
                        'detail' => 'Empaque no puede registrar el Paso 5 hasta que se capturen sus lotes de CRIMP.',
                        'route'  => 'admin.materials.index',
                    ]);
                }
            }

            // ── Inspección ───────────────────────────────────────────────
            if (($lot->inspection_status ?? 'pending') === 'pending' && $lot->canBeInspected()) {
                $this->push($lot, 'inspection', 'Calidad debe aprobar antes de que Producción empiece.');
            }

            // ── Producción ───────────────────────────────────────────────
            $weighed = $lot->weighings->sum('good_pieces') + $lot->weighings->sum('bad_pieces');
            $rework  = $lot->qualityWeighings->where('rework_status', 'pending_rework')->sum('bad_pieces');
            $target  = $lot->quantity + $rework;

            if ($weighed < $target && ($lot->inspection_status ?? '') === 'approved') {
                $missing = max(0, $target - $weighed);
                $this->push($lot, 'production',
                    'Faltan ' . number_format($missing) . ' de ' . number_format($target) . ' piezas por pesar.',
                    $missing);
            }

            // ── Calidad ──────────────────────────────────────────────────
            if ($lot->getQualitySemaphoreStatus() === 'yellow') {
                $pending = $lot->getQualityPendingPieces();
                $this->push($lot, 'quality',
                    'Faltan ' . number_format($pending) . ' piezas por verificar.',
                    $pending);
            }

            // ── Post-empaque: sólo el siguiente paso de la cadena ─────────
            $next = $lot->getNextPendingAction();
            if ($next) {
                $phase = match (true) {
                    $next['phase'] === 'viajero'  => 'viajero',
                    $next['phase'] === 'decision' => 'decision',
                    $next['phase'] === 'material' => $next['state'] === 'in_progress'
                        ? 'surplus_receive'
                        : 'surplus_deliver',
                    default => null,
                };

                if ($phase !== null) {
                    $this->push($lot, $phase, $next['label'] ?? '');
                }
            }
        }

        return $this->items;
    }

    /**
     * Señales que no son una etapa del flujo pero bloquean el avance.
     *
     * @return Collection<int, array{lot: Lot, actor: string, title: string, detail: string, route: ?string}>
     */
    public function advisories(?string $actor = null): Collection
    {
        $this->items(); // llena también los avisos

        return $actor === null
            ? $this->advisories
            : $this->advisories->where('actor', $actor)->values();
    }

    /** Acciones pendientes de un área. */
    public function forActor(string $actor): Collection
    {
        return $this->items()->where('actor', $actor)->values();
    }

    /**
     * Conteo por fase, con todas las claves presentes aunque sean cero.
     *
     * @return array<string, int>
     */
    public function countsByPhase(): array
    {
        $counts = array_fill_keys(array_keys(self::PHASES), 0);

        foreach ($this->items() as $item) {
            $counts[$item['phase']]++;
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function countsByActor(): array
    {
        $counts = array_fill_keys(self::ACTORS, 0);

        foreach ($this->items() as $item) {
            $counts[$item['actor']]++;
        }

        return $counts;
    }

    public function total(): int
    {
        return $this->items()->count();
    }

    /** Piezas que Producción todavía no registra. */
    public function piecesPendingProduction(): int
    {
        return (int) $this->items()->where('phase', 'production')->sum('pieces');
    }

    /** Piezas que Calidad todavía no verifica. */
    public function piecesPendingQuality(): int
    {
        return (int) $this->items()->where('phase', 'quality')->sum('pieces');
    }

    private function push(Lot $lot, string $phase, string $detail, int $pieces = 0): void
    {
        $meta = self::PHASES[$phase];

        $this->items->push([
            'lot'    => $lot,
            'phase'  => $phase,
            'actor'  => $meta['actor'],
            'action' => $meta['action'],
            'route'  => $meta['route'],
            'detail' => $detail,
            'pieces' => $pieces,
        ]);
    }
}
