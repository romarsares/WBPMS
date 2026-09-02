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
        $pdo  = $this->makeConnection()->pdo();

        $e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $n = fn(float $v): string  => number_format($v, 2);

        $title = match ($type) {
            'payroll'       => 'Payroll Summary Report',
            'attendance'    => 'Attendance Report',
            'requests'      => 'Leave / Request Report',
            'contributions' => 'Government Contributions Report',
            '13th_month'    => '13th Month Pay Report',
            'employees'     => 'Employee List',
            default         => 'Report',
        };

        // ---------- Collect data ----------
        $rows   = [];
        $headers = [];
        $year   = (int) ($_GET['year'] ?? date('Y'));

        switch ($type) {
            case 'payroll':
                $headers = ['Branch', 'Period', 'Employees', 'Gross Pay', 'Total Deductions', 'Net Pay', 'Approved At'];
                $stmt = $pdo->query(
                    "SELECT b.branch_name, pp.period_start, pp.period_end,
                            COUNT(p.payroll_id) AS emp_count,
                            COALESCE(SUM(p.gross_pay),0) AS gross,
                            COALESCE(SUM(p.total_deductions),0) AS deductions,
                            COALESCE(SUM(p.net_pay),0) AS net,
                            pr.reviewed_at AS approved_at
                       FROM payroll_run pr
                       JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
                       JOIN branch b ON b.branch_id = pr.branch_id
                       LEFT JOIN payroll p ON p.payroll_run_id = pr.payroll_run_id
                      WHERE pr.status = 'Approved'
                      GROUP BY pr.payroll_run_id, b.branch_name, pp.period_start, pp.period_end, pr.reviewed_at
                      ORDER BY pp.period_start DESC"
                );
                foreach ($stmt->fetchAll() as $r) {
                    $rows[] = [
                        $r['branch_name'],
                        $r['period_start'] . ' – ' . $r['period_end'],
                        (int)$r['emp_count'],
                        '₱' . $n((float)$r['gross']),
                        '₱' . $n((float)$r['deductions']),
                        '₱' . $n((float)$r['net']),
                        (string)($r['approved_at'] ?? ''),
                    ];
                }
                break;

            case 'attendance':
                $headers = ['Employee', 'Date', 'Time In', 'Time Out', 'Worked (min)', 'Late (min)', 'Undertime (min)', 'Overtime (min)', 'Status'];
                $stmt = $pdo->query(
                    "SELECT CONCAT(e.last_name,', ',e.first_name) AS name,
                            a.attendance_date, a.time_in, a.time_out,
                            a.hours_worked_minutes, a.late_minutes,
                            a.undertime_minutes, a.overtime_minutes, a.status
                       FROM attendance a
                       JOIN employee e ON e.employee_id = a.employee_id
                      ORDER BY a.attendance_date DESC, e.last_name
                      LIMIT 500"
                );
                foreach ($stmt->fetchAll() as $r) {
                    $rows[] = [
                        $r['name'], $r['attendance_date'],
                        $r['time_in'] ?? '—', $r['time_out'] ?? '—',
                        $r['hours_worked_minutes'], $r['late_minutes'],
                        $r['undertime_minutes'], $r['overtime_minutes'],
                        $r['status'],
                    ];
                }
                break;

            case 'requests':
                $headers = ['Employee', 'Type', 'Submitted', 'Status', 'Reason'];
                $stmt = $pdo->query(
                    "SELECT CONCAT(e.last_name,', ',e.first_name) AS name,
                            rt.type_name AS type, r.submitted_at, r.status, r.reason
                       FROM request r
                       JOIN employee e ON e.employee_id = r.employee_id
                       JOIN request_type rt ON rt.request_type_id = r.request_type_id
                      ORDER BY r.submitted_at DESC LIMIT 500"
                );
                foreach ($stmt->fetchAll() as $r) {
                    $rows[] = [$r['name'], $r['type'], $r['submitted_at'], $r['status'], $r['reason']];
                }
                break;

            case 'contributions':
                $headers = ['Employee', 'Period', 'SSS Employee', 'PhilHealth Employee', 'Pag-IBIG Employee', 'Total'];
                $stmt = $pdo->query(
                    "SELECT CONCAT(e.last_name,', ',e.first_name) AS name,
                            CONCAT(pp.period_start,' – ',pp.period_end) AS period,
                            SUM(CASE WHEN cr.contribution_type='SSS'       THEN cr.employee_share ELSE 0 END) AS sss,
                            SUM(CASE WHEN cr.contribution_type='PhilHealth' THEN cr.employee_share ELSE 0 END) AS phil,
                            SUM(CASE WHEN cr.contribution_type='PagIBIG'    THEN cr.employee_share ELSE 0 END) AS pag,
                            SUM(cr.employee_share) AS total
                       FROM contribution_record cr
                       JOIN payroll p ON p.payroll_id = cr.payroll_id
                       JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
                       JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
                       JOIN employee e ON e.employee_id = p.employee_id
                      WHERE pr.status = 'Approved'
                      GROUP BY p.employee_id, pp.payroll_period_id
                      ORDER BY pp.period_start DESC, e.last_name LIMIT 500"
                );
                foreach ($stmt->fetchAll() as $r) {
                    $rows[] = [
                        $r['name'], $r['period'],
                        '₱' . $n((float)$r['sss']),
                        '₱' . $n((float)$r['phil']),
                        '₱' . $n((float)$r['pag']),
                        '₱' . $n((float)$r['total']),
                    ];
                }
                break;

            case '13th_month':
                $headers = ['Employee #', 'Employee Name', 'Total Basic Pay', '13th Month Pay'];
                $service = new \Wbpms\Application\PayrollService($this->makeConnection());
                foreach ($service->compute13thMonth($year) as $r) {
                    $rows[] = [
                        $r['employee_number'],
                        $r['employee_name'],
                        '₱' . $n($r['basic_total']),
                        '₱' . $n($r['thirteenth_month']),
                    ];
                }
                break;

            case 'employees':
                $headers = ['Employee #', 'Name', 'Position', 'Branch', 'Type', 'Status'];
                $stmt = $pdo->query(
                    "SELECT e.employee_number, CONCAT(e.last_name,', ',e.first_name) AS name,
                            e.position, b.branch_name, e.employee_type, e.status
                       FROM employee e
                       LEFT JOIN employee_branch_assignment eba
                             ON eba.employee_id = e.employee_id AND eba.effective_to IS NULL
                       LEFT JOIN branch b ON b.branch_id = eba.branch_id
                      ORDER BY e.last_name, e.first_name"
                );
                foreach ($stmt->fetchAll() as $r) {
                    $rows[] = [$r['employee_number'], $r['name'], $r['position'], $r['branch_name'] ?? '—', $r['employee_type'], $r['status']];
                }
                break;
        }

        // ---------- Render print-ready HTML ----------
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<title>' . $e($title) . '</title>';
        echo '<style>
            body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
            h2{margin:0 0 4px;font-size:15px}
            .meta{font-size:10px;color:#555;margin-bottom:12px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #ccc;padding:4px 7px;text-align:left}
            th{background:#f0f0f0;font-weight:bold}
            tr:nth-child(even){background:#fafafa}
            .no-data{text-align:center;padding:20px;color:#999}
            @media print{.no-print{display:none}}
        </style></head><body>';
        echo '<h2>' . $e($title) . '</h2>';
        echo '<div class="meta">Light Diamond Enterprises &nbsp;|&nbsp; Generated: ' . date('m/d/Y H:i') . ($type === '13th_month' ? ' &nbsp;|&nbsp; Year: ' . $year : '') . '</div>';
        echo '<div class="no-print" style="margin-bottom:10px"><button onclick="window.print()">🖨 Print</button> &nbsp; <button onclick="history.back()">← Back</button></div>';

        if ($rows === []) {
            echo '<p class="no-data">No data available for this report.</p>';
        } else {
            echo '<table><thead><tr>';
            foreach ($headers as $h) {
                echo '<th>' . $e($h) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . $e((string)$cell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '<p class="meta" style="margin-top:10px">Total records: ' . count($rows) . '</p>';
        }

        echo '</body></html>';
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }
}
