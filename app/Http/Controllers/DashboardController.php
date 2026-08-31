<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use PDO;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * DashboardController — renders the role-specific dashboard for each role.
 *
 * Routes:
 *   GET /hr/dashboard       → hrDashboard()   [HRHead]
 *   GET /owner/dashboard    → ownerDashboard() [BusinessOwner]
 *   GET /employee/dashboard → empDashboard()  [Employee]
 *
 * REQ: 12.1 — Role-based dashboard summaries.
 * Live queries are kept intentionally simple for the P0 vertical slice.
 */
final class DashboardController
{
    /**
     * GET /hr/dashboard
     *
     * @param array<string, string> $params
     */
    public function hrDashboard(array $params = []): void
    {
        $connection = $this->makeConnection();
        $pdo        = $connection->pdo();

        $totalEmployees = (int) $pdo
            ->query("SELECT COUNT(*) FROM employee WHERE status = 'Active'")
            ->fetchColumn();

        $pendingRequests = (int) $pdo
            ->query("SELECT COUNT(*) FROM request WHERE status = 'Pending'")
            ->fetchColumn();

        // Incomplete attendance: rows where time_out IS NULL
        $incompleteAttendance = (int) $pdo
            ->query("SELECT COUNT(*) FROM attendance WHERE status = 'Incomplete'")
            ->fetchColumn();

        // Payroll runs in Draft or Computed state
        $payrollDrafts = (int) $pdo
            ->query("SELECT COUNT(*) FROM payroll_run WHERE status IN ('Draft','Computed')")
            ->fetchColumn();

        // Recent requests (last 10 pending)
        // request_type.type_name is the actual column name (migration 004)
        $stmt = $pdo->query(
            "SELECT r.request_id AS id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    rt.type_name AS type,
                    r.submitted_at,
                    r.status
             FROM request r
             JOIN employee e    ON e.employee_id          = r.employee_id
             JOIN request_type rt ON rt.request_type_id   = r.request_type_id
             WHERE r.status = 'Pending'
             ORDER BY r.submitted_at DESC
             LIMIT 10"
        );
        $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Payroll runs (last 10)
        // payroll_run uses payroll_period_id; payroll_period uses payroll_period_id
        $stmt = $pdo->query(
            "SELECT pr.payroll_run_id AS id,
                    b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period,
                    pr.status,
                    (SELECT COUNT(*) FROM payroll p WHERE p.payroll_run_id = pr.payroll_run_id)
                        AS employee_count
             FROM payroll_run pr
             JOIN branch b         ON b.branch_id           = pr.branch_id
             JOIN payroll_period pp ON pp.payroll_period_id  = pr.payroll_period_id
             ORDER BY pr.created_at DESC
             LIMIT 10"
        );
        $payrollRuns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('hr/dashboard', [
            'totalEmployees'       => $totalEmployees,
            'pendingRequests'      => $pendingRequests,
            'incompleteAttendance' => $incompleteAttendance,
            'payrollDrafts'        => $payrollDrafts,
            'recentRequests'       => $recentRequests,
            'payrollRuns'          => $payrollRuns,
        ], 'HR Dashboard');
    }

    /**
     * GET /owner/dashboard
     *
     * @param array<string, string> $params
     */
    public function ownerDashboard(array $params = []): void
    {
        $connection = $this->makeConnection();
        $pdo        = $connection->pdo();

        $pendingApprovals = (int) $pdo
            ->query("SELECT COUNT(*) FROM payroll_run WHERE status = 'PendingOwnerApproval'")
            ->fetchColumn();

        $approvedThisPeriod = (int) $pdo
            ->query("SELECT COUNT(*) FROM payroll_run WHERE status = 'Approved'")
            ->fetchColumn();

        $totalBranches = (int) $pdo
            ->query("SELECT COUNT(*) FROM branch WHERE status = 'Active'")
            ->fetchColumn();

        // Pending payroll runs awaiting owner approval (last 20)
        $stmt = $pdo->query(
            "SELECT pr.payroll_run_id AS id,
                    b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period,
                    pr.status,
                    pr.submitted_at,
                    (SELECT COUNT(*) FROM payroll p WHERE p.payroll_run_id = pr.payroll_run_id)
                        AS employee_count
             FROM payroll_run pr
             JOIN branch b         ON b.branch_id          = pr.branch_id
             JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
             WHERE pr.status = 'PendingOwnerApproval'
             ORDER BY pr.submitted_at DESC
             LIMIT 20"
        );
        $pendingRuns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recently approved runs (last 5)
        $stmt = $pdo->query(
            "SELECT b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period,
                    '0.00' AS gross_total,
                    '0.00' AS net_total,
                    pr.reviewed_at AS approved_at
             FROM payroll_run pr
             JOIN branch b         ON b.branch_id          = pr.branch_id
             JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
             WHERE pr.status = 'Approved'
             ORDER BY pr.reviewed_at DESC
             LIMIT 5"
        );
        $recentApproved = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('owner/dashboard', [
            'pendingApprovals'   => $pendingApprovals,
            'approvedThisPeriod' => $approvedThisPeriod,
            'totalBranches'      => $totalBranches,
            'currentPeriod'      => '',
            'pendingRuns'        => $pendingRuns,
            'recentApproved'     => $recentApproved,
        ], 'Owner Dashboard');
    }

