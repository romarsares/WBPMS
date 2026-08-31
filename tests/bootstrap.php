<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for WBPMS tests.
 *
 * Responsibilities:
 *  1. Autoload Composer classes.
 *  2. Load .env.testing (if it exists) or .env so that TEST_DB_PASSWORD and
 *     other environment variables are available to integration tests that
 *     open a real MySQL connection.
 *
 * The phpunit.xml <php><env> block provides defaults for CI environments
 * where .env is absent. Values already set in the process environment (e.g.
 * injected by the CI pipeline or by phpunit.xml) are NOT overwritten when
 * Dotenv is called with createImmutable().
 *
 * Integration tests require the wbpms_test schema to exist and have
 * all migrations applied:
 *   vendor/bin/phinx migrate -e testing
 */

// 1. Composer autoloader — always required.
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Load environment variables from .env.testing or .env.
//    We use createImmutable() so existing process variables take precedence
//    over file values; phpunit.xml <env> entries therefore always win in CI.
$root = dirname(__DIR__);

$candidates = [
    $root . '/.env.testing',
    $root . '/.env',
];

foreach ($candidates as $envFile) {
    if (file_exists($envFile)) {
        \Dotenv\Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
        break;
    }
}
