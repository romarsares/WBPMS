<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * ReportsController — Reports management for HR and Business Owner.
 *
 * Routes:
 *   GET /hr/reports         → index()  [HRHead, BusinessOwner]
 *   GET /hr/reports/export  → export() [HRHead, BusinessOwner]
 *
 * REQ065–REQ073.
 */
final class ReportsController
{
    // -----------------------------------------------------------------------
    // GET /hr/reports
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        $totalEmployees = (int) $pdo->query(
            "SELECT COUNT(*) FROM employee WHERE status = 'Active'"
        )->fetchColumn();

        $approvedPayroll = (int) $pdo->query(
            "SELECT COUNT(*) FROM payroll_run WHERE status = 'Approved'"
        )->fetchColumn();

        $approvedNetPay = (float) $pdo->query(
            "SELECT COALESCE(SUM(p.net_pay), 0)
               FROM payroll p
               JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
              WHERE pr.status = 'Approved'"
        )->fetchColumn();

        $totalRequests = (int) $pdo->query(
            "SELECT COUNT(*) FROM request"
        )->fetchColumn();

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

        ViewRenderer::render('hr/reports/index', [
            'totalEmployees'  => $totalEmployees,
            'approvedPayroll' => $approvedPayroll,
            'approvedNetPay'  => $approvedNetPay,
            'totalRequests'   => $totalRequests,
            'payrollSummary'  => $payrollSummary,
        ], 'Reports');
    }

    // -----------------------------------------------------------------------
    // GET /hr/reports/export
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function export(array $params = []): void
    {
        $type = trim((string) ($_GET['type'] ?? ''));

        ViewRenderer::render('hr/reports/export', [
            'type'   => $type,
            'errors' => [],
        ], 'Export Report');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }
}
