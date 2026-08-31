<?php

declare(strict_types=1);

/**
 * Database configuration.
 *
 * Returns the config array for the current APP_ENV.
 * MySQL strict mode is enforced at the connection level per ADR-0001.
 */

$env = $_ENV['APP_ENV'] ?? 'production';

$strictMode = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, "
    . "sql_mode='STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,"
    . "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'";

$baseOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // PDO::MYSQL_ATTR_INIT_COMMAND is deprecated in PHP 8.5; use the namespaced constant.
    Pdo\Mysql::ATTR_INIT_COMMAND => $strictMode,
];

$configs = [
    'development' => [
        'driver'   => 'mysql',
        'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
        'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_NAME']     ?? 'wbpms',
        'username' => $_ENV['DB_USER']     ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset'  => 'utf8mb4',
        'options'  => $baseOptions,
    ],
    'testing' => [
        'driver'   => 'mysql',
        'host'     => $_ENV['TEST_DB_HOST']     ?? '127.0.0.1',
        'port'     => (int) ($_ENV['TEST_DB_PORT'] ?? 3306),
        'database' => $_ENV['TEST_DB_NAME']     ?? 'wbpms_test',
        'username' => $_ENV['TEST_DB_USER']     ?? 'root',
        'password' => $_ENV['TEST_DB_PASSWORD'] ?? '',
        'charset'  => 'utf8mb4',
        'options'  => $baseOptions,
    ],
    'production' => [
        'driver'   => 'mysql',
        'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
        'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_NAME']     ?? 'wbpms',
        'username' => $_ENV['DB_USER']     ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset'  => 'utf8mb4',
        'options'  => $baseOptions,
    ],
];

return $configs[$env] ?? $configs['production'];
