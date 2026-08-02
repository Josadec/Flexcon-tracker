<x-ui.page eyebrow="Producción" title="Nuevo estándar"
    subtitle="Define cuántas piezas por hora se esperan de una parte según dónde se produce."
    back="{{ route('admin.standards.index') }}" backLabel="Volver a estándares">

    <form wire:submit="saveStandard" class="space-y-5">
        <x-ui.section title="Parte" hint="Cada parte tiene un solo estándar, con una o varias configuraciones.">
            <x-ui.field label="Parte" required :error="$errors->first('part_id')">
                <select wire:model="part_id" class="w-full" required>
                    <option value="">Seleccione una parte</option>
                    @foreach ($parts as $part)
                        <option value="{{ $part->id }}">{{ $part->number }} - {{ Str::limit($part->description, 40) }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </x-ui.section>

        @include('livewire.admin.standards.partials.configurations')

        <x-ui.section title="Estado y notas">
            <x-ui.check label="Estándar activo" hint="Los inactivos no se usan para calcular tiempos ni avances.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Descripción" optional class="mt-4"
                hint="Contexto del estándar: condiciones, supuestos, quién lo midió."
                :error="$errors->first('description')">
                <textarea wire:model="description" rows="3" class="w-full"
                    placeholder="Descripción detallada del estándar..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.standards.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear estándar</x-ui.btn>
        </div>
    </form>
</x-ui.page>
