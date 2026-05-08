<?php
/**
 * Regenera:
 *   - 04_standards_final_v3_ready.csv  (1 fila por part_id × workstation_type)
 *   - 05_standard_configurations_final.csv  (N filas por standard, persons_required + u/h)
 *
 * Fuentes:
 *   - 02_prices_final_v2.csv  (define qué workstation_types tiene cada part)
 *   - 04_standards_final_v3_ready.csv  (formato legacy: persons_1/2/3 + u/h por fila)
 *
 * Reglas (confirmadas por el usuario):
 *   - Para parts con multi-ws, replicar el mismo (1p/2p/3p) para cada workstation_type
 *   - is_migrated=1 en standards (modelo nuevo)
 *   - persons_X y units_per_hour en standards quedan NULL/1 (la productividad real va en configurations)
 *   - is_default=1 en la config con menor persons_required
 *
 * Mapeo workstation_type:
 *   prices.table          ↔ standard_configurations.manual
 *   prices.machine        ↔ standard_configurations.machine
 *   prices.semi_automatic ↔ standard_configurations.semi_automatic
 */

$dir = __DIR__ . '/../Diagramas_flujo/imports';
$inputPrices    = "$dir/02_prices_final_v2.csv";
$inputLegacy    = "$dir/04_standards_final_v3_ready.csv";
$backupLegacy   = "$dir/04_standards_legacy_backup.csv";
$outputStd      = "$dir/04_standards_final_v3_ready.csv";  // sobreescribir
$outputConfigs  = "$dir/05_standard_configurations_final.csv";

// Si no existe el backup, crearlo (idempotencia: si re-corremos, leemos del backup)
if (!is_file($backupLegacy)) {
    copy($inputLegacy, $backupLegacy);
    echo "Backup creado: $backupLegacy\n";
}
// Siempre leer del backup (que tiene el formato legacy original)
$inputLegacy = $backupLegacy;

$createdAt = '2026-04-09 20:05:00';

// Helper: lee CSV con BOM
function readCsv(string $path): array {
    $content = file_get_contents($path);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);
    $lines = preg_split("/\r\n|\n|\r/", trim($content));
    $header = str_getcsv(array_shift($lines));
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $r = str_getcsv($line);
        if (count($r) < count($header)) continue;
        $rows[] = array_combine($header, array_slice($r, 0, count($header)));
    }
    return [$header, $rows];
}

// 1) Cargar prices: part_id => [ws_types]
[$h, $prices] = readCsv($inputPrices);
$pricesByPart = [];
foreach ($prices as $p) {
    $pricesByPart[(int)$p['part_id']][] = $p['workstation_type'];
}
foreach ($pricesByPart as $pid => $ws) {
    sort($pricesByPart[$pid]);
}
echo "Prices cargados: " . count($pricesByPart) . " parts con workstation_types\n";

// 2) Cargar legacy standards: part_id => [{persons_1, persons_2, persons_3, units_per_hour, description}, ...]
[$h, $legacyStd] = readCsv($inputLegacy);
$legacyByPart = [];
foreach ($legacyStd as $row) {
    $partId = (int)$row['part_id'];
    // Cada fila legacy tiene UNA persons_X poblada
    $personsReq = null;
    if ($row['persons_1'] !== '' && $row['persons_1'] !== '\N') $personsReq = (int)$row['persons_1'];
    elseif ($row['persons_2'] !== '' && $row['persons_2'] !== '\N') $personsReq = (int)$row['persons_2'];
    elseif ($row['persons_3'] !== '' && $row['persons_3'] !== '\N') $personsReq = (int)$row['persons_3'];

    $legacyByPart[$partId][] = [
        'persons_required' => $personsReq,
        'units_per_hour'   => (int)$row['units_per_hour'],
        'description'      => $row['description'],
    ];
}
echo "Legacy standards: " . count($legacyByPart) . " parts con configs\n";

// 3) Mapear workstation_type prices → standards
$wsMap = [
    'table'           => 'manual',
    'machine'         => 'machine',
    'semi_automatic'  => 'semi_automatic',
];

// 4) Generar standards y configurations
$standards = [];
$configs   = [];
$nextStdId    = 1;
$nextConfigId = 1;

