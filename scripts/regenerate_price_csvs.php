<?php
/**
 * Regenera los CSVs de prices y price_tiers separando correctamente
 * los registros por workstation_type (table / machine / semi_automatic).
 *
 * Entrada (lee, no modifica):
 *   - Diagramas_flujo/imports/02_prices_final.csv
 *   - Diagramas_flujo/imports/03_price_tiers_final.csv
 *   - Diagramas_flujo/imports/03_price_tiers_machine_final.csv
 *   - Diagramas_flujo/imports/03_price_tiers_semiauto_final.csv
 *
 * Salida (escribe nuevos archivos _v2):
 *   - Diagramas_flujo/imports/02_prices_final_v2.csv
 *   - Diagramas_flujo/imports/03_price_tiers_table_final.csv
 *   - Diagramas_flujo/imports/03_price_tiers_machine_final_v2.csv
 *   - Diagramas_flujo/imports/03_price_tiers_semiauto_final_v2.csv
 *
 * Reglas:
 *   - Rangos table estandar: (1000,10999), (11000,49999), (50000,99999), (100000,200000)
 *   - Rangos semi-auto: (2000,10999), (11000,200000)
 *   - Cualquier otro rango (o repeticion de table-range con tier_price distinto) = machine
 *   - Para parts cuyo unico tier es machine, NO se genera price 'table'.
 *   - tier_price siempre se guarda dividido entre 100 (formato BD: precio unitario).
 *   - sample_price queda como esta (0.5).
 */

$dir = __DIR__ . '/../Diagramas_flujo/imports';

$inputPrices         = "$dir/02_prices_final.csv";
$inputTiersAll       = "$dir/03_price_tiers_final.csv";
$inputTiersMachine   = "$dir/03_price_tiers_machine_final.csv";
$inputTiersSemiauto  = "$dir/03_price_tiers_semiauto_final.csv";

$outputPrices        = "$dir/02_prices_final_v2.csv";
$outputTiersTable    = "$dir/03_price_tiers_table_final.csv";
$outputTiersMachine  = "$dir/03_price_tiers_machine_final_v2.csv";
$outputTiersSemiauto = "$dir/03_price_tiers_semiauto_final_v2.csv";

// ---------- 1) Cargar prices viejos (1:1 part_id -> old_price_id) ----------
$oldPrices = []; // old_price_id => ['part_id'=>..., 'sample_price'=>..., 'effective_date'=>..., 'comments'=>...]
$fh = fopen($inputPrices, 'r');
$header = fgetcsv($fh); // descartar
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < 9) continue;
    [$id, $partId, $samplePrice, $wsType, $effDate, $active, $comments, $createdAt, $updatedAt] = $r;
    $oldPrices[(int)$partId] = [
        'old_price_id'   => (int)$partId, // mismo numero (1:1)
        'part_id'        => (int)$partId,
        'sample_price'   => $samplePrice,
        'effective_date' => $effDate,
        'active'         => $active,
        'comments'       => $comments,
        'created_at'     => $createdAt,
    ];
}
fclose($fh);
echo "Cargados " . count($oldPrices) . " prices viejos\n";

// ---------- 2) Cargar machine tiers (source of truth para machine) ----------
$machineTiersByPriceId = []; // old_price_id => [['min'=>..., 'max'=>..., 'tier_price'=>...], ...]
$fh = fopen($inputTiersMachine, 'r');
$header = fgetcsv($fh);
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < 5) continue;
    [$id, $priceId, $minQ, $maxQ, $tierPrice] = $r;
    if ($priceId === '') continue;
    $machineTiersByPriceId[(int)$priceId][] = [
        'min'        => (int)$minQ,
        'max'        => (int)$maxQ,
        'tier_price' => $tierPrice, // ya viene /100 (ej: 0.0853)
        'created_at' => $r[5] ?? '2026-04-09 20:05:00',
    ];
}
fclose($fh);
$totalMachineTiers = array_sum(array_map('count', $machineTiersByPriceId));
echo "Cargados $totalMachineTiers machine tiers para " . count($machineTiersByPriceId) . " parts\n";

