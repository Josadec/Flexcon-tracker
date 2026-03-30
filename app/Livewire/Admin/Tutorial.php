<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class Tutorial extends Component
{
    public string $activeSection = 'admin';

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            $this->activeSection = 'admin';
        } elseif ($user->hasRole('Produccion')) {
            $this->activeSection = 'produccion';
        } elseif ($user->hasRole('Calidad')) {
            $this->activeSection = 'calidad';
        } elseif ($user->hasRole('Materiales')) {
            $this->activeSection = 'materiales';
        } elseif ($user->hasRole('Empaques')) {
            $this->activeSection = 'empaques';
        }
    }

    public function render()
    {
        return view('livewire.admin.tutorial');
    }
}
