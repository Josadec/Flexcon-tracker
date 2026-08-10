<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $last_name = '';
    public string $email = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->last_name = $user->last_name ?? '';
        $this->email = $user->email;
    }

    /**
     * Actualiza los datos del usuario autenticado.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            // El apellido existe en la base y se usa en toda la aplicación, pero
            // esta pantalla no lo dejaba editar: sólo se podía cambiar entrando
            // como administrador al alta de usuarios.
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Escribe un correo válido.',
            'email.lowercase' => 'El correo debe ir en minúsculas.',
            'email.unique' => 'Ese correo ya está registrado por otra persona.',
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function with(): array
    {
        return ['user' => Auth::user()];
    }
}; ?>

<x-settings.layout active="profile" heading="Perfil"
    subheading="Tu nombre y tu correo. Es lo que ve el resto del equipo cuando firmas una pesada, una inspección o un empaque.">

    @php
        $verificaCorreo = $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail;
        $sinVerificar = $verificaCorreo && ! $user->hasVerifiedEmail();
    @endphp

    <form wire:submit="updateProfileInformation" class="space-y-5">
        <x-ui.section title="Datos personales" hint="Tu nombre aparece junto a cada registro que capturas.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input type="text" wire:model="name" autocomplete="name" autofocus class="w-full">
                </x-ui.field>

                <x-ui.field label="Apellido" optional :error="$errors->first('last_name')">
                    <input type="text" wire:model="last_name" autocomplete="family-name" class="w-full">
                </x-ui.field>
            </div>

            <x-ui.field label="Correo electrónico" required class="mt-4"
                hint="Con este correo entras al sistema."
                :error="$errors->first('email')">
                <input type="email" wire:model="email" autocomplete="email" class="w-full">
            </x-ui.field>

            @if ($sinVerificar)
                <x-ui.note tone="warn" title="Tu correo no está verificado" class="mt-4">
                    <p>Verifícalo para no perder el acceso si olvidas la contraseña.</p>
                    <button type="button" wire:click.prevent="resendVerificationNotification"
                        class="mt-2 font-semibold underline underline-offset-2">
                        Reenviar el correo de verificación
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-semibold">Listo: enviamos un enlace nuevo a tu correo.</p>
                    @endif
                </x-ui.note>
            @elseif ($verificaCorreo)
                <p class="mt-3 flex items-center gap-1.5 text-xs font-medium text-green-700 dark:text-green-300">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Correo verificado
                </p>
            @endif

            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                Si cambias el correo tendrás que volver a verificarlo.
            </p>
        </x-ui.section>

        <div class="flex flex-wrap items-center gap-3">
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
            <x-action-message on="profile-updated"
                class="font-semibold text-green-700 dark:text-green-300">
                Guardado.
            </x-action-message>
        </div>
    </form>

    {{-- Datos de la cuenta que no se editan aquí --}}
    <x-ui.section title="Tu cuenta" hint="Estos datos los administra el área de sistemas; aquí sólo se consultan.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Usuario" :value="$user->account ?: '—'" />
            <x-ui.kv label="Permisos"
                :value="$user->getRoleNames()->isNotEmpty() ? $user->getRoleNames()->implode(', ') : 'Sin rol asignado'"
                help="Determinan a qué áreas del sistema entras." />
            <x-ui.kv label="Cuenta creada" :value="$user->created_at?->format('d/m/Y') ?? '—'" />
        </dl>
    </x-ui.section>

    <livewire:admin.settings.delete-user-form />
</x-settings.layout>
