@php
    use App\Livewire\Admin\History\HistoryExplorer;

    $etiquetaTipo = fn ($clase) => HistoryExplorer::ENTITIES[$clase] ?? class_basename($clase);
    $etiquetaAccion = fn ($accion) => HistoryExplorer::ACTIONS[$accion] ?? $accion;

    $tonoAccion = fn ($accion) => match (true) {
        $accion === 'create' => 'good',
        $accion === 'delete' => 'bad',
        $accion === 'reopen' => 'warn',
        str_starts_with($accion, 'legacy.') => 'neutral',
        default => 'info',
    };

    // Los cambios se pintan campo a campo: "de X a Y". Es lo que hace legible
    // una entrada sin tener que abrir el JSON.
    $cambios = function ($entrada) {
        $antes = $entrada->old_values ?? [];
        $despues = $entrada->new_values ?? [];
        $claves = array_diff(array_keys($despues), ['motivo', 'cascada']);

        return collect($claves)->map(fn ($k) => [
            'campo' => $k,
            'antes' => is_scalar($antes[$k] ?? null) ? (string) ($antes[$k] ?? '—') : '—',
            'despues' => is_scalar($despues[$k] ?? null) ? (string) ($despues[$k] ?? '—') : '—',
        ])->take(6);
    };
@endphp

{{-- Raíz HTML plana obligatoria: Livewire saca el tag del componente hijo con
     un regex sobre el HTML renderizado. Si la raíz fuera el @if (cuyas dos
     ramas son componentes Blade), el tag sale vacío y el render falla. --}}
<div>
@if ($this->isEmbedded())
    {{-- Línea de tiempo dentro de la ficha de un registro --}}
    <x-ui.section title="Historial" hint="Todo lo que se ha hecho sobre este registro, de lo más reciente a lo más antiguo.">
        @forelse ($entries as $entrada)
            <div wire:key="h-{{ $entrada->id }}" class="flex gap-3 border-b border-slate-100 py-3 last:border-b-0 dark:border-slate-700/60">
                <x-ui.badge :tone="$tonoAccion($entrada->action)">{{ $etiquetaAccion($entrada->action) }}</x-ui.badge>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        <span class="font-semibold">{{ $entrada->actor_name }}</span>
                        <span class="text-slate-400"> · {{ $entrada->created_at?->format('d/m/Y H:i') }}</span>
                    </p>
                    @if (! empty($entrada->new_values['motivo']))
                        <p class="mt-0.5 text-xs italic text-slate-600 dark:text-slate-300">
                            «{{ $entrada->new_values['motivo'] }}»
                        </p>
                    @endif
                    @foreach ($cambios($entrada) as $c)
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-mono">{{ $c['campo'] }}</span>:
                            {{ $c['antes'] }} → <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $c['despues'] }}</span>
                        </p>
                    @endforeach
                </div>
            </div>
        @empty
            <x-ui.empty icon="doc" title="Sin movimientos registrados"
                hint="Cuando alguien modifique este registro, aquí quedará el rastro." />
        @endforelse

        @if ($entries->hasPages())
            <div class="mt-3">{{ $entries->links() }}</div>
        @endif
    </x-ui.section>
