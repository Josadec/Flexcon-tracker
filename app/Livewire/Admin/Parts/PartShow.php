<?php

namespace App\Livewire\Admin\Parts;

use App\Models\Part;
use App\Models\Price;
use Livewire\Component;

class PartShow extends Component
{
    public Part $part;

    public array $pricesByType = [];

    /**
     * Alta y edición de precios sin salir de la parte.
     *
     * Antes "Agregar precio" navegaba a /admin/prices/create?part_id=…, lo que
     * sacaba al usuario del contexto de la parte y lo devolvía al listado
     * general de precios. Aquí se hace en un modal y la tabla se recarga sola.
     */
    public bool $showPriceModal = false;

    public ?int $editingPriceId = null;

    public string $priceWorkstationType = 'table';

    public string $priceSample = '';

    public string $priceEffectiveDate = '';

    public bool $priceActive = true;

    public string $priceComments = '';

    /** @var array<int, array{id: ?int, min_quantity: string, max_quantity: string, tier_price: string}> */
    public array $priceTiers = [];

    public function mount(Part $part): void
    {
        $this->part = $part;
        $this->loadPricesByType();
    }

    private function loadPricesByType(): void
    {
        $prices = $this->part->prices()->with('tiers')->get();

        // Inicializar array con todos los tipos
        foreach (Price::WORKSTATION_TYPES as $type => $label) {
            $this->pricesByType[$type] = [
                'label' => $label,
                'prices' => $prices
                    ->where('workstation_type', $type)
                    ->sortByDesc('effective_date')
                    ->values()
                    ->all(),
            ];
        }
    }

    public function openPriceModal(string $workstationType = 'table'): void
    {
        $this->resetPriceForm();

        $this->priceWorkstationType = array_key_exists($workstationType, Price::WORKSTATION_TYPES)
            ? $workstationType
            : 'table';

        $this->showPriceModal = true;
    }

    public function openEditPriceModal(int $priceId): void
    {
        $price = $this->part->prices()->with('tiers')->findOrFail($priceId);

        $this->resetPriceForm();

        $this->editingPriceId       = $price->id;
        $this->priceWorkstationType = $price->workstation_type;
        $this->priceSample          = (string) $price->sample_price;
        $this->priceEffectiveDate   = $price->effective_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->priceActive          = (bool) $price->active;
        $this->priceComments        = (string) ($price->comments ?? '');
        $this->priceTiers           = $price->tiers_array ?: $this->blankTiers();

        $this->showPriceModal = true;
    }

    public function closePriceModal(): void
    {
        $this->showPriceModal = false;
        $this->resetPriceForm();
    }

    private function resetPriceForm(): void
    {
        $this->resetValidation();

        $this->editingPriceId     = null;
        $this->priceSample        = '';
        $this->priceEffectiveDate = now()->format('Y-m-d');
        $this->priceActive        = true;
        $this->priceComments      = '';
        $this->priceTiers         = $this->blankTiers();
    }

    /** @return array<int, array{id: null, min_quantity: string, max_quantity: string, tier_price: string}> */
    private function blankTiers(): array
    {
        return [
            ['id' => null, 'min_quantity' => '', 'max_quantity' => '', 'tier_price' => ''],
        ];
    }

    public function addPriceTier(): void
    {
        $this->priceTiers[] = ['id' => null, 'min_quantity' => '', 'max_quantity' => '', 'tier_price' => ''];
    }

    public function removePriceTier(int $index): void
    {
        if (isset($this->priceTiers[$index])) {
            unset($this->priceTiers[$index]);
            $this->priceTiers = array_values($this->priceTiers);
        }
    }

    protected function rules(): array
    {
        return [
            'priceWorkstationType'      => 'required|in:table,machine,semi_automatic',
            'priceSample'               => 'required|numeric|min:0',
            'priceEffectiveDate'        => 'required|date',
            'priceActive'               => 'boolean',
            'priceComments'             => 'nullable|string',
            'priceTiers'                => 'array',
            'priceTiers.*.min_quantity' => 'nullable|numeric|min:0',
            'priceTiers.*.max_quantity' => 'nullable|numeric|min:0',
            'priceTiers.*.tier_price'   => 'nullable|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'priceWorkstationType.required'      => 'Debe seleccionar un tipo de estación.',
            'priceWorkstationType.in'            => 'El tipo de estación no es válido.',
            'priceSample.required'               => 'El precio de muestra es obligatorio.',
            'priceSample.numeric'                => 'El precio de muestra debe ser un número.',
            'priceSample.min'                    => 'El precio de muestra debe ser mayor o igual a 0.',
            'priceEffectiveDate.required'        => 'La fecha efectiva es obligatoria.',
            'priceEffectiveDate.date'            => 'La fecha efectiva debe ser una fecha válida.',
            'priceTiers.*.min_quantity.numeric'  => 'La cantidad mínima debe ser un número.',
            'priceTiers.*.max_quantity.numeric'  => 'La cantidad máxima debe ser un número.',
            'priceTiers.*.tier_price.numeric'    => 'El precio del nivel debe ser un número.',
        ];
    }

    public function savePrice(): void
    {
        $this->validate();

        // Un nivel con precio necesita cantidad mínima; si no, no se sabe desde
        // cuándo aplica. Se avisa en el campo exacto en vez de descartarlo callado.
        foreach ($this->priceTiers as $i => $tier) {
            if (! empty($tier['tier_price']) && empty($tier['min_quantity'])) {
                $this->addError("priceTiers.{$i}.min_quantity", 'Este nivel tiene precio pero no tiene cantidad mínima.');

                return;
            }

            if (! empty($tier['max_quantity']) && ! empty($tier['min_quantity'])
                && (float) $tier['max_quantity'] < (float) $tier['min_quantity']) {
                $this->addError("priceTiers.{$i}.max_quantity", 'La cantidad máxima no puede ser menor que la mínima.');

                return;
            }
        }

        $attributes = [
            'part_id'          => $this->part->id,
            'sample_price'     => $this->priceSample,
            'workstation_type' => $this->priceWorkstationType,
            'effective_date'   => $this->priceEffectiveDate,
            'active'           => $this->priceActive,
            'comments'         => $this->priceComments,
        ];

        try {
            if ($this->editingPriceId) {
                $price = $this->part->prices()->findOrFail($this->editingPriceId);
                $price->update($attributes);
                $message = 'Precio actualizado correctamente.';
            } else {
                $price = Price::create($attributes);
                $message = 'Precio creado correctamente.';
            }

            $price->syncTiers($this->priceTiers);

            $this->showPriceModal = false;
            $this->resetPriceForm();

            // Refrescar la parte y la tabla para reflejar el cambio de inmediato.
            $this->part->refresh();
            $this->loadPricesByType();

            session()->flash('message', $message);
        } catch (\Exception $e) {
            $this->addError('priceSample', 'No se pudo guardar el precio: ' . $e->getMessage());
        }
    }

    public function deletePrice(int $priceId): void
    {
        try {
            $this->part->prices()->findOrFail($priceId)->delete();

            $this->part->refresh();
            $this->loadPricesByType();

            session()->flash('message', 'Precio eliminado correctamente.');
        } catch (\Exception $e) {
            session()->flash('error', 'No se pudo eliminar el precio: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.parts.part-show', [
            'workstationTypes' => Price::WORKSTATION_TYPES,
        ]);
    }
}
