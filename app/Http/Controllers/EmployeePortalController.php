<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Middleware\CsrfMiddleware;
use Wbpms\Infrastructure\Database\Connection;

/**
 * EmployeePortalController — self-service views scoped to the authenticated employee.
 *
 * All queries are bound to session.employee_id. No route parameter can
 * select another employee's data (no employee_id in the URL).
 */
final class EmployeePortalController
{
    // -----------------------------------------------------------------------
    // My Attendance — GET /my-attendance
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function myAttendance(array $params = []): void
    {
        [$identity, $pdo, $base, $csrfField, $flash] = $this->setup();
        $employeeId = (int) ($identity['employee_id'] ?? 0);

        $rows = [];
        if ($employeeId > 0) {
            $stmt = $pdo->prepare(
                "SELECT a.attendance_date, a.time_in, a.time_out,
                        a.worked_minutes, a.late_minutes,
                        a.undertime_minutes, a.overtime_minutes,
                        a.is_incomplete
                   FROM attendance a
                  WHERE a.employee_id = ?
                  ORDER BY a.attendance_date DESC
                  LIMIT 90"
            );
            $stmt->execute([$employeeId]);
            $rows = $stmt->fetchAll();
        }

        $total      = count($rows);
        $incomplete = count(array_filter($rows, fn($r) => (bool) $r['is_incomplete']));
        $present    = $total - $incomplete;
        $totalOT    = array_sum(array_column($rows, 'overtime_minutes'));

        $title      = 'My Attendance';
        $activePage = 'my-attendance';
        $notifCount = $incomplete;
        $displayName = $identity['display_name'] ?? $identity['username'];

        ob_start();
        require APP_ROOT . '/resources/views/employee/attendance.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }

    // -----------------------------------------------------------------------
    // My Requests — GET /my-requests
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function myRequests(array $params = []): void
    {
        [$identity, $pdo, $base, $csrfField, $flash] = $this->setup();
        $employeeId = (int) ($identity['employee_id'] ?? 0);

        $rows = [];
        if ($employeeId > 0) {
            $stmt = $pdo->prepare(
                "SELECT r.request_id, rt.type_name, r.status,
                        r.submitted_at, r.remarks, r.hr_note
                   FROM request r
                   JOIN request_type rt ON rt.request_type_id = r.request_type_id
                  WHERE r.employee_id = ?
                  ORDER BY r.submitted_at DESC"
            );
            $stmt->execute([$employeeId]);
            $rows = $stmt->fetchAll();
        }

        $total    = count($rows);
        $pending  = count(array_filter($rows, fn($r) => $r['status'] === 'Pending'));
        $approved = count(array_filter($rows, fn($r) => $r['status'] === 'Approved'));
        $rejected = count(array_filter($rows, fn($r) => $r['status'] === 'Rejected'));

        $title      = 'My Requests';
        $activePage = 'my-requests';
        $notifCount = $pending;
        $displayName = $identity['display_name'] ?? $identity['username'];

        ob_start();
        require APP_ROOT . '/resources/views/employee/requests.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }

    // -----------------------------------------------------------------------
    // My Payslips — GET /my-payslips
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function myPayslips(array $params = []): void
    {
        [$identity, $pdo, $base, $csrfField, $flash] = $this->setup();
        $employeeId = (int) ($identity['employee_id'] ?? 0);

        $rows = [];
        if ($employeeId > 0) {
            $stmt = $pdo->prepare(
                "SELECT pd.payroll_detail_id,
                        pp.period_label,
                        b.branch_name,
                        pd.gross_pay, pd.total_deductions, pd.net_pay,
                        pr.approved_at
                   FROM payroll_detail pd
                   JOIN payroll_run pr    ON pr.payroll_run_id = pd.payroll_run_id
                   JOIN payroll_period pp ON pp.period_id      = pr.period_id
                   JOIN branch b          ON b.branch_id       = pr.branch_id
                  WHERE pd.employee_id = ?
                    AND pr.status = 'Approved'
                  ORDER BY pr.approved_at DESC"
            );
            $stmt->execute([$employeeId]);
            $rows = $stmt->fetchAll();
        }

        $total      = count($rows);
        $totalNet   = array_sum(array_column($rows, 'net_pay'));
        $latestNet  = $rows[0]['net_pay'] ?? 0;

        $title      = 'My Payslips';
        $activePage = 'my-payslips';
        $notifCount = 0;
        $displayName = $identity['display_name'] ?? $identity['username'];

        ob_start();
        require APP_ROOT . '/resources/views/employee/payslips.php';
        $content = ob_get_clean();

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        require APP_ROOT . '/resources/views/layout.php';
    }

    // -----------------------------------------------------------------------
    // Shared setup helper
    // -----------------------------------------------------------------------

    /** @return array{0:array<string,mixed>,1:\PDO,2:string,3:string,4:array<mixed>} */
    private function setup(): array
    {
        $identity  = AuthMiddleware::identity() ?? [];
        $config    = require APP_ROOT . '/config/database.php';
        $pdo       = (new Connection($config))->pdo();
        $base      = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        $csrfField = CsrfMiddleware::field();
        $flash     = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return [$identity, $pdo, $base, $csrfField, $flash];
    }
}
