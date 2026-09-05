<?php
require 'vendor/autoload.php';
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
$spreadsheet = $reader->load('docs/Biometric_logs/Daily-Log-H20260830_1439aug.xls');
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();
$total = count($rows);
echo "Total rows: $total\n\n";
foreach (array_slice($rows, 0, 60) as $i => $row) {
    echo $i . ': ' . implode(' | ', array_map(fn($v) => (string)$v, $row)) . "\n";
}
