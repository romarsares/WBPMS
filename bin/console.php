#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * WBPMS Console CLI — Database management commands for Railway deployment.
 *
 * Usage:
 *   php bin/console db:reseed        # Truncate all tables and re-run all seeders
 *   php bin/console db:clean         # Truncate all tables only (no seeding)
 *   php bin/console db:seed          # Run seeders only (no truncation)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Wbpms\Infrastructure\Database\Connection;

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createUnsafeMutable(__DIR__ . '/../');
    $dotenv->load();
}

/**
 * Database table truncation order (leaf tables first, respecting FK constraints).
 * Order derived from migration files and FK relationships.
 */
const TRUNCATION_ORDER = [
    // Payroll & Contribution leaf tables
    'cash_advance_repayment',
    'payslip',
    'contribution_record',
    'deduction',
    'payroll_earnings',
    'payroll',
    'payroll_run',

    // Attendance leaf tables
    'attendance_policy_flag',
    'attendance_adjustment',
    'attendance_punch',
    'attendance',
    'biometric_punch',
    'attendance_import_batch',

    // Leave & Cash Advance leaf tables
    'leave_ledger',
    'leave_entitlement',
    'cash_advance_history',
    'cash_advance_request_detail',
    'overtime_request_detail',
    'leave_request_detail',
    'request',

    // Employee relationship tables (effective-dated)
    'employment_contract_review',
    'employee_branch_assignment',
    'employee_biometric_enrollment',
    'work_schedule',
    'salary',

    // Contribution policy detail tables
    'sss_bracket',
    'philhealth_rate',
    'pagibig_rate',

    // Device/Branch coverage
    'biometric_device_branch',
    'biometric_device',

    // Address hierarchy
    'address',
    'barangay',
    'city',
    'province',

    // Core entity tables
    'employee',
    'users',

    // Organization tables
    'branch',
    'attendance_site',
    'holiday_calendar',

    // Payroll period & policy
    'payroll_period',
    'payroll_policy_version',
    'contribution_policy_version',

    // Lookup tables
    'request_type',
    'role',
    'job_position',

    // System tables
    'sessions',
    'audit_logs',
];

/**
 * Get database configuration from environment.
 */
function getDbConfig(): array
{
    return [
        'driver'   => 'mysql',
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'wbpms',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
        'options'  => [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ];
}

/**
 * Create a PDO connection.
 */
function createPdo(array $config): \PDO
{
    $dsn = sprintf(
        '%s:host=%s;port=%d;dbname=%s;charset=%s',
        $config['driver'],
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );

    return new \PDO($dsn, $config['username'], $config['password'], $config['options']);
}

/**
 * Truncate all tables in the correct order.
 */
function truncateTables(\PDO $pdo, bool $verbose = true): void
{
    if ($verbose) {
        echo "Disabling foreign key checks...\n";
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    try {
        foreach (TRUNCATION_ORDER as $table) {
            // Check if table exists before truncating (SHOW TABLES doesn't support prepared statements)
            $escapedTable = $pdo->quote($table);
            $stmt = $pdo->query("SHOW TABLES LIKE {$escapedTable}");
            if ($stmt->fetch()) {
                if ($verbose) {
                    echo "  Truncating {$table}...\n";
                }
                $pdo->exec("TRUNCATE TABLE `{$table}`");
            } elseif ($verbose) {
                echo "  Skipping {$table} (does not exist)\n";
            }
        }
    } finally {
        if ($verbose) {
            echo "Re-enabling foreign key checks...\n";
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    if ($verbose) {
        echo "All tables truncated successfully.\n";
    }
}

/**
 * Run all seeders using Phinx CLI.
 */
function runSeeders(bool $verbose = true): int
{
    $phinxBin = __DIR__ . '/../vendor/bin/phinx';
    $configFile = __DIR__ . '/../phinx.php';
    $cmd = "$phinxBin seed:run -c $configFile";

    if ($verbose) {
        echo "Running: $cmd\n";
        // Use passthru to show output in real-time, capture exit code via $return_var
        passthru($cmd, $returnCode);
        return $returnCode;
    } else {
        // Suppress output
        exec($cmd . ' 2>&1', $output, $returnCode);
        return $returnCode;
    }
}

/**
 * Main command handler.
 */
function handleCommand(string $command): int
{
    $pdo = createPdo(getDbConfig());

    switch ($command) {
        case 'db:clean':
            echo "Cleaning database (truncating all tables)...\n";
            truncateTables($pdo);
            echo "Database cleaned.\n";
            return 0;

        case 'db:seed':
            echo "Running database seeders...\n";
            $exitCode = runSeeders();
            if ($exitCode === 0) {
                echo "Seeders completed successfully.\n";
            } else {
                echo "Seeders failed with exit code {$exitCode}.\n";
            }
            return $exitCode;

        case 'db:reseed':
            echo "=== WBPMS Database Reseed ===\n";
            echo "Step 1/2: Cleaning database...\n";
            truncateTables($pdo);

            echo "\nStep 2/2: Running seeders...\n";
            $exitCode = runSeeders();

            if ($exitCode === 0) {
                echo "\n=== Database reseed completed successfully ===\n";
            } else {
                echo "\n=== Database reseed FAILED (exit code: {$exitCode}) ===\n";
            }
            return $exitCode;

        default:
            echo "Unknown command: {$command}\n\n";
            echo "Available commands:\n";
            echo "  db:clean   - Truncate all tables (no seeding)\n";
            echo "  db:seed    - Run all seeders (no truncation)\n";
            echo "  db:reseed  - Truncate all tables AND run all seeders\n";
            return 1;
    }
}

// ---------------------------------------------------------------------------
// Entry point
// ---------------------------------------------------------------------------

$command = $argv[1] ?? 'help';

if ($command === 'help' || $command === '--help' || $command === '-h') {
    echo "WBPMS Console - Database Management Commands\n\n";
    echo "Usage: php bin/console.php <command>\n\n";
    echo "Commands:\n";
    echo "  db:clean   - Truncate all tables (respects FK constraints)\n";
    echo "  db:seed    - Run all seeders in dependency order\n";
    echo "  db:reseed  - Full reset: truncate all tables then run all seeders\n";
    echo "               (Recommended for Railway deployment)\n";
    exit(0);
}

exit(handleCommand($command));