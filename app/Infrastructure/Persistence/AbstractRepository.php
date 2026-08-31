<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use PDO;
use Wbpms\Infrastructure\Database\Connection;

/**
 * Abstract base repository.
 *
 * Provides:
 *   - Access to the shared PDO connection via $this->pdo().
 *   - A delegating transaction() wrapper for multi-repository operations.
 *   - A writeAudit() convenience method so every repository can record
 *     audit events without duplicating the INSERT SQL.
 *
 * Concrete repositories must:
 *   - Inject Connection via constructor.
 *   - Own all SQL for their domain table(s) — no SQL in controllers or services.
 *   - Use prepared statements with bound parameters for every query.
 *
 * ADR-0003: PDO repositories own SQL and prepared statements.
 */
abstract class AbstractRepository
{
    protected Connection $connection;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
    }

    /**
     * Return the shared PDO handle.
     */
    protected function pdo(): PDO
    {
        return $this->connection->pdo();
    }

    /**
     * Execute a callable inside a database transaction.
     *
     * @template T
     * @param  callable(PDO): T $callback
     * @return T
     */
    protected function transaction(callable $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    /**
     * Write an entry to audit_logs.
     *
     * This method may be called from any repository for traceability.
     * Audit writes are best-effort: an audit failure does not roll back
     * the primary operation, but callers should wrap both in the same
     * transaction when atomicity is required.
     *
     * @param int|null    $userId
     * @param string      $eventType           e.g. 'login_success', 'payroll_approved'
     * @param string      $actionPerformed      e.g. 'login', 'approve_payroll'
     * @param string|null $tableAffected
     * @param int|null    $recordId
     * @param string|null $attemptedIdentifier  Only for auth failure events
     * @param string|null $requestId            UUID from X-Request-ID header
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string|null $description
     */
    protected function writeAudit(
        ?int    $userId,
        string  $eventType,
        string  $actionPerformed,
        ?string $tableAffected      = null,
        ?int    $recordId           = null,
        ?string $attemptedIdentifier = null,
        ?string $requestId          = null,
        ?string $ipAddress          = null,
        ?string $userAgent          = null,
        ?string $description        = null
    ): void {
        $actionAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $stmt = $this->pdo()->prepare(
            'INSERT INTO audit_logs
                (user_id, event_type, action_performed, table_affected, record_id,
                 attempted_identifier, request_id, ip_address, user_agent,
                 description, action_at, created_at)
             VALUES
                (:user_id, :event_type, :action, :table, :record_id,
                 :attempted, :req_id, :ip, :ua,
                 :desc, :action_at, :created_at)'
        );

        $stmt->execute([
            ':user_id'    => $userId,
            ':event_type' => $eventType,
            ':action'     => $actionPerformed,
            ':table'      => $tableAffected,
            ':record_id'  => $recordId,
            ':attempted'  => $attemptedIdentifier,
            ':req_id'     => $requestId,
            ':ip'         => $ipAddress,
            ':ua'         => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
            ':desc'       => $description,
            ':action_at'  => $actionAt,
            ':created_at' => $actionAt,
        ]);
    }
}
