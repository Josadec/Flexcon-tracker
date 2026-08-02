<x-ui.page eyebrow="Producción" :title="'Estándar de '.$standard->part->number"
    :subtitle="$standard->part->description ?: 'Ritmo de producción esperado por tipo de estación.'"
    back="{{ route('admin.standards.index') }}" backLabel="Volver a estándares">

    <x-slot:actions>
        @if ($standard->active)
            <x-ui.badge tone="good" dot>Activo</x-ui.badge>
            @if ($is_current)
                <x-ui.badge tone="accent">Vigente</x-ui.badge>
            @endif
        @else
            <x-ui.badge tone="bad" dot>Inactivo</x-ui.badge>
        @endif

        <x-ui.btn variant="primary" href="{{ route('admin.standards.edit', $standard) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar
        </x-ui.btn>
    </x-slot:actions>

    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Resumen de productividad: el dato que se viene a consultar. --}}
    @if ($configurationStats['total'] > 0)
        <x-ui.stats cols="4">
            <x-ui.stat label="Configuraciones" :value="$configurationStats['total']" />
            <x-ui.stat label="Productividad mín." :value="$configurationStats['min_productivity'] ?? '—'" unit="uph" tone="warn" />
            <x-ui.stat label="Productividad máx." :value="$configurationStats['max_productivity'] ?? '—'" unit="uph" tone="good" />
            <x-ui.stat label="Tiene predeterminada"
                :value="$configurationStats['has_default'] ? 'Sí' : 'No'"
                :tone="$configurationStats['has_default'] ? 'good' : 'bad'"
                help="Sin una configuración predeterminada el sistema no sabe qué ritmo usar por omisión." />
        </x-ui.stats>
    @endif

    {{-- Ficha --}}
    <x-ui.section title="Información general">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Parte" :value="$standard->part->number.' — '.$standard->part->description" />
            <x-ui.kv label="Estado" :value="$standard->active ? ($is_current ? 'Activo y vigente' : 'Activo') : 'Inactivo'"
                :tone="$standard->active ? 'good' : 'bad'" />
            @if ($standard->description)
                <x-ui.kv label="Descripción" :value="$standard->description" />
            @endif
            <x-ui.kv label="Creado" :value="$standard->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$standard->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>

        @if (!empty($configurationStats['by_type']))
            <div class="mt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Configuraciones por tipo</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($configurationStats['by_type'] as $type => $count)
                        <x-ui.badge tone="info">{{ $this->getWorkstationTypeLabel($type) }}: {{ $count }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ui.section>

    {{-- Configuraciones --}}
    @if ($standard->configurations->count() > 0)
        @php
            $typeTones = ['manual' => 'good', 'semi_automatic' => 'warn', 'machine' => 'accent'];
        @endphp
        <x-ui.table title="Configuraciones de producción"
            :hint="$standard->configurations->count().' '.Str::plural('configuración', $standard->configurations->count()).' registrada'.($standard->configurations->count() === 1 ? '' : 's')">
            <x-slot:head>
                <tr>
                    <x-ui.th>Tipo de estación</x-ui.th>
                    <x-ui.th>Estación</x-ui.th>
                    <x-ui.th align="center">Personas</x-ui.th>
                    <x-ui.th align="right">Unidades por hora</x-ui.th>
                    <x-ui.th align="center">Predeterminada</x-ui.th>
                    <x-ui.th>Notas</x-ui.th>
                </tr>
            </x-slot:head>

            @foreach ($standard->configurations as $config)
                <tr wire:key="std-config-{{ $config->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="px-4 py-3">
                        <x-ui.badge :tone="$typeTones[$config->workstation_type] ?? 'neutral'">
                            {{ $config->workstation_type_label }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $config->workstation_name }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex size-7 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                            {{ $config->persons_required }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">
                        {{ number_format($config->units_per_hour) }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if ($config->is_default)
                            <x-ui.badge tone="accent">Sí</x-ui.badge>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $config->notes ?: '—' }}</td>
                </tr>
            @endforeach
        </x-ui.table>

    @elseif (! $standard->is_migrated)
        {{-- Esquema anterior: un solo UPH para toda la parte. --}}
        <x-ui.section title="Configuración anterior"
            hint="Este estándar todavía usa el esquema previo, con un solo ritmo para toda la parte.">
            <x-ui.note tone="warn" class="mb-4" title="Conviene migrarlo">
                El esquema nuevo permite un ritmo distinto por tipo de estación y por cantidad de personal.
            </x-ui.note>

            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Unidades por hora" :value="($standard->units_per_hour ?? '—').' uph'" />
                @if ($standard->workTable)
                    <x-ui.kv label="Mesa de trabajo" :value="$standard->workTable->number" />
                @endif
                @if ($standard->semiAutoWorkTable)
                    <x-ui.kv label="Mesa semi-automática" :value="$standard->semiAutoWorkTable->number" />
                @endif
                @if ($standard->machine)
                    <x-ui.kv label="Máquina" :value="$standard->machine->name" />
                @endif
                <x-ui.kv label="Personas (config. 1)" :value="$standard->persons_1 ?? '—'" />
                <x-ui.kv label="Personas (config. 2)" :value="$standard->persons_2 ?? '—'" />
                <x-ui.kv label="Personas (config. 3)" :value="$standard->persons_3 ?? '—'" />
            </dl>
        </x-ui.section>

    @else
        <x-ui.section>
            <x-ui.empty icon="doc" title="Sin configuraciones"
                hint="Este estándar no tiene ninguna configuración: el sistema no puede calcular tiempos con él.">
                <x-slot:action>
                    <x-ui.btn variant="primary" href="{{ route('admin.standards.edit', $standard) }}">
                        Agregar configuraciones
                    </x-ui.btn>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.section>
    @endif

    {{-- Acciones sobre el registro: al final y visualmente aparte. --}}
    <x-ui.section title="Acciones sobre el estándar"
        hint="Desactivar lo saca de los cálculos sin borrarlo; eliminar es permanente.">
        <div class="flex flex-col gap-2 sm:flex-row">
            @if ($standard->active)
                <x-ui.btn variant="warning" wire:click="toggleActive">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Desactivar estándar
                </x-ui.btn>
            @else
                <x-ui.btn variant="success" wire:click="toggleActive">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Activar estándar
                </x-ui.btn>
            @endif

            <x-ui.btn variant="danger" wire:click="delete"
                wire:confirm="¿Eliminar este estándar? Se eliminarán todas sus configuraciones. Esta acción no se puede deshacer.">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Eliminar estándar
            </x-ui.btn>
        </div>
    </x-ui.section>
</x-ui.page>
