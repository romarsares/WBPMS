<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

final class ReportsController
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

        // Canonical schema v1.1:
        //   payroll_run has NO approved_at column — reviewed_at is used when status=Approved.
        //   employee-level totals live in the 'payroll' table (not payroll_detail).
        //   payroll_period uses payroll_period_id and has period_start, period_end, pay_date.
        //   — period_label, period_id, payroll_detail do NOT exist.

        $totalEmployees = (int) $pdo->query(
            "SELECT COUNT(*) FROM employee WHERE status = 'Active'"
        )->fetchColumn();

        $approvedPayroll = (int) $pdo->query(
            "SELECT COUNT(*) FROM payroll_run WHERE status = 'Approved'"
        )->fetchColumn();

        // Sum net pay from employee-level payroll rows for approved runs.
        $approvedNetPay = (float) $pdo->query(
            "SELECT COALESCE(SUM(p.net_pay), 0)
               FROM payroll p
               JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
              WHERE pr.status = 'Approved'"
        )->fetchColumn();

        $totalRequests = (int) $pdo->query(
            "SELECT COUNT(*) FROM request"
        )->fetchColumn();

        // Recent approved payroll runs summary.
        // reviewed_at is the approval timestamp (set when status = Approved).
        $payrollSummary = $pdo->query(
            "SELECT pp.period_start,
                    pp.period_end,
                    b.branch_name,
                    COUNT(p.payroll_id)   AS emp_count,
                    SUM(p.gross_pay)      AS gross,
                    SUM(p.net_pay)        AS net,
                    pr.reviewed_at        AS approved_at
               FROM payroll_run pr
               JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
               JOIN branch b          ON b.branch_id          = pr.branch_id
               LEFT JOIN payroll p    ON p.payroll_run_id     = pr.payroll_run_id
              WHERE pr.status = 'Approved'
              GROUP BY pr.payroll_run_id, pp.period_start, pp.period_end,
                       b.branch_name, pr.reviewed_at
              ORDER BY pr.reviewed_at DESC
              LIMIT 20"
        )->fetchAll();

        $title      = 'Reports';
        $activePage = 'reports';
        $notifCount = 0;

        ob_start();
        require APP_ROOT . '/resources/views/reports/index.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }
}
