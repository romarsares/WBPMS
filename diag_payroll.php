<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT);
$dotenv->safeLoad();

// Force-set from .env if not already set (CLI may not inherit web env)
if (empty($_ENV['DB_PASSWORD'])) {
    $lines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
        putenv(trim($k) . '=' . trim($v));
    }
}

$cfg  = require 'config/database.php';
$conn = new Wbpms\Infrastructure\Database\Connection($cfg);
$pdo  = $conn->pdo();

echo "=== Testing payroll_period table columns ===\n";
try {
    $cols = $pdo->query("DESCRIBE payroll_period")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Testing payroll_run JOIN query ===\n";
try {
    $stmt = $pdo->query(
        "SELECT pr.payroll_run_id,
                pp.period_start,
                pp.period_end,
                pp.pay_date,
                b.branch_name,
                pr.status,
                pr.created_at,
                pr.submitted_at,
                pr.reviewed_at,
                pr.return_reason,
                pr.cancellation_reason,
                COUNT(p.payroll_id)           AS employee_count,
                COALESCE(SUM(p.gross_pay), 0) AS gross_total,
                COALESCE(SUM(p.net_pay), 0)   AS net_total
           FROM payroll_run pr
           JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
           JOIN branch b          ON b.branch_id          = pr.branch_id
           LEFT JOIN payroll p    ON p.payroll_run_id     = pr.payroll_run_id
          GROUP BY pr.payroll_run_id, pp.period_start, pp.period_end, pp.pay_date,
                   b.branch_name, pr.status, pr.created_at, pr.submitted_at,
                   pr.reviewed_at, pr.return_reason, pr.cancellation_reason
          ORDER BY pr.created_at DESC
          LIMIT 100"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "OK — " . count($rows) . " rows returned.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Testing PayrollService::listPeriods ===\n";
try {
    $svc     = new Wbpms\Application\PayrollService($conn);
    $periods = $svc->listPeriods();
    echo "OK — " . count($periods) . " periods.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nDone.\n";
