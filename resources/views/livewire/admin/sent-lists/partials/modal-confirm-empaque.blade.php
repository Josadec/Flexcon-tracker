{{--
    PASO 5 · CONFIRMACIÓN DE EMPAQUE (CRIMP) — modal compartido.

    Lo usan el tablero de piso (ShippingListDisplay) y la vista de Empaque
    (SentListPackagingView). Antes cada uno tenía su propia copia del markup.

    Requiere del componente: $showConfirmModal, $confirmLotId, $confirmCrimpLotId,
    $editPieceWId, $editCrimpWId, $editPieceWQty, $editCrimpWQty, $cPieceQty,
    $cCrimpQty, $confirmDone, $confirmComments, $confirmLabelCount y sus métodos.
--}}
    @if ($showConfirmModal)
        @php
            $cfLot = \App\Models\Lot::with(['crimpLots','workOrder.purchaseOrder.part','qualityWeighings','packagingPieceWeighings','packagingCrimpWeighings'])->find($confirmLotId);
            $cfWO    = $cfLot?->workOrder;
            $cfPart  = $cfWO?->purchaseOrder?->part;
            $cfWoNum = $cfWO?->purchaseOrder?->wo ?? $cfWO?->wo_number ?? '—';
            $cfCrimpLots = $cfLot?->crimpLots ?? collect();
            $cfSel   = $cfCrimpLots->firstWhere('id', $confirmCrimpLotId);
            $cfPW = $cfLot ? $cfLot->packagingPieceWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
            $cfCW = $cfLot ? $cfLot->packagingCrimpWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
            $cfPiecesTotal = (int) $cfPW->sum('quantity');
            $cfCrimpTotal  = (int) $cfCW->sum('quantity');
            $cfPiecesSurplus = $cfLot ? $cfLot->getPackagedPiecesSurplus() : 0;
            $cfCrimpSurplus  = $cfLot ? $cfLot->getPackagedCrimpSurplus() : 0;
            $cfOtherLots = $cfCrimpLots->where('id', '!=', $confirmCrimpLotId);
        @endphp
        <x-ui-modal wire:key="modal-confirm" badge="Paso 5" title="Confirmación de empaque"
            subtitle="Selecciona el lote, registra las pesadas y confirma las cantidades."
            close="closeConfirmModal" maxWidth="7xl">
            <x-slot:context>
                <x-ui-modal.ctx label="Descripción" :value="$cfPart?->description ?? $cfPart?->number ?? '—'" />
                <x-ui-modal.ctx label="Orden (WO + viajero)" :value="$cfWoNum.($cfLot?->lot_number ?? '—')" />
                <x-ui-modal.ctx label="Etiqueta (WO + lote)" :value="$cfWoNum.($cfSel?->crimp_lot_number ?? '—')" />
                <x-ui-modal.ctx label="Cantidad en viajero" :value="number_format($cfLot?->quantity ?? 0)" />
            </x-slot:context>
                        @if ($cfCrimpLots->isEmpty())
                            <x-ui.section>
                                <x-ui.note tone="warn" title="Este viajero no tiene lotes de CRIMP">
                                    Antes de empacar, <strong>Materiales</strong> debe capturar los lotes de CRIMP desde la columna
                                    <strong>Material</strong> de la tabla.
                                </x-ui.note>
                            </x-ui.section>
                        @else

                            {{-- PASO 1 · elegir el lote de CRIMP sobre el que se captura --}}
                            <x-ui.section step="1" title="Selecciona el lote de CRIMP"
                                hint="Todo lo que captures abajo se guarda contra el lote que elijas aquí.">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach ($cfCrimpLots as $cl)
                                        <x-ui.choice wire:key="confirm-crimp-option-{{ $cl->id }}"
                                            tone="info"
                                            :title="'Lote CRIMP '.$cl->crimp_lot_number"
                                            :desc="$cl->lote_fabricante ? 'Lote de fabricante: '.$cl->lote_fabricante : null"
                                            :meta="number_format($cl->quantity).' pz'"
                                            :selected="$confirmCrimpLotId == $cl->id"
                                            wire:click="$set('confirmCrimpLotId', {{ $cl->id }})" />
                                    @endforeach
                                </div>

                                @error('confirmCrimpLotId')
                                    <x-ui.note tone="danger" class="mt-3">{{ $message }}</x-ui.note>
                                @enderror

                                @if ($cfOtherLots->isNotEmpty())
                                    <x-ui.note tone="muted" class="mt-3" title="El viajero viene dividido">
                                        Este lote contiene {{ number_format($cfSel?->quantity ?? 0) }} pz y
                                        existe{{ $cfOtherLots->count() > 1 ? 'n otros lotes' : ' otro lote' }} por
                                        {{ $cfOtherLots->map(fn ($l) => number_format($l->quantity))->join(', ') }} pz.
                                    </x-ui.note>
                                @endif
                            </x-ui.section>

                            {{-- PASO 2 · capturar pesadas --}}
                            <x-ui.section step="2" title="Registra las pesadas"
                                hint="Captura por separado las piezas «manguitas» y las piezas CRIMP. Puedes agregar varias pesadas.">
                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                                    @foreach ([
                                        [
                                            'titulo'   => 'Piezas «manguitas»',
                                            'meta'     => null,
                                            'filas'    => $cfPW,
                                            'total'    => $cfPiecesTotal,
                                            'editId'   => $editPieceWId,
                                            'editProp' => 'editPieceWQty',
                                            'saveFn'   => 'saveConfirmPieceWeighing',
                                            'cancelFn' => 'cancelEditPieceWeighing',
                                            'editFn'   => 'editConfirmPieceWeighing',
                                            'delFn'    => 'deleteConfirmPieceWeighing',
                                            'addFn'    => 'addConfirmPieceWeighing',
                                            'addProp'  => 'cPieceQty',
                                            'numClass' => 'text-green-700 dark:text-green-400',
                                            'btn'      => 'success',
                                            'key'      => 'pw',
                                        ],
                                        [
                                            'titulo'   => 'Piezas CRIMP',
                                            'meta'     => 'objetivo: '.number_format($cfSel?->quantity ?? 0),
                                            'filas'    => $cfCW,
                                            'total'    => $cfCrimpTotal,
                                            'editId'   => $editCrimpWId,
                                            'editProp' => 'editCrimpWQty',
                                            'saveFn'   => 'saveConfirmCrimpWeighing',
                                            'cancelFn' => 'cancelEditCrimpWeighing',
                                            'editFn'   => 'editConfirmCrimpWeighing',
                                            'delFn'    => 'deleteConfirmCrimpWeighing',
                                            'addFn'    => 'addConfirmCrimpWeighing',
                                            'addProp'  => 'cCrimpQty',
                                            'numClass' => 'text-cyan-700 dark:text-cyan-400',
                                            'btn'      => 'accent',
                                            'key'      => 'cw',
                                        ],
                                    ] as $t)
                                        <div wire:key="cf-table-{{ $t['key'] }}" class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                                            <div class="flex items-baseline justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/50">
                                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ $t['titulo'] }}</span>
                                                @if ($t['meta'])
                                                    <span class="text-[11px] text-slate-400">{{ $t['meta'] }}</span>
                                                @endif
                                            </div>

                                            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                                                @forelse ($t['filas'] as $w)
                                                    <li wire:key="cf-{{ $t['key'] }}-{{ $w->id }}" class="flex items-center gap-2 px-3 py-2">
                                                        @if ($t['editId'] === $w->id)
                                                            <input type="number" min="1" wire:model="{{ $t['editProp'] }}"
                                                                aria-label="Corregir cantidad"
                                                                class="w-full text-right font-bold tabular-nums">
                                                            <x-ui.icon-btn tone="success" label="Guardar cambio" wire:click="{{ $t['saveFn'] }}">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                            </x-ui.icon-btn>
                                                            <x-ui.icon-btn tone="neutral" label="Cancelar edición" wire:click="{{ $t['cancelFn'] }}">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </x-ui.icon-btn>
                                                        @else
                                                            <span class="flex-1 text-right text-sm font-bold tabular-nums {{ $t['numClass'] }}">{{ number_format($w->quantity) }}</span>
                                                            <x-ui.icon-btn tone="primary" label="Editar pesada" wire:click="{{ $t['editFn'] }}({{ $w->id }})">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.1 2.1 0 113 2.97L8.4 18H4v-4.4L16.862 3.487z"/></svg>
                                                            </x-ui.icon-btn>
                                                            <x-ui.icon-btn tone="danger" label="Eliminar pesada"
                                                                wire:click="{{ $t['delFn'] }}({{ $w->id }})" wire:confirm="¿Eliminar esta pesada?">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16m-5 0V4H9v3"/></svg>
                                                            </x-ui.icon-btn>
                                                        @endif
                                                    </li>
                                                @empty
                                                    <li class="px-3 py-4 text-center text-xs text-slate-400">Todavía no hay pesadas</li>
                                                @endforelse
                                            </ul>

                                            {{-- Alta rápida --}}
                                            <div class="border-t border-slate-200 bg-slate-50 px-3 py-3 dark:border-slate-700 dark:bg-slate-900/40">
                                                <div class="flex gap-2">
                                                    <input type="number" min="1" wire:model="{{ $t['addProp'] }}" placeholder="0"
                                                        aria-label="Cantidad de {{ $t['titulo'] }}"
                                                        class="min-w-0 flex-1 text-right text-base font-bold tabular-nums">
                                                    <x-ui.btn :variant="$t['btn']" wire:click="{{ $t['addFn'] }}"
                                                        wire:loading.attr="disabled" wire:target="{{ $t['addFn'] }}">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m7-7H5"/></svg>
                                                        Agregar
                                                    </x-ui.btn>
                                                </div>
                                                @error($t['addProp'])
                                                    <p class="mt-1.5 text-[11px] font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="border-t border-slate-200 px-3 py-2 text-right text-sm font-bold dark:border-slate-700">
                                                <span class="text-xs font-normal text-slate-500 dark:text-slate-400">Total capturado:</span>
                                                <span class="ml-1 tabular-nums {{ $t['numClass'] }}">{{ number_format($t['total']) }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </x-ui.section>

                            {{-- PASO 3 · confirmar cantidades --}}
                            <x-ui.section step="3" tone="accent" title="Revisa y confirma las cantidades"
                                hint="Al confirmar, estas cifras quedan fijas y se habilita el paso 6.">
                                <x-ui.stats cols="4">
                                    <x-ui.stat label="Piezas «manguitas»" :value="number_format($cfPiecesTotal)" tone="good" />
                                    <x-ui.stat label="Piezas CRIMP" :value="number_format($cfCrimpTotal)" tone="accent" />
                                    <x-ui.stat label="CRIMP sobrante" :value="number_format($cfCrimpSurplus)" tone="warn"
                                        help="CRIMP del objetivo que aún no se empacaron: quedan disponibles." />
                                    <x-ui.stat label="Piezas sobrantes" :value="number_format($cfPiecesSurplus)" tone="warn"
                                        help="Piezas buenas que NO se empacaron. Existen físicamente y Empaque debe entregarlas." />
                                </x-ui.stats>

                                <x-ui.btn class="mt-4" :variant="$confirmDone ? 'success' : 'primary'" block
                                    wire:click="confirmPackaging" wire:loading.attr="disabled" wire:target="confirmPackaging"
                                    :disabled="$confirmDone">
                                    @if ($confirmDone)
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        Cantidades confirmadas
                                    @else
                                        Confirmar cantidades
                                    @endif
                                </x-ui.btn>
                            </x-ui.section>

                            {{-- PASO 4 · resumen generado --}}
                            @if ($confirmDone)
                                <x-ui.section step="4" title="Resumen de empaque terminado"
                                    hint="Es lo que verán Empaque y Materiales. Ya no se descarga como PDF.">
                                    @php
                                        $cfRows = [
                                            ['Destinatarios', 'Empaque + Materiales'],
                                            ['Descripción', $cfPart?->description ?? $cfPart?->number ?? '—'],
                                            ['No. Order (WO + Viajero)', $cfWoNum.($cfLot?->lot_number ?? '—')],
                                            ['Número de orden en etiquetas (WO + Lote de CRIMP)', $cfWoNum.($cfSel?->crimp_lot_number ?? '—')],
                                            ['Cantidad en el viajero', number_format($cfLot?->quantity ?? 0)],
                                            ['División del lote', $cfOtherLots->isNotEmpty()
                                                ? 'Este lote contiene '.number_format($cfSel?->quantity ?? 0).' y existe'.($cfOtherLots->count() > 1 ? 'n otros lotes' : ' otro lote').' por '.$cfOtherLots->map(fn ($l) => number_format($l->quantity))->join(', ')
                                                : 'Lote único'],
                                            ['Cantidad completa de piezas (manguitas)', number_format($cfPiecesTotal)],
                                            ['Cantidad completa de CRIMP', number_format($cfCrimpTotal)],
                                            ['CRIMP sobrante', number_format($cfCrimpSurplus)],
                                            ['Piezas / manguitas sobrantes', number_format($cfPiecesSurplus)],
                                            ['Empacado por', auth()->user()?->name ?? '—'],
                                            ['Fecha', now()->format('Y-m-d')],
                                            ['Comentarios', $confirmComments ?: '—'],
                                        ];
                                    @endphp
                                    <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                        @foreach ($cfRows as [$k, $v])
                                            <x-ui.kv :label="$k" :value="$v" />
                                        @endforeach
                                    </dl>

                                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <x-ui.field label="No. de etiquetas" optional
                                            hint="Cuántas etiquetas se imprimieron para este empaque."
                                            :error="$errors->first('confirmLabelCount')">
                                            <input type="number" min="0" wire:model="confirmLabelCount" placeholder="—"
                                                class="w-full text-right font-bold tabular-nums">
                                        </x-ui.field>
                                        <x-ui.field label="Comentarios" optional
                                            hint="Se incluyen en la notificación a Empaque y Materiales.">
                                            <input type="text" wire:model="confirmComments" class="w-full"
                                                placeholder="Observaciones...">
                                        </x-ui.field>
                                    </div>
                                </x-ui.section>
                            @endif
                        @endif

            <x-slot:note>
                @if ($confirmDone)
                    Las cantidades ya están confirmadas. Continúa al <strong>Paso 6</strong> para que Materiales tome la decisión.
                @else
                    Primero confirma las cantidades del paso 3; hasta entonces se habilita el paso 6.
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn variant="secondary" wire:click="closeConfirmModal">Cerrar</x-ui.btn>
                @if ($confirmDone)
                    <x-ui.btn variant="success" wire:click="confirmAndNotifyFromModal"
                        wire:loading.attr="disabled" wire:target="confirmAndNotifyFromModal">
                        {{ $cfLot?->packaging_notified_at ? 'Reenviar notificación' : 'Confirmar y notificar' }}
                    </x-ui.btn>
                @endif
                <x-ui.btn variant="warning" wire:click="goToDecisionFromConfirm"
                    wire:loading.attr="disabled" wire:target="goToDecisionFromConfirm"
                    :disabled="!$confirmDone"
                    :title="$confirmDone ? null : 'Primero confirma las cantidades en el paso 3'">
                    Continuar al paso 6
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </x-ui.btn>
            </x-slot:footer>
        </x-ui-modal>
    @endif
