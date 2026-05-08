<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../Diagramas_flujo/imports/Capacidad.xlsx';
$sheetName = $argv[1] ?? '06-05-2025';

$reader = IOFactory::createReaderForFile($file);
$reader->setReadDataOnly(true);
$book = $reader->load($file);
$sheet = $book->getSheetByName($sheetName);
if (!$sheet) { fwrite(STDERR, "No existe hoja: $sheetName\n"); exit(1); }

// Normalizador: minusculas, sin "STS ", sin sufijos comunes, espacios colapsados
$normalize = function (string $s): string {
    $s = trim($s);
    $s = preg_replace('/^STS\s+/i', '', $s);
    // Quitar sufijos comunes que aparecen en descripciones de parts pero no en capacidad
    $s = preg_replace('/\s+(CRIMP|C\/G|S\/G|H|10|\(.*\))\s*$/i', '', $s);
    $s = preg_replace('/\*+\s*$/', '', $s);  // quitar *** finales
    $s = preg_replace('/\s+/', ' ', $s);
    return strtoupper(trim($s));
};

// 1) Leer parts: keys = exact, prefix
$partsExact = [];   // key normalizada exacta
$partsList  = [];   // lista de (id, normalizada, original) para prefix-match
$f = fopen(__DIR__ . '/../Diagramas_flujo/imports/01_parts_final.csv', 'r');
$header = fgetcsv($f);
$id = 1;
while (($r = fgetcsv($f)) !== false) {
    $number = $r[1];
    $itemNumber = $r[2];
    $description = $r[7] ?? '';
    $norm = $normalize($description);
    $partsExact[$norm] = [
        'id' => $id,
        'number' => $number,
        'item_number' => $itemNumber,
        'description' => $description,
    ];
    $partsList[] = [
        'id' => $id,
        'norm' => $norm,
        'item_number' => $itemNumber,
        'description' => $description,
    ];
    $id++;
}
fclose($f);
echo "Parts cargados: " . count($partsExact) . "\n";

// 2) Leer datos de la hoja Capacidad
$rows = $sheet->getHighestRow();
$matches = [];
$unmatched = [];

for ($r = 6; $r <= $rows; $r++) {
    $partDesignator = trim((string)$sheet->getCell('A' . $r)->getValue());
    if ($partDesignator === '' || stripos($partDesignator, 'total') !== false) continue;

    $u1 = $sheet->getCell('B' . $r)->getValue();
    $u2 = $sheet->getCell('C' . $r)->getValue();
    $u3 = $sheet->getCell('D' . $r)->getValue();

    $key = $normalize($partDesignator);

    $hit = null;
    $matchType = '';
    if (isset($partsExact[$key])) {
        $hit = $partsExact[$key];
        $matchType = 'exact';
    } else {
        // Prefix match: buscar parts cuya descripcion normalizada empiece con $key
        $candidates = [];
        foreach ($partsList as $p) {
            if (str_starts_with($p['norm'], $key) && $p['norm'] !== '') {
                $candidates[] = $p;
            }
        }
        if (count($candidates) === 1) {
            $hit = $candidates[0];
            $matchType = 'prefix-unique';
        } elseif (count($candidates) > 1) {
            // Multiple matches — registrar pero no elegir automaticamente
            $hit = $candidates[0];
            $matchType = 'prefix-ambiguous (' . count($candidates) . ')';
        }
    }

    if ($hit) {
        $matches[] = [
            'designator' => $partDesignator,
            'part_id' => $hit['id'],
            'item_number' => $hit['item_number'],
            'description' => $hit['description'],
            'u1' => $u1, 'u2' => $u2, 'u3' => $u3,
            'match_type' => $matchType,
        ];
    } else {
        $unmatched[] = [
            'designator' => $partDesignator,
            'u1' => $u1, 'u2' => $u2, 'u3' => $u3,
        ];
    }
}

echo "\n=== Matches por descripcion (sin prefijo STS) ===\n";
echo "Matched:    " . count($matches) . "\n";
echo "Unmatched:  " . count($unmatched) . "\n";

$byType = [];
foreach ($matches as $m) $byType[$m['match_type']] = ($byType[$m['match_type']] ?? 0) + 1;
echo "\nPor tipo de match:\n";
foreach ($byType as $t => $n) echo "  $t: $n\n";

echo "\n=== Sample matches ambiguos (primeros 8) ===\n";
$amb = array_filter($matches, fn($m) => str_starts_with($m['match_type'], 'prefix-ambiguous'));
foreach (array_slice($amb, 0, 8) as $m) {
    echo "  '$m[designator]' -> part_id=$m[part_id] desc='$m[description]' (".$m['match_type'].")\n";
}

echo "\n=== Unmatched (todos) ===\n";
foreach ($unmatched as $u) {
    echo "  '$u[designator]'  u1=$u[u1] u2=$u[u2] u3=$u[u3]\n";
}
