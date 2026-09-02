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

        try {
            $service = new \Wbpms\Application\PayrollService($this->makeConnection());
            $data    = $service->payslipData($payslipId);
        } catch (\RuntimeException) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        // Ownership guard — employee cannot view another's payslip
        try {
            $this->scope->assertOwnRecord($employeeId, $data['employee_id']);
        } catch (\DomainException) {
            http_response_code(403);
            ViewRenderer::render('errors/403', [], 'Forbidden');
            return;
        }

        // Build items for display
        $items = [];
        foreach ($data['earnings'] as $e) {
            $items[] = ['label' => $e['description'], 'amount' => '₱' . number_format((float)$e['amount'], 2), 'type' => 'earning'];
        }
        foreach ($data['deductions'] as $d) {
            $items[] = ['label' => $d['description'], 'amount' => '−₱' . number_format((float)$d['amount'], 2), 'type' => 'deduction'];
        }

        $base = rtrim((string)($_ENV['APP_BASE_URL'] ?? ''), '/');

        $payslip = [
            'period'     => $data['period'],
            'gross'      => number_format($data['gross_pay'], 2),
            'deductions' => number_format($data['total_deductions'], 2),
            'net'        => number_format($data['net_pay'], 2),
            'items'      => $items,
            'data'       => $data,
        ];

        ViewRenderer::render('employee/payslip', [
            'payslip'     => $payslip,
            'downloadUrl' => $base . '/employee/payslips/' . $payslipId . '/print',
        ], 'Payslip — ' . $data['period']);
    }

    /**
     * GET /employee/payslips/{id}/print
     * Print-ready payslip — no layout wrapper, inline styles only. REQ082.
     *
     * @param array<string, string> $params
     */
    public function payslipPrint(array $params = []): void
    {
        $employeeId = $this->requireEmployeeId();
        $payslipId  = (int) ($params['id'] ?? 0);

        try {
            $service = new \Wbpms\Application\PayrollService($this->makeConnection());
            $data    = $service->payslipData($payslipId);
        } catch (\RuntimeException) {
            http_response_code(404);
            echo 'Payslip not found.';
            return;
        }

        try {
            $this->scope->assertOwnRecord($employeeId, $data['employee_id']);
        } catch (\DomainException) {
            http_response_code(403);
            echo 'Forbidden.';
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        // Inline print-ready HTML — no layout dependency
        $e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $n = fn(float $v): string  => number_format($v, 2);

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<title>Payslip — ' . $e($data['period']) . '</title>';
        echo '<style>
            body{font-family:Arial,sans-serif;font-size:12px;margin:20px}
            h2{margin:0 0 4px}
            .header{display:flex;justify-content:space-between;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:12px}
            .meta{margin-bottom:12px;font-size:11px}
            table{width:100%;border-collapse:collapse;margin-bottom:12px}
            th,td{border:1px solid #ccc;padding:5px 8px;font-size:11px}
            th{background:#f0f0f0;text-align:left}
            .total-row td{font-weight:bold;background:#f9f9f9}
            .net-row td{font-weight:bold;font-size:13px;background:#e8f5e9}
            @media print{button{display:none}}
        </style></head><body>';
        echo '<div class="header">';
        echo '<div><h2>Light Diamond Enterprises</h2><small>PAYSLIP</small></div>';
        echo '<div style="text-align:right"><b>Pay Period:</b> ' . $e($data['period']) . '<br>';
        echo '<b>Pay Date:</b> ' . $e($data['pay_date']) . '</div>';
        echo '</div>';
        echo '<div class="meta">';
        echo '<b>Employee:</b> ' . $e($data['employee_name']) . ' (' . $e($data['employee_number']) . ')<br>';
        echo '<b>Position:</b> ' . $e($data['position']) . ' &nbsp;&nbsp; <b>Branch:</b> ' . $e($data['branch_name']) . '<br>';
        echo '<b>Daily Rate:</b> ₱' . $n($data['daily_rate']) . ' &nbsp;&nbsp; <b>Issue Date:</b> ' . $e($data['issue_date']);
        echo '</div>';

        echo '<table><thead><tr><th>EARNINGS</th><th style="text-align:right">AMOUNT</th></tr></thead><tbody>';
        foreach ($data['earnings'] as $row) {
            echo '<tr><td>' . $e($row['description']) . '</td><td style="text-align:right">₱' . $n((float)$row['amount']) . '</td></tr>';
        }
        echo '<tr class="total-row"><td>GROSS PAY</td><td style="text-align:right">₱' . $n($data['gross_pay']) . '</td></tr>';
        echo '</tbody></table>';

        echo '<table><thead><tr><th>DEDUCTIONS</th><th style="text-align:right">AMOUNT</th></tr></thead><tbody>';
        foreach ($data['deductions'] as $row) {
            echo '<tr><td>' . $e($row['description']) . '</td><td style="text-align:right">₱' . $n((float)$row['amount']) . '</td></tr>';
        }
        echo '<tr class="total-row"><td>TOTAL DEDUCTIONS</td><td style="text-align:right">₱' . $n($data['total_deductions']) . '</td></tr>';
        echo '</tbody></table>';

        echo '<table><tbody>';
        echo '<tr class="net-row"><td><b>NET PAY</b></td><td style="text-align:right"><b>₱' . $n($data['net_pay']) . '</b></td></tr>';
        echo '</tbody></table>';

        echo '<p style="font-size:10px;margin-top:20px;color:#666">This is a system-generated payslip. — Light Diamond Enterprises</p>';
        echo '<button onclick="window.print()" style="padding:6px 16px;cursor:pointer">🖨 Print</button>';
        echo '</body></html>';
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
