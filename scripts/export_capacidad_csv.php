<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$file      = __DIR__ . '/../Diagramas_flujo/imports/Capacidad.xlsx';
$sheetName = $argv[1] ?? '06-05-2025';
$outCsv    = __DIR__ . '/../Diagramas_flujo/imports/05_capacidad_final.csv';
$outSql    = __DIR__ . '/../Diagramas_flujo/imports/05_capacidad_final.sql';

$reader = IOFactory::createReaderForFile($file);
$reader->setReadDataOnly(true);
$book = $reader->load($file);
$sheet = $book->getSheetByName($sheetName);
if (!$sheet) { fwrite(STDERR, "No existe hoja: $sheetName\n"); exit(1); }

// Normalizador
$normalize = function (string $s): string {
    $s = trim($s);
    $s = preg_replace('/^STS\s+/i', '', $s);
    $s = preg_replace('/\s+(CRIMP|C\/G|S\/G|H|10|\(.*\))\s*$/i', '', $s);
    $s = preg_replace('/\*+\s*$/', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return strtoupper(trim($s));
};

// Cargar parts
$partsExact = [];
$partsList  = [];
$f = fopen(__DIR__ . '/../Diagramas_flujo/imports/01_parts_final.csv', 'r');
fgetcsv($f);
$id = 1;
while (($r = fgetcsv($f)) !== false) {
    $description = $r[7] ?? '';
    $norm = $normalize($description);
    $partsExact[$norm] = ['id'=>$id, 'item_number'=>$r[2], 'description'=>$description];
    $partsList[] = ['id'=>$id, 'norm'=>$norm, 'item_number'=>$r[2], 'description'=>$description];
    $id++;
}
fclose($f);

// Helper: limpiar valor numerico (manejar "-", "N/A", "*", " ")
$cleanNum = function ($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-' || strtoupper($v) === 'N/A' || $v === '*') return null;
    if (!is_numeric($v)) return null;
    return (int)$v;
};

// Procesar filas
$rows = $sheet->getHighestRow();
$out = []; // [part_id, item_number, part_designator, description, u1, u2, u3, match_type]

for ($r = 6; $r <= $rows; $r++) {
    $designator = trim((string)$sheet->getCell('A' . $r)->getValue());
    if ($designator === '' || stripos($designator, 'total') !== false) continue;

    $u1 = $cleanNum($sheet->getCell('B' . $r)->getValue());
    $u2 = $cleanNum($sheet->getCell('C' . $r)->getValue());
    $u3 = $cleanNum($sheet->getCell('D' . $r)->getValue());

    // Skip rows where todas son null (sin datos productivos)
    if ($u1 === null && $u2 === null && $u3 === null) continue;

    // Detectar workstation_type por sufijo del designator
    $wsType = null;
    $designatorBase = $designator;
    if (preg_match('/\s+(MESA|MAQ|MAQUINA)\s*$/i', $designator, $m)) {
        $wsType = strtoupper($m[1]) === 'MESA' ? 'table' : 'machine';
        $designatorBase = trim(preg_replace('/\s+(MESA|MAQ|MAQUINA)\s*$/i', '', $designator));
    }

    // Match contra parts
    $key = $normalize($designatorBase);
    $hit = null; $matchType = '';
    if (isset($partsExact[$key])) {
        $hit = $partsExact[$key]; $matchType = 'exact';
    } else {
        $candidates = array_filter($partsList, fn($p) => $p['norm'] !== '' && str_starts_with($p['norm'], $key));
        if (count($candidates) === 1) {
            $hit = array_values($candidates)[0]; $matchType = 'prefix';
        } elseif (count($candidates) > 1) {
            $hit = array_values($candidates)[0]; $matchType = 'prefix-ambiguous';
        }
    }

    $out[] = [
        'part_id'        => $hit['id'] ?? null,
        'item_number'    => $hit['item_number'] ?? '',
        'part_designator'=> $designator,
        'description'    => $hit['description'] ?? '',
        'workstation_type' => $wsType,
        'units_per_hour_1p' => $u1,
        'units_per_hour_2p' => $u2,
        'units_per_hour_3p' => $u3,
        'match_type'     => $matchType ?: 'no-match',
    ];
}

// Escribir CSV
$fh = fopen($outCsv, 'w');
fwrite($fh, "\xEF\xBB\xBF"); // BOM UTF-8
fputcsv($fh, ['part_id','item_number','part_designator','description','workstation_type',
              'units_per_hour_1p','units_per_hour_2p','units_per_hour_3p','match_type']);
foreach ($out as $row) {
    fputcsv($fh, [
        $row['part_id'] ?? '',
        $row['item_number'],
        $row['part_designator'],
        $row['description'],
        $row['workstation_type'] ?? '',
        $row['units_per_hour_1p'] ?? '',
        $row['units_per_hour_2p'] ?? '',
        $row['units_per_hour_3p'] ?? '',
        $row['match_type'],
    ]);
}
fclose($fh);

// Escribir SQL (solo filas con part_id matched, formato standards-compatible)
$fh = fopen($outSql, 'w');
fwrite($fh, "-- Capacidad semanal de '{$sheetName}' importable como standards\n");
fwrite($fh, "-- Generado: " . date('Y-m-d H:i:s') . "\n");
fwrite($fh, "-- Cada fila u1/u2/u3 con valor genera 1 INSERT (3 personas configs distintas)\n\n");

$created = '2026-04-09 20:05:00';
$inserts = [];
foreach ($out as $row) {
    if (!$row['part_id']) continue;
    $partId = $row['part_id'];
    $desc = "'" . str_replace("'", "''", $row['description']) . "'";
    foreach (['1p'=>'persons_1', '2p'=>'persons_2', '3p'=>'persons_3'] as $sfx => $col) {
        $u = $row['units_per_hour_' . $sfx];
        if ($u === null) continue;
        $personsVals = [
            'persons_1' => $col === 'persons_1' ? '1' : 'NULL',
            'persons_2' => $col === 'persons_2' ? '2' : 'NULL',
            'persons_3' => $col === 'persons_3' ? '3' : 'NULL',
        ];
        $inserts[] = "(NULL,$partId,$u,NULL,NULL,NULL,$personsVals[persons_1],$personsVals[persons_2],$personsVals[persons_3],1,0,$desc,NULL,'$created',NULL)";
    }
}

if ($inserts) {
    fwrite($fh, "INSERT INTO `standards` (`id`,`part_id`,`units_per_hour`,`work_table_id`,`semi_auto_work_table_id`,`machine_id`,`persons_1`,`persons_2`,`persons_3`,`active`,`is_migrated`,`description`,`deleted_at`,`created_at`,`updated_at`) VALUES\n");
    fwrite($fh, implode(",\n", $inserts) . ";\n");
}
fclose($fh);

// Resumen
$matched = count(array_filter($out, fn($r) => $r['part_id']));
$unmatched = count($out) - $matched;
echo "=== Resumen ===\n";
echo "Hoja procesada: $sheetName\n";
echo "Filas con datos: " . count($out) . "\n";
echo "  Matched (con part_id): $matched\n";
echo "  Unmatched: $unmatched\n";
echo "Inserts generados (1 por persons-config): " . count($inserts) . "\n\n";
echo "Archivos:\n";
echo "  $outCsv\n";
echo "  $outSql\n";
