<x-ui.page eyebrow="Administración" title="Crear día festivo"
    subtitle="El cálculo de capacidad descuenta este día de los días hábiles del período."
    back="{{ route('admin.holidays.index') }}" backLabel="Volver a días festivos">

    <form wire:submit="saveHoliday" class="space-y-5">
        <x-ui.section title="Día festivo" hint="Un registro por fecha; si el asueto dura varios días, captura uno por día.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" placeholder="Ej: Día de la Independencia" required>
                </x-ui.field>

                <x-ui.field label="Fecha" required
                    hint="El día exacto en que no se produce."
                    :error="$errors->first('date')">
                    <input wire:model="date" type="date" class="w-full" required>
                </x-ui.field>
            </div>

            <x-ui.field label="Descripción" optional class="mt-4"
                hint="Contexto: si es oficial, de la planta, o un paro programado."
                :error="$errors->first('description')">
                <textarea wire:model="description" rows="3" class="w-full"
                    placeholder="Descripción del día festivo..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.holidays.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear día festivo</x-ui.btn>
        </div>
    </form>
</x-ui.page>