ksort($pricesByPart);
foreach ($pricesByPart as $partId => $wsList) {
    // Descripcion de referencia: la primera del legacy o vacia
    $description = $legacyByPart[$partId][0]['description'] ?? '';
    $configsForPart = $legacyByPart[$partId] ?? [];

    foreach ($wsList as $wsPrice) {
        $wsStd = $wsMap[$wsPrice];
        $stdId = $nextStdId++;

        $standards[] = [
            'id'                       => $stdId,
            'part_id'                  => $partId,
            'units_per_hour'           => 1,                // placeholder (NOT NULL en BD)
            'work_table_id'            => null,
            'semi_auto_work_table_id'  => null,
            'machine_id'               => null,
            'persons_1'                => null,
            'persons_2'                => null,
            'persons_3'                => null,
            'active'                   => 1,
            'is_migrated'              => 1,
            'description'              => $description,
        ];

        if (empty($configsForPart)) continue;

        // Deduplicar por persons_required (legacy puede tener filas duplicadas
        // con misma persons_X). Si hay distinto u/h, gana el mayor.
        $byPersons = [];
        foreach ($configsForPart as $c) {
            if ($c['persons_required'] === null) continue;
            $p = $c['persons_required'];
            if (!isset($byPersons[$p]) || $c['units_per_hour'] > $byPersons[$p]['units_per_hour']) {
                $byPersons[$p] = $c;
            }
        }
        if (empty($byPersons)) continue;

        $minPersons = min(array_keys($byPersons));

        foreach ($byPersons as $p => $c) {
            $configs[] = [
                'id'                => $nextConfigId++,
                'standard_id'       => $stdId,
                'workstation_type'  => $wsStd,
                'workstation_id'    => null,
                'persons_required'  => $p,
                'units_per_hour'    => $c['units_per_hour'],
                'is_default'        => $p === $minPersons ? 1 : 0,
                'notes'             => null,
            ];
        }
    }
}

echo "\nGenerados:\n";
echo "  Standards: " . count($standards) . "\n";
echo "  Configurations: " . count($configs) . "\n";

// Distribucion
$wsCount = [];
foreach ($standards as $s) {
    // Buscar el ws desde prices (no esta en $s, recalculamos)
    // En realidad ya esta capturado por orden, pero para reporte:
}
// Skip: no critico

// 5) Escribir standards (mismo formato que antes: \N para NULLs, BOM, etc.)
$writeRow = function ($fh, array $cells) {
    $parts = [];
    foreach ($cells as $c) {
        $val = $c['value'] ?? '';
        switch ($c['type']) {
            case 'null': $parts[] = '\\N'; break;
            case 'num':  $parts[] = ($val === '' || $val === null) ? '\\N' : $val; break;
            case 'text':
                if ($val === '' || $val === null) {
                    $parts[] = '\\N';
                } else {
                    $parts[] = '"' . str_replace('"', '""', $val) . '"';
                }
                break;
            case 'raw':  $parts[] = $val; break;
        }
    }
    fwrite($fh, implode(',', $parts) . "\n");
};

$bom = "\xEF\xBB\xBF";

// === standards CSV ===
$fh = fopen($outputStd, 'w');
fwrite($fh, $bom);
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
foreach ($standards as $s) {
    $writeRow($fh, [
        ['type'=>'null'],                                            // id auto-inc
        ['type'=>'num',  'value'=>$s['part_id']],
        ['type'=>'num',  'value'=>$s['units_per_hour']],
        ['type'=>'num',  'value'=>$s['work_table_id']],
        ['type'=>'num',  'value'=>$s['semi_auto_work_table_id']],
        ['type'=>'num',  'value'=>$s['machine_id']],
        ['type'=>'num',  'value'=>$s['persons_1']],
        ['type'=>'num',  'value'=>$s['persons_2']],
        ['type'=>'num',  'value'=>$s['persons_3']],
        ['type'=>'num',  'value'=>$s['active']],
        ['type'=>'num',  'value'=>$s['is_migrated']],
        ['type'=>'text', 'value'=>$s['description']],
        ['type'=>'null'],
        ['type'=>'text', 'value'=>$createdAt],
        ['type'=>'null'],
    ]);
}
fclose($fh);
echo "Escrito: $outputStd\n";

// === configurations CSV ===
$fh = fopen($outputConfigs, 'w');
fwrite($fh, $bom);
$writeRow($fh, [
    ['type'=>'raw','value'=>'id'],
    ['type'=>'raw','value'=>'standard_id'],
    ['type'=>'raw','value'=>'workstation_type'],
    ['type'=>'raw','value'=>'workstation_id'],
    ['type'=>'raw','value'=>'persons_required'],
    ['type'=>'raw','value'=>'units_per_hour'],
    ['type'=>'raw','value'=>'is_default'],
    ['type'=>'raw','value'=>'notes'],
    ['type'=>'raw','value'=>'created_at'],
    ['type'=>'raw','value'=>'updated_at'],
]);
foreach ($configs as $c) {
    $writeRow($fh, [
        ['type'=>'null'],                                            // id auto-inc
        ['type'=>'num',  'value'=>$c['standard_id']],
        ['type'=>'text', 'value'=>$c['workstation_type']],
        ['type'=>'num',  'value'=>$c['workstation_id']],
        ['type'=>'num',  'value'=>$c['persons_required']],
        ['type'=>'num',  'value'=>$c['units_per_hour']],
        ['type'=>'num',  'value'=>$c['is_default']],
        ['type'=>'text', 'value'=>$c['notes']],
        ['type'=>'text', 'value'=>$createdAt],
        ['type'=>'null'],
    ]);
}
fclose($fh);
echo "Escrito: $outputConfigs\n";

