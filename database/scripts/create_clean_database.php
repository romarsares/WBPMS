<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$databaseName = $argv[1] ?? 'wbpms_clean';
if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
    throw new InvalidArgumentException('Database names may contain only letters, numbers, and underscores.');
}

$dotenv = Dotenv\Dotenv::createUnsafeMutable(dirname(__DIR__, 2));
$dotenv->load();

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s', $_ENV['DB_HOST'] ?? '127.0.0.1', $_ENV['DB_PORT'] ?? '3306'),
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $databaseName
));

echo "Database '{$databaseName}' is ready." . PHP_EOL;
