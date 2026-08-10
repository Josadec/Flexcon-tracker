<?php

use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public bool $showDeleteModal = false;
    public string $password = '';

    /**
     * Si el usuario es el único administrador, borrarse deja el sistema sin
     * nadie que pueda dar de alta usuarios, partes ni órdenes.
     */
    public function blockReason(): ?string
    {
        $user = Auth::user();

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return 'Eres el único administrador del sistema. Si borras tu cuenta, nadie podrá dar de alta '
                .'usuarios ni configurar el sistema. Nombra a otro administrador antes de hacerlo.';
        }

        return null;
    }

    public function confirmDelete(): void
    {
        if ($this->blockReason()) {
            return;
        }

        $this->password = '';
        $this->resetErrorBag();
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->password = '';
        $this->resetErrorBag();
    }

    public function deleteUser(Logout $logout): void
    {
        if ($motivo = $this->blockReason()) {
            $this->addError('password', $motivo);

            return;
        }

        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ], [
            'password.required' => 'Escribe tu contraseña para confirmar.',
            'password.current_password' => 'Esa no es tu contraseña.',
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    @php $bloqueo = $this->blockReason(); @endphp

    <x-ui.section title="Eliminar mi cuenta"
        hint="Cierra tu acceso al sistema. Los registros que capturaste (pesadas, inspecciones, empaques) se conservan.">

        @if ($bloqueo)
            <x-ui.note tone="warn" title="No puedes eliminar tu cuenta">{{ $bloqueo }}</x-ui.note>
        @else
            <x-ui.note tone="danger" title="Esto no se deshace desde aquí">
                Perderás el acceso de inmediato. Para volver a entrar, un administrador tendría que darte
                de alta otra vez.
            </x-ui.note>

            <div class="mt-4">
                <x-ui.btn variant="danger" wire:click="confirmDelete">Eliminar mi cuenta</x-ui.btn>
            </div>
        @endif
    </x-ui.section>

    @if ($showDeleteModal)
        <x-ui-modal wire:key="modal-delete-account" title="¿Eliminar tu cuenta?"
            subtitle="Confirma con tu contraseña. Al terminar, la sesión se cierra."
            close="cancelDelete" maxWidth="lg">

            <x-ui.note tone="danger">
                Vas a cerrar tu propio acceso a Flexcon Tracker. Lo que ya capturaste se queda en el
                historial, pero no podrás entrar de nuevo hasta que un administrador te reactive.
            </x-ui.note>

            <x-ui.section title="Confirma que eres tú">
                <x-ui.field label="Tu contraseña" required :error="$errors->first('password')">
                    <input type="password" wire:model="password" autocomplete="current-password" class="w-full">
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>Al confirmar se cierra la sesión y vuelves a la pantalla de inicio.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="cancelDelete">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="deleteUser">Sí, eliminar mi cuenta</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</div>