// ---------- 3) Cargar semi-auto tiers ----------
$semiautoTiersByPriceId = [];
$fh = fopen($inputTiersSemiauto, 'r');
$header = fgetcsv($fh);
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < 5) continue;
    [$id, $priceId, $minQ, $maxQ, $tierPrice] = $r;
    if ($priceId === '') continue;
    $semiautoTiersByPriceId[(int)$priceId][] = [
        'min'        => (int)$minQ,
        'max'        => (int)$maxQ,
        'tier_price' => $tierPrice,
        'created_at' => $r[5] ?? '2026-04-09 20:05:00',
    ];
}
fclose($fh);
$totalSemiTiers = array_sum(array_map('count', $semiautoTiersByPriceId));
echo "Cargados $totalSemiTiers semi-auto tiers para " . count($semiautoTiersByPriceId) . " parts\n";

// ---------- 4) Cargar tiers viejos (mixed) ----------
$tableRanges = [
    '1000-10999' => true,
    '11000-49999' => true,
    '50000-99999' => true,
    '100000-200000' => true,
];

// Set de filas exactas que pertenecen a machine/semi-auto (price_id|min|max|tier_price_normalizado)
// Para deduplicar contra el viejo final.csv cuando los rangos coinciden con table-ranges
$machineSemiKeys = [];
foreach ($machineTiersByPriceId as $pid => $tiers) {
    foreach ($tiers as $t) {
        $tp = number_format((float)$t['tier_price'], 4, '.', '');
        $machineSemiKeys["{$pid}|{$t['min']}|{$t['max']}|{$tp}"] = true;
    }
}
foreach ($semiautoTiersByPriceId as $pid => $tiers) {
    foreach ($tiers as $t) {
        $tp = number_format((float)$t['tier_price'], 4, '.', '');
        $machineSemiKeys["{$pid}|{$t['min']}|{$t['max']}|{$tp}"] = true;
    }
}

$tableTiersByPriceId = []; // solo los rangos estandar table, con tier_price/100
$skippedAsMachineSemi = 0;
$fh = fopen($inputTiersAll, 'r');
$header = fgetcsv($fh);
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < 5) continue;
    [$id, $priceId, $minQ, $maxQ, $tierPrice] = $r;
    if ($priceId === '') continue;
    $key = $minQ . '-' . $maxQ;
    if (!isset($tableRanges[$key])) {
        // No es rango table estandar -> es machine o semi-auto, ignorar aqui
        continue;
    }
    // Es rango table -> dividir tier_price entre 100
    $tpDiv = number_format(((float)$tierPrice) / 100, 4, '.', '');
    // Dedup: si esta misma fila aparece en machine/semi-auto, NO incluirla en table
    $dedupKey = "{$priceId}|" . (int)$minQ . "|" . (int)$maxQ . "|{$tpDiv}";
    if (isset($machineSemiKeys[$dedupKey])) {
        $skippedAsMachineSemi++;
        continue;
    }
    $tableTiersByPriceId[(int)$priceId][] = [
        'min'        => (int)$minQ,
        'max'        => (int)$maxQ,
        'tier_price' => $tpDiv,
        'created_at' => $r[5] ?? '2026-04-09 20:05:00',
    ];
}
fclose($fh);
// Deduplicar dentro de table: si quedan filas con mismo (price_id, min, max),
// conservar la de tier_price MAYOR (regla conservadora: table > machine).
// Emitir warning con los casos para que el usuario revise manualmente.
$tableDupWarnings = [];
foreach ($tableTiersByPriceId as $pid => $tiers) {
    $byKey = [];
    foreach ($tiers as $t) {
        $k = $t['min'] . '|' . $t['max'];
        if (!isset($byKey[$k])) {
            $byKey[$k] = $t;
        } else {
            $existing = (float)$byKey[$k]['tier_price'];
            $candidate = (float)$t['tier_price'];
            if ($candidate > $existing) {
                $tableDupWarnings[] = "  price_id=$pid rango {$t['min']}-{$t['max']}: descartado tier_price={$byKey[$k]['tier_price']}, conservado {$t['tier_price']}";
                $byKey[$k] = $t;
            } else {
                $tableDupWarnings[] = "  price_id=$pid rango {$t['min']}-{$t['max']}: descartado tier_price={$t['tier_price']}, conservado {$byKey[$k]['tier_price']}";
            }
        }
    }
    $tableTiersByPriceId[$pid] = array_values($byKey);
}

