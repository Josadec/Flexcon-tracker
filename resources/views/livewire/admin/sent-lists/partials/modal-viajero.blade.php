{{--
    PASO 7 · ENTREGA DE VIAJERO — modal compartido.

    Lo usan el tablero de piso (ShippingListDisplay) y la vista de Empaque
    (SentListPackagingView). Antes cada uno tenía su propia copia del markup y
    se veían distintos según por dónde lo abrieras.

    Requiere del componente: $showViajeroModal, $viajeroModalLotId y los
    métodos closeViajeroModal / markViajeroReceived / revertViajeroReceived.
--}}
    @if ($showViajeroModal)
        @php
            $vjLot = \App\Models\Lot::with(['crimpLots','workOrder.purchaseOrder.part','qualityWeighings','packagingPieceWeighings','packagingCrimpWeighings'])->find($viajeroModalLotId);
            $vjWO = $vjLot?->workOrder;
            $vjPart = $vjWO?->purchaseOrder?->part;
            $vjWoNum = $vjWO?->purchaseOrder?->wo ?? $vjWO?->wo_number ?? '—';
            $vjReceived = (bool) ($vjLot?->viajero_received);
            $vjPiezas = $vjLot ? $vjLot->getPackagedPiecesTotal() : 0;
            $vjCrimp = $vjLot ? $vjLot->getPackagedCrimpTotal() : 0;
            $vjPiezasSob = $vjLot ? $vjLot->getPackagedPiecesSurplus() : 0;
            $vjCrimpSob = $vjLot ? $vjLot->getPackagedCrimpSurplus() : 0;
            $vjDecLabel = ($vjLot && $vjLot->closure_decision) ? $vjLot->getPostQualityLifecycle()['decision']['label'] : 'Sin decisión todavía';
            $vjCrimpLots = $vjLot?->crimpLots ?? collect();
        @endphp
        <x-ui-modal wire:key="modal-viajero" badge="Paso 7" title="Entrega de Viajero" subtitle="Empaque confirma «Viajero recibido»"
            close="closeViajeroModal" maxWidth="4xl">
            <x-slot:context>
                <x-ui-modal.ctx label="Descripción" :value="$vjPart?->description ?? $vjPart?->number ?? '—'" />
                <x-ui-modal.ctx label="No. Order (WO + Viajero)" :value="$vjWoNum.($vjLot?->lot_number ?? '—')" />
                <x-ui-modal.ctx label="Lotes de CRIMP" :value="$vjCrimpLots->pluck('crimp_lot_number')->join(', ') ?: '—'" />
                <x-ui-modal.ctx label="Cantidad en viajero" :value="number_format($vjLot?->quantity ?? 0)" />
            </x-slot:context>

            <x-ui.section title="Estado de la entrega" hint="Empaque entrega el viajero y Materiales lo recibe.">
                @if ($vjReceived)
                    <x-ui.note tone="success" title="Viajero recibido por Materiales">
                        @if ($vjLot?->viajero_received_at)
                            Recibido el {{ \Carbon\Carbon::parse($vjLot->viajero_received_at)->format('d/m/Y H:i') }}{{ $vjLot->viajeroReceivedByUser?->name ? ' por '.$vjLot->viajeroReceivedByUser->name : '' }}.
                        @else
                            La entrega ya quedó registrada.
                        @endif
                    </x-ui.note>
                @else
                    <x-ui.note tone="warn" title="Pendiente: Empaque debe entregar el viajero">
                        Marca la entrega sólo cuando el viajero ya esté físicamente en Materiales.
                    </x-ui.note>
                @endif
            </x-ui.section>

            <x-ui.section title="Resumen del empaque" hint="Estas son las cantidades que van dentro del viajero.">
                <x-ui.stats cols="4">
                    <x-ui.stat label="Piezas «manguitas»" :value="number_format($vjPiezas)" tone="good" />
                    <x-ui.stat label="Piezas CRIMP" :value="number_format($vjCrimp)" tone="accent" />
                    <x-ui.stat label="Sobrantes de piezas" :value="number_format($vjPiezasSob)" tone="warn" />
                    <x-ui.stat label="Sobrantes de CRIMP" :value="number_format($vjCrimpSob)" tone="warn" />
                </x-ui.stats>

                <dl class="mt-4 divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    <x-ui.kv label="Decisión de Materiales" :value="$vjDecLabel" />
                </dl>
            </x-ui.section>

            <x-slot:note>
                Paso 7 de 8. Después de la entrega sigue el <strong>Paso 8</strong>: regresar los sobrantes a Materiales.
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeViajeroModal">Cerrar</x-ui.btn>
                @if ($vjReceived)
                    <x-ui.btn variant="secondary" wire:click="revertViajeroReceived({{ $viajeroModalLotId }})"
                        wire:confirm="¿Revertir la entrega? El viajero quedará como NO recibido.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Revertir entrega
                    </x-ui.btn>
                @else
                    <x-ui.btn variant="success" wire:click="markViajeroReceived({{ $viajeroModalLotId }})">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Marcar viajero como recibido
                    </x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui-modal>
    @endif
