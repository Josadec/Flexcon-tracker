<?php
/**
 * Convierte 04_standards_final_v3_ready.csv a SQL con NULLs explicitos
 * para las columnas nullable (FKs y dates) — evita el problema de phpMyAdmin
 * que convierte celdas vacias a '' en lugar de NULL y rompe los FKs.
 *
 * Salida: Diagramas_flujo/imports/04_standards_final_v3_ready.sql
 */

$input  = __DIR__ . '/../Diagramas_flujo/imports/04_standards_final_v3_ready.csv';
$output = __DIR__ . '/../Diagramas_flujo/imports/04_standards_final_v3_ready.sql';

if (!is_file($input)) {
    fwrite(STDERR, "No existe: $input\n");
    exit(1);
}

// Leer CSV (manejar BOM)
$content = file_get_contents($input);
if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);
$lines  = preg_split("/\r\n|\n|\r/", trim($content));
$header = str_getcsv(array_shift($lines));

// Columnas que deben ser NULL si vienen vacias
$nullableCols = [
    'id', 'work_table_id', 'semi_auto_work_table_id', 'machine_id',
    'persons_1', 'persons_2', 'persons_3', 'description',
    'deleted_at', 'updated_at',
];
// Columnas que son string (NOT NULL o nullable) - necesitan quoting
$stringCols = ['description', 'deleted_at', 'created_at', 'updated_at'];

$colIdx = array_flip($header);

$fh = fopen($output, 'w');
fwrite($fh, "-- Importacion de standards (NULLs explicitos para evitar #1452)\n");
fwrite($fh, "-- Generado: " . date('Y-m-d H:i:s') . "\n\n");
fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n\n");

$count = 0;
$batch = [];
$batchSize = 100;

foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $r = str_getcsv($line);
    if (count($r) < count($header)) continue;

    $values = [];
    foreach ($header as $i => $col) {
        $v = $r[$i] ?? '';

        // NULL si esta vacia y la columna lo permite
        if ($v === '' && in_array($col, $nullableCols, true)) {
            $values[] = 'NULL';
            continue;
        }

        // String columns: escapar y poner comillas
        if (in_array($col, $stringCols, true)) {
            if ($v === '') {
                $values[] = "''";
            } else {
                $values[] = "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $v) . "'";
            }
            continue;
        }

        // Resto (numericas NOT NULL): valor literal sin quote
        if ($v === '') {
            // Caso raro: numerica NOT NULL vacia (no deberia pasar)
            $values[] = 'NULL';
        } else {
            $values[] = $v;
        }
    }

    $batch[] = '(' . implode(',', $values) . ')';
    $count++;

    if (count($batch) >= $batchSize) {
        fwrite($fh, "INSERT INTO `standards` (`" . implode('`,`', $header) . "`) VALUES\n");
        fwrite($fh, implode(",\n", $batch) . ";\n\n");
        $batch = [];
    }
}

if ($batch) {
    fwrite($fh, "INSERT INTO `standards` (`" . implode('`,`', $header) . "`) VALUES\n");
    fwrite($fh, implode(",\n", $batch) . ";\n");
}

fclose($fh);
echo "OK — $count INSERTs generados en $output\n";
echo "Importa este .sql en phpMyAdmin (pestana SQL > pegar contenido, o pestana Importar > formato SQL)\n";