$totalTableTiers = array_sum(array_map('count', $tableTiersByPriceId));
echo "Cargados $totalTableTiers table tiers para " . count($tableTiersByPriceId) . " parts (saltados $skippedAsMachineSemi como machine/semi-duplicados)\n";

if ($tableDupWarnings) {
    echo "\nWARNING — parts con tiers table duplicados en (price_id,min,max):\n";
    echo "(probablemente son tiers de machine que faltan en machine_final.csv)\n";
    foreach ($tableDupWarnings as $w) echo $w . "\n";
    echo "\n";
}

// ---------- 5) Construir nuevos prices con IDs secuenciales ----------
// Para cada part_id, determinar workstation_types presentes y emitir 1 fila por cada uno.
$newPrices = [];               // lista ordenada de filas para 02_prices_final_v2.csv
$mapping   = [];               // (part_id, ws_type) => new_price_id
$nextId    = 1;

ksort($oldPrices);
foreach ($oldPrices as $partId => $old) {
    $hasTable    = isset($tableTiersByPriceId[$partId]);
    $hasMachine  = isset($machineTiersByPriceId[$partId]);
    $hasSemiauto = isset($semiautoTiersByPriceId[$partId]);

    // Si no tiene NINGUN tier (algunas parts pueden estar asi), igual generamos table
    // para no perder el price record. Pero en este dataset, el viejo csv tiene 4 tiers
    // estandar para ~428 parts, asi que la mayoria entran por hasTable.
    if (!$hasTable && !$hasMachine && !$hasSemiauto) {
        // Mantener un price 'table' sin tiers (raro)
        $hasTable = true;
    }

    foreach (['table', 'machine', 'semi_automatic'] as $ws) {
        $present = match ($ws) {
            'table'           => $hasTable,
            'machine'         => $hasMachine,
            'semi_automatic'  => $hasSemiauto,
        };
        if (!$present) continue;

        $mapping["{$partId}|{$ws}"] = $nextId;
        $newPrices[] = [
            'id'               => '',
            'part_id'          => $partId,
            'sample_price'     => $old['sample_price'],
            'workstation_type' => $ws,
            'effective_date'   => $old['effective_date'],
            'active'           => $old['active'],
            'comments'         => $old['comments'],
            'created_at'       => $old['created_at'],
            'updated_at'       => '',
            '_new_id'          => $nextId,
        ];
        $nextId++;
    }
}
echo "Generados " . count($newPrices) . " prices nuevos (IDs 1.." . ($nextId - 1) . ")\n";

// ---------- 6) Escribir 02_prices_final_v2.csv ----------
$fh = fopen($outputPrices, 'w');
fputcsv($fh, ['id','part_id','sample_price','workstation_type','effective_date','active','comments','created_at','updated_at']);
foreach ($newPrices as $row) {
    fputcsv($fh, [
        $row['id'],
        $row['part_id'],
        $row['sample_price'],
        $row['workstation_type'],
        $row['effective_date'],
        $row['active'],
        $row['comments'],
        $row['created_at'],
        $row['updated_at'],
    ]);
}
fclose($fh);
echo "Escrito $outputPrices\n";

