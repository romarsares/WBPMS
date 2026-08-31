<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class PayrollController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $identity    = AuthMiddleware::identity();
        $displayName = $identity['display_name'] ?? ($identity['username'] ?? '');
        $roleName    = $identity['role_name'] ?? '';
        $base        = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        $csrfField   = CsrfMiddleware::field();
        $flash       = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        $config = require APP_ROOT . '/config/database.php';
        $pdo    = (new Connection($config))->pdo();

        $runs = $pdo->query(
            "SELECT pr.payroll_run_id,
                    pp.period_label,
                    pp.period_start, pp.period_end,
                    b.branch_name,
                    pr.status,
                    pr.created_at,
                    pr.submitted_at,
                    pr.return_reason,
                    COUNT(pd.payroll_detail_id)          AS employee_count,
                    COALESCE(SUM(pd.gross_pay), 0)       AS gross_total,
                    COALESCE(SUM(pd.net_pay), 0)         AS net_total
               FROM payroll_run pr
               JOIN payroll_period pp ON pp.period_id   = pr.period_id
               JOIN branch b          ON b.branch_id    = pr.branch_id
               LEFT JOIN payroll_detail pd ON pd.payroll_run_id = pr.payroll_run_id
              GROUP BY pr.payroll_run_id, pp.period_label, pp.period_start,
                       pp.period_end, b.branch_name, pr.status,
                       pr.created_at, pr.submitted_at, pr.return_reason
              ORDER BY pr.created_at DESC
              LIMIT 100"
        )->fetchAll();

        $total   = count($runs);
        $draft   = count(array_filter($runs, fn($r) => in_array($r['status'], ['Draft', 'Computed'])));
        $pending = count(array_filter($runs, fn($r) => $r['status'] === 'PendingOwnerApproval'));
        $approved= count(array_filter($runs, fn($r) => $r['status'] === 'Approved'));

        $title      = 'Payroll';
        $activePage = 'payroll';
        $notifCount = $pending;

        ob_start();
        require APP_ROOT . '/resources/views/payroll/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