// === Generar SQL equivalentes (NULL explicitos, sin necesidad de LOAD DATA) ===
$outputStdSql     = "$dir/04_standards_final_v3_ready.sql";
$outputConfigsSql = "$dir/05_standard_configurations_final.sql";

$esc = fn($s) => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$s) . "'";
$num = fn($v) => ($v === null || $v === '') ? 'NULL' : (string)$v;

$fh = fopen($outputStdSql, 'w');
fwrite($fh, "-- Standards (modelo nuevo: 1 fila por part_id × workstation_type, is_migrated=1)\n");
fwrite($fh, "-- Generado: " . date('Y-m-d H:i:s') . "\n\n");
fwrite($fh, "INSERT INTO `standards` (`id`,`part_id`,`units_per_hour`,`work_table_id`,`semi_auto_work_table_id`,`machine_id`,`persons_1`,`persons_2`,`persons_3`,`active`,`is_migrated`,`description`,`deleted_at`,`created_at`,`updated_at`) VALUES\n");
$batch = [];
foreach ($standards as $s) {
    $batch[] = sprintf(
        "(NULL,%s,%s,NULL,NULL,NULL,NULL,NULL,NULL,%s,%s,%s,NULL,'%s',NULL)",
        $num($s['part_id']), $num($s['units_per_hour']),
        $num($s['active']), $num($s['is_migrated']),
        $esc($s['description']),
        $createdAt
    );
}
fwrite($fh, implode(",\n", $batch) . ";\n");
fclose($fh);
echo "Escrito: $outputStdSql\n";

$fh = fopen($outputConfigsSql, 'w');
fwrite($fh, "-- Standard configurations (1 fila por persons_required de cada standard)\n");
fwrite($fh, "-- Importar DESPUES de standards (depende de standard_id auto-incrementado)\n");
fwrite($fh, "-- Generado: " . date('Y-m-d H:i:s') . "\n\n");
fwrite($fh, "INSERT INTO `standard_configurations` (`id`,`standard_id`,`workstation_type`,`workstation_id`,`persons_required`,`units_per_hour`,`is_default`,`notes`,`created_at`,`updated_at`) VALUES\n");
$batch = [];
foreach ($configs as $c) {
    $batch[] = sprintf(
        "(NULL,%s,%s,NULL,%s,%s,%s,NULL,'%s',NULL)",
        $num($c['standard_id']),
        $esc($c['workstation_type']),
        $num($c['persons_required']),
        $num($c['units_per_hour']),
        $num($c['is_default']),
        $createdAt
    );
}
fwrite($fh, implode(",\n", $batch) . ";\n");
fclose($fh);
echo "Escrito: $outputConfigsSql\n";

// Resumen final
echo "\n=== Verificacion ===\n";
$stdsByWs = [];
foreach ($pricesByPart as $partId => $wsList) {
    foreach ($wsList as $ws) {
        $stdsByWs[$ws] = ($stdsByWs[$ws] ?? 0) + 1;
    }
}
echo "Standards por workstation_type:\n";
foreach ($stdsByWs as $w => $n) echo "  $w: $n\n";
echo "Total standards: " . count($standards) . " (esperado: " . array_sum($stdsByWs) . ")\n";

// Configs sin defaults o configs con FK invalido
$standardsWithDefault = [];
foreach ($configs as $c) {
    if ($c['is_default']) $standardsWithDefault[$c['standard_id']] = true;
}
$standardsWithAnyConfig = [];
foreach ($configs as $c) $standardsWithAnyConfig[$c['standard_id']] = true;
echo "Standards con configs: " . count($standardsWithAnyConfig) . " / " . count($standards) . "\n";
echo "Standards con default config: " . count($standardsWithDefault) . " / " . count($standardsWithAnyConfig) . "\n";

// Configs por (workstation_type)
$cByWs = [];
foreach ($configs as $c) $cByWs[$c['workstation_type']] = ($cByWs[$c['workstation_type']] ?? 0) + 1;
echo "Configurations por workstation_type:\n";
foreach ($cByWs as $w => $n) echo "  $w: $n\n";
