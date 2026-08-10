<?php

namespace App\Livewire\Admin\PurchaseOrders;

use App\Models\Part;
use App\Models\Price;
use App\Models\PurchaseOrder;
use App\Services\POPriceDetectionService;
use App\Services\PurchaseOrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class POEdit extends Component
{
    use WithFileUploads;

    public PurchaseOrder $purchaseOrder;

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

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->po_number = $purchaseOrder->po_number;
        $this->wo = $purchaseOrder->wo ?? '';
        $this->part_id = $purchaseOrder->part_id;
        $this->workstation_type = $purchaseOrder->workstation_type;
        $this->po_date = $purchaseOrder->po_date->format('Y-m-d');
        $this->due_date = $purchaseOrder->due_date->format('Y-m-d');
        $this->quantity = $purchaseOrder->quantity;
        $this->unit_price = (string) $purchaseOrder->unit_price;
        $this->comments = $purchaseOrder->comments ?? '';

        $this->loadAvailableWorkstationTypes();
        $this->validatePrice();
    }

    protected function rules(): array
    {
        return [
            'po_number' => ['required', 'string', 'max:255', Rule::unique('purchase_orders', 'po_number')->ignore($this->purchaseOrder->id)->withoutTrashed()],
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
     * a partir de los Prices activos. Si no hay uno seleccionado, sugiere uno.
     */
    protected function loadAvailableWorkstationTypes(): void
    {
        $this->available_workstation_types = [];

        if (! $this->part_id) {
            return;
        }

        $part = Part::find($this->part_id);
        if (! $part) {
            return;
        }

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

        // Si no hay seleccionado, sugerir default desde el Standard
        if (! $this->workstation_type) {
            $standard = $part->standards()->where('active', true)->first();
            if ($standard) {
                $detectionService = app(POPriceDetectionService::class);
                $tmp = new PurchaseOrder(['part_id' => $part->id]);
                $tmp->setRelation('part', $part);
                $detection = $detectionService->detectPrice($tmp);
                if ($detection->found && $activePrices->has($detection->workstationType)) {
                    $this->workstation_type = $detection->workstationType;
                }
            }
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

    public function updatePO(): void
    {
        $this->validate();

        $previousPdfPath = $this->purchaseOrder->pdf_path;
        $pdfPath = $previousPdfPath;
        if ($this->pdf_file) {
            $pdfPath = $this->pdf_file->store('purchase-orders', 'public');
        }

        $previousStatus = $this->purchaseOrder->status;

        try {
            $this->purchaseOrder->update([
                'po_number' => $this->po_number,
                'wo' => $this->wo ?: null,
                'part_id' => $this->part_id,
                'workstation_type' => $this->workstation_type ?: null,
                'po_date' => $this->po_date,
                'due_date' => $this->due_date,
                'quantity' => $this->quantity,
                'unit_price' => $this->unit_price,
                'comments' => $this->comments ?: null,
                'pdf_path' => $pdfPath,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otra sesión tomó el mismo número entre el validate() y el update.
            if ($pdfPath !== $previousPdfPath) {
                Storage::disk('public')->delete($pdfPath);
            }

            throw ValidationException::withMessages([
                'po_number' => 'Ya existe una orden de compra con este número.',
            ]);
        }

        // El PDF anterior se descarta solo cuando el update ya quedó guardado.
        if ($previousPdfPath && $pdfPath !== $previousPdfPath) {
            Storage::disk('public')->delete($previousPdfPath);
        }

        // SIEMPRE revalidar el precio después de actualizar (consistente con Create)
        $validation = $this->purchaseOrderService->validatePrice($this->purchaseOrder);

        if (! $validation['valid']) {
            // Marcar como pending_correction sin sobreescribir los comments del usuario
            $this->purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_PENDING_CORRECTION,
            ]);

            $bannerMsg = $previousStatus === PurchaseOrder::STATUS_PENDING_CORRECTION
                ? 'Orden de compra actualizada pero aún requiere corrección de precio. '.$validation['message']
                : 'Orden de compra actualizada. El precio no es válido y la PO quedó marcada para corrección. '.$validation['message'];

            session()->flash('flash.banner', $bannerMsg);
            session()->flash('flash.bannerStyle', 'warning');
        } else {
            // Precio válido: si estaba en pending_correction, regresar a pending
            if ($previousStatus === PurchaseOrder::STATUS_PENDING_CORRECTION) {
                $this->purchaseOrder->update(['status' => PurchaseOrder::STATUS_PENDING]);
                session()->flash('flash.banner', 'Orden de compra actualizada. El precio ahora es válido.');
            } else {
                session()->flash('flash.banner', 'Orden de compra actualizada correctamente.');
            }
            session()->flash('flash.bannerStyle', 'success');
        }

        $this->redirect(route('admin.purchase-orders.index'), navigate: true);
    }

    /**
     * Delete the current PDF file.
     */
    public function deletePdf(): void
    {
        if ($this->purchaseOrder->pdf_path) {
            Storage::disk('public')->delete($this->purchaseOrder->pdf_path);
            $this->purchaseOrder->update(['pdf_path' => null]);
            $this->purchaseOrder = $this->purchaseOrder->fresh();

            session()->flash('flash.banner', 'PDF eliminado correctamente.');
            session()->flash('flash.bannerStyle', 'success');
        }
    }

    public function render()
    {
        return view('livewire.admin.purchase-orders.po-edit', [
            'parts' => Part::active()->orderBy('number')->get(),
        ]);
    }
}
