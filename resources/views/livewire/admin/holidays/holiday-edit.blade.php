<x-ui.page eyebrow="Administración" :title="'Editar '.$holiday->name"
    subtitle="Al cambiar la fecha, el día que dejas libre vuelve a contar como hábil."
    back="{{ route('admin.holidays.index') }}" backLabel="Volver a días festivos">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.holidays.show', $holiday) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="updateHoliday" class="space-y-5">
        <x-ui.section title="Día festivo" hint="Un registro por fecha; si el asueto dura varios días, captura uno por día.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" required>
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
                <textarea wire:model="description" rows="3" class="w-full"></textarea>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Alta" :value="$holiday->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$holiday->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.holidays.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
