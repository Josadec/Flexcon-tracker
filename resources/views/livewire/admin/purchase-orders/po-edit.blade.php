@php
    $statusTones = [
        'pending'            => 'warn',
        'approved'           => 'good',
        'rejected'           => 'bad',
        'pending_correction' => 'warn',
    ];
@endphp

<x-ui.page eyebrow="Compras" title="Editar orden de compra"
    :subtitle="'PO '.$purchaseOrder->po_number.' · los cambios pueden requerir una nueva aprobación.'"
    back="{{ route('admin.purchase-orders.index') }}" backLabel="Volver a órdenes de compra">

    <x-slot:actions>
        <x-ui.badge :tone="$statusTones[$purchaseOrder->status] ?? 'neutral'" dot>{{ $purchaseOrder->status_label }}</x-ui.badge>
        <x-ui.btn variant="secondary" href="{{ route('admin.purchase-orders.show', $purchaseOrder) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="updatePO" class="space-y-5">
        @include('livewire.admin.purchase-orders.partials.po-form')

        {{--
            Documento PDF — DESHABILITADO TEMPORALMENTE (ya venía así).
            Se conserva el bloque, ya migrado al sistema de diseño, para poder
            reactivarlo quitando este comentario. El componente sigue exponiendo
            la propiedad `pdf_file` y el método `deletePdf()`.

        <x-ui.section step="5" title="Documento" hint="Archivo PDF de la orden de compra. Máximo 10 MB.">
            @if ($purchaseOrder->pdf_path)
                <div class="mb-4 flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:bg-slate-900/40">
                    <div class="flex min-w-0 items-center gap-3">
                        <svg class="size-8 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">PDF actual</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ basename($purchaseOrder->pdf_path) }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.btn variant="secondary" size="sm" :href="Storage::url($purchaseOrder->pdf_path)" :navigate="false" target="_blank">Ver</x-ui.btn>
                        <x-ui.btn variant="secondary" size="sm" :href="Storage::url($purchaseOrder->pdf_path)" :navigate="false" download>Descargar</x-ui.btn>
                        <x-ui.btn variant="danger" size="sm" wire:click="deletePdf" wire:confirm="¿Eliminar el PDF de esta orden?">Eliminar</x-ui.btn>
                    </div>
                </div>
            @endif

            <x-ui.field :label="$purchaseOrder->pdf_path ? 'Reemplazar archivo PDF' : 'Archivo PDF de la PO'"
                hint="Máximo 10 MB, sólo PDF." :error="$errors->first('pdf_file')">
                <input wire:model="pdf_file" type="file" accept=".pdf"
                    class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100 dark:text-slate-300 dark:file:bg-sky-900/40 dark:file:text-sky-300">
            </x-ui.field>
            <p wire:loading wire:target="pdf_file" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Cargando archivo...</p>
        </x-ui.section>
        --}}

        <x-ui.section step="5" title="Comentarios" hint="Información adicional para quien revise la orden.">
            <x-ui.field label="Comentarios" optional :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="4" class="w-full"
                    placeholder="Comentarios adicionales..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.purchase-orders.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Guardar cambios
            </x-ui.btn>
        </div>
    </form>
</x-ui.page>
