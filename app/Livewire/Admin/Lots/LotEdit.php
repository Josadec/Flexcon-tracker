<?php

namespace App\Livewire\Admin\Lots;

use App\Models\Lot;
use Illuminate\Validation\Rule;
use Livewire\Component;

class LotEdit extends Component
{
    public Lot $lot;
    public string $lot_number = '';
    public int $quantity = 0;

    protected function rules(): array
    {
        return [
            // La base tiene índice único (work_order_id, lot_number): sin esta
            // regla, un número repetido salía como error de SQL en pantalla.
            'lot_number' => [
                'required', 'string', 'max:255',
                Rule::unique('lots', 'lot_number')
                    ->where('work_order_id', $this->lot->work_order_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->lot->id),
            ],
            'quantity' => 'required|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'lot_number.unique' => 'Esa orden ya tiene otro viajero con ese número.',
        ];
    }

    public function mount(Lot $lot): void
    {
        $this->lot = $lot->load(['workOrder.purchaseOrder.part']);
        $this->lot_number = $lot->lot_number;
        $this->quantity = $lot->quantity;
    }

    public function save(): void
    {
        $this->validate();

        // Validar que la suma de lotes no sobrepase la Cant. WO
        $workOrder = $this->lot->workOrder;
        $otherLotsTotal = $workOrder->lots()->where('id', '!=', $this->lot->id)->sum('quantity');
        if (($otherLotsTotal + $this->quantity) > $workOrder->original_quantity) {
            $available = $workOrder->original_quantity - $otherLotsTotal;
            $this->addError('quantity', 'La cantidad total de lotes sobrepasaría la Cant. WO (' . number_format($workOrder->original_quantity) . '). Máximo disponible: ' . number_format($available));
            return;
        }

        $this->lot->update([
            'lot_number' => $this->lot_number,
            'quantity' => $this->quantity,
        ]);

        session()->flash('message', 'Lote actualizado correctamente.');
        $this->redirect(route('admin.lots.show', $this->lot), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.lots.lot-edit');
    }
}
