<x-ui.page eyebrow="Producción" title="Crear viajero"
    subtitle="Un viajero es una corrida de producción de una orden. Su cantidad no puede exceder lo pendiente de la orden."
    back="{{ route('admin.lots.index') }}" backLabel="Volver a viajeros">

    <form wire:submit="save" class="space-y-5">
        <x-ui.section title="Orden de trabajo" hint="Sólo aparecen las órdenes abiertas o en progreso con cantidad pendiente.">
            <x-ui.field label="Orden de trabajo" required :error="$errors->first('work_order_id')">
                <select wire:model.live="work_order_id" class="w-full" required>
                    <option value="">Seleccionar</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}">
                            WO {{ $wo->purchaseOrder?->wo ?? $wo->id }} ·
                            {{ $wo->purchaseOrder?->part?->number ?? 'Sin parte' }}
                            ({{ number_format($wo->pending_quantity) }} pz pendientes)
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            @if ($workOrders->isEmpty())
                <x-ui.note tone="warn" class="mt-3">
                    No hay órdenes de trabajo con cantidad pendiente. Crea o reabre una orden antes de dar de alta un viajero.
                </x-ui.note>
            @endif

            @if ($selectedWorkOrder)
                <dl class="mt-4 divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    <x-ui.kv label="Parte" :value="$selectedWorkOrder->purchaseOrder?->part?->number ?? '—'" />
                    <x-ui.kv label="Descripción" :value="$selectedWorkOrder->purchaseOrder?->part?->description ?: '—'" />
                    <x-ui.kv label="Cantidad pendiente" :value="number_format($selectedWorkOrder->pending_quantity).' pz'" tone="info" />
                    @if ($selectedWorkOrder->purchaseOrder?->part?->is_crimp)
                        <x-ui.kv label="Tipo de parte" value="CRIMP" tone="info"
                            help="El viajero seguirá el flujo de ocho pasos con lotes de CRIMP." />
                    @endif
                </dl>
            @endif
        </x-ui.section>

        <x-ui.section title="Datos del viajero">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Número de viajero" required
                    hint="No se puede repetir dentro de la misma orden."
                    :error="$errors->first('lot_number')">
                    <input wire:model="lot_number" type="text" class="w-full" placeholder="Ej: 001" required>
                </x-ui.field>

                <x-ui.field label="Cantidad" required
                    hint="Piezas que se van a producir en esta corrida."
                    :error="$errors->first('quantity')">
                    <input wire:model="quantity" type="number" min="1" step="1"
                        class="w-full text-right tabular-nums" required>
                </x-ui.field>
            </div>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.lots.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear viajero</x-ui.btn>
        </div>
    </form>
</x-ui.page>
