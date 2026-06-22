<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Importa el catálogo REAL del cliente (partes + precios + tiers) desde los CSV
 * canónicos en Diagramas_flujo/DB/plantillas_importacion. Normalmente el cliente
 * los importa por phpMyAdmin; este seeder reproduce esa carga (p. ej. tras un
 * migrate:fresh que deja parts/prices vacíos, pues están deshabilitados en
 * DatabaseSeeder a propósito).
 *
 * Inserta con IDs explícitos según el orden de fila para preservar la integridad
 * referencial (prices.part_id 1..431 → parts; price_tiers.price_id → prices).
 *
 *   php artisan db:seed --class=ClientCatalogImportSeeder
 *
 * Seguridad: aborta si ya hay partes (para no duplicar el catálogo real).
 */
class ClientCatalogImportSeeder extends Seeder
{
    private string $dir;

    public function run(): void
    {
        $this->dir = base_path('Diagramas_flujo/DB/plantillas_importacion');

        // Idempotente por tabla: importa solo lo que falta (evita duplicar el
        // catálogo real). Re-ejecutable sin truncar manualmente.
        if (DB::table('parts')->count() === 0) {
            $this->importParts();
        } else {
            $this->command?->warn('  parts: ya hay '.DB::table('parts')->count().' — se omite.');
        }

        if (DB::table('prices')->count() === 0) {
            $this->importPrices();
        } else {
            $this->command?->warn('  prices: ya hay '.DB::table('prices')->count().' — se omite.');
        }

        // Los tiers traen duplicados (price_id+min+max) que violan price_tier_unique;
        // se reimportan completos con insertOrIgnore (descarta los duplicados).
        $this->importTiers();

        $this->command?->info('Catálogo del cliente:');
        $this->command?->info('  • Partes:      '.DB::table('parts')->count().' (CRIMP: '.DB::table('parts')->where('is_crimp', 1)->count().')');
        $this->command?->info('  • Precios:     '.DB::table('prices')->count());
        $this->command?->info('  • Price tiers: '.DB::table('price_tiers')->count());
    }

    private function importParts(): int
    {
        $rows = $this->readCsv('01_parts_final_rev_1.csv');
        $id = 1;
        $batch = [];
        foreach ($rows as $r) {
            $batch[] = [
                'id'              => $id++,
                'number'          => $r['number'],
                'item_number'     => $r['item_number'],
                'unit_of_measure' => $this->nv($r['unit_of_measure'] ?? null),
                'active'          => (int) ($this->nv($r['active'] ?? '1') ?? 1),
                'is_crimp'        => (int) ($this->nv($r['is_crimp'] ?? '0') ?? 0),
                'label_spec'      => $this->nv($r['label_spec'] ?? null),
                'description'     => $this->nv($r['description'] ?? null),
                'notes'           => $this->nv($r['notes'] ?? null),
                'deleted_at'      => $this->nv($r['deleted_at'] ?? null),
                'created_at'      => $this->nv($r['created_at'] ?? null) ?? now(),
                'updated_at'      => $this->nv($r['updated_at'] ?? null),
            ];
        }
        $this->insertChunked('parts', $batch);
        return count($batch);
    }

    private function importPrices(): int
    {
        $rows = $this->readCsv('02_prices_final_rev_1.csv');
        $id = 1;
        $batch = [];
        foreach ($rows as $r) {
            $batch[] = [
                'id'               => $id++,
                'part_id'          => (int) $r['part_id'],
                'sample_price'     => (float) $r['sample_price'],
                'workstation_type' => $this->nv($r['workstation_type'] ?? 'table') ?? 'table',
                'effective_date'   => $this->nv($r['effective_date'] ?? null) ?? now()->toDateString(),
                'active'           => (int) ($this->nv($r['active'] ?? '1') ?? 1),
                'comments'         => $this->nv($r['comments'] ?? null),
                'created_at'       => $this->nv($r['created_at'] ?? null) ?? now(),
                'updated_at'       => $this->nv($r['updated_at'] ?? null),
            ];
        }
        $this->insertChunked('prices', $batch);
        return count($batch);
    }

    private function importTiers(): int
    {
        DB::table('price_tiers')->delete(); // reimporta limpio (corrige cargas parciales; truncate no aplica por FK)

        $rows = $this->readCsv('03_price_tiers_final.csv');
        $id = 1;
        $batch = [];
        foreach ($rows as $r) {
            $batch[] = [
                'id'           => $id++,
                'price_id'     => (int) $r['price_id'],
                'min_quantity' => (float) $r['min_quantity'],
                'max_quantity' => $this->nv($r['max_quantity'] ?? null),
                'tier_price'   => (float) $r['tier_price'],
                'created_at'   => $this->nv($r['created_at'] ?? null) ?? now(),
                'updated_at'   => $this->nv($r['updated_at'] ?? null),
            ];
        }
        // insertOrIgnore: descarta filas que violan price_tier_unique (duplicados del CSV).
        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('price_tiers')->insertOrIgnore($chunk);
        }
        return count($batch);
    }

    /** Lee un CSV (header + filas) y devuelve filas como arrays asociativos. */
    private function readCsv(string $file): array
    {
        $path = $this->dir.'/'.$file;
        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new \RuntimeException("No se pudo abrir {$path}");
        }

        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            return [];
        }
        // Quitar BOM UTF-8 del primer encabezado.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if ($r === [null] || (count($r) === 1 && trim((string) $r[0]) === '')) {
                continue; // línea vacía
            }
            // Rellenar/recortar a la longitud del header.
            $r = array_pad(array_slice($r, 0, count($header)), count($header), null);
            $rows[] = array_combine($header, $r);
        }
        fclose($fh);

        return $rows;
    }

    /** Normaliza '', 'NULL' y null → null. */
    private function nv($v): ?string
    {
        if ($v === null) return null;
        $v = trim((string) $v);
        if ($v === '' || strcasecmp($v, 'NULL') === 0) return null;
        return $v;
    }

    private function insertChunked(string $table, array $batch): void
    {
        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
        // Dejar el AUTO_INCREMENT pasado el último id explícito.
        $next = (count($batch) + 1);
        DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = {$next}");
    }
}
