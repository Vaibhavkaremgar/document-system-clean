<?php
require_once 'db.php'; // contains $sheetService & $SPREADSHEET_ID

header("Content-Type: application/json");

$type = $_GET['type'] ?? '';
$q    = trim($_GET['q'] ?? '');

if ($q === '' || !in_array($type, ['family', 'name'])) {
    echo json_encode([]);
    exit;
}

$rows = $sheetService
    ->spreadsheets_values
    ->get($SPREADSHEET_ID, 'persons!A2:B')
    ->getValues() ?? [];

$result = [];
$seen   = [];

foreach ($rows as $r) {
    if (!isset($r[0], $r[1])) continue;

    $family = trim($r[0]);
    $name   = trim($r[1]);

    // Case 1: typing FAMILY CODE → show names
    if ($type === 'family' && stripos($family, $q) !== false) {
        $key = $family . '|' . $name;
        if (!isset($seen[$key])) {
            $result[] = [
                'family' => $family,
                'name'   => $name
            ];
            $seen[$key] = true;
        }
    }

    // Case 2: typing NAME → show families
    if ($type === 'name' && stripos($name, $q) !== false) {
        $key = $family . '|' . $name;
        if (!isset($seen[$key])) {
            $result[] = [
                'family' => $family,
                'name'   => $name
            ];
            $seen[$key] = true;
        }
    }
}

echo json_encode($result);
