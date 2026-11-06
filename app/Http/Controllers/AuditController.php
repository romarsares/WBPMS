<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\AuditLogRepository;

/**
 * AuditController — Audit Trail viewer.
 *
 * Routes:
 *   GET /hr/audit  → index() [HRHead, BusinessOwner]
 *
 * Displays a paginated, filterable read-only view of the audit_logs table.
 * Writing audit records is handled by AuditRepository / AbstractRepository::writeAudit().
 *
 * Access: HRHead and BusinessOwner only. Employees have no access.
 */
final class AuditController
{
    // -----------------------------------------------------------------------
    // GET /hr/audit
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $repo    = new AuditLogRepository($this->makeConnection());
        $filters = $this->extractFilters();
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $rows       = $repo->findPaginated($filters, $page);
        $total      = $repo->countFiltered($filters);
        $pageSize   = $repo->pageSize();
        $totalPages = (int) ceil($total / $pageSize);

        $eventTypes     = $repo->distinctEventTypes();
        $tablesAffected = $repo->distinctTablesAffected();

        // Build a user list for the user_id dropdown — only users that actually
        // appear in audit_logs are included to keep the list meaningful.
        $pdo   = $this->makeConnection()->pdo();
        $users = $pdo->query(
            "SELECT DISTINCT u.user_id, u.username
               FROM audit_logs al
               JOIN users u ON u.user_id = al.user_id
              ORDER BY u.username"
        )->fetchAll();

        ViewRenderer::render('hr/audit/index', [
            'rows'           => $rows,
            'total'          => $total,
            'page'           => $page,
            'totalPages'     => $totalPages,
            'filters'        => $filters,
            'eventTypes'     => $eventTypes,
            'tablesAffected' => $tablesAffected,
            'users'          => $users,
            'pageSize'       => $pageSize,
            'activePage'     => 'reports',
        ], 'Audit Trail');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Extract and sanitise filter parameters from $_GET.
     *
     * @return array<string, string|int|null>
     */
    private function extractFilters(): array
    {
        $str = static fn(string $key): string => trim((string) ($_GET[$key] ?? ''));

        return [
            'user_id'        => $str('user_id') !== '' ? (int) $str('user_id') : null,
            'event_type'     => $str('event_type')     ?: null,
            'table_affected' => $str('table_affected') ?: null,
            'date_from'      => $str('date_from')      ?: null,
            'date_to'        => $str('date_to')        ?: null,
            'ip_address'     => $str('ip_address')     ?: null,
            'search'         => $str('search')         ?: null,
        ];
    }

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }
}
