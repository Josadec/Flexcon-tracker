{{--
    VISTA DE INSPECCIÓN DENTRO DE UNA LISTA PRELIMINAR

    Se monta como pestaña del detalle de la lista, así que NO lleva
    <x-ui.page>: la cabecera la pone la pantalla contenedora.

    Calidad aprueba o rechaza cada lote ANTES de que Producción lo trabaje.
--}}
<div class="space-y-5">

    {{-- Sólo lectura: además del aviso, las acciones de escritura no se pintan. --}}
    @php $puedeEditar = $this->canEditDepartment(); @endphp

    @unless ($puedeEditar)
        <x-ui.note tone="warn" title="Modo sólo lectura">
            Esta lista no está en la etapa de tu departamento o ya fue cerrada. Puedes consultarla, pero no modificarla.
        </x-ui.note>
    @endunless

    @if (session()->has('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session()->has('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    @php
        $allLots       = $workOrders->flatMap->lots;
        $totalLots     = $allLots->count();
        $approvedCount = $allLots->where('inspection_status', 'approved')->count();
        $rejectedCount = $allLots->where('inspection_status', 'rejected')->count();
        $pendingCount  = $allLots->where('inspection_status', 'pending')->count();
        $pct           = $totalLots > 0 ? round(($approvedCount / $totalLots) * 100) : 0;
    @endphp

    {{-- Avance de la inspección --}}
    <x-ui.section title="Avance de la inspección"
        :hint="$approvedCount.' de '.$totalLots.' '.Str::plural('lote', $totalLots).' aprobados. Producción no puede empezar hasta que todos pasen.'">
        <x-ui.stats cols="3">
            <x-ui.stat label="Pendientes" :value="$pendingCount" :tone="$pendingCount > 0 ? 'warn' : 'good'" />
            <x-ui.stat label="Aprobados" :value="$approvedCount" tone="good" />
            <x-ui.stat label="Rechazados" :value="$rejectedCount" :tone="$rejectedCount > 0 ? 'bad' : 'neutral'" />
        </x-ui.stats>

        <div class="mt-4">
            <div class="mb-1.5 flex items-baseline justify-between text-xs">
                <span class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Aprobado</span>
                <span class="font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ $pct }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                <div class="h-full rounded-full transition-all {{ $pct >= 100 ? 'bg-green-500' : 'bg-sky-500' }}" style="width: {{ $pct }}%"></div>
            </div>
        </div>
    </x-ui.section>

    {{-- Un bloque por Work Order --}}
    @forelse ($workOrders as $wo)
        @php $isCrimp = $wo->purchaseOrder->part->is_crimp ?? false; @endphp

        <x-ui.section wire:key="wo-{{ $wo->id }}"
            :title="($wo->purchaseOrder->wo ?? $wo->wo_number).' · '.($wo->purchaseOrder->part->number ?? '—')"
            :hint="$wo->purchaseOrder->part->description ?? null">

            <x-slot:aside>
                <div class="flex items-center gap-2">
                    @if ($isCrimp)
                        <x-ui.badge tone="accent">CRIMP</x-ui.badge>
                    @endif
                    <x-ui.btn variant="secondary" size="sm" href="{{ route('admin.sent-lists.display.wo', $wo->id) }}">
                        Ver en el tablero
                    </x-ui.btn>
                </div>
            </x-slot:aside>

            @if ($wo->lots->isEmpty())
                <x-ui.empty icon="box" title="Este WO no tiene lotes asignados"
                    hint="Los lotes se crean desde el tablero de piso." />
            @else
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <x-ui.th class="w-48">{{ $isCrimp ? 'Viajero' : 'Lote' }}</x-ui.th>
                            <x-ui.th class="w-28" align="right">Cantidad</x-ui.th>
                            @if ($isCrimp)
                                <x-ui.th>Lotes de CRIMP</x-ui.th>
                            @endif
                            <x-ui.th class="w-32">Inspección</x-ui.th>
                            <x-ui.th class="w-56" align="right">Acciones</x-ui.th>
                        </tr>
                    </x-slot:head>

                    @foreach ($wo->lots as $lot)
                        <tr wire:key="lot-{{ $lot->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3">
                                <span class="block font-semibold text-slate-900 dark:text-white">{{ $lot->lot_number }}</span>
                                @if ($lot->inspection_comments && $lot->inspection_status === 'rejected')
                                    <span class="mt-0.5 block text-xs leading-4 text-red-700 dark:text-red-400">
                                        {{ Str::limit($lot->inspection_comments, 80) }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">
                                {{ number_format($lot->quantity) }}
                            </td>

                            @if ($isCrimp)
                                <td class="px-4 py-3">
                                    @if ($lot->crimpLots->isEmpty())
                                        <span class="text-xs text-slate-400">Sin lotes de CRIMP capturados</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($lot->crimpLots as $cl)
                                                <x-ui.badge tone="accent">{{ $cl->crimp_lot_number }}</x-ui.badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            @endif

                            <td class="px-4 py-3">
                                @if ($lot->inspection_status === 'approved')
                                    <x-ui.badge tone="good" dot>Aprobado</x-ui.badge>
                                @elseif ($lot->inspection_status === 'rejected')
                                    <x-ui.badge tone="bad" dot>Rechazado</x-ui.badge>
                                @else
                                    <x-ui.badge tone="warn" dot>Pendiente</x-ui.badge>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($puedeEditar)
                                    <x-ui.btn variant="success" size="sm"
                                        wire:click="approveLot({{ $lot->id }})"
                                        :disabled="$lot->inspection_status === 'approved'"
                                        :title="$lot->inspection_status === 'approved' ? 'Este lote ya está aprobado' : null">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        Aprobar
                                    </x-ui.btn>

                                    <x-ui.btn variant="danger" size="sm"
                                        wire:click="openRejectModal({{ $lot->id }})"
                                        :disabled="$lot->inspection_status === 'rejected'"
                                        :title="$lot->inspection_status === 'rejected' ? 'Este lote ya está rechazado' : null">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Rechazar
                                    </x-ui.btn>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.section>
    @empty
        <x-ui.section>
            <x-ui.empty title="No hay Work Orders en esta lista"
                hint="Las órdenes se agregan a la lista desde el wizard de capacidad." />
        </x-ui.section>
    @endforelse

    {{-- Cierre de la etapa --}}
    <x-ui.section title="Cerrar la inspección"
        hint="La lista pasa a Producción sólo cuando todos los lotes están aprobados.">
        @unless ($allApproved)
            <x-ui.note tone="muted" class="mb-4">
                Faltan lotes por aprobar. El botón de envío se habilita cuando todos pasen la inspección.
            </x-ui.note>
        @endunless

        <div class="flex flex-col gap-2 sm:flex-row sm:justify-between">
            @if ($puedeEditar)
                <x-ui.btn :variant="$hasRejected ? 'danger' : 'secondary'" wire:click="openReturnModal">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                    Regresar a Materiales
                </x-ui.btn>

                <x-ui.btn variant="success" wire:click="openApproveModal" :disabled="! $allApproved"
                    :title="$allApproved ? null : 'Faltan lotes por aprobar'">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Aprobar y enviar a Producción
                </x-ui.btn>
            @endif
        </div>
    </x-ui.section>

    {{-- Rechazo de un lote --}}
    @if ($showRejectModal)
        <x-ui-modal wire:key="modal-reject" title="Rechazar lote"
            subtitle="El motivo queda en el historial y el lote no avanza hasta corregirse."
            close="closeRejectModal" maxWidth="2xl">

            <x-ui.section title="Motivo del rechazo" hint="Sé específico: es lo que va a leer Materiales para corregir.">
                <x-ui.field label="Motivo" required
                    hint="Mínimo 5 caracteres."
                    :error="$errors->first('rejectReason')">
                    <textarea wire:model="rejectReason" rows="4" class="w-full"
                        placeholder="Describe el problema encontrado..."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>El lote quedará marcado como rechazado y no podrá pasar a Producción.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeRejectModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="rejectLot"
                    wire:loading.attr="disabled" wire:target="rejectLot">Confirmar rechazo</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Retorno a Materiales --}}
    @if ($showReturnModal)
        <x-ui-modal wire:key="modal-return" title="Regresar la lista a Materiales"
            subtitle="La lista sale de Inspección y vuelve al departamento anterior."
            close="closeReturnModal" maxWidth="2xl">

            <x-ui.section title="Motivo del retorno" hint="Explica qué debe corregir Materiales antes de devolverla.">
                <x-ui.field label="Motivo" required
                    hint="Mínimo 5 caracteres."
                    :error="$errors->first('returnReason')">
                    <textarea wire:model="returnReason" rows="4" class="w-full"
                        placeholder="Indica qué debe corregir Materiales..."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>Queda registrado en el historial de la lista con tu usuario y la fecha.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeReturnModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="warning" wire:click="returnToMaterials"
                    wire:loading.attr="disabled" wire:target="returnToMaterials">Confirmar retorno</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif

    {{-- Aprobar y enviar a Producción --}}
    @if ($showApproveModal)
        <x-ui-modal wire:key="modal-approve" title="Aprobar y enviar a Producción"
            subtitle="La lista pasa a la siguiente etapa del flujo."
            close="closeApproveModal" maxWidth="2xl">

            <x-ui.section title="Resultado de la inspección">
                <x-ui.note tone="success" title="Todos los lotes aprobados">
                    {{ $approvedCount }} de {{ $totalLots }} {{ Str::plural('lote', $totalLots) }} pasaron la inspección.
                </x-ui.note>
            </x-ui.section>

            <x-ui.section title="Notas para Producción">
                <x-ui.field label="Notas de aprobación" optional
                    hint="Cualquier detalle que Producción deba saber antes de empezar.">
                    <textarea wire:model="approveNotes" rows="3" class="w-full"
                        placeholder="Observaciones para Producción..."></textarea>
                </x-ui.field>
            </x-ui.section>

            <x-slot:note>Al enviar, la lista cambia de departamento y Producción puede empezar a pesar.</x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeApproveModal">Cancelar</x-ui.btn>
                <x-ui.btn variant="success" wire:click="sendToProduction"
                    wire:loading.attr="disabled" wire:target="sendToProduction">Enviar a Producción</x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
</div>