    /**
     * GET /employee/dashboard
     *
     * @param array<string, string> $params
     */
    public function empDashboard(array $params = []): void
    {
        $identity   = AuthMiddleware::identity();
        $employeeId = $identity['employee_id'] ?? null;

        if ($employeeId === null) {
            ViewRenderer::render('employee/dashboard', [
                'employeeName'     => $identity['username'] ?? 'Unknown',
                'branchName'       => '—',
                'scheduleName'     => '—',
                'sickLeaveBalance' => 0,
                'pendingRequests'  => 0,
                'recentPayslips'   => [],
                'recentRequests'   => [],
            ], 'Dashboard');
            return;
        }

        $connection = $this->makeConnection();
        $pdo        = $connection->pdo();

        // Fetch employee basic info
        $stmt = $pdo->prepare(
            "SELECT CONCAT(first_name, ' ', last_name) AS full_name
             FROM employee WHERE employee_id = :id"
        );
        $stmt->execute([':id' => $employeeId]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        $employeeName = $emp ? (string) $emp['full_name'] : ($identity['username'] ?? '');

        // Current branch (most-recent open assignment)
        $stmt = $pdo->prepare(
            "SELECT b.branch_name
             FROM employee_branch_assignment eba
             JOIN branch b ON b.branch_id = eba.branch_id
             WHERE eba.employee_id = :id AND eba.effective_to IS NULL
             ORDER BY eba.effective_from DESC LIMIT 1"
        );
        $stmt->execute([':id' => $employeeId]);
        $row        = $stmt->fetch(PDO::FETCH_ASSOC);
        $branchName = $row ? (string) $row['branch_name'] : '—';

        // Current schedule
        $stmt = $pdo->prepare(
            "SELECT CONCAT(work_start_time, ' – ', work_end_time) AS schedule_name
             FROM work_schedule
             WHERE employee_id = :id AND effective_to IS NULL
             ORDER BY effective_from DESC LIMIT 1"
        );
        $stmt->execute([':id' => $employeeId]);
        $row          = $stmt->fetch(PDO::FETCH_ASSOC);
        $scheduleName = $row ? (string) $row['schedule_name'] : '—';

        // Pending own requests
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM request WHERE employee_id = :id AND status = 'Pending'"
        );
        $stmt->execute([':id' => $employeeId]);
        $pendingRequests = (int) $stmt->fetchColumn();

        // Recent requests (last 5) — type_name is the column (migration 004)
        $stmt = $pdo->prepare(
            "SELECT r.request_id AS id,
                    rt.type_name AS type,
                    r.submitted_at,
                    r.status
             FROM request r
             JOIN request_type rt ON rt.request_type_id = r.request_type_id
             WHERE r.employee_id = :id
             ORDER BY r.submitted_at DESC
             LIMIT 5"
        );
        $stmt->execute([':id' => $employeeId]);
        $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('employee/dashboard', [
            'employeeName'     => $employeeName,
            'branchName'       => $branchName,
            'scheduleName'     => $scheduleName,
            'sickLeaveBalance' => 4, // ADR-0001: 4 paid sick days; live balance deferred
            'pendingRequests'  => $pendingRequests,
            'recentPayslips'   => [],
            'recentRequests'   => $recentRequests,
        ], 'Dashboard');
    }

    // -----------------------------------------------------------------------

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }
}
