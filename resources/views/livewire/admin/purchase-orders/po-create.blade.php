<x-ui.page eyebrow="Compras" title="Nueva orden de compra"
    subtitle="Al aprobarse, esta PO genera la Work Order con la que arranca producción."
    back="{{ route('admin.purchase-orders.index') }}" backLabel="Volver a órdenes de compra">

    <form wire:submit="savePO" class="space-y-5">
        @include('livewire.admin.purchase-orders.partials.po-form')

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
                Guardar PO
            </x-ui.btn>
        </div>
    </form>
</x-ui.page>
