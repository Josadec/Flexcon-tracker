{{--
    Tabla de pesadas reutilizable.
    Props:
      $rows       array de pesadas
      $type       'piece' | 'crimp'  (para los wire:click)
      $headBg     clases de fondo del encabezado/pie (string literal Tailwind)
      $accentText clases de color de acento (string literal Tailwind)
      $total      int
--}}
@if (count($rows) > 0)
    <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <table class="w-full text-xs">
            <thead class="{{ $headBg }} border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Fecha</th>
                    <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Lote CRIMP</th>
                    <th class="px-3 py-2 text-right {{ $accentText }}">Cantidad</th>
                    <th class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">Peso (kg)</th>
                    <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Por</th>
                    <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Comentarios</th>
                    <th class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['weighed_at'] ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['crimp_lot'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-medium {{ $accentText }}">{{ number_format($row['quantity']) }}</td>
                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ $row['weight'] !== null ? number_format($row['weight'], 3) : '—' }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['weighed_by'] }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ $row['comments'] ?? '-' }}</td>
                        <td class="px-3 py-2 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="editWeighing('{{ $type }}', {{ $row['id'] }})"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300" title="Editar">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button wire:click="deleteWeighing('{{ $type }}', {{ $row['id'] }})"
                                    wire:confirm="¿Eliminar esta pesada?"
                                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300" title="Eliminar">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="{{ $headBg }} font-semibold border-t border-gray-200 dark:border-gray-700">
                <tr>
                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300" colspan="2">Total</td>
                    <td class="px-3 py-2 text-right {{ $accentText }}">{{ number_format($total) }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">Sin pesadas registradas.</p>
@endif
