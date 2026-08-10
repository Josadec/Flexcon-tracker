<?php

namespace App\Livewire\Admin;

use App\Models\Lot;
use App\Models\Machine;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\SentList;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\AdminNavigation;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Buscador global (Ctrl+K / ⌘K).
 *
 * Resuelve lo que antes no se podía hacer: alguien tiene en la mano el viajero
 * 0042 y tenía que adivinar en qué pantalla vive para usar el filtro local de
 * esa pantalla. Aquí se teclea y se salta directo al registro — o a cualquier
 * pantalla del menú.
 *
 * Todo lo que ofrece está filtrado por los permisos reales del usuario: cada
 * grupo se consulta sólo si su pantalla de destino le está permitida.
 */
class GlobalSearch extends Component
{
    public string $q = '';

    /** Debajo de esto no se consulta la base: dos letras traen media planta. */
    private const MIN_LENGTH = 2;

    /** Resultados por grupo. Un puñado por tipo: esto es un salto, no un listado. */
    private const PER_GROUP = 5;

    public function clear(): void
    {
        $this->q = '';
    }

    private function user(): ?User
    {
        return auth()->user();
    }

    private function can(string $routeName): bool
    {
        return AdminNavigation::canAccess($routeName, $this->user());
    }

    /** Iconos por grupo: leer el tipo de un vistazo es la mitad del valor de la lista. */
    private const ICONS = [
        'screen' => 'M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 9h16',
        'lot' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
        'wo' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        'po' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'part' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'sent' => 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1',
        'user' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'machine' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    ];

    /**
     * Grupos de resultados, en el orden en el que se pintan.
     *
     * @return array<int, array{title: string, icon: string, items: array<int, array{label: string, meta: string, url: string}>}>
     */
    #[Computed]
    public function groups(): array
    {
        $term = trim($this->q);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $like = '%'.$term.'%';

        $grupos = [
            ['title' => 'Ir a', 'icon' => self::ICONS['screen'], 'items' => $this->screenResults($term)],
            ['title' => 'Viajeros', 'icon' => self::ICONS['lot'], 'items' => $this->lotResults($like)],
            ['title' => 'Órdenes de trabajo', 'icon' => self::ICONS['wo'], 'items' => $this->workOrderResults($like)],
            ['title' => 'Órdenes de compra', 'icon' => self::ICONS['po'], 'items' => $this->purchaseOrderResults($like)],
            ['title' => 'Partes', 'icon' => self::ICONS['part'], 'items' => $this->partResults($like)],
            ['title' => 'Listas de envío', 'icon' => self::ICONS['sent'], 'items' => $this->sentListResults($term, $like)],
            ['title' => 'Usuarios', 'icon' => self::ICONS['user'], 'items' => $this->userResults($like)],
            ['title' => 'Máquinas', 'icon' => self::ICONS['machine'], 'items' => $this->machineResults($like)],
        ];

        return array_values(array_filter($grupos, fn ($g) => $g['items'] !== []));
    }

    #[Computed]
    public function total(): int
    {
        return array_sum(array_map(fn ($g) => count($g['items']), $this->groups));
    }

    private function screenResults(string $term): array
    {
        return array_map(fn ($p) => [
            'label' => $p['label'],
            'meta' => $p['group'],
            'url' => $p['url'],
        ], AdminNavigation::search($this->user(), $term, self::PER_GROUP));
    }

    private function lotResults(string $like): array
    {
        if (! $this->can('admin.lots.show')) {
            return [];
        }

        return Lot::with('workOrder.purchaseOrder.part')
            ->where('lot_number', 'like', $like)
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn ($lot) => [
                'label' => 'Viajero '.$lot->lot_number,
                'meta' => 'WO '.($lot->workOrder?->purchaseOrder?->wo ?? '—')
                    .' · '.($lot->workOrder?->purchaseOrder?->part?->number ?? 'sin parte')
                    .' · '.number_format($lot->quantity).' pz',
                'url' => route('admin.lots.show', $lot),
            ])->all();
    }

    private function workOrderResults(string $like): array
    {
        if (! $this->can('admin.work-orders.show')) {
            return [];
        }

        return WorkOrder::with('purchaseOrder.part')
            ->where('wo_number', 'like', $like)
            ->orWhereHas('purchaseOrder', fn ($q) => $q->where('wo', 'like', $like))
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn ($wo) => [
                'label' => 'WO '.($wo->purchaseOrder?->wo ?? $wo->wo_number),
                'meta' => ($wo->purchaseOrder?->part?->number ?? 'sin parte')
                    .' · '.number_format($wo->original_quantity ?? 0).' pz',
                'url' => route('admin.work-orders.show', $wo),
            ])->all();
    }

    private function purchaseOrderResults(string $like): array
    {
        if (! $this->can('admin.purchase-orders.show')) {
            return [];
        }

        return PurchaseOrder::with('part')
            ->where('po_number', 'like', $like)
            ->orWhere('wo', 'like', $like)
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn ($po) => [
                'label' => 'PO '.$po->po_number,
                'meta' => ($po->part?->number ?? 'sin parte').' · '.number_format($po->quantity ?? 0).' pz',
                'url' => route('admin.purchase-orders.show', $po),
            ])->all();
    }

    private function partResults(string $like): array
    {
        if (! $this->can('admin.parts.show')) {
            return [];
        }

        return Part::where('number', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orderBy('number')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn ($part) => [
                'label' => 'Parte '.$part->number,
                'meta' => \Illuminate\Support\Str::limit($part->description ?: 'Sin descripción', 60)
                    .($part->is_crimp ? ' · CRIMP' : ''),
                'url' => route('admin.parts.show', $part),
            ])->all();
    }

    private function sentListResults(string $term, string $like): array
    {
        if (! $this->can('admin.sent-lists.show')) {
            return [];
        }

        $query = SentList::with('purchaseOrder.part');

        // La gente busca la lista por su número; si no es un número, por la
        // orden a la que pertenece.
        if (ctype_digit($term)) {
            $query->where('id', (int) $term);
        } else {
            $query->whereHas('purchaseOrder', fn ($q) => $q->where('wo', 'like', $like)
                ->orWhere('po_number', 'like', $like));
        }

        return $query->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn ($sl) => [
                'label' => 'Lista de envío #'.$sl->id,
                'meta' => ($sl->purchaseOrder?->part?->number ?? 'sin parte')
                    .' · '.$sl->department_label
                    .' · '.($sl->created_at?->format('d/m/Y') ?? ''),
                'url' => route('admin.sent-lists.show', $sl),
            ])->all();
    }

    private function userResults(string $like): array
    {
        if (! $this->can('admin.users.show')) {
            return [];
        }

        return User::where(fn ($q) => $q->where('name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('account', 'like', $like))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn ($user) => [
                'label' => trim($user->name.' '.($user->last_name ?? '')),
                'meta' => $user->email.($user->getRoleNames()->isNotEmpty() ? ' · '.$user->getRoleNames()->implode(', ') : ''),
                'url' => route('admin.users.show', $user),
            ])->all();
    }

    private function machineResults(string $like): array
    {
        if (! $this->can('admin.machines.show')) {
            return [];
        }

        return Machine::where('name', 'like', $like)
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn ($machine) => [
                'label' => 'Máquina '.$machine->name,
                'meta' => 'Catálogo de máquinas',
                'url' => route('admin.machines.show', $machine),
            ])->all();
    }

    public function render()
    {
        return view('livewire.admin.global-search');
    }
}
