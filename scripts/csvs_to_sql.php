<?php
/**
 * Convierte los CSVs de prices y price_tiers a SQL con NULLs explicitos
 * para evitar problemas con la opcion "header as data" de phpMyAdmin.
 */

$dir = __DIR__ . '/../Diagramas_flujo/imports';
$createdAt = '2026-04-09 20:05:00';

$jobs = [
    [
        'csv'    => "$dir/02_prices_final_v2.csv",
        'sql'    => "$dir/02_prices_final_v2.sql",
        'table'  => 'prices',
        'cols'   => ['id','part_id','sample_price','workstation_type','effective_date','active','comments','created_at','updated_at'],
        'nulls'  => ['id','comments','updated_at'],
        'strs'   => ['workstation_type','effective_date','comments','created_at','updated_at'],
    ],
    [
        'csv'    => "$dir/03_price_tiers_table_final.csv",
        'sql'    => "$dir/03_price_tiers_table_final.sql",
        'table'  => 'price_tiers',
        'cols'   => ['id','price_id','min_quantity','max_quantity','tier_price','created_at','updated_at'],
        'nulls'  => ['id','updated_at'],
        'strs'   => ['created_at','updated_at'],
    ],
    [
        'csv'    => "$dir/03_price_tiers_machine_final_v2.csv",
        'sql'    => "$dir/03_price_tiers_machine_final_v2.sql",
        'table'  => 'price_tiers',
        'cols'   => ['id','price_id','min_quantity','max_quantity','tier_price','created_at','updated_at'],
        'nulls'  => ['id','updated_at'],
        'strs'   => ['created_at','updated_at'],
    ],
    [
        'csv'    => "$dir/03_price_tiers_semiauto_final_v2.csv",
        'sql'    => "$dir/03_price_tiers_semiauto_final_v2.sql",
        'table'  => 'price_tiers',
        'cols'   => ['id','price_id','min_quantity','max_quantity','tier_price','created_at','updated_at'],
        'nulls'  => ['id','updated_at'],
        'strs'   => ['created_at','updated_at'],
    ],
];

$esc = fn($s) => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$s) . "'";

foreach ($jobs as $job) {
    if (!is_file($job['csv'])) {
        fwrite(STDERR, "No existe: {$job['csv']}\n");
        continue;
    }

    $fhIn = fopen($job['csv'], 'r');
    $header = fgetcsv($fhIn);
    if (!$header) { fclose($fhIn); continue; }
    // Limpiar BOM del primer header
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

    $colIdx = array_flip($header);
    $rows = [];
    while (($r = fgetcsv($fhIn)) !== false) {
        if (count($r) < count($header)) continue;
        // Saltar fila si todo es vacio
        if (count(array_filter($r, fn($v) => $v !== '' && $v !== null)) === 0) continue;
        $rows[] = $r;
    }
    fclose($fhIn);

    $fhOut = fopen($job['sql'], 'w');
    fwrite($fhOut, "-- {$job['table']} desde " . basename($job['csv']) . "\n");
    fwrite($fhOut, "-- Generado: " . date('Y-m-d H:i:s') . "\n\n");

    if (count($rows) === 0) {
        fwrite($fhOut, "-- Sin filas para importar\n");
        fclose($fhOut);
        echo "  {$job['sql']}: 0 filas\n";
        continue;
    }

    fwrite($fhOut, "INSERT INTO `{$job['table']}` (`" . implode('`,`', $job['cols']) . "`) VALUES\n");

    $batch = [];
    foreach ($rows as $r) {
        $values = [];
        foreach ($job['cols'] as $col) {
            $idx = $colIdx[$col] ?? null;
            $v = $idx !== null ? $r[$idx] : '';

            // NULLs explicitos
            if ($v === '' && in_array($col, $job['nulls'], true)) {
                $values[] = 'NULL';
                continue;
            }

            // String columns -> escape & quote
            if (in_array($col, $job['strs'], true)) {
                if ($v === '') {
                    $values[] = "''";
                } else {
                    $values[] = $esc($v);
                }
                continue;
            }

            // Numericas
            if ($v === '') {
                $values[] = 'NULL';
            } else {
                $values[] = $v;
            }
        }
        $batch[] = '(' . implode(',', $values) . ')';
    }
    fwrite($fhOut, implode(",\n", $batch) . ";\n");
    fclose($fhOut);
    echo "  {$job['sql']}: " . count($batch) . " filas\n";
}
echo "\nListo.\n";
