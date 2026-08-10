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
        $cfLot = \App\Models\Lot::with([
            'crimpLots.surplusCapturedByUser',
            'workOrder.purchaseOrder.part',
            'qualityWeighings',
            'packagingPieceWeighings',
            'packagingCrimpWeighings',
        ])->find($confirmLotId);
        $cfWO = $cfLot?->workOrder;
        $cfPart = $cfWO?->purchaseOrder?->part;
        $cfWoNum = $cfWO?->purchaseOrder?->wo ?? ($cfWO?->wo_number ?? '—');
        $cfCrimpLots = $cfLot?->crimpLots ?? collect();
        $cfSel = $cfCrimpLots->firstWhere('id', $confirmCrimpLotId);
        $cfPW = $cfLot ? $cfLot->packagingPieceWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
        $cfCW = $cfLot ? $cfLot->packagingCrimpWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
        $cfPiecesTotal = (int) $cfPW->sum('quantity');
        $cfCrimpTotal = (int) $cfCW->sum('quantity');
        $cfPiecesShortfall = $cfLot ? $cfLot->getPiecesShortfallTotal() : 0;
        $cfCrimpShortfall = $cfLot ? $cfLot->getCrimpShortfallTotal() : 0;
        // Totales pesados de TODO el viajero (todos sus lotes de CRIMP) — nivel viajero, coherentes con el Faltante.
        $cfPiecesTotalViajero = $cfLot ? $cfLot->getPackagedPiecesTotal() : 0;
        $cfCrimpTotalViajero = $cfLot ? $cfLot->getPackagedCrimpTotal() : 0;
        $cfOtherLots = $cfCrimpLots->where('id', '!=', $confirmCrimpLotId);

        // Bloqueo de la captura de sobrante (mismo criterio que surplusLockedReason()
        // en el componente): ya facturado / listo para embarque / decisión de cierre.
        $cfSurplusLockedReason = null;
        if ($cfLot) {
            if ($cfLot->isInPackingSlip()) {
                $cfSurplusLockedReason = 'El viajero ya tiene un Packing Slip generado.';
            } elseif ($cfLot->ready_for_shipping) {
                $cfSurplusLockedReason = 'El viajero ya está listo para embarque.';
            } elseif ($cfLot->hasClosureDecision()) {
                $cfSurplusLockedReason = 'Materiales ya tomó la decisión de cierre (Paso 6).';
            }
        }
        $cfSurplusLocked = $cfSurplusLockedReason !== null;

        // Advertencia 1:1 (no bloqueo): manguitas sobrantes vs CRIMP sobrantes del lote.
        $cfSurplusMismatch = $cfSel
            && $cfSel->surplus_pieces !== null
            && $cfSel->surplus_crimps !== null
            && (int) $cfSel->surplus_pieces !== (int) $cfSel->surplus_crimps;

        // Sobrante DECLARADO agregado a nivel viajero (suma de sus lotes de CRIMP).
        // Sólo lectura para el resumen/correo; NO participa en ningún cálculo (RP-01).
        $cfHasDeclaredSurplus = $cfCrimpLots->contains(fn($cl) => $cl->hasSurplusCaptured());
        $cfDeclaredPiecesSurplus = (int) $cfCrimpLots->filter(fn($cl) => $cl->surplus_pieces !== null)->sum('surplus_pieces');
        $cfDeclaredCrimpSurplus = (int) $cfCrimpLots->filter(fn($cl) => $cl->surplus_crimps !== null)->sum('surplus_crimps');
    @endphp
    <x-ui-modal wire:key="modal-confirm" badge="Paso 5" title="Confirmación de empaque"
        subtitle="Selecciona el lote, registra las pesadas y confirma las cantidades." close="closeConfirmModal"
        maxWidth="7xl">
        <x-slot:context>
            <x-ui-modal.ctx label="Descripción" :value="$cfPart?->description ?? ($cfPart?->number ?? '—')" />
            <x-ui-modal.ctx label="Orden (WO + viajero)" :value="$cfWoNum . ($cfLot?->lot_number ?? '—')" />
            <x-ui-modal.ctx label="Etiqueta (WO + lote)" :value="$cfWoNum . ($cfSel?->crimp_lot_number ?? '—')" />
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
                        <x-ui.choice wire:key="confirm-crimp-option-{{ $cl->id }}" tone="info"
                            :title="'Lote CRIMP ' . $cl->crimp_lot_number" :desc="$cl->lote_fabricante ? 'Lote de fabricante: ' . $cl->lote_fabricante : null" :meta="number_format($cl->quantity) . ' pz'" :selected="$confirmCrimpLotId == $cl->id"
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
                        {{ $cfOtherLots->map(fn($l) => number_format($l->quantity))->join(', ') }} pz.
                    </x-ui.note>
                @endif
            </x-ui.section>

            {{-- PASO 2 · capturar pesadas --}}
            <x-ui.section step="2" title="Registra las pesadas"
                hint="Captura por separado las piezas «manguitas» y las piezas CRIMP. Puedes agregar varias pesadas.">
                @if ($cfSurplusLocked)
                    <x-ui.note tone="muted" class="mb-3" title="La captura de sobrante está bloqueada">
                        {{ $cfSurplusLockedReason }} Las pesadas y el sobrante ya no pueden modificarse.
                    </x-ui.note>
                @endif
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                    @foreach ([
        [
            'titulo' => 'Piezas «manguitas»',
            'meta' => null,
            'filas' => $cfPW,
            'total' => $cfPiecesTotal,
            'editId' => $editPieceWId,
            'editProp' => 'editPieceWQty',
            'saveFn' => 'saveConfirmPieceWeighing',
            'cancelFn' => 'cancelEditPieceWeighing',
            'editFn' => 'editConfirmPieceWeighing',
            'delFn' => 'deleteConfirmPieceWeighing',
            'addFn' => 'addConfirmPieceWeighing',
            'addProp' => 'cPieceQty',
            'numClass' => 'text-green-700 dark:text-green-400',
            'btn' => 'success',
            'key' => 'pw',
            'surplusProp' => 'cPieceSurplus',
            'surplusSave' => 'saveConfirmPieceSurplus',
            'surplusLabel' => 'Manguitas sobrantes',
            'surplusValue' => $cfSel?->surplus_pieces,
        ],
        [
            'titulo' => 'Piezas CRIMP',
            'meta' => 'objetivo: ' . number_format($cfSel?->quantity ?? 0),
            'filas' => $cfCW,
            'total' => $cfCrimpTotal,
            'editId' => $editCrimpWId,
            'editProp' => 'editCrimpWQty',
            'saveFn' => 'saveConfirmCrimpWeighing',
            'cancelFn' => 'cancelEditCrimpWeighing',
            'editFn' => 'editConfirmCrimpWeighing',
            'delFn' => 'deleteConfirmCrimpWeighing',
            'addFn' => 'addConfirmCrimpWeighing',
            'addProp' => 'cCrimpQty',
            'numClass' => 'text-cyan-700 dark:text-cyan-400',
            'btn' => 'accent',
            'key' => 'cw',
            'surplusProp' => 'cCrimpSurplus',
            'surplusSave' => 'saveConfirmCrimpSurplus',
            'surplusLabel' => 'CRIMP sobrantes',
            'surplusValue' => $cfSel?->surplus_crimps,
        ],
    ] as $t)
                        <div wire:key="cf-table-{{ $t['key'] }}"
                            class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                            <div
                                class="flex items-baseline justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/50">
                                <span
                                    class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ $t['titulo'] }}</span>
                                @if ($t['meta'])
                                    <span class="text-[11px] text-slate-400">{{ $t['meta'] }}</span>
                                @endif
                            </div>

                            <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                                @forelse ($t['filas'] as $w)
                                    <li wire:key="cf-{{ $t['key'] }}-{{ $w->id }}"
                                        class="flex items-center gap-2 px-3 py-2">
                                        @if ($t['editId'] === $w->id)
                                            <input type="number" min="1" wire:model="{{ $t['editProp'] }}"
                                                aria-label="Corregir cantidad"
                                                class="w-full text-right font-bold tabular-nums">
                                            <x-ui.icon-btn tone="success" label="Guardar cambio"
                                                wire:click="{{ $t['saveFn'] }}">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </x-ui.icon-btn>
                                            <x-ui.icon-btn tone="neutral" label="Cancelar edición"
                                                wire:click="{{ $t['cancelFn'] }}">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </x-ui.icon-btn>
                                        @else
                                            <span
                                                class="flex-1 text-right text-sm font-bold tabular-nums {{ $t['numClass'] }}">{{ number_format($w->quantity) }}</span>
                                            <x-ui.icon-btn tone="primary" label="Editar pesada"
                                                wire:click="{{ $t['editFn'] }}({{ $w->id }})">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M16.862 3.487a2.1 2.1 0 113 2.97L8.4 18H4v-4.4L16.862 3.487z" />
                                                </svg>
                                            </x-ui.icon-btn>
                                            <x-ui.icon-btn tone="danger" label="Eliminar pesada"
                                                wire:click="{{ $t['delFn'] }}({{ $w->id }})"
                                                wire:confirm="¿Eliminar esta pesada?">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16m-5 0V4H9v3" />
                                                </svg>
                                            </x-ui.icon-btn>
                                        @endif
                                    </li>
                                @empty
                                    <li class="px-3 py-4 text-center text-xs text-slate-400">Todavía no hay pesadas</li>
                                @endforelse
                            </ul>

                            {{-- Error de la edición inline (p.ej. tope del lote de CRIMP superado) --}}
                            @error($t['editProp'])
                                <p class="border-t border-slate-200 px-3 pt-2 text-[11px] font-medium text-red-600 dark:border-slate-700 dark:text-red-400">
                                    {{ $message }}</p>
                            @enderror

                            {{-- Alta rápida --}}
                            <div
                                class="border-t border-slate-200 bg-slate-50 px-3 py-3 dark:border-slate-700 dark:bg-slate-900/40">
                                <div class="flex gap-2">
                                    <input type="number" min="1" wire:model="{{ $t['addProp'] }}"
                                        placeholder="0" aria-label="Cantidad de {{ $t['titulo'] }}"
                                        class="min-w-0 flex-1 text-right text-base font-bold tabular-nums">
                                    <x-ui.btn :variant="$t['btn']" wire:click="{{ $t['addFn'] }}"
                                        wire:loading.attr="disabled" wire:target="{{ $t['addFn'] }}">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                d="M12 5v14m7-7H5" />
                                        </svg>
                                        Agregar
                                    </x-ui.btn>
                                </div>
                                @error($t['addProp'])
                                    <p class="mt-1.5 text-[11px] font-medium text-red-600 dark:text-red-400">
                                        {{ $message }}</p>
                                @enderror
                            </div>

                            <div
                                class="border-t border-slate-200 px-3 py-2 text-right text-sm font-bold dark:border-slate-700">
                                <span class="text-xs font-normal text-slate-500 dark:text-slate-400">Total
                                    capturado:</span>
                                <span
                                    class="ml-1 tabular-nums {{ $t['numClass'] }}">{{ number_format($t['total']) }}</span>
                            </div>

                            {{-- Sobrante DECLARADO — captura manual por lote de CRIMP (Interpretación A).
                                 wire:key con $confirmCrimpLotId: al cambiar de lote Livewire recrea el
                                 nodo y no arrastra el valor del lote anterior (R-02). --}}
                            <div wire:key="cf-surplus-{{ $t['key'] }}-{{ $confirmCrimpLotId }}"
                                class="border-t border-slate-200 bg-amber-50/60 px-3 py-3 dark:border-slate-700 dark:bg-amber-900/10">
                                <x-ui.field :label="$t['surplusLabel']" optional
                                    hint="Piezas buenas que sobraron de ESTE lote de CRIMP y se devuelven a Materiales. Usa 0 si no sobró nada."
                                    :error="$errors->first($t['surplusProp'])">
                                    <div class="flex gap-2">
                                        <input type="number" min="0" step="1" wire:model="{{ $t['surplusProp'] }}"
                                            placeholder="—" aria-label="{{ $t['surplusLabel'] }}"
                                            @disabled($cfSurplusLocked || !$confirmCrimpLotId)
                                            class="min-w-0 flex-1 text-right text-base font-bold tabular-nums">
                                        <x-ui.btn variant="warning" wire:click="{{ $t['surplusSave'] }}"
                                            wire:loading.attr="disabled" wire:target="{{ $t['surplusSave'] }}"
                                            :disabled="$cfSurplusLocked || !$confirmCrimpLotId">
                                            Guardar
                                        </x-ui.btn>
                                    </div>
                                </x-ui.field>

                                @if (!is_null($t['surplusValue']))
                                    <p class="mt-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        Guardado: <span class="font-semibold tabular-nums">{{ number_format($t['surplusValue']) }}</span>
                                        @if ($cfSel?->surplus_captured_at)
                                            · {{ \Carbon\Carbon::parse($cfSel->surplus_captured_at)->format('d/m/Y H:i') }}{{ $cfSel->surplusCapturedByUser?->name ? ' por ' . $cfSel->surplusCapturedByUser->name : '' }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($cfSurplusMismatch)
                    <x-ui.note tone="warn" class="mt-3" title="Los sobrantes no coinciden">
                        El sobrante de manguitas ({{ number_format((int) $cfSel->surplus_pieces) }}) y el de CRIMP
                        ({{ number_format((int) $cfSel->surplus_crimps) }}) son distintos. Normalmente van 1:1;
                        verifica que sea correcto.
                    </x-ui.note>
                @endif
            </x-ui.section>

            {{-- PASO 3 · confirmar cantidades --}}
            <x-ui.section step="3" tone="accent" title="Revisa y confirma las cantidades"
                hint="Al confirmar, estas cifras quedan fijas y se habilita el paso 6.">
                <x-ui.stats cols="4">
                    <x-ui.stat label="Piezas «manguitas»" :value="number_format($cfPiecesTotalViajero)" tone="good"
                        help="Total de manguitas pesadas en TODO el viajero (todos sus lotes de CRIMP)." />
                    <x-ui.stat label="Piezas CRIMP" :value="number_format($cfCrimpTotalViajero)" tone="accent"
                        help="Total de CRIMP pesados en TODO el viajero (todos sus lotes de CRIMP)." />
                    <x-ui.stat label="Faltante CRIMP" :value="number_format($cfCrimpShortfall)" tone="warn"
                        help="CRIMP que faltan por completar respecto al objetivo de TODO el viajero (suma de sus lotes de CRIMP)." />
                    <x-ui.stat label="Faltante «manguitas»" :value="number_format($cfPiecesShortfall)" tone="warn"
                        help="Manguitas que faltan por completar respecto al objetivo de TODO el viajero (suma de sus lotes de CRIMP)." />
                </x-ui.stats>

                <x-ui.btn class="mt-4" :variant="$confirmDone ? 'success' : 'primary'" block wire:click="confirmPackaging"
                    wire:loading.attr="disabled" wire:target="confirmPackaging" :disabled="$confirmDone">
                    @if ($confirmDone)
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M5 13l4 4L19 7" />
                        </svg>
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
                            ['Descripción', $cfPart?->description ?? ($cfPart?->number ?? '—')],
                            ['No. Order (WO + Viajero)', $cfWoNum . ($cfLot?->lot_number ?? '—')],
                            [
                                'Número de orden en etiquetas (WO + Lote de CRIMP)',
                                $cfWoNum . ($cfSel?->crimp_lot_number ?? '—'),
                            ],
                            ['Cantidad en el viajero', number_format($cfLot?->quantity ?? 0)],
                            [
                                'División del lote',
                                $cfOtherLots->isNotEmpty()
                                    ? 'Este lote contiene ' .
                                        number_format($cfSel?->quantity ?? 0) .
                                        ' y existe' .
                                        ($cfOtherLots->count() > 1 ? 'n otros lotes' : ' otro lote') .
                                        ' por ' .
                                        $cfOtherLots->map(fn($l) => number_format($l->quantity))->join(', ')
                                    : 'Lote único',
                            ],
                            ['Cantidad completa de piezas (manguitas)', number_format($cfPiecesTotalViajero)],
                            ['Cantidad completa de CRIMP', number_format($cfCrimpTotalViajero)],
                            ['Faltante total CRIMP', number_format($cfCrimpShortfall)],
                            ['Faltante total manguitas', number_format($cfPiecesShortfall)],
                            [
                                'Sobrante declarado (manguitas / CRIMP)',
                                $cfHasDeclaredSurplus
                                    ? number_format($cfDeclaredPiecesSurplus) . ' / ' . number_format($cfDeclaredCrimpSurplus)
                                    : 'Sin capturar',
                            ],
                            ['Empacado por', auth()->user()?->name ?? '—'],
                            ['Fecha', now()->format('Y-m-d')],
                            ['Comentarios', $confirmComments ?: '—'],
                        ];
                    @endphp
                    <dl
                        class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                        @foreach ($cfRows as [$k, $v])
                            <x-ui.kv :label="$k" :value="$v" />
                        @endforeach
                    </dl>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.field label="No. de etiquetas" optional
                            hint="Cuántas etiquetas se imprimieron para este empaque." :error="$errors->first('confirmLabelCount')">
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
                Las cantidades ya están confirmadas. Continúa al <strong>Paso 6</strong> para que Materiales tome la
                decisión.
            @else
                Primero confirma las cantidades del paso 3; hasta entonces se habilita el paso 6.
            @endif
        </x-slot:note>
        <x-slot:footer>
            <x-ui.btn variant="secondary" wire:click="closeConfirmModal">Cerrar</x-ui.btn>
            @if ($confirmDone)
                <x-ui.btn variant="success" wire:click="confirmAndNotifyFromModal" wire:loading.attr="disabled"
                    wire:target="confirmAndNotifyFromModal">
                    {{ $cfLot?->packaging_notified_at ? 'Reenviar notificación' : 'Confirmar y notificar' }}
                </x-ui.btn>
            @endif
            <x-ui.btn variant="warning" wire:click="goToDecisionFromConfirm" wire:loading.attr="disabled"
                wire:target="goToDecisionFromConfirm" :disabled="!$confirmDone" :title="$confirmDone ? null : 'Primero confirma las cantidades en el paso 3'">
                Continuar al paso 6
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </x-ui.btn>
        </x-slot:footer>
    </x-ui-modal>
@endif
