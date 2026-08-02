<x-ui.page eyebrow="Catálogo" title="Crear precio"
    subtitle="Un precio se define por parte, tipo de estación y fecha efectiva."
    back="{{ route('admin.prices.index') }}" backLabel="Volver a precios">

    <form wire:submit="savePrice" class="space-y-5">
        @include('livewire.admin.prices.partials.price-form')

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.prices.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Crear precio</x-ui.btn>
        </div>
    </form>
</x-ui.page>
