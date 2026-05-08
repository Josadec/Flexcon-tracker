<?php
/**
 * Reformatea 04_standards_final_v3_ready.csv para que coincida con el esquema
 * de la tabla `standards`:
 *   id, part_id, units_per_hour, work_table_id, semi_auto_work_table_id,
 *   machine_id, persons_1, persons_2, persons_3, active, is_migrated,
 *   description, deleted_at, created_at, updated_at
 *
 * Sobreescribe el archivo original con el formato correcto, usando el mismo
 * estilo de quoting y created_at que los CSVs de prices/parts.
 */

$input  = __DIR__ . '/../Diagramas_flujo/imports/04_standards_final_v3_ready.csv';
$output = $input; // sobreescribir

$createdAt = '2026-04-09 20:05:00';

if (!is_file($input)) {
    fwrite(STDERR, "No existe: $input\n");
    exit(1);
}

// Leer CSV (manejar BOM UTF-8)
$content = file_get_contents($input);
$bom = "\xEF\xBB\xBF";
if (substr($content, 0, 3) === $bom) {
    $content = substr($content, 3);
}

$lines = preg_split("/\r\n|\n|\r/", trim($content));
$header = str_getcsv(array_shift($lines));

// Mapear posicion de cada columna del CSV viejo
$col = array_flip($header);
$required = ['part_id','work_table_id','semi_auto_work_table_id','machine_id',
             'units_per_hour','persons_1','persons_2','persons_3','active',
             'is_migrated','description'];
foreach ($required as $r) {
    if (!isset($col[$r])) {
        fwrite(STDERR, "Falta columna en input: $r\n");
        exit(1);
    }
}

// Reescribir
$fh = fopen($output, 'w');

// Header en orden BD, con BOM UTF-8 (igual que parts/prices)
fwrite($fh, $bom);
fputcsv($fh, [
    'id','part_id','units_per_hour','work_table_id','semi_auto_work_table_id',
    'machine_id','persons_1','persons_2','persons_3','active','is_migrated',
    'description','deleted_at','created_at','updated_at',
]);

$count = 0;
foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $r = str_getcsv($line);
    if (count($r) < count($required)) continue;

    $row = [
        '',                              // id (auto-increment)
        $r[$col['part_id']],
        $r[$col['units_per_hour']],
        $r[$col['work_table_id']],
        $r[$col['semi_auto_work_table_id']],
        $r[$col['machine_id']],
        $r[$col['persons_1']],
        $r[$col['persons_2']],
        $r[$col['persons_3']],
        $r[$col['active']],
        $r[$col['is_migrated']],
        $r[$col['description']],
        '',                              // deleted_at
        $createdAt,                      // created_at
        '',                              // updated_at
    ];
    fputcsv($fh, $row);
    $count++;
}
fclose($fh);

echo "OK — $count filas reescritas en $output\n";
echo "Columnas: id, part_id, units_per_hour, work_table_id, semi_auto_work_table_id, machine_id, persons_1, persons_2, persons_3, active, is_migrated, description, deleted_at, created_at, updated_at\n";
