{{--
    Tabla de pesadas reutilizable.
    Props:
      $rows       array de pesadas
      $type       'piece' | 'crimp'  (para los wire:click)
      $accentText clases de color de acento (string literal Tailwind)
      $total      int
--}}
@if (count($rows) > 0)
    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Fecha</th>
                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Lote CRIMP</th>
                        <th class="px-3 py-2 text-right font-semibold uppercase tracking-wider {{ $accentText }}">Cantidad</th>
                        <th class="px-3 py-2 text-right font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Peso (kg)</th>
                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Por</th>
                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Comentarios</th>
                        <th class="px-3 py-2 text-center font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="whitespace-nowrap px-3 py-2 text-slate-700 dark:text-slate-300">{{ $row['weighed_at'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $row['crimp_lot'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums {{ $accentText }}">{{ number_format($row['quantity']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ $row['weight'] !== null ? number_format($row['weight'], 3) : '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $row['weighed_by'] }}</td>
                            <td class="max-w-xs truncate px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['comments'] ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <x-ui.row-actions label="esta pesada"
                                    delete="deleteWeighing('{{ $type }}', {{ $row['id'] }})"
                                    deleteConfirm="¿Eliminar esta pesada?">
                                    <x-ui.icon-btn tone="primary" label="Editar esta pesada"
                                        wire:click="editWeighing('{{ $type }}', {{ $row['id'] }})">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </x-ui.icon-btn>
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-900/50">
                    <tr>
                        <td class="px-3 py-2 text-slate-700 dark:text-slate-300" colspan="2">Total</td>
                        <td class="px-3 py-2 text-right tabular-nums {{ $accentText }}">{{ number_format($total) }}</td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@else
    <p class="text-sm text-slate-500 dark:text-slate-400">Sin pesadas registradas.</p>
@endif
