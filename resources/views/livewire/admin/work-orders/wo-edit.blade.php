@php
    $po   = $workOrder->purchaseOrder;
    $part = $po?->part;
@endphp

<x-ui.page eyebrow="Órdenes"
    :title="'Editar WO '.($po?->wo ?? $workOrder->wo_number)"
    subtitle="El cambio de estado queda registrado en el historial de la orden."
    back="{{ route('admin.work-orders.show', $workOrder) }}" backLabel="Volver a la orden">

    {{-- La cabecera NO lleva un enlace a la ficha: ya está el "Volver a la orden"
         de arriba y el "Cancelar" del pie. Un tercer enlace al mismo sitio sólo
         añade ruido. La única acción de esta pantalla es Guardar; el hueco de
         acciones se usa para el contexto que hace falta al editar. --}}
    <x-slot:actions>
        {{-- Estado GUARDADO, para tener a la vista de dónde parte el cambio.
             El color es dato del catálogo de estados, no del sistema de tonos. --}}
        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Estado actual
            <span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold normal-case tracking-normal text-white"
                style="background-color: {{ $workOrder->status?->color ?? '#6b7280' }}">
                {{ $workOrder->status?->name ?? 'Sin estado' }}
            </span>
        </span>
    </x-slot:actions>

    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    <form wire:submit="save" class="space-y-5">
        {{-- Contexto de sólo lectura: de qué orden estamos hablando. --}}
        <x-ui.section title="Orden" hint="Datos que vienen de la orden de compra. No se editan aquí.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                @if ($po?->wo)
                    <x-ui.kv label="WO (cliente)" :value="$po->wo" tone="info" />
                @endif
                <x-ui.kv label="ID interno" :value="$workOrder->wo_number" />
                <x-ui.kv label="Orden de compra" :value="$po?->po_number ?? '—'" />
                <x-ui.kv label="Parte" :value="$part?->number ?? '—'" />
                <x-ui.kv label="Descripción" :value="$part?->description ?: '—'" />
                <x-ui.kv label="Cantidad original" :value="number_format($workOrder->original_quantity)" />
            </dl>
        </x-ui.section>

        <x-ui.section title="Estado" hint="Decide si la orden aparece en Capacidad y en la Lista de Envío.">
            <x-ui.field label="Estado de la orden" required :error="$errors->first('status_id')">
                {{-- wire:ignore OBLIGATORIO: partials/head.blade.php monta
                     TomSelect sobre TODO <select> que no lleve data-no-ts, así
                     que este control lo pinta una librería de terceros. Sin el
                     wire:ignore, el morph que dispara este mismo select (es
                     .live) reescribe el <select> y la selección se pierde:
                     verificado en el navegador quitando el atributo en caliente
                     y forzando un $refresh — el valor volvía de «In Progress» a
                     «Open». El catálogo de estados no cambia mientras la
                     pantalla vive, así que ignorar el subárbol no cuesta nada. --}}
                <div wire:ignore>
                    <select wire:model.live="status_id" id="status_id" class="w-full" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected((int) $status_id === (int) $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
            </x-ui.field>

            {{-- El motivo sólo se pide cuando de verdad hay un cambio pendiente. --}}
            @if ((int) $status_id !== (int) $workOrder->status_id)
                <div class="mt-4">
                    <x-ui.note tone="warn" title="Vas a cambiar el estado de la orden">
                        De <strong>{{ $workOrder->status?->name ?? 'Sin estado' }}</strong> a
                        <strong>{{ $statuses->firstWhere('id', (int) $status_id)?->name ?? '—' }}</strong>.
                        El cambio se registrará en el historial al guardar.
                    </x-ui.note>

                    <x-ui.field label="Motivo del cambio" optional class="mt-4"
                        hint="Queda guardado junto al cambio de estado."
                        :error="$errors->first('status_change_comments')">
                        <textarea wire:model="status_change_comments" id="status_change_comments" rows="2"
                            class="w-full" placeholder="Razón del cambio de estado..."></textarea>
                    </x-ui.field>
                </div>
            @endif
        </x-ui.section>

        {{-- wire:ignore en las fechas: el selector de estado es .live y su morph
             destruye el altInput de Flatpickr (mismo caso que el modal de precios
             en Partes). Sin él, el calendario se rompe y borra la fecha. --}}
        <x-ui.section title="Fechas de envío" hint="La programada es el compromiso; la real se llena al enviar.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Fecha programada de envío" optional
                    :error="$errors->first('scheduled_send_date')">
                    <div wire:ignore>
                        <input wire:model="scheduled_send_date" type="date" id="scheduled_send_date" class="w-full">
                    </div>
                </x-ui.field>

                <x-ui.field label="Fecha real de envío" optional
                    hint="Déjala vacía mientras la orden no se haya enviado."
                    :error="$errors->first('actual_send_date')">
                    <div wire:ignore>
                        <input wire:model="actual_send_date" type="date" id="actual_send_date" class="w-full">
                    </div>
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Asignación" hint="Equipo y personal responsables de la orden.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Equipo (EQ)" optional :error="$errors->first('eq')">
                    <input wire:model="eq" type="text" id="eq" class="w-full" placeholder="Equipo asignado...">
                </x-ui.field>

                <x-ui.field label="Personal (PR)" optional :error="$errors->first('pr')">
                    <input wire:model="pr" type="text" id="pr" class="w-full" placeholder="Personal asignado...">
                </x-ui.field>
            </div>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno; no se imprime." :error="$errors->first('comments')">
                <textarea wire:model="comments" id="comments" rows="3" class="w-full"
                    placeholder="Comentarios adicionales..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.work-orders.show', $workOrder) }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
