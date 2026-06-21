<div class="space-y-6">
    @if(session('success'))
        <div class="p-3 rounded-md bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-3 rounded-md bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.work-orders.index') }}" class="inline-flex items-center justify-center w-10 h-10 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-md transition-colors" title="Volver">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">WO: {{ $workOrder->purchaseOrder?->wo ?? $workOrder->wo_number }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $workOrder->purchaseOrder?->part?->number ?? '' }} — {{ Str::limit($workOrder->purchaseOrder?->part?->description ?? '', 50) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.work-orders.edit', $workOrder) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors">Editar</a>
            <a href="{{ route('admin.work-orders.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 rounded-md transition-colors">Volver</a>
        </div>
    </div>

    @php
        $progress = $workOrder->original_quantity > 0 ? round(($workOrder->sent_pieces / $workOrder->original_quantity) * 100, 1) : 0;
        $lotsCount = $workOrder->lots->count();
        $crimpLotsCount = $workOrder->lots->sum(fn($l) => $l->crimpLots->count());
        $totalWeighings = $workOrder->lots->sum(fn($l) => $l->weighings->count());
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Estado</p>
                <span class="mt-1 inline-block px-2 py-0.5 text-xs font-semibold rounded-full text-white" style="background-color: {{ $workOrder->status?->color ?? '#6b7280' }}">
                    {{ $workOrder->status?->name ?? 'Sin estado' }}
                </span>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Cantidad</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($workOrder->original_quantity) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Lotes</p>
                <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $lotsCount }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Lotes de CRIMP</p>
                <p class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $crimpLotsCount }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Pesadas</p>
                <p class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $totalWeighings }}</p>
            </div>
        </div>

        {{-- Tabs Navigation --}}
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
            <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                @foreach([
                    'general' => ['label' => 'General', 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'lots' => ['label' => 'Lotes (' . $lotsCount . ')', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                ] as $tab => $info)
                    <button wire:click="setTab('{{ $tab }}')"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-1.5
                        {{ $activeTab === $tab
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $info['icon'] }}"/></svg>
                        {{ $info['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- ============================================ --}}
        {{-- TAB: GENERAL --}}
        {{-- ============================================ --}}
        @if($activeTab === 'general')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Información General</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID (Interno)</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">{{ $workOrder->wo_number }}</dd>
                    </div>
                    @if($workOrder->purchaseOrder?->wo)
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 p-3 rounded-lg -m-1">
                        <dt class="text-sm font-medium text-indigo-600 dark:text-indigo-400">WO (Cliente)</dt>
                        <dd class="mt-1 text-xl text-indigo-700 dark:text-indigo-300 font-bold">{{ $workOrder->purchaseOrder->wo }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full text-white" style="background-color: {{ $workOrder->status?->color ?? '#6b7280' }}">
                                {{ $workOrder->status?->name ?? 'Sin estado' }}
                            </span>
                        </dd>
                    </div>
                    @if($workOrder->purchaseOrder)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Purchase Order</dt>
                        <dd class="mt-1 text-sm"><a href="{{ route('admin.purchase-orders.show', $workOrder->purchaseOrder) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">{{ $workOrder->purchaseOrder->po_number }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Parte</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $workOrder->purchaseOrder->part->number ?? 'N/A' }} @if($workOrder->purchaseOrder->part)<span class="text-gray-500 dark:text-gray-400">- {{ $workOrder->purchaseOrder->part->description }}</span>@endif</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Cantidad Original</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">{{ number_format($workOrder->original_quantity) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Piezas Enviadas</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">{{ number_format($workOrder->sent_pieces) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Cantidad Pendiente</dt>
                        <dd class="mt-1 text-sm {{ $workOrder->pending_quantity > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }} font-semibold">{{ number_format($workOrder->pending_quantity) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Apertura</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $workOrder->opened_date?->format('d/m/Y') ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha Prog. Envío</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $workOrder->scheduled_send_date?->format('d/m/Y') ?? 'No definida' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha Real Envío</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $workOrder->actual_send_date?->format('d/m/Y') ?? 'No enviado' }}</dd>
                    </div>
                    @if($workOrder->eq)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Equipo (EQ)</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $workOrder->eq }}</dd>
                    </div>
                    @endif
                    @if($workOrder->pr)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Personal (PR)</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $workOrder->pr }}</dd>
                    </div>
                    @endif
                </dl>
                @if($workOrder->comments)
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Comentarios</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $workOrder->comments }}</dd>
                </div>
                @endif
            </div>

            <div class="space-y-6">
                {{-- Cambiar Estado --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Cambiar Estado</h2>
                    <div class="flex flex-col gap-2">
                        @foreach($statuses as $status)
                            @if($status->id === $workOrder->status_id)
                                <div class="flex items-center gap-2 px-3 py-2 rounded-md border-2" style="border-color: {{ $status->color }}">
                                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $status->color }}"></span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $status->name }}</span>
                                    <span class="ml-auto text-xs font-medium text-gray-400 dark:text-gray-500">Actual</span>
                                </div>
                            @else
                                <button type="button" wire:click="updateStatus({{ $status->id }})"
                                    wire:confirm="¿Cambiar el estado del WO a '{{ $status->name }}'?"
                                    class="flex items-center gap-2 px-3 py-2 rounded-md border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left cursor-pointer">
                                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $status->color }}"></span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $status->name }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                        Al marcar <span class="font-medium">Completed</span> el WO desaparece de la Lista de Envío.
                        Reábrelo con <span class="font-medium">Open</span> para que vuelva a aparecer en Capacidad y en la Lista de Envío.
                    </p>
                </div>
                {{-- Progress --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Progreso</h2>
                    <div class="mb-2 flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Completado</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $progress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700">
                        <div class="h-3 rounded-full {{ $progress >= 100 ? 'bg-green-500' : 'bg-blue-500' }}" style="width: {{ min($progress, 100) }}%"></div>
                    </div>
                    <div class="mt-4 text-center">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($workOrder->sent_pieces) }} / {{ number_format($workOrder->original_quantity) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">piezas enviadas</p>
                    </div>
                </div>
                {{-- Status Log --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Historial de Estados</h2>
                    @if($workOrder->statusLogs->count() > 0)
                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            @foreach($workOrder->statusLogs->sortByDesc('created_at')->take(5) as $log)
                            <li>
                                <div class="relative pb-6">
                                    @if(!$loop->last)<span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>@endif
                                    <div class="relative flex space-x-3">
                                        <div><span class="h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white dark:ring-gray-800" style="background-color: {{ $log->toStatus->color ?? '#6B7280' }}"><svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span></div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    @if($log->fromStatus)<span class="font-medium" style="color: {{ $log->fromStatus->color }}">{{ $log->fromStatus->name }}</span> → @else Creado como @endif
                                                    <span class="font-medium" style="color: {{ $log->toStatus->color }}">{{ $log->toStatus->name }}</span>
                                                    @if($log->user) por <span class="font-medium text-gray-900 dark:text-white">{{ $log->user->name }}</span>@endif
                                                </p>
                                            </div>
                                            <div class="whitespace-nowrap text-right text-xs text-gray-500 dark:text-gray-400">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin cambios de estado.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Signatures - DESHABILITADO TEMPORALMENTE --}}
        {{--
        @if($workOrder->purchaseOrder?->pdf_path)
        <div class="mt-6 bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Firmas del Documento</h2>
                <button wire:click="openSignatureModal" class="inline-flex items-center px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    Firmar
                </button>
            </div>
            @if($signatures && (is_countable($signatures) ? count($signatures) : $signatures->count()) > 0)
                <div class="space-y-3">
                    @foreach($signatures as $signature)
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <img src="{{ $signature->signature_url }}" alt="Firma" class="h-12 w-20 object-contain border rounded bg-white">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $signature->user->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $signature->signed_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Firmado</span>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Este documento aún no ha sido firmado.</p>
            @endif
        </div>
        @endif
        --}}

        {{-- Documento Firmado (Upload) --}}
        @if($workOrder->purchaseOrder)
        <div class="mt-6 bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Documento Firmado</h2>
            </div>

            @if($workOrder->purchaseOrder->signed_document_path)
                <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg class="w-8 h-8 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ basename($workOrder->purchaseOrder->signed_document_path) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Documento firmado cargado</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ Storage::url($workOrder->purchaseOrder->signed_document_path) }}" target="_blank"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-sm font-medium rounded-lg transition-colors">
                            Ver
                        </a>
                        <a href="{{ Storage::url($workOrder->purchaseOrder->signed_document_path) }}" download
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-green-100 hover:bg-green-200 dark:bg-green-900/30 dark:hover:bg-green-900/50 text-green-700 dark:text-green-300 text-sm font-medium rounded-lg transition-colors">
                            Descargar
                        </a>
                        <button type="button" wire:click="deleteSignedDocument" wire:confirm="¿Eliminar el documento firmado?"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 text-sm font-medium rounded-lg transition-colors">
                            Eliminar
                        </button>
                    </div>
                </div>
            @endif

            <div>
                <label for="signedDocument" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {{ $workOrder->purchaseOrder->signed_document_path ? 'Reemplazar documento firmado' : 'Subir documento firmado' }}
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input wire:model="signedDocument" id="signedDocument" type="file" accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white" />
                    <button type="button" wire:click="uploadSignedDocument"
                        wire:loading.attr="disabled"
                        wire:target="signedDocument,uploadSignedDocument"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                        <span wire:loading.remove wire:target="signedDocument,uploadSignedDocument">Subir</span>
                        <span wire:loading wire:target="signedDocument,uploadSignedDocument">Subiendo...</span>
                    </button>
                </div>
                @error('signedDocument') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Máximo 10MB. Formatos: PDF, JPG, PNG.</p>
            </div>
        </div>
        @endif
        @endif

        {{-- ============================================ --}}
        {{-- TAB: LOTES --}}
        {{-- ============================================ --}}
        @if($activeTab === 'lots')
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lotes de esta Work Order</h2>
            </div>
            @if($workOrder->lots->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No hay lotes creados. Haz clic en "Agregar Lote" para crear uno.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Lote #</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cant.</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Lotes de CRIMP</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Producción</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Calidad</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Empaque</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avance</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ver</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($workOrder->lots as $lot)
                            @php
                                $lotColor = match($lot->status) {
                                    'pending' => 'zinc', 'in_progress' => 'yellow', 'completed' => 'green', 'cancelled' => 'red', default => 'zinc',
                                };

                                $prodGood  = $lot->getProductionGoodPieces();
                                $prodBad   = $lot->getProductionBadPieces();
                                $prodTotal = $prodGood + $prodBad;
                                $prodPct   = $lot->quantity > 0 ? min(100, (int) round($prodTotal / $lot->quantity * 100)) : 0;

                                $qSem     = $lot->getQualitySemaphoreStatus();
                                $qGood    = $lot->getQualityGoodPieces();
                                $qPending = $lot->getQualityPendingPieces();

                                $pSem     = $lot->getPackagingSemaphoreStatus();
                                $pPacked  = $lot->getPackagingPackedPieces();
                                $pPending = $lot->getPackagingPendingPieces();

                                $progress = $lot->getProgressSummary();

                                $dotHex = [
                                    'gray' => '#d1d5db', 'yellow' => '#fbbf24', 'green' => '#22c55e',
                                    'blue' => '#3b82f6', 'orange' => '#f97316',
                                ];
                                $barHex = [
                                    'zinc' => '#a1a1aa', 'amber' => '#f59e0b', 'cyan' => '#06b6d4',
                                    'blue' => '#3b82f6', 'emerald' => '#10b981', 'green' => '#22c55e', 'red' => '#ef4444',
                                ];

                                $qText = $qSem === 'gray'
                                    ? 'Sin pesadas'
                                    : number_format($qGood) . ' ok' . ($qPending > 0 ? ' · ' . number_format($qPending) . ' pend.' : '');

                                $pText = $pSem === 'gray'
                                    ? 'Sin avance'
                                    : number_format($pPacked) . ' emp.' . ($pPending > 0 ? ' · ' . number_format($pPending) . ' pend.' : '');

                                // Progreso de calidad: piezas verificadas / piezas buenas producidas
                                $qWeighed = $qGood + $lot->getQualityBadPieces();
                                $qBase    = max($prodGood, $qWeighed);
                                $qPct     = $qBase > 0 ? min(100, (int) round($qWeighed / $qBase * 100)) : 0;

                                // Progreso de empaque: piezas empacadas / piezas aprobadas por calidad
                                $pAvail = $lot->getPackagingAvailablePieces();
                                $pBase  = max($pAvail, $pPacked);
                                $pPct   = $pBase > 0 ? min(100, (int) round($pPacked / $pBase * 100)) : 0;

                                $qDot = $dotHex[$qSem] ?? '#d1d5db';
                                $pDot = $dotHex[$pSem] ?? '#d1d5db';
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                {{-- Lote # --}}
                                <td class="px-4 py-4 whitespace-nowrap align-top">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $lot->lot_number }}</div>
                                    @if($lot->description)
                                        <div class="text-xs text-gray-400 truncate max-w-[140px]" title="{{ $lot->description }}">{{ $lot->description }}</div>
                                    @endif
                                </td>

                                {{-- Cantidad --}}
                                <td class="px-4 py-4 text-center text-sm text-gray-900 dark:text-white align-top">{{ number_format($lot->quantity) }}</td>

                                {{-- Estado --}}
                                <td class="px-4 py-4 text-center align-top"><flux:badge :color="$lotColor" size="sm">{{ $lot->status_label }}</flux:badge></td>

                                {{-- Lotes de CRIMP --}}
                                <td class="px-4 py-4 align-top">
                                    @if($lot->crimpLots->isEmpty())
                                        <span class="text-xs text-gray-400">Sin lotes de CRIMP</span>
                                    @else
                                        <div class="flex flex-wrap gap-1 max-w-[210px]">
                                            @foreach($lot->crimpLots as $cl)
                                                <flux:badge color="purple" size="sm">{{ $cl->crimp_lot_number }}</flux:badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- Producción (pesadas) --}}
                                <td class="px-4 py-4 align-top">
                                    <div class="text-xs text-gray-600 dark:text-gray-300 mb-1 whitespace-nowrap">
                                        {{ number_format($prodTotal) }} / {{ number_format($lot->quantity) }} pz
                                    </div>
                                    <div class="w-24 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded-full" style="width: {{ $prodPct }}%; background-color: #f59e0b;"></div>
                                    </div>
                                    @if($prodBad > 0)
                                        <div class="text-[10px] text-red-500 mt-0.5">{{ number_format($prodBad) }} malas</div>
                                    @endif
                                </td>

                                {{-- Calidad --}}
                                <td class="px-4 py-4 align-top">
                                    <div class="flex items-center gap-2 mb-1.5 whitespace-nowrap">
                                        <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background-color: {{ $qDot }}; box-shadow: 0 0 0 3px {{ $qDot }}33;"></span>
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-200">{{ $qText }}</span>
                                    </div>
                                    <div class="w-24 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded-full" style="width: {{ $qPct }}%; background-color: #06b6d4;"></div>
                                    </div>
                                </td>

                                {{-- Empaque --}}
                                <td class="px-4 py-4 align-top">
                                    <div class="flex items-center gap-2 mb-1.5 whitespace-nowrap">
                                        <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background-color: {{ $pDot }}; box-shadow: 0 0 0 3px {{ $pDot }}33;"></span>
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-200">{{ $pText }}</span>
                                    </div>
                                    <div class="w-24 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded-full" style="width: {{ $pPct }}%; background-color: #3b82f6;"></div>
                                    </div>
                                </td>

                                {{-- Avance general --}}
                                <td class="px-4 py-4 align-top">
                                    <div class="flex items-center gap-1.5 mb-1 whitespace-nowrap">
                                        @if($progress['done'])
                                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                        @endif
                                        <flux:badge :color="$progress['color']" size="sm">{{ $progress['label'] }}</flux:badge>
                                    </div>
                                    <div class="w-28 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded-full" style="width: {{ $progress['percent'] }}%; background-color: {{ $barHex[$progress['color']] ?? '#a1a1aa' }};"></div>
                                    </div>
                                </td>

                                {{-- Ver seguimiento --}}
                                <td class="px-4 py-4 text-center align-top">
                                    <a href="{{ route('admin.sent-lists.display.wo', $workOrder) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition-colors"
                                        title="Ver seguimiento de lotes">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Ver
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

        {{-- TAB: KITS eliminado — CRIMP ya no usa Kit (lotes de CRIMP en la Lista de envío) --}}

        {{-- ============================================ --}}
        {{-- TAB: PESADAS --}}
        {{-- ============================================ --}}
        @if($activeTab === 'weighings')
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pesadas de esta Work Order</h2>
                @if($workOrder->lots->isNotEmpty())
                <button wire:click="openCreateWeighingModal"
                    class="inline-flex items-center px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Registrar Pesada
                </button>
                @else
                <span class="text-xs text-gray-500 dark:text-gray-400 italic">Crea al menos un lote primero</span>
                @endif
            </div>
            @if($weighings->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No hay pesadas registradas.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Lote</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Buenas</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Malas</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pesó</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($weighings as $weighing)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $weighing->weighed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $weighing->lot?->lot_number ?? '—' }}</td>
                                <td class="px-6 py-4 text-center text-sm font-medium text-green-600 dark:text-green-400">{{ number_format($weighing->good_pieces) }}</td>
                                <td class="px-6 py-4 text-center text-sm font-medium text-red-600 dark:text-red-400">{{ number_format($weighing->bad_pieces) }}</td>
                                <td class="px-6 py-4 text-center text-sm font-bold text-gray-900 dark:text-white">{{ number_format($weighing->good_pieces + $weighing->bad_pieces) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $weighing->weighedBy?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-1">
                                        <button wire:click="openEditWeighingModal({{ $weighing->id }})" class="p-1.5 text-blue-600 hover:text-blue-800 dark:text-blue-400" title="Editar pesada">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="confirmDeleteWeighing({{ $weighing->id }})" class="p-1.5 text-red-600 hover:text-red-800 dark:text-red-400" title="Eliminar pesada">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

    {{-- ============================================ --}}
    {{-- MODALS --}}
    {{-- ============================================ --}}

    {{-- Lot Modal (Create/Edit) --}}
    @if($showLotModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeLotModal"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <form wire:submit="saveLot">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $editingLotId ? 'Editar Lote' : 'Agregar Lote' }}</h3>
                        <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                <span class="font-medium">WO:</span> {{ $workOrder->purchaseOrder?->wo ?? 'N/A' }} |
                                <span class="font-medium">Cant. WO:</span> {{ number_format($workOrder->original_quantity) }}
                            </p>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Número de Lote *</label>
                                <input type="text" wire:model="lotNumber" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: 001">
                                @error('lotNumber') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cantidad *</label>
                                <input type="number" wire:model="lotQuantity" min="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('lotQuantity') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            @if($editingLotId)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                                <select wire:model="lotStatus" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="pending">Pendiente</option>
                                    <option value="in_progress">En Progreso</option>
                                    <option value="completed">Completado</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción</label>
                                <input type="text" wire:model="lotDescription" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Comentarios</label>
                                <textarea wire:model="lotComments" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end gap-2">
                        <button type="button" wire:click="closeLotModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">{{ $editingLotId ? 'Guardar' : 'Crear Lote' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete Lot Confirm --}}
    @if($showDeleteLotConfirm)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showDeleteLotConfirm', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-sm w-full p-6">
                <div class="flex items-start gap-3 mb-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Eliminar Lote</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">¿Estás seguro? Se eliminarán también sus pesadas. Esta acción no se puede deshacer.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button wire:click="$set('showDeleteLotConfirm', false)" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                    <button wire:click="deleteLot" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg">Eliminar</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modales de Kit eliminados — CRIMP ya no usa Kit --}}

    {{-- Weighing Modal (Create/Edit) --}}
    @if($showWeighingModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeWeighingModal"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full">
                <form wire:submit="saveWeighing">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $editingWeighingId ? 'Editar Pesada' : 'Registrar Pesada' }}</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lote *</label>
                                <select wire:model.live="weighingLotId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">— Seleccionar Lote —</option>
                                    @foreach($workOrder->lots as $lot)
                                        <option value="{{ $lot->id }}">{{ $lot->lot_number }} — {{ number_format($lot->quantity) }} pcs</option>
                                    @endforeach
                                </select>
                                @error('weighingLotId') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>

                            @if($weighingLotId)
                            <div class="p-3 rounded-lg {{ $remainingPieces > 0 ? 'bg-indigo-50 dark:bg-indigo-900/20' : 'bg-green-50 dark:bg-green-900/20' }}">
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    @php $selectedLot = $workOrder->lots->find($weighingLotId); @endphp
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Cant. Lote</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($selectedLot?->quantity ?? 0) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Ya Pesadas</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format(($selectedLot?->quantity ?? 0) - $remainingPieces) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs {{ $remainingPieces > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-green-600 dark:text-green-400' }}">Pendiente</p>
                                        <p class="text-lg font-bold {{ $remainingPieces > 0 ? 'text-indigo-700 dark:text-indigo-300' : 'text-green-700 dark:text-green-300' }}">{{ number_format($remainingPieces) }}</p>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Piezas Buenas *</label>
                                    <input type="number" wire:model="goodPieces" min="0" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-green-500 focus:border-green-500">
                                    @error('goodPieces') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Piezas Malas *</label>
                                    <input type="number" wire:model="badPieces" min="0" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-red-500 focus:border-red-500">
                                    @error('badPieces') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha y Hora *</label>
                                <input type="datetime-local" wire:model="weighedAt" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('weighedAt') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Comentarios</label>
                                <textarea wire:model="weighingComments" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end gap-2">
                        <button type="button" wire:click="closeWeighingModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg">{{ $editingWeighingId ? 'Guardar' : 'Registrar Pesada' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete Weighing Confirm --}}
    @if($showDeleteWeighingConfirm)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showDeleteWeighingConfirm', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-sm w-full p-6">
                <div class="flex items-start gap-3 mb-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Eliminar Pesada</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">¿Estás seguro? Esta acción no se puede deshacer.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button wire:click="$set('showDeleteWeighingConfirm', false)" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                    <button wire:click="deleteWeighing" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg">Eliminar</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Signature Modal Component - DESHABILITADO TEMPORALMENTE --}}
    {{-- <livewire:admin.signature-modal @signature-completed="refreshWorkOrder" /> --}}
</div>
