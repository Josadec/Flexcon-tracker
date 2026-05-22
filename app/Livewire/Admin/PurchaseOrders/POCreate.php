<?php

namespace App\Livewire\Admin\PurchaseOrders;

use App\Models\Part;
use App\Models\Price;
use App\Models\PurchaseOrder;
use App\Services\POPriceDetectionService;
use App\Services\PurchaseOrderService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class POCreate extends Component
{
    use WithFileUploads;

    public string $po_number = '';

    public string $wo = '';

    public ?int $part_id = null;

    public ?string $workstation_type = null;

    public string $po_date = '';

    public string $due_date = '';

    public $quantity = 0;  // Changed from int to mixed to avoid type issues

    public string $unit_price = '';

    public string $comments = '';

    public $pdf_file = null;

    // Opciones de workstation_type disponibles para la parte seleccionada
    // [['value' => 'table', 'label' => 'Mesa de Trabajo', 'sample_price' => 0.5], ...]
    public array $available_workstation_types = [];

    // Price validation feedback
    public ?float $expected_price = null;

    public bool $price_valid = false;

    public string $price_message = '';

    protected PurchaseOrderService $purchaseOrderService;

    public function boot(PurchaseOrderService $purchaseOrderService): void
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    public function mount(): void
    {
        $this->po_date = now()->format('Y-m-d');
        $this->due_date = now()->addDays(30)->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'po_number' => ['required', 'string', 'max:255', Rule::unique('purchase_orders', 'po_number')->withoutTrashed()],
            'wo' => 'nullable|string|max:255',
            'part_id' => 'required|exists:parts,id',
            'workstation_type' => 'nullable|in:table,machine,semi_automatic',
            'po_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:po_date',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'comments' => 'nullable|string',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'po_number.required' => 'El número de PO es obligatorio.',
            'po_number.unique' => 'Ya existe una orden de compra con este número.',
            'part_id.required' => 'Debe seleccionar una parte.',
            'part_id.exists' => 'La parte seleccionada no existe.',
            'po_date.required' => 'La fecha de PO es obligatoria.',
            'due_date.required' => 'La fecha de entrega es obligatoria.',
            'due_date.after_or_equal' => 'La fecha de entrega debe ser igual o posterior a la fecha de PO.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'unit_price.required' => 'El precio unitario es obligatorio.',
            'unit_price.min' => 'El precio unitario debe ser mayor o igual a 0.',
            'pdf_file.required' => 'El documento PDF es obligatorio.',
            'pdf_file.mimes' => 'El archivo debe ser un PDF.',
            'pdf_file.max' => 'El archivo no debe superar los 10MB.',
        ];
    }

    public function selectPart($value): void
    {
        $this->part_id = $value ? (int) $value : null;
        $this->loadAvailableWorkstationTypes();
        $this->validatePrice();
    }

    public function updatedPartId(): void
    {
        $this->loadAvailableWorkstationTypes();
        $this->validatePrice();
    }

    public function updatedWorkstationType(): void
    {
        $this->validatePrice();
    }

    public function updatedQuantity(): void
    {
        $this->validatePrice();
    }

    public function updatedUnitPrice(): void
    {
        $this->validatePrice();
    }

    /**
     * Carga los workstation_type disponibles para la parte seleccionada
     * a partir de los Prices activos. Sugiere uno por defecto.
     */
    protected function loadAvailableWorkstationTypes(): void
    {
        $this->available_workstation_types = [];

        if (! $this->part_id) {
            $this->workstation_type = null;

            return;
        }

        $part = Part::find($this->part_id);
        if (! $part) {
            $this->workstation_type = null;

            return;
        }

        // Prices activos agrupados por workstation_type
        $activePrices = $part->prices()
            ->where('active', true)
            ->orderBy('workstation_type')
            ->get()
            ->keyBy('workstation_type');

        foreach (Price::WORKSTATION_TYPES as $value => $label) {
            if ($activePrices->has($value)) {
                $this->available_workstation_types[] = [
                    'value' => $value,
                    'label' => $label,
                    'sample_price' => (float) $activePrices[$value]->sample_price,
                ];
            }
        }

        // Si el workstation_type actual ya no aplica para la nueva parte, limpiarlo
        if ($this->workstation_type && ! $activePrices->has($this->workstation_type)) {
            $this->workstation_type = null;
        }

        // Sugerir default: el que indique el Standard de la parte
        if (! $this->workstation_type) {
            $standard = $part->standards()->where('active', true)->first();
            if ($standard) {
                $detectionService = app(POPriceDetectionService::class);
                // Crear PO temporal "vacío" sólo para resolver el default
                $tmp = new PurchaseOrder(['part_id' => $part->id]);
                $tmp->setRelation('part', $part);
                $detection = $detectionService->detectPrice($tmp);
                if ($detection->found && $activePrices->has($detection->workstationType)) {
                    $this->workstation_type = $detection->workstationType;
                }
            }

            // Si aún no hay default (no hay Standard activo), tomar el primero disponible
            if (! $this->workstation_type && ! empty($this->available_workstation_types)) {
                $this->workstation_type = $this->available_workstation_types[0]['value'];
            }
        }
    }

    protected function validatePrice(): void
    {
        if (! $this->part_id || ! $this->quantity || ! $this->unit_price) {
            $this->expected_price = null;
            $this->price_valid = false;
            $this->price_message = '';

            return;
        }

        // Construir PO temporal para detectar precio usando el workstation_type elegido
        $tmpPO = new PurchaseOrder([
            'part_id' => $this->part_id,
            'workstation_type' => $this->workstation_type ?: null,
        ]);
        $part = Part::find($this->part_id);
        if ($part) {
            $tmpPO->setRelation('part', $part);
        }

        $priceDetectionService = app(POPriceDetectionService::class);
        $detection = $priceDetectionService->detectPrice($tmpPO);

        if (! $detection->found) {
            $this->expected_price = null;
            $this->price_valid = false;
            $this->price_message = $detection->error ?? 'No se pudo detectar el precio.';

            return;
        }

        $this->expected_price = $detection->price->getPriceForQuantity((int) $this->quantity);

        if ($this->expected_price === null) {
            $this->price_valid = false;
            $this->price_message = 'No se pudo calcular el precio para la cantidad especificada.';

            return;
        }

        $poPrice = (float) $this->unit_price;
        $tolerance = 0.0001;

        if (abs($poPrice - $this->expected_price) <= $tolerance) {
            $this->price_valid = true;
            $typeLabel = Price::WORKSTATION_TYPES[$detection->workstationType] ?? $detection->workstationType;
            $this->price_message = "El precio es válido para tipo de estación: {$typeLabel}";
        } else {
            $this->price_valid = false;
            $typeLabel = Price::WORKSTATION_TYPES[$detection->workstationType] ?? $detection->workstationType;
            $this->price_message = sprintf(
                'El precio no coincide. Precio esperado: $%.4f (Tipo: %s)',
                $this->expected_price,
                $typeLabel
            );
        }
    }

    public function savePO(): void
    {
        $this->validate();

        $pdfPath = null;
        if ($this->pdf_file) {
            $pdfPath = $this->pdf_file->store('purchase-orders', 'public');
        }

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => $this->po_number,
            'wo' => $this->wo ?: null,
            'part_id' => $this->part_id,
            'workstation_type' => $this->workstation_type ?: null,
            'po_date' => $this->po_date,
            'due_date' => $this->due_date,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'status' => PurchaseOrder::STATUS_PENDING,
            'comments' => $this->comments ?: null,
            'pdf_path' => $pdfPath,
        ]);

        // Validate price and update status accordingly
        $validation = $this->purchaseOrderService->validatePrice($purchaseOrder);

        if (! $validation['valid']) {
            $this->purchaseOrderService->markAsPendingCorrection(
                $purchaseOrder,
                $validation['message']
            );

            session()->flash('flash.banner', 'Orden de compra creada pero requiere corrección de precio.');
            session()->flash('flash.bannerStyle', 'warning');
        } else {
            session()->flash('flash.banner', 'Orden de compra creada correctamente.');
            session()->flash('flash.bannerStyle', 'success');
        }

        $this->redirect(route('admin.purchase-orders.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.purchase-orders.po-create', [
            'parts' => Part::active()->orderBy('number')->get(),
        ]);
    }
}
