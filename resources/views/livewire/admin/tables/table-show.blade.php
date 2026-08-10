<x-ui.page eyebrow="Administración" :title="'Mesa '.$table->number"
    :subtitle="$table->name ?: 'Detalle de la mesa de trabajo y de su equipo.'"
    back="{{ route('admin.tables.index') }}" backLabel="Volver a mesas">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.tables.edit', $table) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar mesa
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Ficha --}}
    <x-ui.section title="Información de la mesa">
        <x-slot:aside>
            <div class="flex items-center gap-2">
                @if ($table->productionStatus)
                    <x-ui.badge tone="accent">{{ $table->productionStatus->name }}</x-ui.badge>
                @endif
                @if ($table->active)
                    <x-ui.badge tone="good" dot>Activa</x-ui.badge>
                @else
                    <x-ui.badge tone="neutral" dot>Inactiva</x-ui.badge>
                @endif
            </div>
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Número" :value="$table->number" />
            <x-ui.kv label="Nombre" :value="$table->name ?: '—'" />
            <x-ui.kv label="Área">
                @if ($table->area)
                    <a href="{{ route('admin.areas.show', $table->area) }}" wire:navigate
                        class="font-bold text-sky-700 hover:underline dark:text-sky-300">{{ $table->area->name }}</a>
                @else
                    <span class="font-normal text-slate-400 dark:text-slate-500">Sin área</span>
                @endif
            </x-ui.kv>
            <x-ui.kv label="Estado de producción" :value="$table->productionStatus?->name ?: '—'" />
            <x-ui.kv label="Empleados"
                :value="$table->employees ? $table->employees.' '.\Illuminate\Support\Str::plural('operador', $table->employees) : '—'" />
            {{-- Standard no tiene nombre: se identifica por su descripción o su id. --}}
            <x-ui.kv label="Estándar asignado"
                :value="$table->standard ? ($table->standard->description ?: 'Estándar #'.$table->standard->id) : '—'"
                help="El estándar con el que se calcula su producción." />
        </dl>
    </x-ui.section>

    {{-- Equipo --}}
    <x-ui.section title="Equipo" hint="Datos de inventario capturados en la ficha de la mesa.">
        @if ($table->brand || $table->model || $table->s_n || $table->asset_number)
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="Marca" :value="$table->brand ?: '—'" />
                <x-ui.kv label="Modelo" :value="$table->model ?: '—'" />
                <x-ui.kv label="Número de serie" :value="$table->s_n ?: '—'" />
                <x-ui.kv label="Número de activo" :value="$table->asset_number ?: '—'" />
            </dl>
        @else
            <x-ui.empty icon="box" title="Sin datos de equipo"
                hint="Marca, modelo, número de serie y número de activo se capturan al editar la mesa." />
        @endif
    </x-ui.section>

    {{-- Texto libre --}}
    @if ($table->description || $table->comments)
        <x-ui.section title="Notas">
            @if ($table->description)
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Descripción</p>
                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $table->description }}</p>
            @endif

            @if ($table->comments)
                <p class="{{ $table->description ? 'mt-4' : '' }} text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Comentarios</p>
                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $table->comments }}</p>
            @endif
        </x-ui.section>
    @endif

    {{-- Registro --}}
    <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Alta" :value="$table->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$table->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>
</x-ui.page>
