<?php

use App\Livewire\Admin\SentLists\TvDisplay;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Ruta pública para el monitor TV de listas de envío (sin autenticación)
Route::get('/tv', TvDisplay::class)->name('tv.display');

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    } elseif ($user->hasRole('Materiales')) {
        return redirect()->route('admin.materials.index');
    } elseif ($user->hasRole('Produccion')) {
        return redirect()->route('admin.production.index');
    } elseif ($user->hasRole('Calidad')) {
        return redirect()->route('admin.quality.index');
    } elseif ($user->hasRole('Empaques')) {
        return redirect()->route('admin.packaging.index');
    } elseif ($user->hasRole('employee')) {
        return redirect()->route('employee.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
});

require __DIR__.'/auth.php';
