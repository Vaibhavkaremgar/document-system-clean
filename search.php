<?php
require_once 'db.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$q    = trim($_GET['q'] ?? '');

$data = [];

if ($q === '') {
    echo json_encode($data);
    exit;
}

$rows = $sheetService->spreadsheets_values
    ->get($SPREADSHEET_ID, 'persons!A2:B')
    ->getValues() ?? [];

foreach ($rows as $r) {
    if (!isset($r[0], $r[1])) continue;

    $family = trim($r[0]);
    $name   = trim($r[1]);

    // 🔹 FAMILY SEARCH → return ALL matching names
    if ($type === 'family' && stripos($family, $q) !== false) {
        $data[] = [
            'family' => $family,
            'name'   => $name
        ];
    }

    // 🔹 NAME SEARCH → return ALL matching families
    if ($type === 'name' && stripos($name, $q) !== false) {
        $data[] = [
            'name'   => $name,
            'family' => $family
        ];
    }
}

echo json_encode($data);
