<?php

namespace App\Livewire\Admin\History;

use App\Models\AuditTrail;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\PackingSlip;
use App\Models\PackingSlipItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\WorkOrder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Historial del sistema: qué pasó, cuándo y quién lo hizo.
 *
 * Hasta ahora `audit_trails` se escribía y **no se leía desde ninguna parte**:
 * no había pantalla, ni ruta, ni vista. Para una retención de 5 años por ISO,
 * poder demostrar lo que pasó es la mitad del requisito.
 *
 * Es de sólo lectura y la consultan todos los departamentos, como pidió el
 * cliente. La búsqueda es por lo que la gente tiene a mano: número de orden,
 * de parte, de viajero, o el nombre de quien hizo el cambio.
 *
 * Sirve para dos cosas con el mismo código: la pantalla completa, y la línea de
 * tiempo de un registro concreto embebida en su ficha (modo `entityType`).
 */
#[Layout('components.layouts.app')]
class HistoryExplorer extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'tipo', except: '')]
    public string $filterType = '';

    #[Url(as: 'accion', except: '')]
    public string $filterAction = '';

    #[Url(as: 'desde', except: '')]
    public string $startDate = '';

    #[Url(as: 'hasta', except: '')]
    public string $endDate = '';

    public int $perPage = 25;

    /** Modo embebido: la línea de tiempo de un registro concreto. */
    public ?string $entityType = null;
    public ?int $entityId = null;

    /** Nombres legibles de lo que se audita. La clave es la clase real. */
    public const ENTITIES = [
        PurchaseOrder::class => 'Orden de compra',
        WorkOrder::class => 'Orden de trabajo',
        SentList::class => 'Lista de envío',
        Lot::class => 'Viajero',
        PackingSlip::class => 'Packing slip',
        PackingSlipItem::class => 'Renglón de packing slip',
        Invoice::class => 'Factura',
    ];

    /** Nombres legibles de las acciones. */
    public const ACTIONS = [
        'create' => 'Alta',
        'update' => 'Cambio',
        'delete' => 'Baja',
        'restore' => 'Restauración',
        'reopen' => 'Reapertura',
        'status_change' => 'Cambio de estado',
        'returned_to_packaging' => 'Devuelto a Empaque',
        'legacy.wo_status' => 'Cambio de estado (histórico)',
        'legacy.lot_cycle' => 'Ciclo de viajero (histórico)',
        'legacy.sent_list_rejection' => 'Rechazo de lista (histórico)',
    ];

    public function mount(?string $entityType = null, ?int $entityId = null): void
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterType = '';
        $this->filterAction = '';
        $this->startDate = '';
        $this->endDate = '';
        $this->resetPage();
    }

    /** ¿Está embebido en la ficha de un registro? */
    public function isEmbedded(): bool
    {
        return $this->entityType !== null && $this->entityId !== null;
    }

    private function query()
    {
        $query = AuditTrail::query()->with('user');

        if ($this->isEmbedded()) {
            return $query->where('auditable_type', $this->entityType)
                ->where('auditable_id', $this->entityId);
        }

        if ($this->search !== '') {
            $this->applySearch($query);
        }

        if ($this->filterType !== '') {
            $query->where('auditable_type', $this->filterType);
        }

        if ($this->filterAction !== '') {
            $query->where('action', $this->filterAction);
        }

        if ($this->startDate !== '') {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate !== '') {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        return $query;
    }

    /**
     * Busca por lo que la gente tiene delante: el WO impreso en el viajero, el
     * número de parte, el número de viajero, o quién hizo el cambio.
     *
     * El texto se traduce a ids y se filtra por las columnas de contexto, que
     * es lo que hace que esto sea una consulta con índice y no un recorrido de
     * una tabla polimórfica.
     */
    private function applySearch($query): void
    {
        $like = '%'.trim($this->search).'%';

        $woIds = WorkOrder::where('wo_number', 'like', $like)
            ->orWhereHas('purchaseOrder', fn ($q) => $q->where('wo', 'like', $like))
            ->pluck('id');

        $poIds = PurchaseOrder::where('po_number', 'like', $like)
            ->orWhere('wo', 'like', $like)
            ->pluck('id');

        $partIds = Part::where('number', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhere('item_number', 'like', $like)
            ->pluck('id');

        $lotIds = Lot::withTrashed()->where('lot_number', 'like', $like)->pluck('id');

        $query->where(function ($q) use ($woIds, $poIds, $partIds, $lotIds, $like) {
            $q->whereIn('work_order_id', $woIds)
                ->orWhereIn('purchase_order_id', $poIds)
                ->orWhereIn('part_id', $partIds)
                ->orWhereIn('lot_id', $lotIds)
                ->orWhere('user_name', 'like', $like);
        });
    }

    /** Etiquetas de los tipos que realmente tienen historial. */
    #[Computed]
    public function entityOptions(): array
    {
        $presentes = AuditTrail::select('auditable_type')->distinct()->pluck('auditable_type');

        return collect(self::ENTITIES)
            ->filter(fn ($label, $class) => $presentes->contains($class))
            ->all();
    }

    #[Computed]
    public function actionOptions(): array
    {
        $presentes = AuditTrail::select('action')->distinct()->pluck('action');

        return $presentes
            ->mapWithKeys(fn ($a) => [$a => self::ACTIONS[$a] ?? $a])
            ->sort()
            ->all();
    }

    public function render()
    {
        return view('livewire.admin.history.history-explorer', [
            'entries' => $this->query()->orderByDesc('created_at')->orderByDesc('id')
                ->paginate($this->isEmbedded() ? 10 : $this->perPage),
        ]);
    }
}
