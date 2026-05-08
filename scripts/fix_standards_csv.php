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

// Helper: arma una linea CSV manualmente con quoting controlado
// - $cells es array de ['type'=>'null|num|text', 'value'=>...]
// - 'null'  => escribe \N (sin comillas)
// - 'num'   => escribe el numero crudo (sin comillas), o \N si es vacio
// - 'text'  => escribe "valor" (con comillas, escapando " interno)
$writeRow = function ($fh, array $cells) {
    $parts = [];
    foreach ($cells as $c) {
        $val = $c['value'] ?? '';
        switch ($c['type']) {
            case 'null':
                $parts[] = '\\N';
                break;
            case 'num':
                $parts[] = ($val === '' || $val === null) ? '\\N' : $val;
                break;
            case 'text':
                if ($val === '' || $val === null) {
                    $parts[] = '\\N';
                } else {
                    $parts[] = '"' . str_replace('"', '""', $val) . '"';
                }
                break;
            case 'raw':
                // Para el header (sin comillas, valor literal)
                $parts[] = $val;
                break;
        }
    }
    fwrite($fh, implode(',', $parts) . "\n");
};

// Reescribir
$fh = fopen($output, 'w');
fwrite($fh, $bom);

// Header sin quoting (igual estilo que el original)
$writeRow($fh, [
    ['type'=>'raw','value'=>'id'],
    ['type'=>'raw','value'=>'part_id'],
    ['type'=>'raw','value'=>'units_per_hour'],
    ['type'=>'raw','value'=>'work_table_id'],
    ['type'=>'raw','value'=>'semi_auto_work_table_id'],
    ['type'=>'raw','value'=>'machine_id'],
    ['type'=>'raw','value'=>'persons_1'],
    ['type'=>'raw','value'=>'persons_2'],
    ['type'=>'raw','value'=>'persons_3'],
    ['type'=>'raw','value'=>'active'],
    ['type'=>'raw','value'=>'is_migrated'],
    ['type'=>'raw','value'=>'description'],
    ['type'=>'raw','value'=>'deleted_at'],
    ['type'=>'raw','value'=>'created_at'],
    ['type'=>'raw','value'=>'updated_at'],
]);

$count = 0;
foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $r = str_getcsv($line);
    if (count($r) < count($required)) continue;

    $writeRow($fh, [
        ['type'=>'null'],                                                       // id (auto-increment)
        ['type'=>'num',  'value'=>$r[$col['part_id']]],                          // NOT NULL
        ['type'=>'num',  'value'=>$r[$col['units_per_hour']]],                   // NOT NULL
        ['type'=>'num',  'value'=>$r[$col['work_table_id']]],                    // FK nullable
        ['type'=>'num',  'value'=>$r[$col['semi_auto_work_table_id']]],          // FK nullable
        ['type'=>'num',  'value'=>$r[$col['machine_id']]],                       // FK nullable
        ['type'=>'num',  'value'=>$r[$col['persons_1']]],                        // nullable
        ['type'=>'num',  'value'=>$r[$col['persons_2']]],                        // nullable
        ['type'=>'num',  'value'=>$r[$col['persons_3']]],                        // nullable
        ['type'=>'num',  'value'=>$r[$col['active']]],                           // NOT NULL (default 1)
        ['type'=>'num',  'value'=>$r[$col['is_migrated']]],                      // NOT NULL (default 0)
        ['type'=>'text', 'value'=>$r[$col['description']]],                      // text nullable
        ['type'=>'null'],                                                        // deleted_at
        ['type'=>'text', 'value'=>$createdAt],                                   // created_at NOT NULL
        ['type'=>'null'],                                                        // updated_at
    ]);
    $count++;
}
fclose($fh);

echo "OK — $count filas reescritas en $output (con \\N para NULLs)\n";
echo "\nIMPORTANTE: para importar este CSV en phpMyAdmin debes:\n";
echo "  1. Pestana 'Importar'\n";
echo "  2. Formato: 'CSV usando LOAD DATA' (NO 'CSV' a secas)\n";
echo "  3. LOAD DATA reconoce \\N como NULL nativamente\n";
