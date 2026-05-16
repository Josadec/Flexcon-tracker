<?php

namespace App\Livewire\Admin\Parts;

use App\Models\Part;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartList extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $sortField = 'number';
    public string $sortDirection = 'asc';
    public int $perPage = 10;
    public string $filterActive = 'all';

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

    public function deletePart(int $id): void
    {
        $part = Part::findOrFail($id);
        if (!$part->canBeDeleted()) {
            session()->flash('error', 'No se puede eliminar esta parte porque tiene precios asociados.');
            return;
        }
        $part->delete();
        session()->flash('flash.banner', 'Parte eliminada correctamente.');
        session()->flash('flash.bannerStyle', 'success');
    }

    public function render()
    {
        $query = Part::search($this->search);

        if ($this->filterActive === 'active') {
            $query->active();
        } elseif ($this->filterActive === 'inactive') {
            $query->where('active', false);
        }

        $parts = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $totalParts = Part::count();
        $activeParts = Part::active()->count();
        $withPrices = Part::has('prices')->count();

        return view('livewire.admin.parts.part-list', [
            'parts' => $parts,
            'totalParts' => $totalParts,
            'activeParts' => $activeParts,
            'withPrices' => $withPrices,
        ]);
    }

    // ==========================================
    // CSV: Export, Template, Import
    // ==========================================

    /**
     * Columnas del CSV en orden fijo. Contrato para export e import.
     */
    public static function csvColumns(): array
    {
        return [
            'number',
            'item_number',
            'description',
            'unit_of_measure',
            'label_spec',
            'is_crimp',
            'active',
            'notes',
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'partes_' . now()->format('Ymd_His') . '.csv';
        $columns = self::csvColumns();

        $query = Part::search($this->search);
        if ($this->filterActive === 'active') {
            $query->active();
        } elseif ($this->filterActive === 'inactive') {
            $query->where('active', false);
        }
        $parts = $query->orderBy($this->sortField, $this->sortDirection)->get();

        return response()->streamDownload(function () use ($parts, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            foreach ($parts as $part) {
                fputcsv($out, [
                    $part->number,
                    $part->item_number,
                    $part->description,
                    $part->unit_of_measure,
                    $part->label_spec,
                    $part->is_crimp ? '1' : '0',
                    $part->active ? '1' : '0',
                    $part->notes,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $columns = self::csvColumns();

        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);
            // Fila de ejemplo
            fputcsv($out, [
                '189-10492',                  // number * (único)
                'STS-CR-436-37',              // item_number * (único)
                'STS H-CR-436-37 CRIMP',      // description
                'PZA',                        // unit_of_measure (PZA, KG, etc.)
                'M83519/2-8',                 // label_spec
                '1',                          // is_crimp (1=sí, 0=no)
                '1',                          // active (1=activa, 0=inactiva)
                'Parte de ejemplo',           // notes
            ]);
            fclose($out);
        }, 'plantilla_partes.csv', [
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
            $this->importResults = ['errors' => ['No se pudo abrir el archivo.'], 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
            return;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->importResults = ['errors' => ['El archivo está vacío.'], 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
            return;
        }

        $header = array_map(fn($h) => trim(strtolower($h)), $header);
        $expected = self::csvColumns();
        $missing = array_diff($expected, $header);

        if (!empty($missing)) {
            fclose($handle);
            $this->importResults = [
                'errors' => ['Faltan columnas en el CSV: ' . implode(', ', $missing)],
                'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0,
            ];
            return;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $rowNum = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $row = array_combine($header, array_pad($row, count($header), null));

                $number = trim((string) ($row['number'] ?? ''));
                $itemNumber = trim((string) ($row['item_number'] ?? ''));

                if ($number === '' || $itemNumber === '') {
                    $failed++;
                    $errors[] = "Fila {$rowNum}: faltan campos obligatorios (number, item_number).";
                    continue;
                }

                $payload = [
                    'number' => $number,
                    'item_number' => $itemNumber,
                    'description' => trim((string) ($row['description'] ?? '')) ?: null,
                    'unit_of_measure' => trim((string) ($row['unit_of_measure'] ?? '')) ?: null,
                    'label_spec' => trim((string) ($row['label_spec'] ?? '')) ?: null,
                    'is_crimp' => in_array(trim((string) ($row['is_crimp'] ?? '1')), ['1', 'true', 'TRUE', 'si', 'sí'], true),
                    'active' => in_array(trim((string) ($row['active'] ?? '1')), ['1', 'true', 'TRUE', 'si', 'sí'], true),
                    'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                ];

                // Buscar por number (clave única primaria)
                $existing = Part::where('number', $number)->first();

                if ($existing) {
                    // Validar conflicto de item_number con otra parte
                    if ($itemNumber !== $existing->item_number) {
                        $conflict = Part::where('item_number', $itemNumber)->where('id', '!=', $existing->id)->exists();
                        if ($conflict) {
                            $failed++;
                            $errors[] = "Fila {$rowNum}: el item_number '{$itemNumber}' ya está usado por otra parte.";
                            continue;
                        }
                    }

                    // Detectar cambios
                    $changed = false;
                    foreach ($payload as $key => $val) {
                        if ((string) $existing->{$key} !== (string) $val) {
                            $changed = true;
                            break;
                        }
                    }

                    if (!$changed) {
                        $skipped++;
                        continue;
                    }

                    try {
                        $existing->update($payload);
                        $updated++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: error al actualizar ({$e->getMessage()}).";
                    }
                } else {
                    // INSERT: verificar que item_number no esté usado por otro
                    if (Part::where('item_number', $itemNumber)->exists()) {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: el item_number '{$itemNumber}' ya existe en otra parte.";
                        continue;
                    }
                    try {
                        Part::create($payload);
                        $created++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = "Fila {$rowNum}: error al crear ({$e->getMessage()}).";
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = 'Error general: ' . $e->getMessage();
        } finally {
            fclose($handle);
        }

        $this->importResults = compact('created', 'updated', 'skipped', 'failed', 'errors');

        if ($created > 0 || $updated > 0) {
            $parts = [];
            if ($created > 0) $parts[] = "{$created} creadas";
            if ($updated > 0) $parts[] = "{$updated} actualizadas";
            if ($skipped > 0) $parts[] = "{$skipped} sin cambios";
            if ($failed > 0)  $parts[] = "{$failed} fallaron";
            session()->flash('flash.banner', 'Import partes: ' . implode(', ', $parts) . '.');
            session()->flash('flash.bannerStyle', $failed > 0 ? 'warning' : 'success');
        }
    }
}
