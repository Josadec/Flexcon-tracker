@php
    $producido = $lot->getProductionTotalWeighed();
    $empacado = $lot->getPackagingPackedPieces();
@endphp

<x-ui.page eyebrow="Producción" :title="'Editar viajero '.$lot->lot_number"
    subtitle="La suma de los viajeros de la orden no puede sobrepasar su cantidad original."
    back="{{ route('admin.lots.index') }}" backLabel="Volver a viajeros">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.lots.show', $lot) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="save" class="space-y-5">
        @if ($producido > 0 || $empacado > 0)
            <x-ui.note tone="warn">
                Este viajero ya tiene movimiento: <strong>{{ number_format($producido) }}</strong> piezas producidas y
                <strong>{{ number_format($empacado) }}</strong> empacadas. Bajar la cantidad por debajo de eso deja
                cifras incoherentes en los reportes.
            </x-ui.note>
        @endif

        <x-ui.section title="Datos del viajero">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Número de viajero" required
                    hint="No se puede repetir dentro de la misma orden."
                    :error="$errors->first('lot_number')">
                    <input wire:model="lot_number" type="text" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Cantidad" required
                    hint="Piezas de esta corrida."
                    :error="$errors->first('quantity')">
                    <input wire:model="quantity" type="number" min="1" step="1"
                        class="w-full text-right tabular-nums" required>
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Contexto" hint="Datos de la orden, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Orden de trabajo" :value="'WO '.($lot->workOrder?->purchaseOrder?->wo ?? $lot->work_order_id)" />
                <x-ui.kv label="Parte" :value="$lot->workOrder?->purchaseOrder?->part?->number ?? '—'" />
                <x-ui.kv label="Cantidad de la orden" :value="number_format($lot->workOrder?->original_quantity ?? 0).' pz'" />
                <x-ui.kv label="Producido" :value="number_format($producido).' pz'" />
                <x-ui.kv label="Empacado" :value="number_format($empacado).' pz'" />
                <x-ui.kv label="Alta" :value="$lot->created_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.lots.show', $lot) }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
