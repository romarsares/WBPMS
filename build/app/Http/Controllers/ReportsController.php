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
 *   GET /hr/reports/13th-month → thirteenthMonth() [HRHead, BusinessOwner]
 *   GET /hr/reports/export  → export() [HRHead, BusinessOwner]
 *   GET /hr/reports/{type}  → preview() [HRHead, BusinessOwner]
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
    // GET /hr/reports/13th-month
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function thirteenthMonth(array $params = []): void
    {
        $pdo     = $this->makeConnection()->pdo();
        $filters = $this->thirteenthMonthFilters();
        $year    = $filters['year'];

        $years = $pdo->query(
            "SELECT DISTINCT YEAR(pp.period_start) AS year
               FROM payroll_earnings pe
               JOIN payroll p         ON p.payroll_id         = pe.payroll_id
               JOIN payroll_run pr    ON pr.payroll_run_id    = p.payroll_run_id
               JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
              WHERE pe.earning_type = 'Basic'
                AND pr.status = 'Approved'
              ORDER BY year DESC"
        )->fetchAll();
        $availableYears = array_map(
            static fn(array $row): int => (int) $row['year'],
            $years
        );
        if (!in_array($year, $availableYears, true)) {
            $years[] = ['year' => $year];
            usort($years, static fn(array $left, array $right): int => (int) $right['year'] <=> (int) $left['year']);
        }

        $branches = $pdo->query(
            "SELECT branch_id, branch_name
               FROM branch
              WHERE status = 'Active'
              ORDER BY branch_name"
        )->fetchAll();
        $employees = $pdo->query(
            "SELECT employee_id, employee_number,
                    CONCAT(last_name, ', ', first_name) AS employee_name
               FROM employee
              ORDER BY last_name, first_name"
        )->fetchAll();

        $service = new \Wbpms\Application\PayrollService($this->makeConnection());
        $rows = $service->compute13thMonth($year, [
            'branch_id'   => $filters['branch_id'],
            'employee_id' => $filters['employee_id'],
        ]);

        ViewRenderer::render('hr/reports/thirteenth-month', [
            'rows'       => $rows,
            'years'      => $years,
            'branches'   => $branches,
            'employees'  => $employees,
            'filters'    => $filters,
            'activePage' => 'reports',
        ], '13th Month Pay Report');
    }

    // -----------------------------------------------------------------------
    // GET /hr/reports/{type} — filtered on-screen preview before printing
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function preview(array $params = []): void
    {
        $type = (string) ($params['type'] ?? '');
        if (!in_array($type, ['payroll', 'attendance', 'requests', 'contributions', 'employees'], true)) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $filters = $this->previewFilters($type);
        [$headers, $rows] = $this->previewData($type, $filters);
        $pdo = $this->makeConnection()->pdo();

        ViewRenderer::render('hr/reports/preview', [
            'type'         => $type,
            'title'        => $this->reportTitle($type),
            'headers'      => $headers,
            'rows'         => $rows,
            'filters'      => $filters,
            'branches'     => $pdo->query("SELECT branch_id, branch_name FROM branch WHERE status = 'Active' ORDER BY branch_name")->fetchAll(),
            'requestTypes' => $pdo->query("SELECT request_type_id, type_name FROM request_type ORDER BY type_name")->fetchAll(),
            'activePage'   => 'reports',
        ], $this->reportTitle($type));
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

        $title = $this->reportTitle($type);

        // ---------- Collect data ----------
        $rows   = [];
        $headers = [];
        $year   = (int) ($_GET['year'] ?? date('Y'));

        if (in_array($type, ['payroll', 'attendance', 'requests', 'contributions', 'employees'], true)) {
            [$headers, $rows] = $this->previewData($type, $this->previewFilters($type));
        } else {
            switch ($type) {

            case '13th_month':
                $headers = ['Employee #', 'Employee Name', 'Total Basic Pay', '13th Month Pay'];
                $filters = $this->thirteenthMonthFilters();
                $year = $filters['year'];
                $service = new \Wbpms\Application\PayrollService($this->makeConnection());
                foreach ($service->compute13thMonth($year, [
                    'branch_id' => $filters['branch_id'],
                    'employee_id' => $filters['employee_id'],
                ]) as $r) {
                    $rows[] = [
                        $r['employee_number'],
                        $r['employee_name'],
                        '₱' . $n($r['basic_total']),
                        '₱' . $n($r['thirteenth_month']),
                    ];
                }
                break;

            }
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
        $reportScope = '';
        if ($type === '13th_month') {
            $reportScope = ' &nbsp;|&nbsp; Year: ' . $year;
            if ($filters['branch_id'] > 0) {
                $reportScope .= ' &nbsp;|&nbsp; Branch filter applied';
            }
            if ($filters['employee_id'] > 0) {
                $reportScope .= ' &nbsp;|&nbsp; Employee filter applied';
            }
        }
        echo '<div class="meta">Light Diamond Enterprises &nbsp;|&nbsp; Generated: ' . date('m/d/Y H:i') . $reportScope . '</div>';
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

    private function reportTitle(string $type): string
    {
        return match ($type) {
            'payroll'       => 'Payroll Summary Report',
            'attendance'    => 'Attendance Report',
            'requests'      => 'Leave / Request Report',
            'contributions' => 'Government Contributions Report',
            '13th_month'    => '13th Month Pay Report',
            'employees'     => 'Employee List',
            default         => 'Report',
        };
    }

    /**
     * @return array{branch_id:int,date_from:string,date_to:string,employee_search:string,status:string,type_id:int,program:string}
     */
    private function previewFilters(string $type): array
    {
        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $branchId = filter_var($_GET['branch_id'] ?? 0, FILTER_VALIDATE_INT);
        $typeId = filter_var($_GET['type_id'] ?? 0, FILTER_VALIDATE_INT);
        $validStatuses = match ($type) {
            'attendance' => ['Complete', 'Approved', 'Incomplete', 'ReviewRequired'],
            'requests' => ['Pending', 'Approved', 'Rejected', 'Cancelled'],
            'employees' => ['Active', 'Inactive', 'Separated', 'Archived'],
            default => [],
        };
        $status = trim((string) ($_GET['status'] ?? ''));
        $program = trim((string) ($_GET['program'] ?? ''));

        return [
            'branch_id' => $branchId !== false && $branchId > 0 ? $branchId : 0,
            'date_from' => $this->validDate($dateFrom) ? $dateFrom : '',
            'date_to' => $this->validDate($dateTo) ? $dateTo : '',
            'employee_search' => trim((string) ($_GET['employee_search'] ?? '')),
            'status' => in_array($status, $validStatuses, true) ? $status : '',
            'type_id' => $typeId !== false && $typeId > 0 ? $typeId : 0,
            'program' => in_array($program, ['SSS', 'PhilHealth', 'PagIBIG'], true) ? $program : '',
        ];
    }

    /**
     * @param array{branch_id:int,date_from:string,date_to:string,employee_search:string,status:string,type_id:int,program:string} $filters
     * @return array{0:list<string>,1:list<list<string|int|float>>}
     */
    private function previewData(string $type, array $filters): array
    {
        $pdo = $this->makeConnection()->pdo();
        $where = [];
        $bind = [];
        $employeeSearch = $filters['employee_search'];

        $applyEmployeeSearch = static function (array &$where, array &$bind, string $search, string $employeeAlias = 'e'): void {
            if ($search === '') {
                return;
            }
            $where[] = "({$employeeAlias}.last_name LIKE :employee_search_last OR {$employeeAlias}.first_name LIKE :employee_search_first OR {$employeeAlias}.employee_number LIKE :employee_search_number)";
            $pattern = '%' . $search . '%';
            $bind[':employee_search_last'] = $pattern;
            $bind[':employee_search_first'] = $pattern;
            $bind[':employee_search_number'] = $pattern;
        };

        if ($type === 'payroll') {
            $where = ["pr.status = 'Approved'"];
            if ($filters['branch_id'] > 0) {
                $where[] = 'pr.branch_id = :branch_id';
                $bind[':branch_id'] = $filters['branch_id'];
            }
            if ($filters['date_from'] !== '') {
                $where[] = 'pp.period_start >= :date_from';
                $bind[':date_from'] = $filters['date_from'];
            }
            if ($filters['date_to'] !== '') {
                $where[] = 'pp.period_end <= :date_to';
                $bind[':date_to'] = $filters['date_to'];
            }
            $stmt = $pdo->prepare(
                "SELECT b.branch_name, pp.period_start, pp.period_end, COUNT(p.payroll_id) AS emp_count,
                        COALESCE(SUM(p.gross_pay), 0) AS gross, COALESCE(SUM(p.total_deductions), 0) AS deductions,
                        COALESCE(SUM(p.net_pay), 0) AS net, pr.reviewed_at AS approved_at
                   FROM payroll_run pr
                   JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
                   JOIN branch b ON b.branch_id = pr.branch_id
                   LEFT JOIN payroll p ON p.payroll_run_id = pr.payroll_run_id
                  WHERE " . implode(' AND ', $where) . "
                  GROUP BY pr.payroll_run_id, b.branch_name, pp.period_start, pp.period_end, pr.reviewed_at
                  ORDER BY pp.period_start DESC LIMIT 500"
            );
            $stmt->execute($bind);
            $rows = array_map(static fn(array $row): array => [
                $row['branch_name'], $row['period_start'] . ' – ' . $row['period_end'], (int) $row['emp_count'],
                '₱' . number_format((float) $row['gross'], 2), '₱' . number_format((float) $row['deductions'], 2),
                '₱' . number_format((float) $row['net'], 2), (string) ($row['approved_at'] ?? '—'),
            ], $stmt->fetchAll());
            return [['Branch', 'Period', 'Employees', 'Gross Pay', 'Total Deductions', 'Net Pay', 'Approved At'], $rows];
        }

        if ($type === 'attendance') {
            if ($filters['date_from'] !== '') { $where[] = 'a.attendance_date >= :date_from'; $bind[':date_from'] = $filters['date_from']; }
            if ($filters['date_to'] !== '') { $where[] = 'a.attendance_date <= :date_to'; $bind[':date_to'] = $filters['date_to']; }
            if ($filters['branch_id'] > 0) { $where[] = 'eba.branch_id = :branch_id'; $bind[':branch_id'] = $filters['branch_id']; }
            if ($filters['status'] !== '') { $where[] = 'a.status = :status'; $bind[':status'] = $filters['status']; }
            $applyEmployeeSearch($where, $bind, $employeeSearch);
            $stmt = $pdo->prepare(
                "SELECT CONCAT(e.last_name, ', ', e.first_name) AS name, a.attendance_date, a.time_in, a.time_out,
                        a.hours_worked_minutes, a.late_minutes, a.undertime_minutes, a.overtime_minutes, a.status
                   FROM attendance a JOIN employee e ON e.employee_id = a.employee_id
                   JOIN employee_branch_assignment eba ON eba.branch_assignment_id = a.branch_assignment_id
                  " . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . "
                  ORDER BY a.attendance_date DESC, e.last_name LIMIT 500"
            );
            $stmt->execute($bind);
            $rows = array_map(static fn(array $row): array => [$row['name'], $row['attendance_date'], $row['time_in'] ?? '—', $row['time_out'] ?? '—', (int) $row['hours_worked_minutes'], (int) $row['late_minutes'], (int) $row['undertime_minutes'], (int) $row['overtime_minutes'], $row['status']], $stmt->fetchAll());
            return [['Employee', 'Date', 'Time In', 'Time Out', 'Worked (min)', 'Late (min)', 'Undertime (min)', 'Overtime (min)', 'Status'], $rows];
        }

        if ($type === 'requests') {
            $where = ['r.archived_at IS NULL'];
            if ($filters['date_from'] !== '') { $where[] = 'DATE(r.submitted_at) >= :date_from'; $bind[':date_from'] = $filters['date_from']; }
            if ($filters['date_to'] !== '') { $where[] = 'DATE(r.submitted_at) <= :date_to'; $bind[':date_to'] = $filters['date_to']; }
            if ($filters['status'] !== '') { $where[] = 'r.status = :status'; $bind[':status'] = $filters['status']; }
            if ($filters['type_id'] > 0) { $where[] = 'r.request_type_id = :type_id'; $bind[':type_id'] = $filters['type_id']; }
            $applyEmployeeSearch($where, $bind, $employeeSearch);
            $stmt = $pdo->prepare(
                "SELECT CONCAT(e.last_name, ', ', e.first_name) AS name, rt.type_name, r.submitted_at, r.status, r.reason
                   FROM request r JOIN employee e ON e.employee_id = r.employee_id
                   JOIN request_type rt ON rt.request_type_id = r.request_type_id
                  WHERE " . implode(' AND ', $where) . " ORDER BY r.submitted_at DESC LIMIT 500"
            );
            $stmt->execute($bind);
            $rows = array_map(static fn(array $row): array => [$row['name'], $row['type_name'], $row['submitted_at'], $row['status'], $row['reason']], $stmt->fetchAll());
            return [['Employee', 'Type', 'Submitted', 'Status', 'Reason'], $rows];
        }

        if ($type === 'contributions') {
            $where = ["pr.status = 'Approved'"];
            if ($filters['date_from'] !== '') { $where[] = 'pp.period_start >= :date_from'; $bind[':date_from'] = $filters['date_from']; }
            if ($filters['date_to'] !== '') { $where[] = 'pp.period_end <= :date_to'; $bind[':date_to'] = $filters['date_to']; }
            if ($filters['program'] !== '') { $where[] = 'cr.contribution_type = :program'; $bind[':program'] = $filters['program']; }
            $applyEmployeeSearch($where, $bind, $employeeSearch);
            $stmt = $pdo->prepare(
                "SELECT CONCAT(e.last_name, ', ', e.first_name) AS name, CONCAT(pp.period_start, ' – ', pp.period_end) AS period,
                        SUM(CASE WHEN cr.contribution_type = 'SSS' THEN cr.employee_share ELSE 0 END) AS sss,
                        SUM(CASE WHEN cr.contribution_type = 'PhilHealth' THEN cr.employee_share ELSE 0 END) AS phil,
                        SUM(CASE WHEN cr.contribution_type = 'PagIBIG' THEN cr.employee_share ELSE 0 END) AS pag,
                        SUM(cr.employee_share) AS total
                   FROM contribution_record cr JOIN payroll p ON p.payroll_id = cr.payroll_id
                   JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
                   JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
                   JOIN employee e ON e.employee_id = p.employee_id
                  WHERE " . implode(' AND ', $where) . "
                  GROUP BY p.employee_id, pp.payroll_period_id, e.last_name, e.first_name, pp.period_start, pp.period_end
                  ORDER BY pp.period_start DESC, e.last_name LIMIT 500"
            );
            $stmt->execute($bind);
            $rows = array_map(static fn(array $row): array => [$row['name'], $row['period'], '₱' . number_format((float) $row['sss'], 2), '₱' . number_format((float) $row['phil'], 2), '₱' . number_format((float) $row['pag'], 2), '₱' . number_format((float) $row['total'], 2)], $stmt->fetchAll());
            return [['Employee', 'Period', 'SSS Employee', 'PhilHealth Employee', 'Pag-IBIG Employee', 'Total'], $rows];
        }

        if ($filters['branch_id'] > 0) { $where[] = 'eba.branch_id = :branch_id'; $bind[':branch_id'] = $filters['branch_id']; }
        if ($filters['status'] !== '') { $where[] = 'e.status = :status'; $bind[':status'] = $filters['status']; }
        $applyEmployeeSearch($where, $bind, $employeeSearch);
        $stmt = $pdo->prepare(
            "SELECT e.employee_number, CONCAT(e.last_name, ', ', e.first_name) AS name, e.position, b.branch_name, e.employee_type, e.status
               FROM employee e LEFT JOIN employee_branch_assignment eba ON eba.employee_id = e.employee_id AND eba.effective_to IS NULL
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
              " . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . " ORDER BY e.last_name, e.first_name LIMIT 500"
        );
        $stmt->execute($bind);
        $rows = array_map(static fn(array $row): array => [$row['employee_number'], $row['name'], $row['position'], $row['branch_name'] ?? '—', $row['employee_type'], $row['status']], $stmt->fetchAll());
        return [['Employee #', 'Name', 'Position', 'Branch', 'Type', 'Status'], $rows];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @return array{year:int,branch_id:int,employee_id:int} */
    private function thirteenthMonthFilters(): array
    {
        $currentYear = (int) date('Y');
        $year = filter_var($_GET['year'] ?? $currentYear, FILTER_VALIDATE_INT);
        $branchId = filter_var($_GET['branch_id'] ?? 0, FILTER_VALIDATE_INT);
        $employeeId = filter_var($_GET['employee_id'] ?? 0, FILTER_VALIDATE_INT);

        return [
            'year' => $year !== false && $year >= 2000 && $year <= $currentYear + 1 ? $year : $currentYear,
            'branch_id' => $branchId !== false && $branchId > 0 ? $branchId : 0,
            'employee_id' => $employeeId !== false && $employeeId > 0 ? $employeeId : 0,
        ];
    }
}
