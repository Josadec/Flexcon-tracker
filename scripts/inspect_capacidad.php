<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../Diagramas_flujo/imports/Capacidad.xlsx';
if (!is_file($file)) { fwrite(STDERR, "No existe: $file\n"); exit(1); }

$reader = IOFactory::createReaderForFile($file);
$reader->setReadDataOnly(true);
$book = $reader->load($file);

echo "=== Hojas en Capacidad.xlsx ===\n";
foreach ($book->getSheetNames() as $name) {
    $sheet = $book->getSheetByName($name);
    $rows = $sheet->getHighestRow();
    $cols = $sheet->getHighestColumn();
    echo "  - '$name'  ($rows filas x columnas A..$cols)\n";
}

echo "\n=== Headers + primeras 3 filas de cada hoja ===\n";
foreach ($book->getSheetNames() as $name) {
    $sheet = $book->getSheetByName($name);
    $rows = $sheet->getHighestRow();
    $highCol = $sheet->getHighestColumn();
    echo "\n--- HOJA: '$name' ---\n";
    $maxRow = min(4, $rows);
    for ($r = 1; $r <= $maxRow; $r++) {
        $rowData = [];
        for ($c = 'A'; $c <= $highCol; $c++) {
            $rowData[] = $sheet->getCell($c . $r)->getValue();
            if ($c === $highCol) break;
        }
        echo "  R$r: " . json_encode($rowData, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
