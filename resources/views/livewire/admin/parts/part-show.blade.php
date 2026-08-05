<x-ui.page :title="$part->number"
    :subtitle="$part->description ?: 'Detalle de la parte y sus precios por tipo de estación.'"
    back="{{ route('admin.parts.index') }}" backLabel="Volver a partes">

    <x-slot:actions>
        @if (Route::has('admin.parts.edit'))
            <x-ui.btn variant="primary" href="{{ route('admin.parts.edit', $part) }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Editar parte
            </x-ui.btn>
        @endif
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Ficha --}}
    <x-ui.section title="Información de la parte">
        <x-slot:aside>
            <div class="flex items-center gap-2">
                @if ($part->is_crimp)
                    <x-ui.badge tone="accent">CRIMP</x-ui.badge>
                @endif
                @if ($part->active)
                    <x-ui.badge tone="good" dot>Activa</x-ui.badge>
                @else
                    <x-ui.badge tone="neutral" dot>Inactiva</x-ui.badge>
                @endif
            </div>
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Número de parte" :value="$part->number" />
            <x-ui.kv label="Número de ítem" :value="$part->item_number" />
            <x-ui.kv label="Unidad de medida" :value="$part->unit_of_measure ?: '—'" />
            <x-ui.kv label="Label Spec" :value="$part->label_spec ?: '—'" />
            <x-ui.kv label="Descripción" :value="$part->description ?: '—'" />
            <x-ui.kv label="Notas" :value="$part->notes ?: '—'" />
            <x-ui.kv label="Creada" :value="$part->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$part->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>

    {{-- Precios: una pestaña por tipo de estación --}}
    @php $firstType = array_key_first($pricesByType); @endphp
    <x-ui.section title="Precios por tipo de estación"
        hint="El precio se cobra según el tipo de estación donde se produce la parte.">

        <div x-data="{ tab: '{{ $firstType }}' }">
            {{-- Pestañas --}}
            <div class="border-b border-slate-200 dark:border-slate-700">
                <nav class="-mb-px flex flex-wrap gap-x-6" aria-label="Tipo de estación">
                    @foreach ($pricesByType as $type => $data)
                        <button type="button" x-on:click="tab = '{{ $type }}'"
                            x-bind:class="tab === '{{ $type }}'
                                ? 'border-sky-600 text-sky-700 dark:text-sky-300'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition-colors">
                            {{ $data['label'] }}
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ count($data['prices']) }}
                            </span>
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- Contenido de cada pestaña --}}
            @foreach ($pricesByType as $type => $data)
                <div x-show="tab === '{{ $type }}'" x-cloak class="pt-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Precios para estaciones tipo <strong class="text-slate-700 dark:text-slate-200">{{ $data['label'] }}</strong>.
                        </p>
                        <x-ui.btn variant="secondary" size="sm" wire:click="openPriceModal('{{ $type }}')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Agregar precio
                        </x-ui.btn>
                    </div>

                    @if (count($data['prices']) > 0)
                        {{-- Anchos fijos en las columnas cortas: sin esto la primera
                             columna se estiraba y dejaba un hueco enorme a la izquierda. --}}
                        <x-ui.table>
                            <x-slot:head>
                                <tr>
                                    <x-ui.th class="w-40">Precio muestra</x-ui.th>
                                    <x-ui.th>Niveles de precio</x-ui.th>
                                    <x-ui.th class="w-36">Fecha efectiva</x-ui.th>
                                    <x-ui.th class="w-28">Estado</x-ui.th>
                                    <x-ui.th class="w-28" align="right">Acciones</x-ui.th>
                                </tr>
                            </x-slot:head>

                            @foreach ($data['prices'] as $price)
                                <tr wire:key="price-{{ $price->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                    <td class="whitespace-nowrap px-4 py-3 text-base font-bold tabular-nums text-slate-900 dark:text-white">
                                        ${{ number_format($price->sample_price, 4) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @php $tiers = $price->tiers->sortBy('min_quantity'); @endphp
                                        @if ($tiers->isEmpty())
                                            <span class="text-xs text-slate-400">Sin niveles — siempre se cobra el precio muestra</span>
                                        @else
                                            <ul class="space-y-0.5">
                                                @foreach ($tiers as $tier)
                                                    <li class="flex items-baseline gap-2 text-xs">
                                                        <span class="tabular-nums text-slate-600 dark:text-slate-300">
                                                            {{ number_format(0 + $tier->min_quantity, 0) }} –
                                                            {{ $tier->max_quantity !== null ? number_format(0 + $tier->max_quantity, 0) : '∞' }}
                                                        </span>
                                                        <span class="font-bold tabular-nums text-slate-900 dark:text-white">${{ number_format($tier->tier_price, 4) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                                        {{ $price->effective_date?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($price->active)
                                            <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-ui.row-actions label="este precio"
                                            delete="deletePrice({{ $price->id }})"
                                            deleteConfirm="¿Eliminar este precio de {{ $data['label'] }}? Esta acción no se puede deshacer.">
                                            <x-ui.icon-btn tone="primary" label="Editar este precio"
                                                wire:click="openEditPriceModal({{ $price->id }})">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </x-ui.icon-btn>
                                        </x-ui.row-actions>
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @else
                        <x-ui.empty icon="doc"
                            title="Sin precios para {{ $data['label'] }}"
                            hint="Mientras no haya precio, esta parte no se puede cotizar en estaciones de este tipo.">
                            <x-slot:action>
                                <x-ui.btn variant="primary" wire:click="openPriceModal('{{ $type }}')">
                                    Crear el primer precio
                                </x-ui.btn>
                            </x-slot:action>
                        </x-ui.empty>
                    @endif
                </div>
            @endforeach
        </div>
    </x-ui.section>

    {{-- Alta y edición de precios, sin salir de la parte --}}
    @if ($showPriceModal)
        <x-ui-modal wire:key="modal-price-{{ $editingPriceId ?? 'new' }}"
            :title="$editingPriceId ? 'Editar precio' : 'Agregar precio'"
            subtitle="El precio aplica a esta parte, en el tipo de estación y desde la fecha que elijas."
            close="closePriceModal" maxWidth="4xl">

            <x-slot:context>
                <x-ui-modal.ctx label="Parte" :value="$part->number" />
                <x-ui-modal.ctx label="Descripción" :value="$part->description ?: '—'" />
                <x-ui-modal.ctx label="Unidad" :value="$part->unit_of_measure ?: '—'" />
                <x-ui-modal.ctx label="Precios registrados"
                    :value="collect($pricesByType)->sum(fn ($d) => count($d['prices'])).' en total'" />
            </x-slot:context>

            <x-ui.section step="1" title="Tipo de estación"
                hint="El mismo producto puede costar distinto según dónde se produce.">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach ($workstationTypes as $value => $label)
                        <x-ui.choice wire:key="ws-{{ $value }}"
                            tone="info" :title="$label"
                            :desc="count($pricesByType[$value]['prices'] ?? []).' '.Str::plural('precio', count($pricesByType[$value]['prices'] ?? [])).' ya registrado'.(count($pricesByType[$value]['prices'] ?? []) === 1 ? '' : 's')"
                            :selected="$priceWorkstationType === $value"
                            wire:click="$set('priceWorkstationType', '{{ $value }}')" />
                    @endforeach
                </div>
                @error('priceWorkstationType')
                    <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
                @enderror
            </x-ui.section>

            <x-ui.section step="2" title="Precio base y vigencia">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Precio de muestra" required
                        hint="Precio base, con hasta 4 decimales."
                        :error="$errors->first('priceSample')">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 font-semibold text-slate-400">$</span>
                            <input wire:model="priceSample" type="number" step="0.0001" min="0"
                                class="w-full pl-7 text-right font-bold tabular-nums" required>
                        </div>
                    </x-ui.field>

                    {{-- wire:ignore: sin él, el morph que dispara el selector de
                         tipo de estación borra la fecha (Flatpickr + morph). --}}
                    <x-ui.field label="Fecha efectiva" required
                        hint="Desde cuándo aplica este precio."
                        :error="$errors->first('priceEffectiveDate')">
                        <div wire:ignore>
                            <input wire:model="priceEffectiveDate" type="date" class="w-full" required>
                        </div>
                    </x-ui.field>
                </div>
            </x-ui.section>

            <x-ui.section step="3" title="Niveles de precio por volumen"
                hint="Opcional. Deja la cantidad máxima vacía para «sin límite».">
                <x-slot:aside>
                    <x-ui.btn variant="success" size="sm" wire:click="addPriceTier">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Agregar nivel
                    </x-ui.btn>
                </x-slot:aside>

                @if (empty($priceTiers))
                    <x-ui.empty icon="doc" title="Sin niveles de volumen"
                        hint="Si no agregas ninguno, siempre se cobra el precio de muestra." />
                @else
                    <div class="space-y-3">
                        @foreach ($priceTiers as $i => $tier)
                            <div wire:key="ptier-{{ $i }}"
                                class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-start gap-3">
                                    <span class="mt-6 flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white dark:bg-slate-200 dark:text-slate-900">
                                        {{ $i + 1 }}
                                    </span>

                                    <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-3">
                                        <x-ui.field label="Cantidad mínima" required
                                            :error="$errors->first('priceTiers.'.$i.'.min_quantity')">
                                            <input wire:model="priceTiers.{{ $i }}.min_quantity" type="number"
                                                step="0.0001" min="0" placeholder="0"
                                                class="w-full text-right tabular-nums">
                                        </x-ui.field>

                                        <x-ui.field label="Cantidad máxima" optional
                                            hint="Vacío = sin límite."
                                            :error="$errors->first('priceTiers.'.$i.'.max_quantity')">
                                            <input wire:model="priceTiers.{{ $i }}.max_quantity" type="number"
                                                step="0.0001" min="0" placeholder="Sin límite"
                                                class="w-full text-right tabular-nums">
                                        </x-ui.field>

                                        <x-ui.field label="Precio del nivel" required
                                            :error="$errors->first('priceTiers.'.$i.'.tier_price')">
                                            <div class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 font-semibold text-slate-400">$</span>
                                                <input wire:model="priceTiers.{{ $i }}.tier_price" type="number"
                                                    step="0.0001" min="0" placeholder="0.0000"
                                                    class="w-full pl-7 text-right font-bold tabular-nums">
                                            </div>
                                        </x-ui.field>
                                    </div>

                                    <div class="pt-6">
                                        <x-ui.icon-btn tone="danger" label="Eliminar el nivel {{ $i + 1 }}"
                                            wire:click="removePriceTier({{ $i }})">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </x-ui.icon-btn>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.section>

            <x-ui.section step="4" title="Estado y notas">
                <x-ui.check label="Precio activo" hint="Los precios inactivos no se usan al cotizar órdenes nuevas.">
                    <input wire:model="priceActive" type="checkbox">
                </x-ui.check>

                <x-ui.field label="Comentarios" optional class="mt-4"
                    hint="Contexto del precio: negociación, vigencia especial, etc."
                    :error="$errors->first('priceComments')">
                    <textarea wire:model="priceComments" rows="2" class="w-full"></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>
                Al guardar, el precio aparece en la pestaña de su tipo de estación sin salir de esta parte.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closePriceModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="savePrice" wire:loading.attr="disabled" wire:target="savePrice">
                    {{ $editingPriceId ? 'Guardar cambios' : 'Crear precio' }}
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</x-ui.page>
