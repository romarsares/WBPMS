<?php

declare(strict_types=1);

/**
 * Phinx migration configuration.
 *
 * Run from the project root:
 *   vendor/bin/phinx migrate -c phinx.php           (development)
 *   vendor/bin/phinx migrate -c phinx.php -e testing
 *   vendor/bin/phinx seed:run -c phinx.php
 */

// Load .env when running Phinx from the CLI (outside front controller).
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__);
    $dotenv->load();
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
        'seeds'      => __DIR__ . '/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host'    => getenv('DB_HOST')     ?: '127.0.0.1',
            'name'    => getenv('DB_NAME')     ?: 'wbpms',
            'user'    => getenv('DB_USER')     ?: 'root',
            'pass'    => getenv('DB_PASSWORD') ?: '',
            'port'    => (int) (getenv('DB_PORT') ?: 3306),
            'charset' => 'utf8mb4',
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host'    => getenv('TEST_DB_HOST')     ?: '127.0.0.1',

            'name'    => getenv('TEST_DB_NAME')     ?: 'wbpms_test',

            'name'    => getenv('TEST_DB_NAME')     ?: 'wbpms',

            'user'    => getenv('TEST_DB_USER')     ?: 'root',
            'pass'    => getenv('TEST_DB_PASSWORD') ?: '',
            'port'    => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'charset' => 'utf8mb4',
        ],
        'production' => [
            'adapter' => 'mysql',
            'host'    => getenv('DB_HOST')     ?: '127.0.0.1',
            'name'    => getenv('DB_NAME')     ?: 'wbpms',
            'user'    => getenv('DB_USER')     ?: 'root',
            'pass'    => getenv('DB_PASSWORD') ?: '',
            'port'    => (int) (getenv('DB_PORT') ?: 3306),
            'charset' => 'utf8mb4',
        ],
    ],
    'version_order' => 'creation',
];
