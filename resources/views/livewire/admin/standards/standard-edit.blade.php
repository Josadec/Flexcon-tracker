{{--
    NOTA: este archivo tenía marcadores de conflicto de Git sin resolver
    (<<<<<<< HEAD / ======= / >>>>>>> sha) que se imprimían como texto en la
    página. Los lados en conflicto sólo diferían en la forma de evaluar
    @selected(); se conservó la variante defensiva (casts explícitos y ?? para
    claves ausentes), que es la que no truena cuando el arreglo viene incompleto.
--}}
<x-ui.page eyebrow="Producción" title="Editar estándar"
    :subtitle="'Parte '.($standard->part?->number ?? 'N/A').' · los cambios afectan el cálculo de avance en piso.'"
    back="{{ route('admin.standards.index') }}" backLabel="Volver a estándares">

    <x-slot:actions>
        @if ($standard->active)
            <x-ui.badge tone="good" dot>Activo</x-ui.badge>
        @else
            <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
        @endif
        <x-ui.btn variant="secondary" href="{{ route('admin.standards.show', $standard) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="updateStandard" class="space-y-5">
        <x-ui.section title="Parte" hint="Cada parte tiene un solo estándar, con una o varias configuraciones.">
            <x-ui.field label="Parte" required :error="$errors->first('part_id')">
                <select wire:model="part_id" class="w-full" required>
                    <option value="">Seleccione una parte</option>
                    @foreach ($parts as $part)
                        <option value="{{ $part->id }}" @selected((int) $part_id === (int) $part->id)>
                            {{ $part->number }} - {{ Str::limit($part->description, 40) }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>
        </x-ui.section>

        @if ($useNewConfigSystem)
            @include('livewire.admin.standards.partials.configurations')
        @else
            {{-- Esquema anterior: un solo UPH para toda la parte. --}}
            <x-ui.section title="Configuración anterior"
                hint="Este estándar todavía usa el esquema previo, con un solo ritmo para toda la parte.">

                <x-ui.note tone="warn" class="mb-4" title="Esquema en desuso">
                    El esquema nuevo permite un ritmo distinto por tipo de estación y por cantidad de personal.
                </x-ui.note>

                <x-ui.field label="Unidades por hora" required
                    hint="Piezas buenas esperadas en una hora."
                    :error="$errors->first('units_per_hour')">
                    <input wire:model="units_per_hour" type="number" min="1" placeholder="Ej: 50"
                        class="w-full text-right font-bold tabular-nums" required>
                </x-ui.field>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-ui.field label="Mesa de trabajo" optional :error="$errors->first('work_table_id')">
                        <select wire:model="work_table_id" class="w-full">
                            <option value="">Seleccione una mesa</option>
                            @foreach ($workTables as $table)
                                <option value="{{ $table->id }}" @selected((int) $work_table_id === (int) $table->id)>{{ $table->number }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field label="Mesa semi-automática" optional :error="$errors->first('semi_auto_work_table_id')">
                        <select wire:model="semi_auto_work_table_id" class="w-full">
                            <option value="">Seleccione una mesa</option>
                            @foreach ($semiAutoWorkTables as $semiAuto)
                                <option value="{{ $semiAuto->id }}" @selected((int) $semi_auto_work_table_id === (int) $semiAuto->id)>{{ $semiAuto->number }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field label="Máquina" optional :error="$errors->first('machine_id')">
                        <select wire:model="machine_id" class="w-full">
                            <option value="">Seleccione una máquina</option>
                            @foreach ($machines as $machine)
                                <option value="{{ $machine->id }}" @selected((int) $machine_id === (int) $machine->id)>{{ $machine->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-ui.field label="Personas (config. 1)" optional :error="$errors->first('persons_1')">
                        <input wire:model="persons_1" type="number" min="1" class="w-full text-right tabular-nums">
                    </x-ui.field>
                    <x-ui.field label="Personas (config. 2)" optional :error="$errors->first('persons_2')">
                        <input wire:model="persons_2" type="number" min="1" class="w-full text-right tabular-nums">
                    </x-ui.field>
                    <x-ui.field label="Personas (config. 3)" optional :error="$errors->first('persons_3')">
                        <input wire:model="persons_3" type="number" min="1" class="w-full text-right tabular-nums">
                    </x-ui.field>
                </div>
            </x-ui.section>
        @endif

        <x-ui.section title="Estado y notas">
            <x-ui.check label="Estándar activo" hint="Los inactivos no se usan para calcular tiempos ni avances.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Descripción" optional class="mt-4"
                hint="Contexto del estándar: condiciones, supuestos, quién lo midió."
                :error="$errors->first('description')">
                <textarea wire:model="description" rows="3" class="w-full"
                    placeholder="Descripción detallada del estándar..."></textarea>
            </x-ui.field>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.standards.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>
</x-ui.page>
