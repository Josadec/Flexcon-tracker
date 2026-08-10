<?php

namespace App\Livewire\Admin\Holidays;

use App\Models\Holiday;
use Livewire\Component;

class HolidayCreate extends Component
{
    public string $name = '';
    public string $date = '';
    public string $description = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'description' => 'nullable|string',
        ];
    }

    public function saveHoliday(): void
    {
        $this->validate();

        Holiday::create([
            'name' => $this->name,
            'date' => $this->date,
            'description' => $this->description,
        ]);

        session()->flash('message', 'Día festivo creado correctamente.');

        $this->redirect(route('admin.holidays.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.holidays.holiday-create');
    }
}
