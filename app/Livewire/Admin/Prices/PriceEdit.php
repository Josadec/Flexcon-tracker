<?php

namespace App\Livewire\Admin\Prices;

use App\Models\Part;
use App\Models\Price;
use Livewire\Component;

class PriceEdit extends Component
{
    public Price $price;
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

    // Mensaje informativo en tiempo real
    public string $info_message = '';
    public bool $has_existing_prices = false;

    public function mount(Price $price): void
    {
        $this->price = $price->load('tiers');
        $this->part_id = (string) $price->part_id;
        $this->sample_price = (string) $price->sample_price;
        $this->workstation_type = $price->workstation_type ?? 'table';
        $this->effective_date = $price->effective_date->format('Y-m-d');
        $this->active = $price->active;
        $this->comments = $price->comments ?? '';

        // Cargar tiers reales desde BD
        $this->tiers = $price->tiers_array;

        $this->checkForConflicts();
    }

    public function updatedPartId(): void
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
        $this->info_message = '';
        $this->has_existing_prices = false;

        if (empty($this->part_id)) {
            return;
        }

        $allPrices = Price::where('part_id', $this->part_id)
            ->where('id', '!=', $this->price->id)
            ->get();

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

            $this->info_message = "Esta parte tiene otros precios registrados - " . implode(' | ', $info);
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
            'part_id.required'           => 'Debe seleccionar una parte.',
            'part_id.exists'             => 'La parte seleccionada no es válida.',
            'sample_price.required'      => 'El precio de muestra es obligatorio.',
            'sample_price.numeric'       => 'El precio de muestra debe ser un número.',
            'sample_price.min'           => 'El precio de muestra debe ser mayor o igual a 0.',
            'workstation_type.required'  => 'Debe seleccionar un tipo de estación de trabajo.',
            'workstation_type.in'        => 'El tipo de estación de trabajo no es válido.',
            'effective_date.required'    => 'La fecha efectiva es obligatoria.',
            'effective_date.date'        => 'La fecha efectiva debe ser una fecha válida.',
            'tiers.*.min_quantity.numeric' => 'La cantidad mínima debe ser un número.',
            'tiers.*.max_quantity.numeric' => 'La cantidad máxima debe ser un número.',
            'tiers.*.tier_price.numeric'   => 'El precio del tier debe ser un número.',
        ];
    }

    public function updatePrice(): void
    {
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
            $this->price->update([
                'part_id'          => $this->part_id,
                'sample_price'     => $this->sample_price,
                'workstation_type' => $this->workstation_type,
                'effective_date'   => $this->effective_date,
                'active'           => $this->active,
                'comments'         => $this->comments,
            ]);

            $this->price->syncTiers($this->tiers);

            session()->flash('flash.banner', 'Precio actualizado correctamente.');
            session()->flash('flash.bannerStyle', 'success');

            $this->redirect(route('admin.prices.index'), navigate: true);
        } catch (\Exception $e) {
            $this->addError('general', 'Error al actualizar el precio: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Cargar partes activas + la parte actual del precio (aunque esté inactiva
        // o soft-deleted) para que el dropdown siempre pueda mostrar la selección.
        $parts = Part::query()
            ->withTrashed()
            ->where(function ($q) {
                $q->where('active', true)
                  ->orWhere('id', $this->price->part_id);
            })
            ->orderBy('number')
            ->get();

        // Garantizar que la parte actual exista en la colección incluso si el
        // query la excluyó por algún motivo (cache, scope global, etc).
        if ($this->price->part_id && !$parts->contains('id', $this->price->part_id)) {
            $current = Part::withTrashed()->find($this->price->part_id);
            if ($current) {
                $parts->push($current);
                $parts = $parts->sortBy('number')->values();
            }
        }

        return view('livewire.admin.prices.price-edit', [
            'parts'            => $parts,
            'workstationTypes' => Price::WORKSTATION_TYPES,
        ]);
    }
}
