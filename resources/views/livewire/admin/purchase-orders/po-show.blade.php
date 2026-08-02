@php
    $statusTones = [
        'pending'            => 'warn',
        'approved'           => 'good',
        'rejected'           => 'bad',
        'pending_correction' => 'warn',
    ];
@endphp

<x-ui.page eyebrow="Compras" :title="'Orden de compra '.$purchaseOrder->po_number"
    :subtitle="$purchaseOrder->part->number.' — '.Str::limit($purchaseOrder->part->description, 60)"
    back="{{ route('admin.purchase-orders.index') }}" backLabel="Volver a órdenes de compra">

    <x-slot:actions>
        <x-ui.badge :tone="$statusTones[$purchaseOrder->status] ?? 'neutral'" dot>{{ $purchaseOrder->status_label }}</x-ui.badge>
        <x-ui.btn variant="primary" href="{{ route('admin.purchase-orders.edit', $purchaseOrder) }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar
        </x-ui.btn>
    </x-slot:actions>

    @if (session('error'))
        <x-ui.note tone="danger">{{ session('error') }}</x-ui.note>
    @endif

    {{-- Las cifras de la orden, arriba de todo. --}}
    <x-ui.stats cols="4">
        <x-ui.stat label="Cantidad" :value="number_format($purchaseOrder->quantity)" unit="pz" />
        <x-ui.stat label="Precio unitario" :value="'$'.number_format($purchaseOrder->unit_price, 4)" />
        <x-ui.stat label="Total de la orden"
            :value="'$'.number_format($purchaseOrder->quantity * $purchaseOrder->unit_price, 2)" tone="info" />
        <x-ui.stat label="WO (cliente)" :value="$purchaseOrder->wo ?: 'Sin asignar'"
            :tone="$purchaseOrder->wo ? 'accent' : 'neutral'" />
    </x-ui.stats>

    {{-- Validación de precio: es lo que decide si la PO se aprueba. --}}
    @if ($price_message)
        <x-ui.section title="Validación de precio"
            hint="Compara el precio capturado contra el precio vigente del catálogo.">
            <x-ui.note :tone="$price_valid ? 'success' : 'warn'" :title="$price_message">
                @if ($expected_price !== null)
                    Precio esperado del catálogo: <strong>${{ number_format($expected_price, 4) }}</strong>
                @endif
            </x-ui.note>
        </x-ui.section>
    @endif

    {{-- Ficha completa --}}
    <x-ui.section title="Información general">
        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
            <x-ui.kv label="Número de PO" :value="$purchaseOrder->po_number" />
            <x-ui.kv label="WO (cliente)" :value="$purchaseOrder->wo ?: 'Sin asignar'" />
            <x-ui.kv label="Estado" :value="$purchaseOrder->status_label"
                :tone="$purchaseOrder->status === 'approved' ? 'good' : ($purchaseOrder->status === 'rejected' ? 'bad' : 'warn')" />
            <x-ui.kv label="Parte" :value="$purchaseOrder->part->number"
                :help="$purchaseOrder->part->description" />
            <x-ui.kv label="Cantidad" :value="number_format($purchaseOrder->quantity).' pz'" />
            <x-ui.kv label="Precio unitario" :value="'$'.number_format($purchaseOrder->unit_price, 4)" />
            <x-ui.kv label="Total" :value="'$'.number_format($purchaseOrder->quantity * $purchaseOrder->unit_price, 2)" tone="info" />
            <x-ui.kv label="Fecha de PO" :value="$purchaseOrder->po_date->format('d/m/Y')" />
            <x-ui.kv label="Fecha de entrega" :value="$purchaseOrder->due_date->format('d/m/Y')" />
            <x-ui.kv label="Creada" :value="$purchaseOrder->created_at->format('d/m/Y H:i')" />
            <x-ui.kv label="Última actualización" :value="$purchaseOrder->updated_at->format('d/m/Y H:i')" />
        </dl>

        @if ($purchaseOrder->comments)
            <div class="mt-4">
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Comentarios</p>
                <p class="whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-200">{{ $purchaseOrder->comments }}</p>
            </div>
        @endif
    </x-ui.section>

    {{-- Work Order generada --}}
    @if ($purchaseOrder->workOrder)
        <x-ui.section title="Work Order asociada" hint="Se generó al aprobar esta orden de compra.">
            <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <x-ui.kv label="WO #" :value="$purchaseOrder->workOrder->wo_number" tone="info" />
                <x-ui.kv label="Estado" :value="$purchaseOrder->workOrder->status->name ?? 'N/A'" />
            </dl>
        </x-ui.section>
    @endif

    {{-- Documento adjunto --}}
    @if ($purchaseOrder->pdf_path)
        <x-ui.section title="Documento PDF" hint="Orden de compra original enviada por el cliente.">
            <div class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:bg-slate-900/40">
                <div class="flex min-w-0 items-center gap-3">
                    <svg class="size-8 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ basename($purchaseOrder->pdf_path) }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Documento de la orden de compra</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.btn variant="secondary" size="sm" :href="Storage::url($purchaseOrder->pdf_path)" :navigate="false" target="_blank">Ver</x-ui.btn>
                    <x-ui.btn variant="secondary" size="sm" :href="Storage::url($purchaseOrder->pdf_path)" :navigate="false" download>Descargar</x-ui.btn>
                </div>
            </div>
        </x-ui.section>
    @endif

    {{-- Aprobación: sólo mientras la PO está pendiente. --}}
    @if ($purchaseOrder->status === 'pending')
        <x-ui.section title="Aprobación" tone="accent"
            hint="Al aprobar se genera la Work Order y la orden entra a producción.">
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-ui.btn variant="success" wire:click="approve"
                    wire:confirm="¿Aprobar la PO {{ $purchaseOrder->po_number }}? Se generará su Work Order.">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Aprobar PO
                </x-ui.btn>

                <x-ui.btn variant="danger" wire:click="reject"
                    wire:confirm="¿Rechazar la PO {{ $purchaseOrder->po_number }}?">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Rechazar PO
                </x-ui.btn>
            </div>
        </x-ui.section>
    @endif
</x-ui.page>
