<?php

namespace App\Livewire\Admin\Prices;

use App\Models\Price;
use App\Models\Part;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PriceList extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $sortField = 'effective_date';
    public string $sortDirection = 'desc';
    public int $perPage = 10;
    public string $filterActive = 'all';
    public string $filterPart = 'all';

    public bool $showImportModal = false;
    public $importFile = null;
    public array $importResults = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
    }

    public function deletePrice(int $id): void
    {
        $price = Price::findOrFail($id);
        if (!$price->canBeDeleted()) {
            session()->flash('error', 'No se puede eliminar este precio.');
            return;
        }
        $price->delete();
        session()->flash('flash.banner', 'Precio eliminado correctamente.');
        session()->flash('flash.bannerStyle', 'success');
    }

    public function render()
    {
        $query = Price::with(['part', 'tiers'])->search($this->search);

        if ($this->filterActive === 'active') {
            $query->active();
        } elseif ($this->filterActive === 'inactive') {
            $query->inactive();
        }

        if ($this->filterPart !== 'all') {
            $query->where('part_id', $this->filterPart);
        }

        $prices = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $parts = Part::active()->orderBy('number')->get();

        $totalPrices = Price::count();
        $activePrices = Price::active()->count();
        $partsWithPrice = Price::distinct('part_id')->count('part_id');

        return view('livewire.admin.prices.price-list', [
            'prices' => $prices,
            'parts' => $parts,
            'totalPrices' => $totalPrices,
            'activePrices' => $activePrices,
            'partsWithPrice' => $partsWithPrice,
        ]);
    }

    // ==========================================
    // CSV: Export, Template, Import
    // ==========================================

    /**
     * Columnas del CSV. Una fila por tier (un precio con N tiers = N filas).
     * Si un precio no tiene tiers, va una sola fila con campos de tier vacíos.
     */
    public static function csvColumns(): array
    {
        return [
            'part_number',
            'workstation_type',
            'effective_date',
            'sample_price',
            'active',
            'comments',
            'min_quantity',
            'max_quantity',
            'tier_price',
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'precios_' . now()->format('Ymd_His') . '.csv';
        $columns = self::csvColumns();

        $query = Price::with(['part', 'tiers'])->search($this->search);
        if ($this->filterActive === 'active') {
            $query->active();
        } elseif ($this->filterActive === 'inactive') {
            $query->inactive();
        }
        if ($this->filterPart !== 'all') {
            $query->where('part_id', $this->filterPart);
        }
        $prices = $query->orderBy($this->sortField, $this->sortDirection)->get();

        return response()->streamDownload(function () use ($prices, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            foreach ($prices as $price) {
                $base = [
                    $price->part?->number,
                    $price->workstation_type,
                    optional($price->effective_date)->format('Y-m-d'),
                    $price->sample_price,
                    $price->active ? '1' : '0',
                    $price->comments,
                ];

                if ($price->tiers->isEmpty()) {
                    fputcsv($out, array_merge($base, ['', '', '']));
                    continue;
                }

                foreach ($price->tiers as $tier) {
                    fputcsv($out, array_merge($base, [
                        $tier->min_quantity !== null ? (0 + $tier->min_quantity) : '',
                        $tier->max_quantity !== null ? (0 + $tier->max_quantity) : '',
                        $tier->tier_price,
                    ]));
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $columns = self::csvColumns();
        $samplePart = Part::active()->orderBy('number')->value('number') ?? '189-10492';

        return response()->streamDownload(function () use ($columns, $samplePart) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);
            // Ejemplo: un precio con 3 tiers → 3 filas con la misma clave (part_number + workstation_type + effective_date)
            fputcsv($out, [$samplePart, 'table', '2026-01-01', '0.50', '1', 'Precio mesa de trabajo', '1',     '999',   '0.6000']);
            fputcsv($out, [$samplePart, 'table', '2026-01-01', '0.50', '1', '',                     '1000',  '10999', '0.5000']);
            fputcsv($out, [$samplePart, 'table', '2026-01-01', '0.50', '1', '',                     '11000', '',      '0.4500']);
            // Ejemplo: un precio sin tiers (sólo sample_price) → 1 fila
            fputcsv($out, [$samplePart, 'machine', '2026-01-01', '0.35', '1', 'Precio sólo muestra', '', '', '']);
            fclose($out);
        }, 'plantilla_precios.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function openImportModal(): void
    {
        $this->reset(['importFile', 'importResults']);
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->reset(['importFile', 'importResults']);
    }

    public function importCsv(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'importFile.required' => 'Selecciona un archivo CSV.',
            'importFile.mimes' => 'El archivo debe ser CSV.',
            'importFile.max' => 'El archivo no puede pesar más de 5 MB.',
        ]);

        $path = $this->importFile->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->importResults = ['errors' => ['No se pudo abrir el archivo.'], 'created' => 0, 'failed' => 0];
            return;
        }

        // BOM strip
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->importResults = ['errors' => ['El archivo está vacío.'], 'created' => 0, 'failed' => 0];
            return;
        }

        $header = array_map(fn($h) => trim(strtolower($h)), $header);
        $expected = self::csvColumns();
        $missing = array_diff($expected, $header);

        if (!empty($missing)) {
            fclose($handle);
            $this->importResults = [
                'errors' => ['Faltan columnas en el CSV: ' . implode(', ', $missing)],
                'created' => 0,
                'failed' => 0,
            ];
            return;
        }

        // Agrupar filas por clave (part_number|workstation_type|effective_date)
        $groups = [];
        $rowErrors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $row = array_combine($header, array_pad($row, count($header), null));

            $partNumber = trim((string) ($row['part_number'] ?? ''));
            $workstationType = trim((string) ($row['workstation_type'] ?? ''));
            $effectiveDate = trim((string) ($row['effective_date'] ?? ''));

            if ($partNumber === '' || $workstationType === '' || $effectiveDate === '') {
                $rowErrors[] = "Fila {$rowNum}: faltan campos obligatorios (part_number, workstation_type, effective_date).";
                continue;
            }

            if (!in_array($workstationType, [Price::WORKSTATION_TABLE, Price::WORKSTATION_MACHINE, Price::WORKSTATION_SEMI_AUTOMATIC], true)) {
                $rowErrors[] = "Fila {$rowNum}: workstation_type '{$workstationType}' inválido. Usa: table, machine, semi_automatic.";
                continue;
            }

            $key = "{$partNumber}|{$workstationType}|{$effectiveDate}";
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'first_row' => $rowNum,
                    'part_number' => $partNumber,
                    'workstation_type' => $workstationType,
                    'effective_date' => $effectiveDate,
                    'sample_price' => trim((string) ($row['sample_price'] ?? '')),
                    'active' => in_array(trim((string) ($row['active'] ?? '1')), ['1', 'true', 'TRUE', 'si', 'sí'], true),
                    'comments' => trim((string) ($row['comments'] ?? '')) ?: null,
                    'tiers' => [],
                ];
            }

            $min = trim((string) ($row['min_quantity'] ?? ''));
            $max = trim((string) ($row['max_quantity'] ?? ''));
            $tierPrice = trim((string) ($row['tier_price'] ?? ''));

            // Sólo agregar tier si hay min_quantity y tier_price
            if ($min !== '' && $tierPrice !== '') {
                $groups[$key]['tiers'][] = [
                    'min_quantity' => $min,
                    'max_quantity' => $max !== '' ? $max : null,
                    'tier_price' => $tierPrice,
                    'row' => $rowNum,
                ];
            } elseif ($min !== '' || $tierPrice !== '') {
                $rowErrors[] = "Fila {$rowNum}: tier incompleto (faltan min_quantity o tier_price).";
            }
        }
        fclose($handle);

        $partsByNumber = Part::pluck('id', 'number');

        $created = 0;
        $failed = 0;
        $errors = $rowErrors;

        DB::beginTransaction();
        try {
            foreach ($groups as $key => $g) {
                $partId = $partsByNumber[$g['part_number']] ?? null;
                if (!$partId) {
                    $failed++;
                    $errors[] = "Fila {$g['first_row']}: la parte '{$g['part_number']}' no existe.";
                    continue;
                }

                $samplePrice = $g['sample_price'];
                if ($samplePrice === '' || !is_numeric($samplePrice)) {
                    $failed++;
                    $errors[] = "Fila {$g['first_row']}: sample_price inválido o vacío.";
                    continue;
                }

                try {
                    $date = \Carbon\Carbon::parse($g['effective_date'])->toDateString();
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Fila {$g['first_row']}: effective_date inválida ('{$g['effective_date']}'). Usa formato YYYY-MM-DD.";
                    continue;
                }

                try {
                    $price = Price::create([
                        'part_id' => $partId,
                        'workstation_type' => $g['workstation_type'],
                        'effective_date' => $date,
                        'sample_price' => $samplePrice,
                        'active' => $g['active'],
                        'comments' => $g['comments'],
                    ]);

                    // Si active=true, desactivar otros precios conflictivos
                    if ($g['active']) {
                        $price->deactivateConflictingPrices();
                    }

                    // Sync tiers
                    if (!empty($g['tiers'])) {
                        $price->syncTiers($g['tiers']);
                    }

                    $created++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Fila {$g['first_row']}: error al crear precio ({$e->getMessage()}).";
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = 'Error general: ' . $e->getMessage();
        }

        $this->importResults = [
            'created' => $created,
            'failed' => $failed,
            'errors' => $errors,
        ];

        if ($created > 0) {
            session()->flash('flash.banner', "Importados {$created} precios." . ($failed > 0 ? " {$failed} grupo(s) fallaron." : ''));
            session()->flash('flash.bannerStyle', $failed > 0 ? 'warning' : 'success');
        }
    }
}
