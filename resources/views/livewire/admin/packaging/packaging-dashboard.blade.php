{{--
    TABLERO DE EMPAQUE

    Empaque cierra el ciclo: empaca lo que Calidad aprobó, entrega el viajero y
    devuelve los sobrantes a Materiales. Los sobrantes van arriba porque son
    piezas físicas que alguien tiene que mover y que se pierden si nadie las
    reclama.
--}}
<x-ui.page eyebrow="Área · Empaque" title="Empaque"
    subtitle="Empaque de piezas, entrega del viajero y devolución de sobrantes.">

    <x-slot:actions>
        <x-ui.btn variant="secondary" href="{{ route('admin.sent-lists.index') }}">Listas de envío</x-ui.btn>
        <x-ui.btn variant="primary" href="{{ route('admin.sent-lists.display') }}">Abrir tablero de piso</x-ui.btn>
    </x-slot:actions>

    {{-- Carga del área --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Pendientes de Empaque" :value="$totalPending"
            :tone="$totalPending > 0 ? 'warn' : 'good'"
            help="Entregas de viajero + devoluciones de sobrantes." />
        <x-ui.stat label="Viajeros por entregar" :value="$viajeroQueue->count()"
            :tone="$viajeroQueue->count() > 0 ? 'warn' : 'good'"
            help="Ya tienen decisión de Materiales; falta entregarlos físicamente." />
        <x-ui.stat label="Sobrantes por devolver" :value="$surplusQueue->count()"
            :tone="$surplusQueue->count() > 0 ? 'info' : 'good'" />
        <x-ui.stat label="Piezas sobrantes en piso" :value="number_format($surplusToDeliver)"
            :tone="$surplusToDeliver > 0 ? 'warn' : 'good'"
            help="Piezas buenas que Empaque tiene y todavía no devuelve a Materiales." />
    </x-ui.stats>

    {{-- Aviso destacado: los sobrantes son material real sin dueño --}}
    @if ($surplusToDeliver > 0)
        <x-ui.note tone="warn" title="Hay {{ number_format($surplusToDeliver) }} piezas sobrantes sin devolver">
            Son piezas buenas que no entraron en la caja y siguen en Empaque.
            Mientras no se entreguen a Materiales, el ciclo de esos lotes no cierra.
        </x-ui.note>
    @endif

    {{-- Las dos colas, separadas --}}
    <x-ui.section step="1" title="Viajeros por entregar"
        hint="Empaque entrega el viajero a Materiales para que el lote pueda cerrarse.">
        <x-ui.pending-table :items="$viajeroQueue"
            emptyTitle="Ningún viajero por entregar"
            emptyHint="Todos los viajeros con decisión tomada ya fueron entregados." />
    </x-ui.section>

    <x-ui.section step="2" title="Sobrantes por devolver"
        hint="Piezas buenas que no se empacaron y hay que regresar a Materiales.">
        <x-ui.pending-table :items="$surplusQueue"
            emptyTitle="Sin sobrantes pendientes"
            emptyHint="No hay piezas sobrantes esperando devolución." />
    </x-ui.section>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        {{-- Producción de empaque --}}
        <x-ui.section title="Producción de empaque" hint="Acumulado histórico y actividad de hoy.">
            <x-ui.stats cols="2">
                <x-ui.stat label="Piezas empacadas" :value="number_format($packedTotal)" tone="good" />
                <x-ui.stat label="Sobrantes registrados" :value="number_format($surplusTotal)" tone="warn"
                    help="Histórico, incluye los ya devueltos." />
                <x-ui.stat label="Ciclos cerrados" :value="number_format($lotsClosed)" tone="info"
                    help="Lotes con decisión de cierre tomada." />
                <x-ui.stat label="Empacado hoy" :value="number_format($todayPacked)"
                    :help="$todayRecords.' '.Str::plural('registro', $todayRecords).' hoy'" />
            </x-ui.stats>
        </x-ui.section>

        {{-- Listas paradas aquí --}}
        <x-ui.section title="Listas de envío en Empaque"
            hint="Listas pendientes cuyo departamento actual es Empaque.">
            @if ($sentListsHere->isEmpty())
                <x-ui.empty icon="doc" title="Ninguna lista parada aquí"
                    hint="Todas las listas pendientes están en otro departamento." />
            @else
                <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    @foreach ($sentListsHere as $sl)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">Lista #{{ $sl->id }}</span>
                                <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $sl->workOrders->count() }} {{ Str::plural('orden', $sl->workOrders->count()) }} ·
                                    {{ $sl->created_at->format('d/m/Y') }}
                                </span>
                            </span>
                            <x-ui.btn variant="secondary" size="sm"
                                href="{{ route('admin.sent-lists.show', $sl->id) }}">Ver</x-ui.btn>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.section>
    </div>

    {{-- Últimos empaques --}}
    <x-ui.table title="Últimos registros de empaque" hint="Los 8 más recientes.">
        <x-slot:head>
            <tr>
                <x-ui.th class="w-36">Lote</x-ui.th>
                <x-ui.th class="w-32">Parte</x-ui.th>
                <x-ui.th class="w-28" align="right">Empacadas</x-ui.th>
                <x-ui.th class="w-28" align="right">Sobrantes</x-ui.th>
                <x-ui.th>Registró</x-ui.th>
                <x-ui.th class="w-40">Fecha</x-ui.th>
            </tr>
        </x-slot:head>

        @forelse ($recentRecords as $r)
            <tr wire:key="pr-{{ $r->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">
                    {{ $r->lot->lot_number ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                    {{ $r->lot->workOrder->purchaseOrder->part->number ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-green-700 dark:text-green-400">
                    {{ number_format($r->packed_pieces) }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums {{ $r->surplus_pieces > 0 ? 'font-semibold text-orange-700 dark:text-orange-400' : 'text-slate-400' }}">
                    {{ number_format($r->surplus_pieces) }}
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $r->packedBy->name ?? '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">
                    {{ $r->packed_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty title="Sin registros de empaque"
                        hint="Se registran desde el tablero de piso, en la columna Emp." />
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-ui.page>
