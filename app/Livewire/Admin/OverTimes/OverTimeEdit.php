<?php

namespace App\Livewire\Admin\OverTimes;

use App\Models\OverTime;
use App\Models\Shift;
use App\Models\User;
use Livewire\Component;
use Carbon\Carbon;

class OverTimeEdit extends Component
{
    public OverTime $overTime;
    public string $name = '';
    public string $date = '';
    public ?string $shift_id = null;
    public string $start_time = '';
    public string $end_time = '';
    public string $break_minutes = '0';
    public string $comments = '';

    // Selección de empleados
    public array $selectedEmployeeIds = [];
    public string $employeeSearch = '';

    public function mount(OverTime $overTime): void
    {
        $this->overTime       = $overTime;
        $this->name           = $overTime->name;
        $this->date           = $overTime->date->toDateString();
        $this->shift_id       = $overTime->shift_id ? (string) $overTime->shift_id : null;
        $this->start_time     = Carbon::parse($overTime->start_time)->format('H:i');
        $this->end_time       = Carbon::parse($overTime->end_time)->format('H:i');
        $this->break_minutes  = (string) $overTime->break_minutes;
        $this->comments       = $overTime->comments ?? '';

        // Cargar empleados ya asignados como strings para Livewire 3
        $this->selectedEmployeeIds = $overTime->users
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function rules(): array
    {
        return [
            'name'                   => 'required|string|max:255',
            'date'                   => 'required|date',
            'shift_id'               => 'nullable|exists:shifts,id',
            'start_time'             => 'required|date_format:H:i',
            'end_time'               => 'required|date_format:H:i|after:start_time',
            'break_minutes'          => 'required|integer|min:0',
            'selectedEmployeeIds'    => 'required|array|min:1',
            'selectedEmployeeIds.*'  => 'exists:users,id',
            'comments'               => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'selectedEmployeeIds.required' => 'Debe seleccionar al menos un empleado.',
            'selectedEmployeeIds.min'      => 'Debe seleccionar al menos un empleado.',
        ];
    }

    public function getNetHoursProperty(): float
    {
        if (!$this->start_time || !$this->end_time) {
            return 0;
        }

        try {
            $start = Carbon::createFromFormat('H:i', $this->start_time);
            $end   = Carbon::createFromFormat('H:i', $this->end_time);

            // Handle overnight shifts
            if ($end->lessThan($start)) {
                $end->addDay();
            }

            $totalMinutes = $start->diffInMinutes($end);
            $breakMinutes = (int) ($this->break_minutes ?: 0);
            $netMinutes   = max(0, $totalMinutes - $breakMinutes);

            return round($netMinutes / 60, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getTotalHoursProperty(): float
    {
        $netHours  = $this->net_hours;
        $employees = count($this->selectedEmployeeIds);

        return round($netHours * $employees, 2);
    }

    public function update()
    {
        $this->validate();

        $this->overTime->update([
            'name'          => $this->name,
            'date'          => $this->date,
            'shift_id'      => $this->shift_id ?: null,
            'start_time'    => $this->start_time,
            'end_time'      => $this->end_time,
            'break_minutes' => $this->break_minutes,
            'comments'      => $this->comments ?: null,
        ]);

        $this->overTime->users()->sync($this->selectedEmployeeIds);

        session()->flash('flash.banner', 'Over Time actualizado correctamente.');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('admin.over-times.show', $this->overTime);
    }

    public function render()
    {
        $shifts = Shift::active()->orderBy('name')->get();

        $employees = User::active()
            ->employees()
            ->when($this->employeeSearch, function ($q) {
                $q->search($this->employeeSearch);
            })
            ->orderBy('name')
            ->get();

        $selectedEmployees = User::whereIn('id', $this->selectedEmployeeIds)
            ->orderBy('name')
            ->get();

        return view('livewire.admin.over-times.over-time-edit', compact('shifts', 'employees', 'selectedEmployees'));
    }
}
