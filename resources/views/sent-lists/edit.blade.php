{{--
    EDITAR LISTA PRELIMINAR (cambio de estado)

    El mismo cambio se puede hacer en un modal desde el listado y desde el
    detalle; esta pantalla se conserva porque la URL es enlazable y aquí cabe
    explicar con calma qué implica cada estado.
--}}
<x-layouts.admin>
    <x-ui.page :title="'Editar lista preliminar #'.$sentList->id"
        subtitle="Cambia el estado de la lista. El resto de los datos se definen en el wizard de capacidad."
        :back="route('admin.sent-lists.show', $sentList)" backLabel="Volver al detalle">

        <x-slot:actions>
            <x-ui.btn variant="secondary" :href="route('admin.sent-lists.display.sl', $sentList->id)">
                Tablero de piso
            </x-ui.btn>
        </x-slot:actions>

        @if (session('error'))
            <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
        @endif

        {{-- Qué se está editando --}}
        <x-ui.section title="Lista que estás editando">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Lista" :value="'#'.$sentList->id" />
                <x-ui.kv label="Órdenes de compra"
                    :value="$sentList->purchaseOrders->count() > 0
                        ? ($sentList->purchaseOrders->count() === 1
                            ? $sentList->purchaseOrders->first()->po_number
                            : $sentList->purchaseOrders->count().' órdenes de compra')
                        : '—'" />
                <x-ui.kv label="Work Orders" :value="$sentList->workOrders->count()" />
                <x-ui.kv label="Período"
                    :value="$sentList->start_date && $sentList->end_date
                        ? $sentList->start_date->format('d/m/Y').' – '.$sentList->end_date->format('d/m/Y')
                        : 'Sin período'" />
                <x-ui.kv label="Departamento actual" :value="$sentList->department_label" />
                <x-ui.kv label="Estado actual" :value="$sentList->status_label"
                    :tone="$sentList->status === 'confirmed' ? 'good' : ($sentList->status === 'canceled' ? 'bad' : 'warn')" />
            </dl>
        </x-ui.section>

        <form action="{{ route('admin.sent-lists.update', $sentList) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            <x-ui.section title="Nuevo estado" hint="Elige a dónde pasa la lista y qué implica el cambio.">
                @php
                    $options = [
                        'pending'   => ['Pendiente',  'Sigue en planeación. Es el único estado que permite editar o eliminar la lista.',       'warn'],
                        'confirmed' => ['Confirmada', 'La lista queda cerrada: ya no se puede editar ni eliminar.',                            'good'],
                        'canceled'  => ['Cancelada',  'La lista se descarta. Tampoco se podrá editar ni eliminar después.',                    'bad'],
                    ];
                    $current = old('status', $sentList->status);
                @endphp

                {{-- Radios reales: esta pantalla no usa Livewire, así que el
                     estado seleccionado tiene que viajar en el formulario. --}}
                <div class="grid grid-cols-1 gap-3">
                    @foreach ($options as $value => [$label, $desc, $tone])
                        @php
                            $toneRing = [
                                'warn' => 'has-checked:border-amber-500 has-checked:bg-amber-50 dark:has-checked:bg-amber-950/30',
                                'good' => 'has-checked:border-green-500 has-checked:bg-green-50 dark:has-checked:bg-green-950/30',
                                'bad'  => 'has-checked:border-red-500 has-checked:bg-red-50 dark:has-checked:bg-red-950/30',
                            ][$tone];
                        @endphp
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700/50 {{ $toneRing }}">
                            <input type="radio" name="status" value="{{ $value }}" class="mt-0.5 shrink-0"
                                @checked($current === $value)>
                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ $label }}</span>
                                <span class="mt-0.5 block text-xs leading-4 text-slate-500 dark:text-slate-400">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('status')
                    <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
                @enderror

                <x-ui.note tone="warn" class="mt-4" title="Al salir de «Pendiente» el cambio es de una sola vía">
                    Una lista confirmada o cancelada ya no se puede editar ni eliminar desde el sistema.
                </x-ui.note>
            </x-ui.section>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
                <x-ui.btn variant="secondary" :href="route('admin.sent-lists.show', $sentList)">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" type="submit">Guardar estado</x-ui.btn>
            </div>
        </form>
    </x-ui.page>
</x-layouts.admin>
