{{--
    Cuerpo compartido del formulario de precios (alta y edición).
    Los dos formularios eran idénticos salvo el título y el método a llamar; se
    unifican aquí para que un cambio de campo no haya que hacerlo dos veces.

    Espera: $parts, $part_id, $sample_price, $effective_date, $workstationTypes,
            $workstation_type, $tiers, $active, $comments,
            $has_existing_prices, $info_message
    Opcional: $partSuffix — closure para anotar la parte en el <option>.
--}}
@php
    $partSuffix ??= null;
@endphp

<x-ui.section title="Parte y vigencia" hint="Un precio aplica a una parte, un tipo de estación y una fecha efectiva.">
    <x-ui.field label="Parte" required
        hint="Escribe para filtrar en la lista."
        :error="$errors->first('part_id')">
        <select wire:model.live="part_id" class="w-full" required>
            <option value="">Seleccione una parte</option>
            @foreach ($parts as $p)
                <option value="{{ $p->id }}" @selected((string) $p->id === (string) $part_id)>
                    {{ $p->number }} - {{ $p->description }}{{ $partSuffix ? $partSuffix($p) : '' }}
                </option>
            @endforeach
        </select>
    </x-ui.field>

    @if ($has_existing_prices && $info_message)
        <x-ui.note tone="info" class="mt-3">{{ $info_message }}</x-ui.note>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <x-ui.field label="Precio de muestra" required
            hint="Precio base, con hasta 4 decimales."
            :error="$errors->first('sample_price')">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 font-semibold text-slate-400">$</span>
                <input wire:model="sample_price" type="number" step="0.0001" min="0"
                    class="w-full pl-7 text-right font-bold tabular-nums" required>
            </div>
        </x-ui.field>

        <x-ui.field label="Fecha efectiva" required
            hint="Desde cuándo aplica este precio."
            :error="$errors->first('effective_date')">
            <input wire:model="effective_date" type="date" class="w-full" required>
        </x-ui.field>
    </div>
</x-ui.section>

<x-ui.section title="Tipo de estación de trabajo"
    hint="El mismo producto puede costar distinto según dónde se produce.">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
        @foreach ($workstationTypes as $value => $label)
            <x-ui.choice wire:key="ws-{{ $value }}"
                tone="info" :title="$label"
                :selected="$workstation_type === $value"
                wire:click="$set('workstation_type', '{{ $value }}')" />
        @endforeach
    </div>
    @error('workstation_type')
        <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
    @enderror
</x-ui.section>

<x-ui.section title="Niveles de precio por volumen"
    hint="Opcional. Define rangos de cantidad con un precio distinto; deja la cantidad máxima vacía para «sin límite».">
    <x-slot:aside>
        <x-ui.btn variant="success" size="sm" type="button" wire:click="addTier">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Agregar nivel
        </x-ui.btn>
    </x-slot:aside>

    @if (empty($tiers))
        <x-ui.empty icon="doc" title="Sin niveles de volumen"
            hint="Si no agregas ninguno, siempre se cobra el precio de muestra." />
    @else
        <div class="space-y-3">
            @foreach ($tiers as $i => $tier)
                <div wire:key="tier-{{ $i }}"
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-start gap-3">
                        <span class="mt-6 flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white dark:bg-slate-200 dark:text-slate-900">
                            {{ $i + 1 }}
                        </span>

                        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-ui.field label="Cantidad mínima" required
                                :error="$errors->first('tiers.'.$i.'.min_quantity')">
                                <input wire:model="tiers.{{ $i }}.min_quantity" type="number" step="0.0001" min="0"
                                    placeholder="0" class="w-full text-right tabular-nums">
                            </x-ui.field>

                            <x-ui.field label="Cantidad máxima" optional
                                hint="Vacío = sin límite."
                                :error="$errors->first('tiers.'.$i.'.max_quantity')">
                                <input wire:model="tiers.{{ $i }}.max_quantity" type="number" step="0.0001" min="0"
                                    placeholder="Sin límite" class="w-full text-right tabular-nums">
                            </x-ui.field>

                            <x-ui.field label="Precio del nivel" required
                                :error="$errors->first('tiers.'.$i.'.tier_price')">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 font-semibold text-slate-400">$</span>
                                    <input wire:model="tiers.{{ $i }}.tier_price" type="number" step="0.0001" min="0"
                                        placeholder="0.0000" class="w-full pl-7 text-right font-bold tabular-nums">
                                </div>
                            </x-ui.field>
                        </div>

                        <div class="pt-6">
                            <x-ui.icon-btn tone="danger" label="Eliminar el nivel {{ $i + 1 }}"
                                type="button" wire:click="removeTier({{ $i }})">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </x-ui.icon-btn>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-ui.section>

<x-ui.section title="Estado y notas">
    <x-ui.check label="Precio activo" hint="Los precios inactivos no se usan al cotizar órdenes nuevas.">
        <input wire:model.live="active" type="checkbox">
    </x-ui.check>

    <x-ui.field label="Comentarios" optional class="mt-4"
        hint="Contexto del precio: negociación, vigencia especial, etc.">
        <textarea wire:model="comments" rows="3" class="w-full"></textarea>
    </x-ui.field>
</x-ui.section>
