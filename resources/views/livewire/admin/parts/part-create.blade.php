<x-ui.page eyebrow="Catálogo" title="Crear parte"
    subtitle="Alta de una parte del catálogo. Sus precios se capturan después, desde el detalle."
    back="{{ route('admin.parts.index') }}" backLabel="Volver a partes">

    <form wire:submit="savePart" class="space-y-5">
        <x-ui.section title="Identificación" hint="Ambos números deben ser únicos en el catálogo.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Número de parte" required
                    hint="El que aparece en el dibujo y en la orden."
                    :error="$errors->first('number')">
                    <input wire:model="number" type="text" class="w-full" placeholder="Ej: PART-001" required>
                </x-ui.field>

                <x-ui.field label="Número de ítem" required
                    hint="El código con el que se controla en el sistema."
                    :error="$errors->first('item_number')">
                    <input wire:model="item_number" type="text" class="w-full" placeholder="Ej: ITEM-001" required>
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Especificación" hint="Datos que se imprimen en etiquetas y hojas de trabajo.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Unidad de medida" optional :error="$errors->first('unit_of_measure')">
                    <input wire:model="unit_of_measure" type="text" class="w-full" placeholder="Ej: PZA, KG, M">
                </x-ui.field>

                <x-ui.field label="Label Spec" optional
                    hint="Especificación militar o aeronáutica. Máximo 150 caracteres."
                    :error="$errors->first('label_spec')">
                    <input wire:model="label_spec" type="text" class="w-full" placeholder="Ej: M83519/2-8">
                </x-ui.field>
            </div>

            <x-ui.field label="Descripción" optional class="mt-4" :error="$errors->first('description')">
                <textarea wire:model="description" rows="3" class="w-full"
                    placeholder="Descripción detallada de la parte..."></textarea>
            </x-ui.field>

            <x-ui.field label="Notas" optional class="mt-4"
                hint="Uso interno; no se imprime." :error="$errors->first('notes')">
                <textarea wire:model="notes" rows="2" class="w-full" placeholder="Notas adicionales..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Comportamiento" hint="Define cómo se trata esta parte en el flujo de producción.">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <x-ui.check label="Parte activa" hint="Las inactivas no aparecen al crear órdenes nuevas.">
                    <input wire:model="active" type="checkbox">
                </x-ui.check>

                <x-ui.check label="Es CRIMP" hint="Su viajero se divide en lotes de CRIMP y sigue el flujo de pasos 5 a 8.">
                    <input wire:model="is_crimp" type="checkbox">
                </x-ui.check>
            </div>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.parts.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear parte</x-ui.btn>
        </div>
    </form>
</x-ui.page>
