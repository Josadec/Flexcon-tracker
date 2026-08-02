{{--
    Lista de configuraciones de producción, compartida por alta y edición.
    Una configuración = tipo de estación + estación concreta + personal + UPH.

    Espera: $configurations, $workstationTypes, $personsOptions
--}}
<x-ui.section title="Configuraciones de producción"
    hint="Define el ritmo esperado según el tipo de estación y cuánta gente trabaja en ella.">

    <x-slot:aside>
        <x-ui.btn variant="success" size="sm" type="button" wire:click="addConfiguration">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Agregar configuración
        </x-ui.btn>
    </x-slot:aside>

    @error('configurations')
        <x-ui.note tone="danger" class="mb-4">{{ $message }}</x-ui.note>
    @enderror

    @if (count($configurations) === 0)
        <x-ui.empty icon="doc" title="Sin configuraciones"
            hint="Agrega al menos una para que el sistema sepa cuántas piezas por hora esperar de esta parte." />
    @else
        <div class="space-y-3">
            @foreach ($configurations as $index => $config)
                <div wire:key="config-{{ $index }}"
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">

                    {{-- Cabecera de la configuración --}}
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3 dark:border-slate-700">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white dark:bg-slate-200 dark:text-slate-900">
                                {{ $index + 1 }}
                            </span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white">Configuración {{ $index + 1 }}</span>
                            @if ($config['is_default'] ?? false)
                                <x-ui.badge tone="accent">Predeterminada</x-ui.badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5">
                            @if (! ($config['is_default'] ?? false))
                                <x-ui.icon-btn tone="neutral" type="button"
                                    label="Usar la configuración {{ $index + 1 }} como predeterminada"
                                    wire:click="setDefaultConfiguration({{ $index }})">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                </x-ui.icon-btn>
                            @endif
                            @if (count($configurations) > 1)
                                <x-ui.icon-btn tone="danger" type="button"
                                    label="Eliminar la configuración {{ $index + 1 }}"
                                    wire:click="removeConfiguration({{ $index }})">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </x-ui.icon-btn>
                            @endif
                        </div>
                    </div>

                    {{-- Campos --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <x-ui.field label="Tipo de estación" required
                            :error="$errors->first('configurations.'.$index.'.workstation_type')">
                            <select wire:model.live="configurations.{{ $index }}.workstation_type" class="w-full">
                                @foreach ($workstationTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(($config['workstation_type'] ?? null) == $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field label="Estación específica" optional
                            hint="Déjalo sin asignar si aplica a todas."
                            :error="$errors->first('configurations.'.$index.'.workstation_id')">
                            <select wire:model="configurations.{{ $index }}.workstation_id" class="w-full">
                                <option value="" @selected(empty($config['workstation_id']))>Sin asignar</option>
                                @foreach ($this->getWorkstationsForType($config['workstation_type']) as $ws)
                                    <option value="{{ $ws['id'] }}" @selected((int) ($config['workstation_id'] ?? 0) === (int) $ws['id'])>{{ $ws['name'] }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field label="Personas requeridas" required
                            :error="$errors->first('configurations.'.$index.'.persons_required')">
                            <select wire:model="configurations.{{ $index }}.persons_required" class="w-full">
                                @foreach ($personsOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(($config['persons_required'] ?? null) == $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field label="Unidades por hora" required
                            hint="Piezas buenas esperadas en una hora."
                            :error="$errors->first('configurations.'.$index.'.units_per_hour')">
                            <input type="number" min="1" placeholder="Ej: 50"
                                wire:model="configurations.{{ $index }}.units_per_hour"
                                class="w-full text-right font-bold tabular-nums">
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Notas" optional class="mt-4"
                        :error="$errors->first('configurations.'.$index.'.notes')">
                        <input type="text" wire:model="configurations.{{ $index }}.notes"
                            placeholder="Observaciones adicionales..." class="w-full">
                    </x-ui.field>
                </div>
            @endforeach
        </div>
    @endif
</x-ui.section>