@else
    <x-ui.page eyebrow="Reportes" title="Historial"
        subtitle="Qué pasó, cuándo y quién lo hizo. Se conserva 5 años por requisito del ISO y del cliente.">

        <x-ui.stats cols="2">
            <x-ui.stat label="Movimientos registrados" :value="number_format($entries->total())" tone="info" />
            <x-ui.stat label="Mostrando" :value="number_format($entries->count()).' de '.number_format($entries->total())" />
        </x-ui.stats>

        {{-- Filtros --}}
        <x-ui.section title="Buscar en el historial"
            hint="Por orden de trabajo, orden de compra, número o descripción de parte, número de viajero, o quién hizo el cambio.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-ui.field label="Texto a buscar" class="lg:col-span-2">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="text" wire:model.live.debounce.400ms="search"
                            placeholder="WO, parte, viajero o persona..." class="w-full pl-10">
                    </div>
                </x-ui.field>

                <x-ui.field label="Tipo de registro">
                    <select wire:model.live="filterType" class="w-full">
                        <option value="">Todos</option>
                        @foreach ($this->entityOptions as $clase => $etiqueta)
                            <option value="{{ $clase }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Qué se hizo">
                    <select wire:model.live="filterAction" class="w-full">
                        <option value="">Todo</option>
                        @foreach ($this->actionOptions as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Desde">
                    <input type="date" wire:model.live="startDate" class="w-full">
                </x-ui.field>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-ui.field label="Hasta">
                    <input type="date" wire:model.live="endDate" class="w-full">
                </x-ui.field>
            </div>

            @if ($search || $filterType || $filterAction || $startDate || $endDate)
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Mostrando resultados filtrados.</span>
                    <x-ui.btn variant="ghost" size="sm" wire:click="clearFilters">Limpiar filtros</x-ui.btn>
                </div>
            @endif
        </x-ui.section>

        {{-- Movimientos --}}
        <x-ui.table title="Movimientos" hint="De lo más reciente a lo más antiguo. Este registro no se puede modificar ni borrar.">
            <x-slot:head>
                <tr>
                    <x-ui.th class="w-44">Cuándo</x-ui.th>
                    <x-ui.th class="w-40">Quién</x-ui.th>
                    <x-ui.th class="w-44">Qué se hizo</x-ui.th>
                    <x-ui.th>Sobre qué</x-ui.th>
                    <x-ui.th>Detalle</x-ui.th>
                </tr>
            </x-slot:head>

            @forelse ($entries as $entrada)
                <tr wire:key="row-{{ $entrada->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="whitespace-nowrap px-4 py-3">
                        <span class="block text-sm text-slate-700 dark:text-slate-200">{{ $entrada->created_at?->format('d/m/Y') }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $entrada->created_at?->format('H:i') }}</span>
                    </td>

                    <td class="px-4 py-3">
                        <span class="block truncate text-sm text-slate-700 dark:text-slate-200">{{ $entrada->actor_name }}</span>
                        @if (! $entrada->user_id && $entrada->user_name)
                            <span class="block text-[11px] text-slate-400">cuenta dada de baja</span>
                        @endif
                    </td>

                    <td class="px-4 py-3">
                        <x-ui.badge :tone="$tonoAccion($entrada->action)">{{ $etiquetaAccion($entrada->action) }}</x-ui.badge>
                    </td>

                    <td class="px-4 py-3">
                        <span class="block text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $etiquetaTipo($entrada->auditable_type) }} #{{ $entrada->auditable_id }}
                        </span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                            @if ($entrada->work_order_id) WO {{ $entrada->work_order_id }} @endif
                            @if ($entrada->lot_id) · Viajero {{ $entrada->lot_id }} @endif
                        </span>
                    </td>

                    <td class="px-4 py-3">
                        @if (! empty($entrada->new_values['motivo']))
                            <p class="text-xs italic text-slate-600 dark:text-slate-300">«{{ $entrada->new_values['motivo'] }}»</p>
                        @endif
                        @foreach ($cambios($entrada) as $c)
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-mono">{{ $c['campo'] }}</span>:
                                {{ $c['antes'] }} → <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $c['despues'] }}</span>
                            </p>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        @if ($search || $filterType || $filterAction || $startDate || $endDate)
                            <x-ui.empty icon="search" title="Nada coincide con esos filtros"
                                hint="Prueba con menos filtros o un rango de fechas más amplio." />
                        @else
                            <x-ui.empty icon="doc" title="Todavía no hay movimientos"
                                hint="Aquí se irá guardando todo lo que se cree, cambie o borre en el sistema." />
                        @endif
                    </td>
                </tr>
            @endforelse

            @if ($entries->hasPages())
                <x-slot:foot>{{ $entries->links() }}</x-slot:foot>
            @endif
        </x-ui.table>
    </x-ui.page>
@endif
</div>
