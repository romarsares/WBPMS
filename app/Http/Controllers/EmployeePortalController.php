<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use PDO;
use Wbpms\Application\EmployeePortal\EmployeeScope;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * EmployeePortalController — employee self-service views.
 *
 * Routes (all require Employee role):
 *   GET  /employee/attendance        → attendance()
 *   GET  /employee/requests          → requests()
 *   GET  /employee/requests/new      → requestForm()
 *   POST /employee/requests          → storeRequest()
 *   GET  /employee/payslips          → payslips()
 *   GET  /employee/payslips/{id}     → payslipDetail()
 *
 * REQ074–REQ082 (P0 view subset).
 * B4: employee self-service portal controller.
 *
 * Schema notes (from migrations 003–005):
 *   attendance: hours_worked_minutes, late_minutes, undertime_minutes, overtime_minutes
 *   request_type: type_name
 *   request: reason, review_notes, submitted_at
 *   payroll_period: payroll_period_id (PK)
 *   payroll_run: payroll_period_id (FK)
 */
final class EmployeePortalController
{
    private EmployeeScope $scope;

    public function __construct()
    {
        $this->scope = new EmployeeScope();
    }

    /**
     * GET /employee/attendance
     *
     * REQ075: own attendance view, scoped to session.employee_id.
     * Variable contract: resources/views/employee/attendance.php
     *   expects $attendance (list of rows with camelCase keys) and $month.
     *
     * @param array<string, string> $params
     */
    public function attendance(array $params = []): void
    {
        $employeeId = $this->requireEmployeeId();
        $pdo        = $this->makeConnection()->pdo();

        $month = trim((string) ($_GET['month'] ?? date('Y-m')));

        $stmt = $pdo->prepare(
            "SELECT attendance_date                      AS date,
                    time_in                              AS timeIn,
                    time_out                             AS timeOut,
                    COALESCE(hours_worked_minutes, 0)    AS workedMinutes,
                    COALESCE(late_minutes, 0)            AS lateMinutes,
                    COALESCE(undertime_minutes, 0)       AS undertimeMinutes,
                    COALESCE(overtime_minutes, 0)        AS overtimeMinutes
             FROM attendance
             WHERE employee_id = :emp_id
               AND DATE_FORMAT(attendance_date, '%Y-%m') = :month
             ORDER BY attendance_date ASC"
        );
        $stmt->execute([':emp_id' => $employeeId, ':month' => $month]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add empty flags array — policy flags are in attendance_policy_flag;
        // deferred until AttendanceService is implemented
        $attendance = array_map(static function (array $row): array {
            $row['flags'] = [];
            return $row;
        }, $rows);

        ViewRenderer::render('employee/attendance', [
            'attendance' => $attendance,
            'month'      => $month,
        ], 'My Attendance');
    }

    /**
     * GET /employee/requests
     *
     * @param array<string, string> $params
     */
    public function requests(array $params = []): void
    {
        $employeeId = $this->requireEmployeeId();
        $pdo        = $this->makeConnection()->pdo();

        $filterStatus = trim((string) ($_GET['status'] ?? ''));
        $whereStatus  = $filterStatus !== '' ? 'AND r.status = :status' : '';
        $queryParams  = [':emp_id' => $employeeId];
        if ($filterStatus !== '') {
            $queryParams[':status'] = $filterStatus;
        }

        // request_type.type_name is the actual column; request.reason for summary;
        // request.review_notes for hr_note
        $stmt = $pdo->prepare(
            "SELECT r.request_id         AS id,
                    rt.type_name         AS type,
                    r.submitted_at,
                    r.status,
                    COALESCE(r.reason, '') AS summary,
                    r.review_notes         AS hr_note
             FROM request r
             JOIN request_type rt ON rt.request_type_id = r.request_type_id
             WHERE r.employee_id = :emp_id
             {$whereStatus}
             ORDER BY r.submitted_at DESC
             LIMIT 50"
        );
        $stmt->execute($queryParams);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('employee/requests/index', [
            'requests'     => $requests,
            'filterStatus' => $filterStatus,
        ], 'My Requests');
    }

    /**
     * GET /employee/requests/new
     *
     * @param array<string, string> $params
     */
    public function requestForm(array $params = []): void
    {
        $this->requireEmployeeId();
        $pdo = $this->makeConnection()->pdo();

        // View contract: $requestTypes with id, name, code (migration 004)
        // type_name values: Leave, Overtime, CashAdvance
        $stmt = $pdo->query(
            "SELECT request_type_id AS id,
                    type_name       AS name,
                    LOWER(REPLACE(type_name, 'A', '_a')) AS code
             FROM request_type WHERE status = 'Active' ORDER BY type_name"
        );
        $requestTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map type names to codes the JS in the view expects (leave, overtime, cash_advance)
        $codeMap = ['Leave' => 'leave', 'Overtime' => 'overtime', 'CashAdvance' => 'cash_advance'];
        $requestTypes = array_map(static function (array $t) use ($codeMap): array {
            $t['code'] = $codeMap[$t['name']] ?? strtolower($t['name']);
            return $t;
        }, $requestTypes);

        ViewRenderer::render('employee/requests/form', [
            'requestTypes'     => $requestTypes,
            'sickLeaveBalance' => 4, // ADR-0001: 4 paid sick days; live balance deferred
            'errors'           => [],
        ], 'Submit Request');
    }

    /**
     * POST /employee/requests
     *
     * @param array<string, string> $params
     */
    public function storeRequest(array $params = []): void
    {
        $employeeId = $this->requireEmployeeId();
        $pdo        = $this->makeConnection()->pdo();

        $typeId = (int) ($_POST['request_type_id'] ?? 0);
        // The form field is 'reason' (matches request.reason column)
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $errors = [];

        if ($typeId === 0) {
            $errors['request_type_id'] = 'Request type is required.';
        }
        if ($reason === '') {
            $errors['reason'] = 'Reason is required.';
        }

        if ($errors !== []) {
            $codeMap      = ['Leave' => 'leave', 'Overtime' => 'overtime', 'CashAdvance' => 'cash_advance'];
            $stmt         = $pdo->query(
                "SELECT request_type_id AS id, type_name AS name FROM request_type WHERE status='Active'"
            );
            $rows         = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $requestTypes = array_map(
                static fn(array $t): array => $t + ['code' => $codeMap[$t['name']] ?? strtolower($t['name'])],
                $rows
            );

            ViewRenderer::render('employee/requests/form', [
                'requestTypes'     => $requestTypes,
                'sickLeaveBalance' => 4,
                'errors'           => $errors,
            ], 'Submit Request');
            return;
        }

        // Insert using actual column names (request.reason, not request.remarks)
        $stmt = $pdo->prepare(
            "INSERT INTO request (employee_id, request_type_id, reason, status, submitted_at)
             VALUES (:emp_id, :type_id, :reason, 'Pending', NOW())"
        );
        $stmt->execute([
            ':emp_id'  => $employeeId,
            ':type_id' => $typeId,
            ':reason'  => $reason,
        ]);

        ViewRenderer::flash('Request submitted successfully.');
        $this->redirect('/employee/requests');
    }

    /**
     * GET /employee/payslips
     *
     * @param array<string, string> $params
     */
    public function payslips(array $params = []): void
    {
        $employeeId = $this->requireEmployeeId();
        $pdo        = $this->makeConnection()->pdo();

        // payroll_period uses payroll_period_id as PK
        $stmt = $pdo->prepare(
            "SELECT ps.payslip_id AS id,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period_label,
                    b.branch_name,
                    p.gross_pay,
                    p.total_deductions,
                    p.net_pay,
                    pr.reviewed_at AS approved_at
             FROM payslip ps
             JOIN payroll p      ON p.payroll_id          = ps.payroll_id
             JOIN payroll_run pr ON pr.payroll_run_id      = p.payroll_run_id
             JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
             JOIN branch b       ON b.branch_id            = pr.branch_id
             WHERE p.employee_id = :emp_id
               AND pr.status = 'Approved'
             ORDER BY pp.period_start DESC
             LIMIT 24"
        );
        $stmt->execute([':emp_id' => $employeeId]);
        $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('employee/payslips', [
            'payslips' => $payslips,
        ], 'My Payslips');
    }

    /**
     * GET /employee/payslips/{id}
     *
     * REQ081: employee views own payslip; REQ075: scoped to session.employee_id.
     * Variable contract: resources/views/employee/payslip.php
     *   expects $payslip['period'], ['gross'], ['deductions'], ['net'], ['items']
     *
     * @param array<string, string> $params
     */
    public function payslipDetail(array $params = []): void
    {
        $employeeId = $this->requireEmployeeId();
        $payslipId  = (int) ($params['id'] ?? 0);
        $pdo        = $this->makeConnection()->pdo();

        $stmt = $pdo->prepare(
            "SELECT ps.payslip_id,
                    p.employee_id,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period,
                    p.gross_pay,
                    p.total_deductions,
                    p.net_pay
             FROM payslip ps
             JOIN payroll p      ON p.payroll_id           = ps.payroll_id
             JOIN payroll_run pr ON pr.payroll_run_id       = p.payroll_run_id
             JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
             WHERE ps.payslip_id = :id"
        );
        $stmt->execute([':id' => $payslipId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        // REQ075/REQ081: ownership guard — employee cannot view another's payslip
        try {
            $this->scope->assertOwnRecord($employeeId, (int) $row['employee_id']);
        } catch (\DomainException) {
            http_response_code(403);
            ViewRenderer::render('errors/403', [], 'Forbidden');
            return;
        }

        // Build the view shape
        $grossFmt  = number_format((float) $row['gross_pay'], 2);
        $dedFmt    = number_format((float) $row['total_deductions'], 2);
        $netFmt    = number_format((float) $row['net_pay'], 2);

        $payslip = [
            'period'     => (string) $row['period'],
            'gross'      => $grossFmt,
            'deductions' => $dedFmt,
            'net'        => $netFmt,
            'items'      => [
                ['label' => 'Gross Pay',        'amount' => '₱' . $grossFmt],
                ['label' => 'Total Deductions', 'amount' => '₱' . $dedFmt],
                ['label' => 'Net Pay',          'amount' => '₱' . $netFmt],
            ],
        ];

        ViewRenderer::render('employee/payslip', [
            'payslip'     => $payslip,
            'downloadUrl' => '#', // full download renderer deferred
        ], 'Payslip');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function requireEmployeeId(): int
    {
        $identity   = AuthMiddleware::identity();
        $employeeId = $identity['employee_id'] ?? null;

        if ($employeeId === null) {
            $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
            header('Location: ' . $base . '/login', true, 302);
            exit;
        }

        return $employeeId;
    }

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