// ---------- 7) Escribir 03_price_tiers_table_final.csv ----------
$tableRows = 0;
$fh = fopen($outputTiersTable, 'w');
fputcsv($fh, ['id','price_id','min_quantity','max_quantity','tier_price','created_at','updated_at']);
foreach ($newPrices as $row) {
    if ($row['workstation_type'] !== 'table') continue;
    $partId = $row['part_id'];
    $newId  = $row['_new_id'];
    if (!isset($tableTiersByPriceId[$partId])) continue;
    foreach ($tableTiersByPriceId[$partId] as $t) {
        fputcsv($fh, ['', $newId, $t['min'], $t['max'], $t['tier_price'], $t['created_at'], '']);
        $tableRows++;
    }
}
fclose($fh);
echo "Escrito $outputTiersTable ($tableRows filas)\n";

// ---------- 8) Escribir 03_price_tiers_machine_final_v2.csv ----------
$machineRows = 0;
$fh = fopen($outputTiersMachine, 'w');
fputcsv($fh, ['id','price_id','min_quantity','max_quantity','tier_price','created_at','updated_at']);
foreach ($newPrices as $row) {
    if ($row['workstation_type'] !== 'machine') continue;
    $partId = $row['part_id'];
    $newId  = $row['_new_id'];
    if (!isset($machineTiersByPriceId[$partId])) continue;
    foreach ($machineTiersByPriceId[$partId] as $t) {
        fputcsv($fh, ['', $newId, $t['min'], $t['max'], $t['tier_price'], $t['created_at'], '']);
        $machineRows++;
    }
}
fclose($fh);
echo "Escrito $outputTiersMachine ($machineRows filas)\n";

// ---------- 9) Escribir 03_price_tiers_semiauto_final_v2.csv ----------
$semiRows = 0;
$fh = fopen($outputTiersSemiauto, 'w');
fputcsv($fh, ['id','price_id','min_quantity','max_quantity','tier_price','created_at','updated_at']);
foreach ($newPrices as $row) {
    if ($row['workstation_type'] !== 'semi_automatic') continue;
    $partId = $row['part_id'];
    $newId  = $row['_new_id'];
    if (!isset($semiautoTiersByPriceId[$partId])) continue;
    foreach ($semiautoTiersByPriceId[$partId] as $t) {
        fputcsv($fh, ['', $newId, $t['min'], $t['max'], $t['tier_price'], $t['created_at'], '']);
        $semiRows++;
    }
}
fclose($fh);
echo "Escrito $outputTiersSemiauto ($semiRows filas)\n";

// ---------- 10) Resumen ----------
echo "\n=== RESUMEN ===\n";
$byType = ['table' => 0, 'machine' => 0, 'semi_automatic' => 0];
foreach ($newPrices as $row) {
    $byType[$row['workstation_type']]++;
}
echo "Prices por workstation_type:\n";
foreach ($byType as $t => $n) {
    echo "  $t: $n\n";
}
echo "Total prices: " . count($newPrices) . "\n";
echo "Total tiers: " . ($tableRows + $machineRows + $semiRows) . " (table=$tableRows, machine=$machineRows, semi=$semiRows)\n";

// ---------- 11) Verificar partes que pierden el price 'table' ----------
$onlyMachine = [];
$onlySemi = [];
foreach ($oldPrices as $partId => $_) {
    $hasTable    = isset($tableTiersByPriceId[$partId]);
    $hasMachine  = isset($machineTiersByPriceId[$partId]);
    $hasSemiauto = isset($semiautoTiersByPriceId[$partId]);
    if (!$hasTable && $hasMachine && !$hasSemiauto) $onlyMachine[] = $partId;
    if (!$hasTable && !$hasMachine && $hasSemiauto) $onlySemi[] = $partId;
}
if ($onlyMachine) echo "Parts SOLO machine (sin table): " . implode(',', $onlyMachine) . "\n";
if ($onlySemi)    echo "Parts SOLO semi-auto: "         . implode(',', $onlySemi) . "\n";
