<x-ui.page eyebrow="Administración" :title="'Semi-automático '.$semiAutomatic->number"
    subtitle="Detalle de la estación semi-automática."
    back="{{ route('admin.semi-automatics.index') }}" backLabel="Volver a semi-automáticos">

    <x-slot:actions>
        <x-ui.btn variant="primary" href="{{ route('admin.semi-automatics.edit', $semiAutomatic) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar semi-automático
        </x-ui.btn>
    </x-slot:actions>

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Ficha --}}
    <x-ui.section title="Información de la estación">
        <x-slot:aside>
            <div class="flex items-center gap-2">
                @if ($semiAutomatic->productionStatus)
                    <x-ui.badge tone="accent">{{ $semiAutomatic->productionStatus->name }}</x-ui.badge>
                @endif
                @if ($semiAutomatic->active)
                    <x-ui.badge tone="good" dot>Activo</x-ui.badge>
                @else
                    <x-ui.badge tone="neutral" dot>Inactivo</x-ui.badge>
                @endif
            </div>
        </x-slot:aside>

        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Número" :value="$semiAutomatic->number" />
            <x-ui.kv label="Área">
                @if ($semiAutomatic->area)
                    <a href="{{ route('admin.areas.show', $semiAutomatic->area) }}" wire:navigate
                        class="font-bold text-sky-700 hover:underline dark:text-sky-300">{{ $semiAutomatic->area->name }}</a>
                @else
                    <span class="font-normal text-slate-400 dark:text-slate-500">Sin área</span>
                @endif
            </x-ui.kv>
            <x-ui.kv label="Estado de producción" :value="$semiAutomatic->productionStatus?->name ?: '—'" />
            <x-ui.kv label="Empleados"
                :value="$semiAutomatic->employees ? $semiAutomatic->employees.' '.\Illuminate\Support\Str::plural('operador', $semiAutomatic->employees) : '—'" />
        </dl>
    </x-ui.section>

    {{-- Comentarios --}}
    @if ($semiAutomatic->comments)
        <x-ui.section title="Comentarios" hint="Notas internas capturadas en la ficha.">
            <p class="whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $semiAutomatic->comments }}</p>
        </x-ui.section>
    @endif

    {{-- Registro --}}
    <x-ui.section title="Registro" hint="Fechas de control, sólo lectura.">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Alta" :value="$semiAutomatic->created_at?->format('d/m/Y H:i') ?? '—'" />
            <x-ui.kv label="Última actualización" :value="$semiAutomatic->updated_at?->format('d/m/Y H:i') ?? '—'" />
        </dl>
    </x-ui.section>
</x-ui.page>
