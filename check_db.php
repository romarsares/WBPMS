<?php
require 'vendor/autoload.php';
$_ENV['APP_ENV'] = 'development';
$cfg = require 'config/database.php';
$pdo = new PDO(
    "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4",
    $cfg['username'], $cfg['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== BRANCHES ===\n";
foreach ($pdo->query("SELECT branch_id, branch_code, branch_name FROM branch ORDER BY branch_id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  [{$r['branch_id']}] {$r['branch_code']} — {$r['branch_name']}\n";
}

echo "\n=== BIOMETRIC DEVICES ===\n";
foreach ($pdo->query("SELECT device_id, device_code, device_name FROM biometric_device ORDER BY device_id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  [{$r['device_id']}] {$r['device_code']} — {$r['device_name']}\n";
}

echo "\n=== EXISTING EMPLOYEES ===\n";
foreach ($pdo->query("SELECT employee_id, employee_number, first_name, last_name FROM employee ORDER BY employee_id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  [{$r['employee_id']}] {$r['employee_number']} — {$r['last_name']}, {$r['first_name']}\n";
}

echo "\n=== HR USER ID ===\n";
$hr = $pdo->query("SELECT user_id, username FROM users WHERE username = 'hrhead'")->fetch(PDO::FETCH_ASSOC);
echo "  user_id={$hr['user_id']} username={$hr['username']}\n";

echo "\n=== JOB POSITIONS (first 5) ===\n";
foreach ($pdo->query("SELECT position_id, position_title FROM job_position ORDER BY sort_order LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  [{$r['position_id']}] {$r['position_title']}\n";
}
