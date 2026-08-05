@props([
    'items',                 // Collection de App\Support\PendingActions
    'showActor'  => false,   // mostrar la columna "Responsable" (tablero global)
    'emptyTitle' => 'Todo al día',
    'emptyHint'  => 'No hay lotes esperando que se registre algo.',
])

@php
    // Mismo color por área en los seis tableros.
    $actorTones = [
        'Materiales' => ['dot' => 'bg-sky-500',    'num' => 'text-sky-700 dark:text-sky-300'],
        'Calidad'    => ['dot' => 'bg-teal-500',   'num' => 'text-teal-700 dark:text-teal-300'],
        'Producción' => ['dot' => 'bg-indigo-500', 'num' => 'text-indigo-700 dark:text-indigo-300'],
        'Empaque'    => ['dot' => 'bg-orange-500', 'num' => 'text-orange-700 dark:text-orange-300'],
    ];
@endphp

{{--
    Tabla de acciones pendientes. Un renglón = un lote esperando que alguien
    registre algo. Se usa igual en el tablero global y en los de área; sólo
    cambia si se muestra la columna del responsable.
--}}
@if ($items->isEmpty())
    <x-ui.note tone="success" :title="$emptyTitle">{{ $emptyHint }}</x-ui.note>
@else
    <x-ui.table {{ $attributes }}>
        <x-slot:head>
            <tr>
                <x-ui.th class="w-40">Lote / viajero</x-ui.th>
                <x-ui.th class="w-32">Parte</x-ui.th>
                <x-ui.th>Qué falta</x-ui.th>
                @if ($showActor)
                    <x-ui.th class="w-36">Responsable</x-ui.th>
                @endif
                <x-ui.th class="w-24" align="right">Ir</x-ui.th>
            </tr>
        </x-slot:head>

        @foreach ($items as $item)
            @php
                $lot  = $item['lot'];
                $wo   = $lot->workOrder;
                $part = $wo?->purchaseOrder?->part;
                $tone = $actorTones[$item['actor']] ?? $actorTones['Materiales'];
            @endphp
            <tr wire:key="pending-{{ $item['phase'] }}-{{ $lot->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        WO {{ $wo?->purchaseOrder?->wo ?? $wo?->wo_number ?? '—' }}
                    </span>
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="block text-sm text-slate-700 dark:text-slate-200">{{ $part?->number ?? '—' }}</span>
                    @if ($part?->is_crimp)
                        <x-ui.badge tone="accent">CRIMP</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="block font-medium text-slate-900 dark:text-white">{{ $item['action'] }}</span>
                    @if ($item['detail'])
                        <span class="block text-xs leading-4 text-slate-500 dark:text-slate-400">{{ $item['detail'] }}</span>
                    @endif
                </td>
                @if ($showActor)
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">
                            <span class="size-2 shrink-0 rounded-full {{ $tone['dot'] }}" aria-hidden="true"></span>
                            {{ $item['actor'] }}
                        </span>
                    </td>
                @endif
                <td class="px-4 py-3 text-right">
                    <x-ui.btn variant="secondary" size="sm"
                        href="{{ route('admin.sent-lists.display.wo', $wo?->id) }}">Abrir</x-ui.btn>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
@endif
