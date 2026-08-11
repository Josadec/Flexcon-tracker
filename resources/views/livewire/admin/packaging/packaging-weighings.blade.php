<x-ui.page eyebrow="Empaque" title="Pesadas de empaque (CRIMP)"
    subtitle="Manguitas (piezas) y CRIMP empacados por viajero.">

    @if (session('message'))
        <x-ui.note tone="success">{{ session('message') }}</x-ui.note>
    @endif
    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Resumen --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Viajeros con pesadas" :value="number_format($stats['lots'])" />
        <x-ui.stat label="Manguitas empacadas" :value="number_format($stats['pieces'])" tone="info" />
        <x-ui.stat label="CRIMP empacados" :value="number_format($stats['crimp'])" tone="warn" />
        <x-ui.stat label="Total registros" :value="number_format($stats['records'])" />
    </x-ui.stats>

    {{-- Filtros --}}
    <x-ui.section title="Buscar" hint="Filtra por WO, viajero o parte.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,2fr)_9rem]">
            <x-ui.field label="Texto a buscar">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="WO, viajero o parte..."
                        class="w-full pl-10">
                </div>
            </x-ui.field>

            <x-ui.field label="Por página">
                <select wire:model.live="perPage" class="w-full">
                    @foreach ([15, 25, 50] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>
    </x-ui.section>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            WO</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Viajero</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Parte</th>
                        <th
                            class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Obj. CRIMP</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-sky-600 dark:text-sky-400 uppercase">
                            Manguitas</th>
                        <th
                            class="px-6 py-3 text-right text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase">
                            CRIMP</th>
                        <th
                            class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            # Pesadas</th>
                        <th
                            class="px-6 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($lots as $lot)
                        @php
                            $po = $lot->workOrder->purchaseOrder ?? null;
                            $part = $po->part ?? null;
                            $pieces = $lot->getPackagedPiecesTotal();
                            $crimp = $lot->getPackagedCrimpTotal();
                            $target = $lot->getCrimpTargetTotal();
                            $count = $lot->packagingPieceWeighings->count() + $lot->packagingCrimpWeighings->count();
                        @endphp
                        <tr wire:key="pkg-lot-{{ $lot->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 text-blue-600 dark:text-blue-400 font-medium">{{ $po->wo ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">{{ $lot->lot_number }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $part->number ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">
                                {{ number_format($target) }}</td>
                            <td class="px-6 py-4 text-right font-medium text-sky-600 dark:text-sky-400">
                                {{ number_format($pieces) }}</td>
                            <td class="px-6 py-4 text-right font-medium text-amber-600 dark:text-amber-400">
                                {{ number_format($crimp) }}</td>
                            <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">
                                {{ number_format($count) }}</td>
                            <td class="px-6 py-4 text-center">
                                <button wire:click="openDetailModal({{ $lot->id }})"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-900/20 border-2 border-teal-200 dark:border-teal-800 rounded-md hover:bg-teal-100 dark:hover:bg-teal-900/40 transition-colors">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Ver Detalle
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                                <p class="mt-4 text-base font-medium text-gray-900 dark:text-white">No hay pesadas de
                                    Empaque registradas</p>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Las pesadas aparecerán aquí
                                    cuando Empaque confirme manguitas o CRIMP.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($lots->hasPages())
            <x-slot:foot>{{ $lots->links() }}</x-slot:foot>
        @endif
        </x-ui.table>

        {{-- Detalle del viajero --}}
        @if ($showDetailModal && $selectedLot)
            <x-ui-modal wire:key="modal-packaging-detail" title="Detalle de pesadas — viajero"
                subtitle="Manguitas y CRIMP registrados para este viajero." close="closeDetailModal" maxWidth="4xl">

                <x-slot:context>
                    <x-ui-modal.ctx label="WO" :value="$selectedLot->workOrder->purchaseOrder->wo ?? 'N/A'" />
                    <x-ui-modal.ctx label="Viajero" :value="$selectedLot->lot_number" />
                    <x-ui-modal.ctx label="Parte" :value="$selectedLot->workOrder->purchaseOrder->part->number ?? 'N/A'" />
                    <x-ui-modal.ctx label="Obj. CRIMP" :value="number_format($crimpTarget)" />
                </x-slot:context>

                <x-ui.stats cols="3">
                    <x-ui.stat label="Obj. CRIMP" :value="number_format($crimpTarget)" />
                    <x-ui.stat label="Manguitas empacadas" :value="number_format($pieceTotal)" tone="info" />
                    <x-ui.stat label="CRIMP empacados" :value="number_format($crimpTotal)" tone="warn" />
                </x-ui.stats>

                <x-ui.section title="Pesadas de manguitas"
                    hint="{{ count($pieceWeighings) }} {{ Str::plural('pesada', count($pieceWeighings)) }} registrada{{ count($pieceWeighings) === 1 ? '' : 's' }}.">
                    @include('livewire.admin.packaging.partials.weighing-table', [
                        'rows' => $pieceWeighings,
                        'type' => 'piece',
                        'accentText' => 'text-sky-700 dark:text-sky-400',
                        'total' => $pieceTotal,
                    ])
                </x-ui.section>

                <x-ui.section title="Pesadas de CRIMP"
                    hint="{{ count($crimpWeighings) }} {{ Str::plural('pesada', count($crimpWeighings)) }} registrada{{ count($crimpWeighings) === 1 ? '' : 's' }}.">
                    @include('livewire.admin.packaging.partials.weighing-table', [
                        'rows' => $crimpWeighings,
                        'type' => 'crimp',
                        'accentText' => 'text-amber-700 dark:text-amber-400',
                        'total' => $crimpTotal,
                    ])
                </x-ui.section>

                <x-slot:footer>
                    <x-ui.btn variant="secondary" wire:click="closeDetailModal">Cerrar</x-ui.btn>
                </x-slot:footer>
            </x-ui-modal>
        @endif

        {{-- Edición de una pesada --}}
        @if ($showEditModal)
            <x-ui-modal wire:key="modal-packaging-edit" :badge="$editType === 'crimp' ? 'CRIMP' : 'Manguitas'" :title="'Editar pesada de '.($editType === 'crimp' ? 'CRIMP' : 'manguitas')" close="closeEditModal"
                maxWidth="lg">

                <x-ui.section title="Datos de la pesada">
                    <div class="space-y-4">
                        <x-ui.field label="Lote de CRIMP" optional>
                            <select wire:model="editCrimpLotId" class="w-full">
                                <option value="">— Sin lote —</option>
                                @foreach ($selectedLot?->crimpLots ?? [] as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->crimp_lot_number }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.field label="Cantidad" required :error="$errors->first('editQuantity')">
                                <input wire:model="editQuantity" type="number" min="1" class="w-full"
                                    placeholder="0">
                            </x-ui.field>
                            <x-ui.field label="Peso (kg)" optional :error="$errors->first('editWeight')">
                                <input wire:model="editWeight" type="number" step="0.001" min="0"
                                    class="w-full" placeholder="opcional">
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Fecha y hora" required :error="$errors->first('editWeighedAt')">
                            <input wire:model="editWeighedAt" type="datetime-local" class="w-full">
                        </x-ui.field>

                        <x-ui.field label="Comentarios" optional>
                            <textarea wire:model="editComments" rows="2" class="w-full" placeholder="Observaciones (opcional)..."></textarea>
                        </x-ui.field>
                    </div>
                </x-ui.section>

                <x-slot:note>Al guardar se recalcularán los totales del viajero.</x-slot:note>
                <x-slot:footer>
                    <x-ui.btn variant="secondary" wire:click="closeEditModal">Cancelar</x-ui.btn>
                    <x-ui.btn variant="primary" wire:click="saveWeighing">Actualizar pesada</x-ui.btn>
                </x-slot:footer>
            </x-ui-modal>
        @endif
</x-ui.page>
