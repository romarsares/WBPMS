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

        // Summary numbers for the stat cards
        $totalEmployees = (int) $pdo->query(
            "SELECT COUNT(*) FROM employee WHERE status = 'Active'"
        )->fetchColumn();

        $approvedPayroll = (int) $pdo->query(
            "SELECT COUNT(*) FROM payroll_run WHERE status = 'Approved'"
        )->fetchColumn();

        $approvedNetPay = (float) $pdo->query(
            "SELECT COALESCE(SUM(pd.net_pay), 0)
               FROM payroll_detail pd
               JOIN payroll_run pr ON pr.payroll_run_id = pd.payroll_run_id
              WHERE pr.status = 'Approved'"
        )->fetchColumn();

        $totalRequests = (int) $pdo->query(
            "SELECT COUNT(*) FROM request"
        )->fetchColumn();

        // Recent approved payroll runs for the summary table
        $payrollSummary = $pdo->query(
            "SELECT pp.period_label, b.branch_name,
                    COUNT(pd.payroll_detail_id) AS emp_count,
                    SUM(pd.gross_pay) AS gross, SUM(pd.net_pay) AS net,
                    pr.approved_at
               FROM payroll_run pr
               JOIN payroll_period pp ON pp.period_id = pr.period_id
               JOIN branch b          ON b.branch_id  = pr.branch_id
               LEFT JOIN payroll_detail pd ON pd.payroll_run_id = pr.payroll_run_id
              WHERE pr.status = 'Approved'
              GROUP BY pr.payroll_run_id, pp.period_label, b.branch_name, pr.approved_at
              ORDER BY pr.approved_at DESC
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
