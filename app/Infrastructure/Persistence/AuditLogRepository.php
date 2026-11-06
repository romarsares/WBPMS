<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use Wbpms\Infrastructure\Database\Connection;

/**
 * AuditLogRepository — read-side repository for the audit_logs table.
 *
 * The write path lives in AuditRepository (which delegates to
 * AbstractRepository::writeAudit).  This repository provides the
 * paginated, filterable query used by AuditController / AuditTrailService.
 *
 * Columns returned per row:
 *   log_id, action_at (Manila local), user_id, username, role_name,
 *   event_type, action_performed, table_affected, record_id,
 *   attempted_identifier, ip_address, description.
 *
 * All time-display values are converted from UTC → Asia/Manila for the UI.
 */
final class AuditLogRepository extends AbstractRepository
{
    private const PAGE_SIZE = 50;

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
    }

    // -----------------------------------------------------------------------
    // Public query API
    // -----------------------------------------------------------------------

    /**
     * Return one page of audit log rows matching the supplied filters.
     *
     * @param  array{
     *           user_id?:     int|string|null,
     *           event_type?:  string|null,
     *           date_from?:   string|null,
     *           date_to?:     string|null,
     *           ip_address?:  string|null,
     *           table_affected?: string|null,
     *           search?:      string|null,
     *         } $filters
     * @param  int $page  1-based page number
     * @return list<array<string, mixed>>
     */
    public function findPaginated(array $filters = [], int $page = 1): array
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * self::PAGE_SIZE;

        [$where, $bindings] = $this->buildWhere($filters);

        $sql = "
            SELECT al.log_id,
                   CONVERT_TZ(al.action_at, '+00:00', '+08:00') AS action_at,
                   al.user_id,
                   u.username,
                   r.role_name,
                   al.event_type,
                   al.action_performed,
                   al.table_affected,
                   al.record_id,
                   al.attempted_identifier,
                   al.ip_address,
                   al.description
              FROM audit_logs al
              LEFT JOIN users u ON u.user_id = al.user_id
              LEFT JOIN role  r ON r.role_id  = u.role_id
            {$where}
             ORDER BY al.log_id DESC
             LIMIT " . self::PAGE_SIZE . " OFFSET {$offset}";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll();
    }

    /**
     * Return the total row count matching the same filters (for pagination UI).
     *
     * @param  array<string, mixed> $filters
     */
    public function countFiltered(array $filters = []): int
    {
        [$where, $bindings] = $this->buildWhere($filters);

        $sql = "
            SELECT COUNT(*)
              FROM audit_logs al
              LEFT JOIN users u ON u.user_id = al.user_id
            {$where}";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Return the distinct event_type values present in the table (for filter dropdown).
     *
     * @return list<string>
     */
    public function distinctEventTypes(): array
    {
        $rows = $this->pdo()
            ->query("SELECT DISTINCT event_type FROM audit_logs ORDER BY event_type")
            ->fetchAll();

        return array_column($rows, 'event_type');
    }

    /**
     * Return the distinct table_affected values present in the table (for filter dropdown).
     *
     * @return list<string>
     */
    public function distinctTablesAffected(): array
    {
        $rows = $this->pdo()
            ->query(
                "SELECT DISTINCT table_affected
                   FROM audit_logs
                  WHERE table_affected IS NOT NULL
                  ORDER BY table_affected"
            )
            ->fetchAll();

        return array_column($rows, 'table_affected');
    }

    /** Page size constant — exposed so the controller can compute total pages. */
    public function pageSize(): int
    {
        return self::PAGE_SIZE;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build a WHERE clause and its PDO bindings from the filter map.
     *
     * @param  array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $clauses  = [];
        $bindings = [];

        // Filter by user_id (exact match)
        if (!empty($filters['user_id'])) {
            $clauses[]              = 'al.user_id = :user_id';
            $bindings[':user_id']   = (int) $filters['user_id'];
        }

        // Filter by event_type (exact match from dropdown)
        if (!empty($filters['event_type'])) {
            $clauses[]               = 'al.event_type = :event_type';
            $bindings[':event_type'] = (string) $filters['event_type'];
        }

        // Filter by table_affected
        if (!empty($filters['table_affected'])) {
            $clauses[]                   = 'al.table_affected = :table_affected';
            $bindings[':table_affected'] = (string) $filters['table_affected'];
        }

        // Date range (inclusive) — compared against Manila-local action_at
        if (!empty($filters['date_from'])) {
            // Convert Manila midnight → UTC start for comparison
            $clauses[]                 = "CONVERT_TZ(al.action_at, '+00:00', '+08:00') >= :date_from";
            $bindings[':date_from']    = (string) $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $clauses[]                 = "CONVERT_TZ(al.action_at, '+00:00', '+08:00') <= :date_to";
            $bindings[':date_to']      = (string) $filters['date_to'] . ' 23:59:59';
        }

        // IP address partial match
        if (!empty($filters['ip_address'])) {
            $clauses[]                 = 'al.ip_address LIKE :ip_address';
            $bindings[':ip_address']   = '%' . $filters['ip_address'] . '%';
        }

        // Free-text search across action_performed, description, attempted_identifier, username
        if (!empty($filters['search'])) {
            $pattern = '%' . $filters['search'] . '%';
            $clauses[]                       = '(al.action_performed LIKE :search_action
                                    OR al.description LIKE :search_description
                                    OR al.attempted_identifier LIKE :search_identifier
                                    OR u.username LIKE :search_username)';
            $bindings[':search_action']      = $pattern;
            $bindings[':search_description'] = $pattern;
            $bindings[':search_identifier']  = $pattern;
            $bindings[':search_username']    = $pattern;
        }

        $where = $clauses !== [] ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$where, $bindings];
    }
}
