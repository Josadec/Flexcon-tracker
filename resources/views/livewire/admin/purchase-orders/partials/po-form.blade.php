{{--
    Cuerpo compartido del formulario de PO (alta y edición).

    Espera: $po_number, $wo, $parts, $part_id, $po_date, $due_date,
            $available_workstation_types, $workstation_type, $quantity,
            $unit_price, $price_message, $price_valid, $expected_price, $comments
--}}

<x-ui.section step="1" title="Información básica" hint="Identifica la orden y la parte que se va a producir.">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.field label="Número de PO" required :error="$errors->first('po_number')">
            <input wire:model="po_number" type="text" placeholder="Ej: PO-2025-001" class="w-full" required>
        </x-ui.field>

        <x-ui.field label="WO" optional
            hint="Se genera al aprobar si lo dejas vacío."
            :error="$errors->first('wo')">
            <input wire:model="wo" type="text" placeholder="Ej: WO-2025-001" class="w-full">
        </x-ui.field>

        <x-ui.field label="Parte" required
            hint="Escribe para filtrar."
            :error="$errors->first('part_id')">
            {{-- wire:ignore: TomSelect controla este nodo; Livewire no debe re-renderizarlo. --}}
            <div wire:ignore x-data="{
                init() {
                    new TomSelect(this.$refs.partSelect, {
                        create: false,
                        allowEmptyOption: true,
                        placeholder: 'Seleccione una parte',
                        sortField: false,
                        maxOptions: 500,
                        render: { no_results: () => '<div class=\'no-results\'>Sin resultados</div>' },
                        onChange: (v) => { $wire.selectPart(v); }
                    });
                }
            }">
                <select x-ref="partSelect" data-no-ts class="w-full">
                    <option value="">Seleccione una parte</option>
                    @foreach ($parts as $part)
                        <option value="{{ $part->id }}" @selected($part_id == $part->id)>
                            {{ $part->number }} - {{ Str::limit($part->description, 40) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </x-ui.field>
    </div>
</x-ui.section>

<x-ui.section step="2" title="Fechas" hint="La fecha de entrega es la que se compromete con el cliente.">
    {{--
        wire:ignore es OBLIGATORIO en los campos de fecha.

        Flatpickr (partials/head.blade.php) los convierte en type="hidden" y crea
        un input visible aparte. Cuando otro campo .live de este formulario
        dispara un commit, el morph de Livewire rehace el input y le borra el
        valor, porque el HTML del servidor no serializa value=. Con wire:ignore
        el morph no toca el nodo y la fecha se conserva.

        wire:model sigue funcionando: wire:ignore sólo frena el re-render del
        DOM, no los eventos del input.
    --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-ui.field label="Fecha de PO" required :error="$errors->first('po_date')">
            <div wire:ignore>
                <input wire:model="po_date" type="date" class="w-full" required>
            </div>
        </x-ui.field>

        <x-ui.field label="Fecha de entrega" required :error="$errors->first('due_date')">
            <div wire:ignore>
                <input wire:model="due_date" type="date" class="w-full" required>
            </div>
        </x-ui.field>
    </div>
</x-ui.section>

<x-ui.section step="3" title="Tipo de estación"
    hint="Define dónde va a correr la orden. De esta elección sale el precio esperado.">
    @if (!empty($available_workstation_types))
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($available_workstation_types as $opt)
                <x-ui.choice wire:key="po-ws-{{ $opt['value'] }}"
                    tone="info" :title="$opt['label']"
                    :desc="'Precio muestra: $'.number_format($opt['sample_price'], 4)"
                    :selected="$workstation_type === $opt['value']"
                    wire:click="$set('workstation_type', '{{ $opt['value'] }}')" />
            @endforeach
        </div>
        @error('workstation_type')
            <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
        @enderror
    @elseif ($part_id)
        <x-ui.note tone="warn" title="Esta parte no tiene precios activos">
            Sin un precio activo no se puede validar el precio de la PO.
            Captura al menos uno en <strong>Catálogo → Precios</strong>.
        </x-ui.note>
    @else
        <x-ui.note tone="muted">Selecciona primero una parte para ver sus tipos de estación disponibles.</x-ui.note>
    @endif
</x-ui.section>

<x-ui.section step="4" title="Cantidad y precio"
    hint="El sistema compara el precio capturado contra el precio del catálogo.">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-ui.field label="Cantidad" required
            hint="Piezas que pide el cliente."
            :error="$errors->first('quantity')">
            <input wire:model.live.debounce.500ms="quantity" type="number" min="1"
                class="w-full text-right font-bold tabular-nums" required>
        </x-ui.field>

        <x-ui.field label="Precio unitario" required
            hint="Con hasta 4 decimales."
            :error="$errors->first('unit_price')">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 font-semibold text-slate-400">$</span>
                <input wire:model.live.debounce.500ms="unit_price" type="number" step="0.0001" min="0"
                    class="w-full pl-7 text-right font-bold tabular-nums" required>
            </div>
        </x-ui.field>
    </div>

    {{-- Validación del precio contra el catálogo --}}
    @if ($price_message)
        <x-ui.note :tone="$price_valid ? 'success' : 'warn'" class="mt-4" :title="$price_message">
            @if ($expected_price !== null)
                Precio esperado para {{ number_format($quantity) }} piezas:
                <strong>${{ number_format($expected_price, 4) }}</strong>
            @endif
        </x-ui.note>
    @endif

    @if ((int) $quantity > 0 && (float) $unit_price > 0)
        <x-ui.stats cols="2" class="mt-4">
            <x-ui.stat label="Total de la orden"
                :value="'$'.number_format((float) $unit_price * (int) $quantity, 2)" tone="info" />
            <x-ui.stat label="Piezas" :value="number_format((int) $quantity)" />
        </x-ui.stats>
    @endif
</x-ui.section>
