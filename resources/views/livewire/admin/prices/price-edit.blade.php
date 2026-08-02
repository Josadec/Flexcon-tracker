<x-ui.page eyebrow="Catálogo" title="Editar precio"
    subtitle="Los cambios aplican a las órdenes que se coticen a partir de ahora."
    back="{{ route('admin.prices.index') }}" backLabel="Volver a precios">

    <form wire:submit="updatePrice" class="space-y-5">
        {{-- La edición anota en la lista las partes archivadas o inactivas. --}}
        @include('livewire.admin.prices.partials.price-form', [
            'partSuffix' => fn ($p) => $p->trashed() ? ' (archivada)' : (! $p->active ? ' (inactiva)' : ''),
        ])

        <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Creado" :value="$price->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$price->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.prices.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
