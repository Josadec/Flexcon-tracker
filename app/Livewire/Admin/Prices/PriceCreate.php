<?php

namespace App\Livewire\Admin\Prices;

use App\Models\Part;
use App\Models\Price;
use Livewire\Component;

class PriceCreate extends Component
{
    public string $part_id = '';
    public string $sample_price = '';
    public string $workstation_type = 'table';
    public string $effective_date = '';
    public bool $active = true;
    public string $comments = '';

    /**
     * Tiers dinámicos. Cada item: ['min_quantity' => '', 'max_quantity' => '', 'tier_price' => '']
     */
    public array $tiers = [];

    // Validación en tiempo real
    public string $validation_message = '';
    public bool $has_conflict = false;
    public string $info_message = '';
    public bool $has_existing_prices = false;

    public function mount(): void
    {
        $this->effective_date = now()->format('Y-m-d');

        if (request()->has('part_id')) {
            $this->part_id = request('part_id');
            $this->checkForConflicts();
        }

        if (request()->has('workstation_type')) {
            $this->workstation_type = request('workstation_type');
            $this->checkForConflicts();
        }

        // Inicializar con un tier vacío para que el usuario tenga por dónde empezar
        $this->tiers = [
            ['id' => null, 'min_quantity' => '', 'max_quantity' => '', 'tier_price' => ''],
        ];
    }

    public function updatedPartId(): void
    {
        $this->checkForConflicts();
    }

    public function updatedActive(): void
    {
        $this->checkForConflicts();
    }

    public function addTier(): void
    {
        $this->tiers[] = [
            'id'           => null,
            'min_quantity' => '',
            'max_quantity' => '',
            'tier_price'   => '',
        ];
    }

    public function removeTier(int $index): void
    {
        if (isset($this->tiers[$index])) {
            unset($this->tiers[$index]);
            $this->tiers = array_values($this->tiers);
        }
    }

    protected function checkForConflicts(): void
    {
        $this->validation_message = '';
        $this->has_conflict = false;
        $this->info_message = '';
        $this->has_existing_prices = false;

        if (empty($this->part_id)) {
            return;
        }

        if ($this->active) {
            $existingActivePrice = Price::where('part_id', $this->part_id)
                ->where('active', true)
                ->first();

            if ($existingActivePrice) {
                $this->has_conflict = true;
                $typeLabel = Price::WORKSTATION_TYPES[$existingActivePrice->workstation_type] ?? $existingActivePrice->workstation_type;
                $this->validation_message = "Esta parte ya tiene un precio activo (Tipo: {$typeLabel}). Solo puede haber un precio activo por parte. Debes desactivar el precio existente primero o crear este precio como inactivo.";
                return;
            }
        }

        $allPrices = Price::where('part_id', $this->part_id)->get();

        if ($allPrices->isNotEmpty()) {
            $this->has_existing_prices = true;
            $activePrices = $allPrices->where('active', true);
            $inactivePrices = $allPrices->where('active', false);

            $info = [];
            if ($activePrices->isNotEmpty()) {
                $types = $activePrices->pluck('workstation_type')->map(function ($type) {
                    return Price::WORKSTATION_TYPES[$type] ?? $type;
                })->join(', ');
                $info[] = "Activos: {$types}";
            }
            if ($inactivePrices->isNotEmpty()) {
                $types = $inactivePrices->pluck('workstation_type')->map(function ($type) {
                    return Price::WORKSTATION_TYPES[$type] ?? $type;
                })->join(', ');
                $info[] = "Inactivos: {$types}";
            }

            $this->info_message = "Esta parte tiene precios registrados - " . implode(' | ', $info);
        }
    }

    protected function rules(): array
    {
        return [
            'part_id'                 => 'required|exists:parts,id',
            'sample_price'            => 'required|numeric|min:0',
            'workstation_type'        => 'required|in:table,machine,semi_automatic',
            'effective_date'          => 'required|date',
            'active'                  => 'boolean',
            'comments'                => 'nullable|string',
            'tiers'                   => 'array',
            'tiers.*.min_quantity'    => 'nullable|numeric|min:0',
            'tiers.*.max_quantity'    => 'nullable|numeric|min:0',
            'tiers.*.tier_price'      => 'nullable|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'part_id.required'             => 'Debe seleccionar una parte.',
            'part_id.exists'               => 'La parte seleccionada no es válida.',
            'sample_price.required'        => 'El precio de muestra es obligatorio.',
            'sample_price.numeric'         => 'El precio de muestra debe ser un número.',
            'sample_price.min'             => 'El precio de muestra debe ser mayor o igual a 0.',
            'workstation_type.required'    => 'Debe seleccionar un tipo de estación de trabajo.',
            'workstation_type.in'          => 'El tipo de estación de trabajo no es válido.',
            'effective_date.required'      => 'La fecha efectiva es obligatoria.',
            'effective_date.date'          => 'La fecha efectiva debe ser una fecha válida.',
            'tiers.*.min_quantity.numeric' => 'La cantidad mínima debe ser un número.',
            'tiers.*.max_quantity.numeric' => 'La cantidad máxima debe ser un número.',
            'tiers.*.tier_price.numeric'   => 'El precio del tier debe ser un número.',
        ];
    }

    public function savePrice(): void
    {
        if ($this->has_conflict && $this->active) {
            $this->addError('part_id', $this->validation_message);
            return;
        }

        $this->validate();

        // Validación adicional: si un tier tiene precio, debe tener min_quantity
        foreach ($this->tiers as $i => $tier) {
            $hasPrice = !empty($tier['tier_price']);
            $hasMin = !empty($tier['min_quantity']);

            if ($hasPrice && !$hasMin) {
                $this->addError("tiers.{$i}.min_quantity", "El tier #" . ($i + 1) . " tiene precio pero no tiene cantidad mínima.");
                return;
            }
        }

        try {
            $price = Price::create([
                'part_id'          => $this->part_id,
                'sample_price'     => $this->sample_price,
                'workstation_type' => $this->workstation_type,
                'effective_date'   => $this->effective_date,
                'active'           => $this->active,
                'comments'         => $this->comments,
            ]);

            $price->syncTiers($this->tiers);

            session()->flash('flash.banner', 'Precio creado correctamente.');
            session()->flash('flash.bannerStyle', 'success');

            $this->redirect(route('admin.prices.index'), navigate: true);
        } catch (\Exception $e) {
            $this->addError('general', 'Error al crear el precio: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.prices.price-create', [
            'parts'            => Part::active()->orderBy('number')->get(),
            'workstationTypes' => Price::WORKSTATION_TYPES,
        ]);
    }
}
