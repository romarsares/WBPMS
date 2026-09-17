<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use Wbpms\Infrastructure\Database\Connection;

/**
 * AuditRepository — write-only repository for the audit_logs table.
 *
 * All create/update/approve/archive actions across modules must record an
 * audit event (task 14.2). This repository centralises that write so
 * application services do not duplicate the INSERT SQL.
 *
 * Audit rows are append-only and immutable. Only INSERT is provided here;
 * queries are routed through report repositories.
 *
 * ADR-0002 §10: user_id may be NULL for unauthenticated/failed-login events.
 */
final class AuditRepository extends AbstractRepository
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * Record an audit event.
     *
     * @param int|null    $userId              NULL for unauthenticated events
     * @param string      $eventType           Stable event code, e.g. 'login_failure'
     * @param string      $actionPerformed     Human-readable action, e.g. 'login_attempt'
     * @param string|null $tableAffected       Primary affected table, or NULL
     * @param int|null    $recordId            PK of the affected record, or NULL
     * @param string|null $attemptedIdentifier Username/email attempted for auth failures
     * @param string|null $requestId           UUID from X-Request-ID header
     * @param string|null $ipAddress           Client IP (IPv4 or IPv6)
     * @param string|null $userAgent           Truncated to 500 chars
     * @param string|null $description         Free-text context
     */
    public function record(
        ?int    $userId,
        string  $eventType,
        string  $actionPerformed,
        ?string $tableAffected       = null,
        ?int    $recordId            = null,
        ?string $attemptedIdentifier = null,
        ?string $requestId           = null,
        ?string $ipAddress           = null,
        ?string $userAgent           = null,
        ?string $description         = null
    ): void {
        $this->writeAudit(
            $userId,
            $eventType,
            $actionPerformed,
            $tableAffected,
            $recordId,
            $attemptedIdentifier,
            $requestId,
            $ipAddress,
            $userAgent,
            $description
        );
    }
}
