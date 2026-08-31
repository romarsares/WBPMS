<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO connection factory and transaction boundary helper.
 *
 * This is the only place in the application where a PDO handle is created.
 * All repositories receive a Connection instance via constructor injection.
 * Direct SQL outside repositories and migrations is prohibited (ADR-0003).
 */
final class Connection
{
    private ?PDO $pdo = null;

    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Return (or lazily open) the shared PDO handle for this connection.
     */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->open();
        }

        return $this->pdo;
    }

    /**
     * Execute $callback inside a single database transaction.
     *
     * Commits on success. On any Throwable, rolls back and re-throws so
     * callers can react to the original exception without a partial state.
     *
     * @template T
     * @param  callable(PDO): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Open and configure the PDO connection.
     *
     * Credentials are never included in thrown exception messages.
     */
    private function open(): PDO
    {
        $driver   = $this->config['driver']   ?? 'mysql';
        $host     = $this->config['host']     ?? '127.0.0.1';
        $port     = $this->config['port']     ?? 3306;
        $database = $this->config['database'] ?? '';
        $charset  = $this->config['charset']  ?? 'utf8mb4';

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';
        $options  = $this->config['options']  ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed. Check DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD.',
                (int) $e->getCode(),
                $e
            );
        }
    }
}
