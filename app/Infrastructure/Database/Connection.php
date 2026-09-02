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
 *
 * Not declared final so test doubles can extend it via getMockBuilder().
 */
class Connection
{
    private ?PDO $pdo = null;

    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config
     * @param PDO|null            $existing  Pass an already-open PDO to reuse it
     *                                       (used by PdoAttendanceImportGateway to
     *                                        wrap a bare PDO in a Connection for
     *                                        transaction() delegation).
     */
    public function __construct(array $config, ?PDO $existing = null)
    {
        $this->config = $config;
        $this->pdo    = $existing;
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
     * If the PDO handle is already inside a transaction (e.g. in integration
     * tests that wrap each test in a transaction for rollback isolation),
     * the callback is executed without opening a new transaction; the outer
     * transaction owner is responsible for commit/rollback.
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
        $pdo    = $this->pdo();
        $nested = $pdo->inTransaction();

        if (!$nested) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if (!$nested) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if (!$nested && $pdo->inTransaction()) {
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
