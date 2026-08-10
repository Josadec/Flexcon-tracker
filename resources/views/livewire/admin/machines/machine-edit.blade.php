<x-ui.page eyebrow="Administración" :title="'Editar '.$machine->name"
    subtitle="Ubicación, inventario y tiempos del equipo. Los tiempos alimentan el cálculo de capacidad."
    back="{{ route('admin.machines.index') }}" backLabel="Volver a máquinas">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.machines.show', $machine) }}">Ver detalle</x-ui.btn>
    </x-slot:actions>

    <form wire:submit="update" class="space-y-5">
        <x-ui.section title="Identificación" hint="Cómo se reconoce la máquina en piso y en los estándares.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Nombre" required :error="$errors->first('name')">
                    <input wire:model="name" type="text" class="w-full" required>
                </x-ui.field>

                <x-ui.field label="Área" required
                    hint="Área de la planta donde está físicamente la máquina."
                    :error="$errors->first('area_id')">
                    <select wire:model="area_id" class="w-full" required>
                        <option value="">Seleccionar</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((int) $area_id === (int) $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Estado de producción" optional
                    hint="En qué punto del flujo trabaja. ¿Falta alguno? Adminístralos sin salir de aquí."
                    :error="$errors->first('production_status_id')">
                    <div class="flex items-center gap-2">
                        <select wire:model="production_status_id" class="w-full">
                            <option value="">Sin estado</option>
                            @foreach ($productionStatuses as $status)
                                <option value="{{ $status->id }}" @selected((int) $production_status_id === (int) $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                        <x-ui.icon-btn tone="primary" label="Administrar estados de producción"
                            wire:click="$dispatch('open-production-statuses')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </x-ui.icon-btn>
                    </div>
                </x-ui.field>

                <x-ui.field label="Número de empleados" optional
                    hint="Cuántos operadores la atienden. Mínimo 1."
                    :error="$errors->first('employees')">
                    <input wire:model="employees" type="number" min="1" step="1"
                        class="w-full text-right tabular-nums">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Equipo" hint="Datos de inventario. Todos opcionales.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Marca" optional :error="$errors->first('brand')">
                    <input wire:model="brand" type="text" class="w-full">
                </x-ui.field>

                <x-ui.field label="Modelo" optional :error="$errors->first('model')">
                    <input wire:model="model" type="text" class="w-full">
                </x-ui.field>

                <x-ui.field label="Número de serie" optional :error="$errors->first('sn')">
                    <input wire:model="sn" type="text" class="w-full font-mono">
                </x-ui.field>

                <x-ui.field label="Número de activo" optional
                    hint="No se puede repetir entre máquinas."
                    :error="$errors->first('asset_number')">
                    <input wire:model="asset_number" type="text" class="w-full font-mono">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Tiempos" hint="En minutos. Se usan para calcular la capacidad disponible.">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-ui.field label="Tiempo de preparación" optional
                    hint="Lo que tarda dejarla lista antes de producir (setup)."
                    :error="$errors->first('setup_time')">
                    <input wire:model="setup_time" type="number" min="0" step="0.01"
                        class="w-full text-right tabular-nums">
                </x-ui.field>

                <x-ui.field label="Tiempo de mantenimiento" optional
                    hint="Lo que se reserva para mantenimiento."
                    :error="$errors->first('maintenance_time')">
                    <input wire:model="maintenance_time" type="number" min="0" step="0.01"
                        class="w-full text-right tabular-nums">
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Estado y notas" hint="Define si la máquina está disponible para producir.">
            <x-ui.check label="Máquina activa" hint="Sólo las activas se pueden elegir al configurar un estándar.">
                <input wire:model="active" type="checkbox">
            </x-ui.check>

            <x-ui.field label="Comentarios" optional class="mt-4"
                hint="Uso interno." :error="$errors->first('comments')">
                <textarea wire:model="comments" rows="3" class="w-full"></textarea>
            </x-ui.field>
        </x-ui.section>

        <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Alta" :value="$machine->created_at?->format('d/m/Y H:i') ?? '—'" />
                <x-ui.kv label="Última actualización" :value="$machine->updated_at?->format('d/m/Y H:i') ?? '—'" />
            </dl>
        </x-ui.section>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-700">
            <x-ui.btn variant="secondary" href="{{ route('admin.machines.index') }}">Cancelar</x-ui.btn>
            <x-ui.btn variant="primary" type="submit">Guardar cambios</x-ui.btn>
        </div>
    </form>

    {{-- Catálogo compartido de estados: se abre con el botón junto al selector. --}}
    <livewire:admin.production-statuses.production-status-manager />
</x-ui.page>
