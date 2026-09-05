<?php
require 'vendor/autoload.php';
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
$spreadsheet = $reader->load('docs/Biometric_logs/Daily-Log-H20260830_1439aug.xls');
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();
echo "Total rows: " . count($rows) . "\n\n";
foreach ($rows as $i => $row) {
    if ($i === 0) continue; // skip header
    $dept = trim((string)$row[0]);
    $userId = trim((string)$row[1]);
    $name = trim((string)$row[2]);
    if ($name === '' || $userId === '') continue;
    echo "$dept | $userId | $name\n";
}
