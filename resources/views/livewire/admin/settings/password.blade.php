<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Cambia la contraseña del usuario autenticado.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ], [
                // Las reglas de Laravel responden en inglés; el resto del sistema
                // está en español y esta pantalla la usa todo el piso.
                'current_password.required' => 'Escribe tu contraseña actual.',
                'current_password.current_password' => 'Esa no es tu contraseña actual.',
                'password.required' => 'Escribe la contraseña nueva.',
                'password.confirmed' => 'Las dos contraseñas nuevas no coinciden.',
                'password.min' => 'La contraseña nueva debe tener al menos :min caracteres.',
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<x-settings.layout active="password" heading="Contraseña"
    subheading="Cámbiala cuando quieras. Necesitas saber la actual: es lo que impide que alguien te la cambie si dejas la sesión abierta.">

    <form wire:submit="updatePassword" class="space-y-5">
        <x-ui.section step="1" title="Confirma que eres tú"
            hint="Sin la contraseña actual no se puede cambiar nada.">
            <x-ui.field label="Contraseña actual" required :error="$errors->first('current_password')">
                <input type="password" wire:model="current_password" autocomplete="current-password" class="w-full">
            </x-ui.field>
        </x-ui.section>

        <x-ui.section step="2" title="Elige la nueva" tone="accent"
            hint="Escríbela dos veces para descartar un error de tecleo.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Contraseña nueva" required :error="$errors->first('password')">
                    <input type="password" wire:model="password" autocomplete="new-password" class="w-full">
                </x-ui.field>

                <x-ui.field label="Repite la contraseña nueva" required :error="$errors->first('password_confirmation')">
                    <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full">
                </x-ui.field>
            </div>

            <x-ui.note tone="muted" class="mt-4">
                <p class="font-semibold">Para que sea segura:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    <li>Al menos 8 caracteres.</li>
                    <li>Que no sea la misma de otro sistema.</li>
                    <li>Evita nombres, el número de empleado o fechas de nacimiento.</li>
                </ul>
            </x-ui.note>
        </x-ui.section>

        <div class="flex flex-wrap items-center gap-3">
            <x-ui.btn variant="primary" type="submit">Cambiar contraseña</x-ui.btn>
            <x-action-message on="password-updated"
                class="font-semibold text-green-700 dark:text-green-300">
                Contraseña actualizada.
            </x-action-message>
        </div>
    </form>
</x-settings.layout>
