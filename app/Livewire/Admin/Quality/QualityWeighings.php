<?php

namespace App\Livewire\Admin\Quality;

use App\Models\Lot;
use App\Models\QualityWeighing;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mesa de trabajo de Calidad (paso 5 del flujo).
 *
 * Lo que hace Calidad aquí: tomar lo que Producción pesó y separarlo en piezas
 * aprobadas y rechazadas. Las aprobadas son las únicas que Empaque puede
 * empacar, así que este número es el que arrastra todo el final del flujo:
 * nunca puede pasar de lo que Producción registró ni quedarse por debajo de lo
 * que Empaque ya consumió.
 */
class QualityWeighings extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterQualityStatus = '';
    public string $filterType = ''; // '' | crimp | standard
    public int $perPage = 15;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // Modal de detalle del viajero
    public bool $showDetailModal = false;
    public ?int $selectedLotId = null;

    // Modal de pesada
    public bool $showWeighingModal = false;
    public ?int $editingQualityWeighingId = null;
    public int $qualGoodPieces = 0;
    public int $qualBadPieces = 0;
    public string $qualWeighedAt = '';
    public string $qualComments = '';

    /** Columnas ordenables. Sin esta lista, `orderBy` recibe lo que mande el navegador. */
    private const SORTABLE = ['lot_number', 'quantity', 'created_at', 'prod_good_sum', 'qual_good_sum', 'qual_bad_sum'];

    private const PER_PAGE = [15, 25, 50, 100];

    // ===============================================
    // DATOS DEL VIAJERO ABIERTO
    // ===============================================

    #[Computed]
    public function selectedLot(): ?Lot
    {
        return $this->selectedLotId
            ? Lot::with([
                'workOrder.purchaseOrder.part',
                'weighings.weighedBy',
                'qualityWeighings.weighedBy',
                'packagingRecords',
            ])->find($this->selectedLotId)
            : null;
    }

    #[Computed]
    public function productionWeighings(): array
    {
        $lot = $this->selectedLot;

        if (! $lot) {
            return [];
        }

        return $lot->weighings->map(fn ($w) => [
            'id' => $w->id,
            'good_pieces' => (int) $w->good_pieces,
            'bad_pieces' => (int) $w->bad_pieces,
            'weighed_at' => $w->weighed_at?->format('d/m/Y H:i') ?? '—',
            'weighed_by' => $w->weighedBy->name ?? '—',
            'comments' => $w->comments,
        ])->all();
    }

    #[Computed]
    public function qualityWeighings(): array
    {
        $lot = $this->selectedLot;

        if (! $lot) {
            return [];
        }

        return $lot->qualityWeighings->map(fn ($qw) => [
            'id' => $qw->id,
            'good_pieces' => (int) $qw->good_pieces,
            'bad_pieces' => (int) $qw->bad_pieces,
            'weighed_at' => $qw->weighed_at?->format('d/m/Y H:i') ?? '—',
            'weighed_by' => $qw->weighedBy->name ?? '—',
            'comments' => $qw->comments,
        ])->all();
    }

    #[Computed]
    public function prodGoodTotal(): int
    {
        return $this->selectedLot?->getProductionGoodPieces() ?? 0;
    }

    #[Computed]
    public function qualGoodTotal(): int
    {
        return $this->selectedLot?->getQualityGoodPieces() ?? 0;
    }

    #[Computed]
    public function qualBadTotal(): int
    {
        return $this->selectedLot?->getQualityBadPieces() ?? 0;
    }

    #[Computed]
    public function qualPending(): int
    {
        return $this->selectedLot?->getQualityPendingPieces() ?? 0;
    }

    /**
     * Piezas que se pueden capturar en el formulario abierto.
     *
     * Se calcula siempre en el servidor. Antes vivía en una propiedad pública
     * que el navegador podía cambiar, así que la validación "no pasar de las
     * pendientes" se podía saltar desde las herramientas del navegador.
     */
    #[Computed]
    public function qualRemainingPieces(): int
    {
        $lot = $this->selectedLot;

        if (! $lot) {
            return 0;
        }

        $pendientes = $lot->getQualityPendingPieces();

        if ($this->editingQualityWeighingId) {
            $qw = $lot->qualityWeighings->firstWhere('id', $this->editingQualityWeighingId);
            // Lo que ya trae la pesada en edición vuelve al bolsón: se está
            // redistribuyendo, no sumando.
            $pendientes += $qw ? (int) $qw->good_pieces + (int) $qw->bad_pieces : 0;
        }

        return $pendientes;
    }

    /**
     * Piezas aprobadas que Empaque ya consumió (empacadas + sobrante declarado,
     * o piezas empacadas en el flujo CRIMP).
     */
    private function packagingCommitted(Lot $lot): int
    {
        return max(
            $lot->getPackagingPackedPieces() + $lot->getPackagingTotalSurplus(),
            $lot->getPackagedPiecesTotal(),
        );
    }

    // ===============================================
    // FILTROS Y ORDEN
    // ===============================================

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterQualityStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage($value): void
    {
        $this->perPage = in_array((int) $value, self::PER_PAGE, true) ? (int) $value : 15;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterQualityStatus = '';
        $this->filterType = '';
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    // ===============================================
    // DETALLE DEL VIAJERO
    // ===============================================

    public function openDetailModal(int $lotId): void
    {
        if (! Lot::whereKey($lotId)->exists()) {
            session()->flash('error', 'Viajero no encontrado.');

            return;
        }

        $this->selectedLotId = $lotId;
        $this->refreshLot();
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedLotId = null;
        $this->refreshLot();
    }

    /** Tira la caché de los computed del viajero tras escribir en la base. */
    private function refreshLot(): void
    {
        foreach ([
            'selectedLot', 'productionWeighings', 'qualityWeighings',
            'prodGoodTotal', 'qualGoodTotal', 'qualBadTotal', 'qualPending', 'qualRemainingPieces',
        ] as $propiedad) {
            unset($this->{$propiedad});
        }
    }

    // ===============================================
    // PESADAS DE CALIDAD
    // ===============================================

    public function openWeighingModal(): void
    {
        $lot = $this->selectedLot;

        if (! $lot) {
            return;
        }

        // La cadena Material → Inspección → Producción tiene que estar completa.
        if (! $lot->canBeQualityChecked()) {
            session()->flash('error', $lot->getProductionBlockedReason()
                ?? 'Producción todavía no registra piezas de este viajero: no hay nada que verificar.');

            return;
        }

        if ($lot->getQualityPendingPieces() <= 0) {
            session()->flash('error', 'Este viajero ya no tiene piezas pendientes de verificar.');

            return;
        }

        $this->editingQualityWeighingId = null;
        $this->qualGoodPieces = 0;
        $this->qualBadPieces = 0;
        $this->qualWeighedAt = now()->format('Y-m-d\TH:i');
        $this->qualComments = '';
        $this->resetErrorBag();
        $this->refreshLot();
        $this->showWeighingModal = true;
    }

    public function closeWeighingModal(): void
    {
        $this->showWeighingModal = false;
        $this->editingQualityWeighingId = null;
        $this->qualGoodPieces = 0;
        $this->qualBadPieces = 0;
        $this->qualWeighedAt = '';
        $this->qualComments = '';
        $this->resetErrorBag();
        $this->refreshLot();
    }

    /**
     * Busca una pesada de calidad DEL viajero abierto en el modal.
     *
     * Antes se buscaba por id suelto y, si no aparecía, el método terminaba en
     * un `return` mudo: el usuario clicaba y no pasaba absolutamente nada, sin
     * forma de saber por qué. Ahora siempre hay respuesta visible.
     */
    private function findQualityWeighingOfSelectedLot(int $id): ?QualityWeighing
    {
        if (! $this->selectedLotId) {
            session()->flash('error', 'Vuelve a abrir el detalle del viajero e inténtalo de nuevo.');

            return null;
        }

        $qw = QualityWeighing::where('lot_id', $this->selectedLotId)->find($id);

        if (! $qw) {
            session()->flash('error', 'Esa pesada de calidad ya no existe. Se actualizó el detalle.');
            $this->refreshLot();

            return null;
        }

        return $qw;
    }

    public function editQualityWeighing(int $qualityWeighingId): void
    {
        $qw = $this->findQualityWeighingOfSelectedLot($qualityWeighingId);

        if (! $qw) {
            return;
        }

        $this->editingQualityWeighingId = $qw->id;
        $this->qualGoodPieces = (int) $qw->good_pieces;
        $this->qualBadPieces = (int) $qw->bad_pieces;
        $this->qualWeighedAt = $qw->weighed_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        $this->qualComments = $qw->comments ?? '';
        $this->resetErrorBag();
        $this->refreshLot();
        $this->showWeighingModal = true;
    }

    public function saveQualityWeighing(): void
    {
        $this->validate([
            'qualGoodPieces' => 'required|integer|min:0',
            'qualBadPieces' => 'required|integer|min:0',
            'qualWeighedAt' => 'required|date|before_or_equal:now',
            'qualComments' => 'nullable|string|max:1000',
        ], [
            'qualGoodPieces.required' => 'Las piezas aprobadas son requeridas.',
            'qualBadPieces.required' => 'Las piezas rechazadas son requeridas.',
            'qualWeighedAt.required' => 'La fecha y hora son requeridas.',
            'qualWeighedAt.before_or_equal' => 'La fecha de la pesada no puede estar en el futuro.',
        ]);

        $lot = $this->selectedLot;

        if (! $lot) {
            session()->flash('error', 'Viajero no encontrado.');

            return;
        }

        if (! $lot->canBeQualityChecked()) {
            session()->flash('error', $lot->getProductionBlockedReason()
                ?? 'Producción todavía no registra piezas de este viajero: no hay nada que verificar.');
            $this->closeWeighingModal();

            return;
        }

        $total = $this->qualGoodPieces + $this->qualBadPieces;

        if ($total <= 0) {
            $this->addError('qualGoodPieces', 'Debes registrar al menos 1 pieza.');

            return;
        }

        // El tope se recalcula aquí, no se toma de una propiedad del navegador.
        $disponible = $this->qualRemainingPieces;

        if ($total > $disponible) {
            $this->addError('qualGoodPieces', 'La suma ('.number_format($total).') sobrepasa las piezas pendientes de verificar ('
                .number_format($disponible).').');

            return;
        }

        $qwEnEdicion = null;

        if ($this->editingQualityWeighingId) {
            $qwEnEdicion = QualityWeighing::where('lot_id', $lot->id)->find($this->editingQualityWeighingId);

            if (! $qwEnEdicion) {
                session()->flash('error', 'Esa pesada de calidad ya no existe.');
                $this->closeWeighingModal();

                return;
            }

            // Bajar las aprobadas por debajo de lo que Empaque ya empacó deja
            // ese empaque sin respaldo: mismo criterio que Producción/Calidad.
            $aprobadasResultantes = $lot->getQualityGoodPieces() - (int) $qwEnEdicion->good_pieces + $this->qualGoodPieces;
            $comprometidas = $this->packagingCommitted($lot);

            if ($aprobadasResultantes < $comprometidas) {
                $this->addError('qualGoodPieces', 'Empaque ya usó '.number_format($comprometidas)
                    .' piezas aprobadas de este viajero; no puedes dejar el total en '.number_format($aprobadasResultantes).'.');

                return;
            }
        }

        if ($qwEnEdicion) {
            $qwEnEdicion->update([
                // kit_id no se reasigna: las pesadas viejas conservan su historial.
                'production_good_pieces' => $lot->getProductionGoodPieces(),
                'good_pieces' => $this->qualGoodPieces,
                'bad_pieces' => $this->qualBadPieces,
                'disposition' => $this->qualBadPieces > 0 ? QualityWeighing::DISPOSITION_SCRAP : null,
                'rework_status' => null,
                'weighed_at' => $this->qualWeighedAt,
                'comments' => $this->qualComments ?: null,
            ]);
            $mensaje = 'Pesada de calidad actualizada.';
        } else {
            QualityWeighing::create([
                'lot_id' => $lot->id,
                // Pesada de calidad a nivel viajero: kit_id = null (CRIMP ya no usa kit).
                'kit_id' => null,
                'production_good_pieces' => $lot->getProductionGoodPieces(),
                'good_pieces' => $this->qualGoodPieces,
                'bad_pieces' => $this->qualBadPieces,
                'disposition' => $this->qualBadPieces > 0 ? QualityWeighing::DISPOSITION_SCRAP : null,
                'rework_status' => null,
                'weighed_at' => $this->qualWeighedAt,
                'weighed_by' => auth()->id(),
                'comments' => $this->qualComments ?: null,
            ]);
            $mensaje = 'Pesada de calidad registrada.';
        }

        if ($this->qualBadPieces > 0) {
            $mensaje .= ' '.number_format($this->qualBadPieces).' piezas descartadas.';
        }

        session()->flash('message', $mensaje);
        $this->closeWeighingModal();
    }

    public function deleteQualityWeighing(int $qualityWeighingId): void
    {
        $qw = $this->findQualityWeighingOfSelectedLot($qualityWeighingId);

        if (! $qw) {
            return;
        }

        $lot = $this->selectedLot;

        if ($lot) {
            $aprobadasResultantes = $lot->getQualityGoodPieces() - (int) $qw->good_pieces;
            $comprometidas = $this->packagingCommitted($lot);

            if ($aprobadasResultantes < $comprometidas) {
                session()->flash('error', 'No se puede borrar: Empaque ya usó '.number_format($comprometidas)
                    .' piezas aprobadas de este viajero y quedarían sólo '.number_format($aprobadasResultantes).'.');

                return;
            }
        }

        // Si se estaba editando justo esa pesada, el formulario queda huérfano.
        if ($this->editingQualityWeighingId === $qw->id) {
            $this->closeWeighingModal();
        }

        $qw->delete();
        session()->flash('message', 'Pesada de calidad eliminada.');
        $this->refreshLot();
    }

    // ===============================================
    // RENDER
    // ===============================================

    /**
     * Subconsultas que comparan lo verificado por Calidad contra lo bueno de
     * Producción. Se repiten en el filtro y en las métricas.
     */
    private const SQL_QUALITY_WEIGHED = '(SELECT COALESCE(SUM(good_pieces),0) + COALESCE(SUM(bad_pieces),0) FROM quality_weighings WHERE quality_weighings.lot_id = lots.id AND quality_weighings.deleted_at IS NULL)';

    private const SQL_PRODUCTION_GOOD = '(SELECT COALESCE(SUM(good_pieces),0) FROM weighings WHERE weighings.lot_id = lots.id AND weighings.deleted_at IS NULL)';

    private function scopePendingQuality($query)
    {
        return $query->where(function ($q) {
            $q->whereDoesntHave('qualityWeighings')
                ->orWhereRaw(self::SQL_QUALITY_WEIGHED.' < '.self::SQL_PRODUCTION_GOOD);
        });
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Lot::whereHas('weighings')->count(),
            'pending' => $this->scopePendingQuality(Lot::whereHas('weighings'))->count(),
            'completed' => Lot::whereHas('weighings')
                ->whereRaw(self::SQL_QUALITY_WEIGHED.' >= '.self::SQL_PRODUCTION_GOOD)
                ->count(),
            'rejected' => Lot::whereHas('qualityWeighings', fn ($q) => $q->where('bad_pieces', '>', 0))->count(),
        ];
    }

    public function render()
    {
        // Las sumas viajan en la propia consulta. Antes cada renglón llamaba a
        // cinco métodos del modelo, y cada uno lanzaba su propio SUM: una tabla
        // de 15 renglones costaba más de cien consultas.
        $query = Lot::query()
            ->with(['workOrder.purchaseOrder.part'])
            ->withSum('weighings as prod_good_sum', 'good_pieces')
            ->withSum('qualityWeighings as qual_good_sum', 'good_pieces')
            ->withSum('qualityWeighings as qual_bad_sum', 'bad_pieces')
            ->whereHas('weighings'); // sólo viajeros que Producción ya pesó

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('lot_number', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('workOrder', fn ($woq) => $woq->where('wo_number', 'like', $term))
                    ->orWhereHas('workOrder.purchaseOrder', fn ($poq) => $poq->where('wo', 'like', $term))
                    ->orWhereHas('workOrder.purchaseOrder.part', fn ($pq) => $pq->where('number', 'like', $term)
                        ->orWhere('description', 'like', $term));
            });
        }

        if ($this->filterQualityStatus === 'pending') {
            $this->scopePendingQuality($query);
        } elseif ($this->filterQualityStatus === 'completed') {
            $query->whereRaw(self::SQL_QUALITY_WEIGHED.' >= '.self::SQL_PRODUCTION_GOOD);
        } elseif ($this->filterQualityStatus === 'rejected') {
            $query->whereHas('qualityWeighings', fn ($q) => $q->where('bad_pieces', '>', 0));
        }

        if ($this->filterType === 'crimp') {
            $query->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', true));
        } elseif ($this->filterType === 'standard') {
            $query->whereHas('workOrder.purchaseOrder.part', fn ($q) => $q->where('is_crimp', false));
        }

        $lots = $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $pendingLots = $this->scopePendingQuality(
            Lot::whereHas('weighings')->with('workOrder.purchaseOrder.part')
                ->withSum('weighings as prod_good_sum', 'good_pieces')
                ->withSum('qualityWeighings as qual_good_sum', 'good_pieces')
                ->withSum('qualityWeighings as qual_bad_sum', 'bad_pieces')
        )->orderByDesc('id')->limit(12)->get();

        return view('livewire.admin.quality.quality-weighings', [
            'lots' => $lots,
            'pendingLots' => $pendingLots,
            'perPageOptions' => self::PER_PAGE,
            'stats' => $this->stats,
        ])->layout('components.layouts.app');
    }
}
