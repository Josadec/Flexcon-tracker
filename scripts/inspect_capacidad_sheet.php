<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../Diagramas_flujo/imports/Capacidad.xlsx';
$reader = IOFactory::createReaderForFile($file);
$reader->setReadDataOnly(true);
$book = $reader->load($file);

// Inspeccionar la hoja mas reciente '06-05-2025'
$sheetName = $argv[1] ?? '06-05-2025';
$sheet = $book->getSheetByName($sheetName);
if (!$sheet) { fwrite(STDERR, "No existe hoja: $sheetName\n"); exit(1); }

$rows = $sheet->getHighestRow();
$highCol = $sheet->getHighestColumn();
echo "=== Hoja: '$sheetName' ($rows filas, A..$highCol) ===\n";

// Mostrar las primeras 15 filas
$max = min(15, $rows);
for ($r = 1; $r <= $max; $r++) {
    $rowData = [];
    for ($c = 'A'; $c <= $highCol; $c++) {
        $rowData[] = $sheet->getCell($c . $r)->getValue();
        if ($c === $highCol) break;
    }
    echo "  R$r: " . json_encode($rowData, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== Filas 50..70 (zona de datos, sample) ===\n";
$start = min(50, $rows);
$end = min(70, $rows);
for ($r = $start; $r <= $end; $r++) {
    $rowData = [];
    for ($c = 'A'; $c <= $highCol; $c++) {
        $rowData[] = $sheet->getCell($c . $r)->getValue();
        if ($c === $highCol) break;
    }
    echo "  R$r: " . json_encode($rowData, JSON_UNESCAPED_UNICODE) . "\n";
}
