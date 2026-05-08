<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.prices.index') }}" class="inline-flex items-center justify-center w-10 h-10 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-md transition-colors" title="Volver">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </a>
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Crear precio</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ingrese la información del nuevo precio por parte</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden divide-y divide-gray-200 dark:divide-gray-700">
        <form wire:submit="savePrice" class="p-6 space-y-6">

            {{-- Parte --}}
            <div>
                <label for="part_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Parte <span class="text-red-500">*</span></label>
                <select wire:model.live="part_id" id="part_id"
                    class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                    <option value="">Seleccione una parte</option>
                    @foreach ($parts as $part)
                        <option value="{{ $part->id }}" @selected((string) $part->id === (string) $part_id)>
                            {{ $part->number }} - {{ $part->description }}
                        </option>
                    @endforeach
                </select>
                @error('part_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                @if($has_existing_prices && $info_message)
                    <div class="mt-2 p-3 rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="text-sm text-blue-700 dark:text-blue-300">{{ $info_message }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Precio muestra + Fecha efectiva --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="sample_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Precio de muestra <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">$</span>
                        <input wire:model="sample_price" id="sample_price" type="number" step="0.0001" min="0"
                            class="block w-full pl-8 pr-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required />
                    </div>
                    @error('sample_price')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="effective_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fecha efectiva <span class="text-red-500">*</span></label>
                    <div wire:ignore>
                        <input wire:model="effective_date" id="effective_date" type="date"
                            class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required />
                    </div>
                    @error('effective_date')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Tipo de estación --}}
            <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Tipo de estación de trabajo</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($workstationTypes as $value => $label)
                        <label class="relative flex cursor-pointer rounded-lg border-2 p-4 {{ $workstation_type === $value ? 'border-blue-500 ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500' }}">
                            <input type="radio" wire:model.live="workstation_type" value="{{ $value }}" class="sr-only">
                            <span class="flex flex-col">
                                <span class="text-sm font-medium {{ $workstation_type === $value ? 'text-blue-900 dark:text-blue-100' : 'text-gray-900 dark:text-white' }}">{{ $label }}</span>
                            </span>
                            @if ($workstation_type === $value)
                                <svg class="h-5 w-5 text-blue-600 ml-auto" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Tiers dinámicos --}}
            <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Niveles de precio por volumen</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Define los rangos de cantidad y su precio. Deje "Cantidad máxima" vacío para "sin límite".</p>
                    </div>
                    <button type="button" wire:click="addTier"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Agregar tier
                    </button>
                </div>

                @if(empty($tiers))
                    <div class="rounded-md border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        No hay tiers configurados. Haz clic en "Agregar tier" para empezar.
                    </div>
                @else
                    <div class="overflow-x-auto rounded-md border-2 border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 w-12">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Cantidad mínima <span class="text-red-500">*</span></th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Cantidad máxima</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Precio del tier</th>
                                    <th class="px-3 py-2 w-20"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($tiers as $i => $tier)
                                    <tr wire:key="tier-{{ $i }}">
                                        <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $i + 1 }}</td>
                                        <td class="px-3 py-2">
                                            <input wire:model="tiers.{{ $i }}.min_quantity" type="number" step="0.0001" min="0" placeholder="0"
                                                class="block w-full px-2 py-1.5 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500" />
                                            @error("tiers.{$i}.min_quantity")<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input wire:model="tiers.{{ $i }}.max_quantity" type="number" step="0.0001" min="0" placeholder="Sin límite"
                                                class="block w-full px-2 py-1.5 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500" />
                                            @error("tiers.{$i}.max_quantity")<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="relative">
                                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 dark:text-gray-400 text-xs">$</span>
                                                <input wire:model="tiers.{{ $i }}.tier_price" type="number" step="0.0001" min="0" placeholder="0.0000"
                                                    class="block w-full pl-6 pr-2 py-1.5 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500" />
                                            </div>
                                            @error("tiers.{$i}.tier_price")<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" wire:click="removeTier({{ $i }})"
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors" title="Eliminar tier">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Activo + Comentarios --}}
            <div class="flex items-center gap-2">
                <input wire:model.live="active" id="active" type="checkbox" class="w-4 h-4 text-blue-600 border-2 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700" />
                <label for="active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Precio activo</label>
            </div>

            <div>
                <label for="comments" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Comentarios</label>
                <textarea wire:model="comments" id="comments" rows="3" class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('admin.prices.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 rounded-md transition-colors">Cancelar</a>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors">Crear precio</button>
            </div>
        </form>
    </div>
</div>
