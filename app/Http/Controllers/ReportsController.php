<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Reports\PdfRenderer;

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
    // GET /hr/reports/transaction-slips  (REQ073 — targeted printing)
    // -----------------------------------------------------------------------

    /**
     * Transaction slip generator.
     *
     * Produces individual BDO-style cash transaction slips from payroll and
     * bank_details data. Each slip covers one employee's net pay for a
     * selected payroll period.
     *
     * Data sources:
     *   payroll        → net_pay (amount to deposit)
     *   payroll_run    → period, branch, status (must be Approved)
     *   payroll_period → period_start / period_end
     *   employee       → name, employee_number
     *   bank_details   → account_name, account_number, bank_name (may be empty)
     *
     * If bank_details has no record for an employee the slip prints with
     * blank account fields and a notice, allowing HR to fill in manually.
     *
     * @param array<string,string> $params
     */
    public function transactionSlips(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        // ---- Filters -------------------------------------------------------
        $employeeId = filter_var($_GET['employee_id'] ?? 0, FILTER_VALIDATE_INT);
        $employeeId = $employeeId !== false && $employeeId > 0 ? (int) $employeeId : 0;

        $branchId = filter_var($_GET['branch_id'] ?? 0, FILTER_VALIDATE_INT);
        $branchId = $branchId !== false && $branchId > 0 ? (int) $branchId : 0;

        $periodId = filter_var($_GET['payroll_period_id'] ?? 0, FILTER_VALIDATE_INT);
        $periodId = $periodId !== false && $periodId > 0 ? (int) $periodId : 0;

        $printMode = isset($_GET['print']) && $_GET['print'] === '1';

        // ---- Reference data for filter dropdowns ---------------------------
        $branches = $pdo->query(
            "SELECT branch_id, branch_name FROM branch WHERE status = 'Active' ORDER BY branch_name"
        )->fetchAll();

        $employees = $pdo->query(
            "SELECT employee_id, CONCAT(last_name, ', ', first_name) AS employee_name
               FROM employee WHERE status = 'Active' ORDER BY last_name, first_name"
        )->fetchAll();

        $periods = $pdo->query(
            "SELECT pp.payroll_period_id, pp.period_start, pp.period_end
               FROM payroll_period pp
               JOIN payroll_run pr ON pr.payroll_period_id = pp.payroll_period_id
              WHERE pr.status = 'Approved'
              GROUP BY pp.payroll_period_id
              ORDER BY pp.period_start DESC
              LIMIT 50"
        )->fetchAll();

        // ---- Slip data query -----------------------------------------------
        $slips = [];
        if ($periodId > 0) {
            $where  = ["pr.status = 'Approved'", 'pr.payroll_period_id = :period_id'];
            $bind   = [':period_id' => $periodId];

            if ($branchId > 0) {
                $where[]          = 'pr.branch_id = :branch_id';
                $bind[':branch_id'] = $branchId;
            }
            if ($employeeId > 0) {
                $where[]              = 'p.employee_id = :employee_id';
                $bind[':employee_id'] = $employeeId;
            }

            $stmt = $pdo->prepare(
                "SELECT
                    e.employee_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    b.branch_name,
                    p.net_pay,
                    pp.period_start,
                    pp.period_end,
                    bd.bank_name,
                    bd.account_name,
                    bd.account_number,
                    bd.account_type
                  FROM payroll p
                  JOIN payroll_run pr       ON pr.payroll_run_id     = p.payroll_run_id
                  JOIN payroll_period pp    ON pp.payroll_period_id  = pr.payroll_period_id
                  JOIN employee e           ON e.employee_id         = p.employee_id
                  JOIN branch b             ON b.branch_id           = pr.branch_id
                  LEFT JOIN bank_details bd ON bd.employee_id        = e.employee_id
                                           AND bd.status             = 'Active'
                                           AND bd.effective_to IS NULL
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY e.last_name, e.first_name"
            );
            $stmt->execute($bind);
            $slips = $stmt->fetchAll();
        }

        // ---- Bank details completeness warning flag ------------------------
        $missingBankCount = count(array_filter($slips, static fn(array $s): bool => empty($s['account_number'])));

        ViewRenderer::render('hr/reports/transaction-slips', [
            'slips'            => $slips,
            'branches'         => $branches,
            'employees'        => $employees,
            'periods'          => $periods,
            'filters'          => [
                'employee_id'       => $employeeId,
                'branch_id'         => $branchId,
                'payroll_period_id' => $periodId,
            ],
            'missingBankCount' => $missingBankCount,
            'printMode'        => $printMode,
            'activePage'       => 'reports',
        ], 'Transaction Slips');
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
    // GET /hr/reports/payslips — Employee Payslip Report
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function payslipsReport(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        // Filters
        $branchId = filter_var($_GET['branch_id'] ?? 0, FILTER_VALIDATE_INT);
        $branchId = $branchId !== false && $branchId > 0 ? (int) $branchId : 0;

        $periodId = filter_var($_GET['payroll_period_id'] ?? 0, FILTER_VALIDATE_INT);
        $periodId = $periodId !== false && $periodId > 0 ? (int) $periodId : 0;

        $employeeSearch = trim((string) ($_GET['employee_search'] ?? ''));

        // Reference data for dropdowns
        $branches = $pdo->query(
            "SELECT branch_id, branch_name FROM branch WHERE status = 'Active' ORDER BY branch_name"
        )->fetchAll();

        $periods = $pdo->query(
            "SELECT pp.payroll_period_id, pp.period_start, pp.period_end
               FROM payroll_period pp
               JOIN payroll_run pr ON pr.payroll_period_id = pp.payroll_period_id
              WHERE pr.status = 'Approved'
              GROUP BY pp.payroll_period_id
              ORDER BY pp.period_start DESC
              LIMIT 60"
        )->fetchAll();

        // Payslip data — always run the query (even with no filter) so the
        // report shows all approved payslips by default. Optional filters
        // narrow the result set. Then load the full earnings + deductions
        // breakdown for each via PayrollService so the report displays a
        // complete payslip per employee (REQ074, REQ081).
        $payslips = [];
        {
            $where = ["pr.status = 'Approved'"];
            $bind  = [];

            if ($periodId > 0) {
                $where[]             = 'pr.payroll_period_id = :period_id';
                $bind[':period_id']  = $periodId;
            }
            if ($branchId > 0) {
                $where[]              = 'pr.branch_id = :branch_id';
                $bind[':branch_id']   = $branchId;
            }
            if ($employeeSearch !== '') {
                $where[] = '(e.last_name LIKE :emp_last OR e.first_name LIKE :emp_first OR e.employee_number LIKE :emp_num)';
                $pattern = '%' . $employeeSearch . '%';
                $bind[':emp_last']  = $pattern;
                $bind[':emp_first'] = $pattern;
                $bind[':emp_num']   = $pattern;
            }

            $stmt = $pdo->prepare(
                "SELECT ps.payslip_id,
                        pr.reviewed_at AS approved_at
                   FROM payslip ps
                   JOIN payroll p         ON p.payroll_id         = ps.payroll_id
                   JOIN payroll_run pr    ON pr.payroll_run_id    = p.payroll_run_id
                   JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
                   JOIN employee e        ON e.employee_id        = p.employee_id
                   JOIN branch b          ON b.branch_id          = pr.branch_id
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY pp.period_start DESC, e.last_name, e.first_name
                  LIMIT 500"
            );
            $stmt->execute($bind);
            $matched = $stmt->fetchAll();

            $service = new \Wbpms\Application\PayrollService($this->makeConnection());
            foreach ($matched as $m) {
                try {
                    $detail = $service->payslipData((int) $m['payslip_id']);
                    $detail['approved_at'] = $m['approved_at'];
                    $payslips[] = $detail;
                } catch (\RuntimeException) {
                    // Skip a payslip that can no longer be resolved (e.g. row
                    // removed between the id query and the detail fetch).
                }
            }
        }

        $filters = [
            'branch_id'         => $branchId,
            'payroll_period_id' => $periodId,
            'employee_search'   => $employeeSearch,
        ];

        ViewRenderer::render('hr/reports/payslips', [
            'payslips'   => $payslips,
            'branches'   => $branches,
            'periods'    => $periods,
            'filters'    => $filters,
            'activePage' => 'reports',
        ], 'Employee Payslip Report');
    }

    // -----------------------------------------------------------------------
    // GET /hr/reports/payslips/bulk  — 1/4-sheet bulk print (REQ074, REQ081)
    // -----------------------------------------------------------------------

    /**
     * Renders all matched payslips in a compact 1/4-sheet (A6) format
     * designed for printing four payslips per A4/Letter page.
     *
     * Accepts the same query-string filters as payslipsReport() so the
     * "Print 1/4 Sheet" button can forward the current filter state.
     *
     * @param array<string, string> $params
     */
    public function bulkPrintPayslips(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        $branchId = filter_var($_GET['branch_id'] ?? 0, FILTER_VALIDATE_INT);
        $branchId = $branchId !== false && $branchId > 0 ? (int) $branchId : 0;

        $periodId = filter_var($_GET['payroll_period_id'] ?? 0, FILTER_VALIDATE_INT);
        $periodId = $periodId !== false && $periodId > 0 ? (int) $periodId : 0;

        $employeeSearch = trim((string) ($_GET['employee_search'] ?? ''));

        $where = ["pr.status = 'Approved'"];
        $bind  = [];

        if ($periodId > 0) {
            $where[]             = 'pr.payroll_period_id = :period_id';
            $bind[':period_id']  = $periodId;
        }
        if ($branchId > 0) {
            $where[]             = 'pr.branch_id = :branch_id';
            $bind[':branch_id']  = $branchId;
        }
        if ($employeeSearch !== '') {
            $where[] = '(e.last_name LIKE :emp_last OR e.first_name LIKE :emp_first OR e.employee_number LIKE :emp_num)';
            $pattern = '%' . $employeeSearch . '%';
            $bind[':emp_last']  = $pattern;
            $bind[':emp_first'] = $pattern;
            $bind[':emp_num']   = $pattern;
        }

        $stmt = $pdo->prepare(
            "SELECT ps.payslip_id
               FROM payslip ps
               JOIN payroll p         ON p.payroll_id         = ps.payroll_id
               JOIN payroll_run pr    ON pr.payroll_run_id    = p.payroll_run_id
               JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
               JOIN employee e        ON e.employee_id        = p.employee_id
               JOIN branch b          ON b.branch_id          = pr.branch_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY pp.period_start DESC, e.last_name, e.first_name
              LIMIT 500"
        );
        $stmt->execute($bind);
        $matched = $stmt->fetchAll();

        $payslips = [];
        $service  = new \Wbpms\Application\PayrollService($this->makeConnection());
        foreach ($matched as $m) {
            try {
                $payslips[] = $service->payslipData((int) $m['payslip_id']);
            } catch (\RuntimeException) {
                // Skip unreachable payslips.
            }
        }

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $n = static fn(float $v): string  => number_format($v, 2);
        $f = static fn(\DateTimeInterface|string|null $v): string => PdfRenderer::formatDate($v);

        $generatedDate = (new \DateTimeImmutable())->format('m/d/Y H:i');

        // ----------------------------------------------------------------
        // Build standalone print document
        // ----------------------------------------------------------------
        $backUrl = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/') . '/hr/reports/payslips'
                 . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
                     ? '?' . $_SERVER['QUERY_STRING'] : '');

        $html  = '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        $html .= '<title>Payslips — 1/4 Sheet Bulk Print</title>';
        $html .= '<style>
/* -----------------------------------------------------------------------
   1/4-sheet payslip layout (A6 per slip — 4 slips per A4/Letter page)
   Each .slip is 99mm × 139mm on paper; the grid arranges them 2×2.
   ----------------------------------------------------------------------- */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:10px;background:#fff;color:#111}
.no-print{display:block}
.slip-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
.slip{
    width:99mm;height:139mm;
    padding:6mm 6mm 4mm;
    border:1px solid #ccc;
    overflow:hidden;
    page-break-inside:avoid;
    display:flex;flex-direction:column;gap:3px;
}
.slip-head{border-bottom:1.5px solid #1d4ed8;padding-bottom:3px;margin-bottom:3px}
.slip-head .company{font-size:9px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.04em}
.slip-head .emp-name{font-size:11px;font-weight:700;margin-top:1px}
.slip-head .emp-meta{font-size:8.5px;color:#555;margin-top:1px}
.slip-period{font-size:8.5px;color:#374151;margin-bottom:2px}
.slip-tables{display:grid;grid-template-columns:1fr 1fr;gap:4px;flex:1}
.slip-table{width:100%;border-collapse:collapse}
.slip-table th{font-size:7.5px;text-transform:uppercase;letter-spacing:.03em;padding:1px 2px;border-bottom:1px solid #d1d5db}
.slip-table td{font-size:8.5px;padding:1px 2px;border-bottom:1px solid #f3f4f6;vertical-align:top}
.slip-table td.amt{text-align:right;font-variant-numeric:tabular-nums}
.earn th{color:#166534}.earn .total td{font-weight:700;background:#f0fdf4}
.ded  th{color:#991b1b}.ded  .total td{font-weight:700;background:#fef2f2}
.earn .total td.amt{color:#15803d}.ded .total td.amt{color:#dc2626}
.net-row{margin-top:3px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:3px;
         padding:3px 5px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0}
.net-label{font-size:7.5px;font-weight:700;text-transform:uppercase;color:#1e40af;letter-spacing:.04em}
.net-amount{font-size:12px;font-weight:700;color:#1d4ed8}
.slip-footer{font-size:7px;color:#9ca3af;text-align:center;margin-top:2px;border-top:1px solid #f3f4f6;padding-top:2px;flex-shrink:0}
.controls{padding:8px 14px;display:flex;gap:8px;align-items:center;background:#f9fafb;border-bottom:1px solid #e5e7eb;margin-bottom:8px;position:sticky;top:0;z-index:10}
.controls small{color:#6b7280}
@media print{
    html,body{margin:0;padding:0}
    .no-print{display:none!important}
    .slip-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;page-break-after:auto}
    .slip{border:1px solid #bbb;page-break-inside:avoid}
    @page{size:A4 portrait;margin:5mm}
}
</style></head><body>';

        $html .= '<div class="no-print controls">';
        $html .= '<button onclick="window.print()" style="padding:5px 14px;background:#1d4ed8;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px">🖨 Print All Payslips</button>';
        $html .= '<a href="' . $e($backUrl) . '" style="padding:5px 12px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;text-decoration:none;color:#374151">← Back to Payslip Report</a>';
        $html .= '<small>' . count($payslips) . ' payslip(s) &nbsp;|&nbsp; Generated: ' . $e($generatedDate) . '</small>';
        $html .= '</div>';

        if ($payslips === []) {
            $html .= '<p style="padding:20px;color:#6b7280">No approved payslips found. Adjust the filters on the Payslip Report page and try again.</p>';
        } else {
            $html .= '<div class="slip-grid">';
            foreach ($payslips as $ps) {
                $empName    = (string) ($ps['employee_name']   ?? '');
                $empNumber  = (string) ($ps['employee_number'] ?? '');
                $position   = (string) ($ps['position']       ?? '—');
                $branchName = (string) ($ps['branch_name']    ?? '');
                $period     = (string) ($ps['period']          ?? '');
                $payDate    = $f($ps['pay_date'] ?? null);
                $issueDate  = $f($ps['issue_date'] ?? null);
                $gross      = (float) ($ps['gross_pay']        ?? 0);
                $deductions = (float) ($ps['total_deductions'] ?? 0);
                $net        = (float) ($ps['net_pay']          ?? 0);
                $earnings   = is_array($ps['earnings']   ?? null) ? $ps['earnings']   : [];
                $dedRows    = is_array($ps['deductions'] ?? null) ? $ps['deductions'] : [];

                $html .= '<div class="slip">';

                // Header
                $html .= '<div class="slip-head">';
                $html .= '<div class="company">Light Diamond Enterprises — Payslip</div>';
                $html .= '<div class="emp-name">' . $e($empName) . '</div>';
                $html .= '<div class="emp-meta">' . $e($empNumber) . ' &middot; ' . $e($position) . ' &middot; ' . $e($branchName) . '</div>';
                $html .= '</div>';

                // Period / dates
                $html .= '<div class="slip-period">'
                       . 'Period: <b>' . $e($period) . '</b>'
                       . ($payDate !== '' ? ' &nbsp; Pay date: <b>' . $e($payDate) . '</b>' : '')
                       . ($issueDate !== '' ? ' &nbsp; Issued: ' . $e($issueDate) : '')
                       . '</div>';

                // Earnings + Deductions side by side
                $html .= '<div class="slip-tables">';

                // Earnings column
                $html .= '<table class="slip-table earn"><thead><tr><th>Earnings</th><th class="amt">₱</th></tr></thead><tbody>';
                foreach ($earnings as $row) {
                    $html .= '<tr><td>' . $e((string) ($row['description'] ?? '')) . '</td>'
                           . '<td class="amt">' . $n((float) ($row['amount'] ?? 0)) . '</td></tr>';
                }
                if ($earnings === []) {
                    $html .= '<tr><td colspan="2" style="color:#9ca3af">—</td></tr>';
                }
                $html .= '</tbody><tfoot><tr class="total"><td>Gross</td><td class="amt">₱' . $n($gross) . '</td></tr></tfoot></table>';

                // Deductions column
                $html .= '<table class="slip-table ded"><thead><tr><th>Deductions</th><th class="amt">₱</th></tr></thead><tbody>';
                foreach ($dedRows as $row) {
                    $html .= '<tr><td>' . $e((string) ($row['description'] ?? '')) . '</td>'
                           . '<td class="amt">' . $n((float) ($row['amount'] ?? 0)) . '</td></tr>';
                }
                if ($dedRows === []) {
                    $html .= '<tr><td colspan="2" style="color:#9ca3af">—</td></tr>';
                }
                $html .= '</tbody><tfoot><tr class="total"><td>Deductions</td><td class="amt">₱' . $n($deductions) . '</td></tr></tfoot></table>';

                $html .= '</div>'; // .slip-tables

                // Net pay bar
                $html .= '<div class="net-row">'
                       . '<span class="net-label">Net Pay</span>'
                       . '<span class="net-amount">₱' . $n($net) . '</span>'
                       . '</div>';

                // Footer
                $html .= '<div class="slip-footer">This is a system-generated payslip. Please keep for your records.</div>';

                $html .= '</div>'; // .slip
            }
            $html .= '</div>'; // .slip-grid
        }

        $html .= '</body></html>';

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    // -----------------------------------------------------------------------
    // GET /hr/reports/export
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function export(array $params = []): void
    {
        $type   = trim((string) ($_GET['type']   ?? ''));
        $format = trim((string) ($_GET['format'] ?? 'html')); // 'html' or 'csv'
        $pdo    = $this->makeConnection()->pdo();

        $e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $n = fn(float $v): string  => number_format($v, 2);

        $title = $this->reportTitle($type);

        // ---------- Collect data ----------
        $rows    = [];
        $headers = [];
        $year    = (int) ($_GET['year'] ?? date('Y'));
        $filters = [];

        if (in_array($type, ['payroll', 'attendance', 'requests', 'contributions', 'employees'], true)) {
            $filters = $this->previewFilters($type);
            [$headers, $rows] = $this->previewData($type, $filters);
        } else {
            switch ($type) {

            case '13th_month':
                $headers = ['Employee #', 'Employee Name', 'Total Basic Pay', '13th Month Pay'];
                $filters = $this->thirteenthMonthFilters();
                $year    = $filters['year'];
                $service = new \Wbpms\Application\PayrollService($this->makeConnection());
                foreach ($service->compute13thMonth($year, [
                    'branch_id'   => $filters['branch_id'],
                    'employee_id' => $filters['employee_id'],
                ]) as $r) {
                    $rows[] = [
                        $r['employee_number'],
                        $r['employee_name'],
                        number_format((float) $r['basic_total'], 2, '.', ''),
                        number_format((float) $r['thirteenth_month'], 2, '.', ''),
                    ];
                }
                break;

            case 'payslips':
                // For HTML and PDF we build a full detailed-payslip document
                // (earnings + deductions per employee) and return early so we
                // never hit the generic table builder below.
                {
                    $periodId = filter_var($_GET['payroll_period_id'] ?? 0, FILTER_VALIDATE_INT);
                    $periodId = $periodId !== false && $periodId > 0 ? (int) $periodId : 0;
                    $branchId = filter_var($_GET['branch_id'] ?? 0, FILTER_VALIDATE_INT);
                    $branchId = $branchId !== false && $branchId > 0 ? (int) $branchId : 0;
                    $employeeSearch = trim((string) ($_GET['employee_search'] ?? ''));

                    $where = ["pr.status = 'Approved'"];
                    $bind  = [];
                    if ($periodId > 0) {
                        $where[]            = 'pr.payroll_period_id = :period_id';
                        $bind[':period_id'] = $periodId;
                    }
                    if ($branchId > 0) {
                        $where[]             = 'pr.branch_id = :branch_id';
                        $bind[':branch_id']  = $branchId;
                    }
                    if ($employeeSearch !== '') {
                        $where[] = '(e.last_name LIKE :emp_last OR e.first_name LIKE :emp_first OR e.employee_number LIKE :emp_num)';
                        $pat = '%' . $employeeSearch . '%';
                        $bind[':emp_last'] = $pat;
                        $bind[':emp_first'] = $pat;
                        $bind[':emp_num']   = $pat;
                    }

                    $idStmt = $pdo->prepare(
                        "SELECT ps.payslip_id, pr.reviewed_at AS approved_at
                           FROM payslip ps
                           JOIN payroll p         ON p.payroll_id         = ps.payroll_id
                           JOIN payroll_run pr    ON pr.payroll_run_id    = p.payroll_run_id
                           JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
                           JOIN employee e        ON e.employee_id        = p.employee_id
                           JOIN branch b          ON b.branch_id          = pr.branch_id
                          WHERE " . implode(' AND ', $where) . "
                          ORDER BY pp.period_start DESC, e.last_name, e.first_name
                          LIMIT 500"
                    );
                    $idStmt->execute($bind);
                    $matched = $idStmt->fetchAll();

                    // CSV: emit one flat row per payslip (summary, no line-item detail)
                    if ($format === 'csv') {
                        $csvHeaders = ['Employee #', 'Employee Name', 'Branch', 'Period', 'Gross Pay', 'Total Deductions', 'Net Pay', 'Approved At'];
                        $csvRows    = [];
                        foreach ($matched as $m) {
                            // Use the plain summary query result already in $matched for speed,
                            // but we need gross/net — reuse payslipData or just re-query.
                            // To avoid N queries for CSV we fetch summary data directly.
                        }
                        // Re-query for CSV summary data
                        $sumStmt = $pdo->prepare(
                            "SELECT e.employee_number,
                                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                                    b.branch_name,
                                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period_label,
                                    p.gross_pay, p.total_deductions, p.net_pay,
                                    pr.reviewed_at AS approved_at
                               FROM payslip ps
                               JOIN payroll p         ON p.payroll_id         = ps.payroll_id
                               JOIN payroll_run pr    ON pr.payroll_run_id    = p.payroll_run_id
                               JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
                               JOIN employee e        ON e.employee_id        = p.employee_id
                               JOIN branch b          ON b.branch_id          = pr.branch_id
                              WHERE " . implode(' AND ', $where) . "
                              ORDER BY pp.period_start DESC, e.last_name
                              LIMIT 500"
                        );
                        $sumStmt->execute($bind);
                        foreach ($sumStmt->fetchAll() as $r) {
                            $csvRows[] = [
                                $r['employee_number'],
                                $r['employee_name'],
                                $r['branch_name'],
                                $r['period_label'],
                                number_format((float) $r['gross_pay'], 2, '.', ''),
                                number_format((float) $r['total_deductions'], 2, '.', ''),
                                number_format((float) $r['net_pay'], 2, '.', ''),
                                (string) ($r['approved_at'] ?? ''),
                            ];
                        }
                        $slug     = 'payslips';
                        $filename = $slug . '-' . date('Ymd') . '.csv';
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Pragma: no-cache');
                        header('Expires: 0');
                        $out = fopen('php://output', 'w');
                        if ($out === false) { http_response_code(500); return; }
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, $csvHeaders);
                        foreach ($csvRows as $row) {
                            fputcsv($out, array_map('strval', $row));
                        }
                        fclose($out);
                        return;
                    }

                    // HTML / PDF: load full payslip detail and render a payslip-per-card document
                    $service  = new \Wbpms\Application\PayrollService($this->makeConnection());
                    $payslips = [];
                    foreach ($matched as $m) {
                        try {
                            $detail = $service->payslipData((int) $m['payslip_id']);
                            $detail['approved_at'] = $m['approved_at'];
                            $payslips[] = $detail;
                        } catch (\RuntimeException) {
                            // Skip unresolvable payslip
                        }
                    }

                    $payslipsHtml = $this->buildPayslipsHtml(
                        $payslips,
                        $e,
                        includeControls: ($format !== 'pdf'),
                    );

                    if ($format === 'pdf') {
                        PdfRenderer::download($payslipsHtml, 'payslips-' . date('ymd') . '.pdf');
                        return;
                    }

                    header('Content-Type: text/html; charset=utf-8');
                    echo $payslipsHtml;
                    return;
                }
                break;

            }
        }

        // ---------- CSV download (REQ076) ----------
        if ($format === 'csv') {
            $slug     = preg_replace('/[^a-z0-9_-]/i', '-', $type ?: 'report');
            $filename = $slug . '-' . date('Ymd') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $out = fopen('php://output', 'w');
            if ($out === false) {
                http_response_code(500);
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
            if ($headers !== []) {
                fputcsv($out, $headers);
            }
            foreach ($rows as $row) {
                fputcsv($out, array_map('strval', $row));
            }
            fclose($out);
            return;
        }

        // ---------- PDF download (format=pdf) ----------
        // Generate a real downloadable PDF via Dompdf instead of relying on
        // the browser's print-to-PDF dialog.
        if ($format === 'pdf') {
            $html        = $this->buildReportHtml($title, $type, $year, $filters, $headers, $rows, $e);
            $slug        = preg_replace('/[^a-z0-9_-]/i', '-', $type ?: 'report');
            $filename    = $slug . '-' . date('ymd') . '.pdf';
            PdfRenderer::download($html, $filename);
            return;
        }

        // ---------- Print-ready HTML (format=html) ----------
        $html = $this->buildReportHtml($title, $type, $year, $filters, $headers, $rows, $e, includeControls: true);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    /**
     * Build the print-ready HTML for a report export.
     *
     * When $includeControls is true (on-screen HTML view), the output
     * includes the Print / PDF / CSV / Back buttons and the auto-print
     * script. When false (PDF generation), only the report content is
     * returned so Dompdf renders a clean document.
     *
     * Dates use MM/DD/YY per project standard.
     *
     * @param list<string>                $headers
     * @param list<list<string|int|float>> $rows
     * @param callable(string): string     $e  HTML-escape helper
     */
    private function buildReportHtml(
        string $title,
        string $type,
        int $year,
        array $filters,
        array $headers,
        array $rows,
        callable $e,
        bool $includeControls = false,
    ): string {
        $reportScope = '';
        if ($type === '13th_month') {
            $reportScope = ' &nbsp;|&nbsp; Year: ' . $year;
            if (($filters['branch_id'] ?? 0) > 0) {
                $reportScope .= ' &nbsp;|&nbsp; Branch filter applied';
            }
            if (($filters['employee_id'] ?? 0) > 0) {
                $reportScope .= ' &nbsp;|&nbsp; Employee filter applied';
            }
        }

        $generatedDate = (new \DateTimeImmutable())->format('m/d/Y H:i');

        $csvUrl = '?' . http_build_query(array_merge($_GET, ['format' => 'csv']));
        $pdfUrl = '?' . http_build_query(array_merge($_GET, ['format' => 'pdf']));

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        $html .= '<title>' . $e($title) . '</title>';
        $html .= '<style>
            body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
            h2{margin:0 0 4px;font-size:15px}
            .meta{font-size:10px;color:#555;margin-bottom:12px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #ccc;padding:4px 7px;text-align:left}
            th{background:#f0f0f0;font-weight:bold}
            tr:nth-child(even){background:#fafafa}
            .no-data{text-align:center;padding:20px;color:#999}
            @media print{.no-print{display:none}}
        </style>';
        $html .= '</head><body>';
        $html .= '<h2>' . $e($title) . '</h2>';
        $html .= '<div class="meta">Light Diamond Enterprises &nbsp;|&nbsp; Generated: ' . $generatedDate . $reportScope . '</div>';

        if ($includeControls) {
            $html .= '<div class="no-print" style="margin-bottom:10px;display:flex;gap:8px;align-items:center">'
                   . '<button onclick="window.print()" style="padding:4px 12px;cursor:pointer">🖨 Print</button>'
                   . '<a href="' . $e($pdfUrl) . '" style="padding:4px 10px;background:#374151;color:#fff;text-decoration:none;border-radius:4px;font-size:11px">⬇ PDF</a>'
                   . '<a href="' . $e($csvUrl) . '" style="padding:4px 10px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:4px;font-size:11px">⬇ CSV</a>'
                   . '<button onclick="history.back()" style="padding:4px 10px;cursor:pointer">← Back</button>'
                   . '</div>';
        }

        if ($rows === []) {
            $html .= '<p class="no-data">No data available for this report.</p>';
        } else {
            $html .= '<table><thead><tr>';
            foreach ($headers as $h) {
                $html .= '<th>' . $e($h) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . $e((string)$cell) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '<p class="meta" style="margin-top:10px">Total records: ' . count($rows) . '</p>';
        }

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Build the print/PDF-ready HTML for the Employee Payslip Report.
     *
     * Renders one card per employee showing the earnings and deductions
     * breakdown, gross pay, total deductions, and net pay — mirroring the
     * on-screen report layout but with print-friendly inline styles.
     *
     * @param list<array<string,mixed>>  $payslips  Each is from PayrollService::payslipData()
     * @param callable(string): string $e          HTML-escape helper
     */
    private function buildPayslipsHtml(array $payslips, callable $e, bool $includeControls = false): string
    {
        $n = static fn(float $v): string => number_format($v, 2);
        $f = static fn(\DateTimeInterface|string|null $v): string => PdfRenderer::formatDate($v);

        $generatedDate = (new \DateTimeImmutable())->format('m/d/Y H:i');

        $totalGross = 0.0;
        $totalDeductions = 0.0;
        $totalNet = 0.0;
        foreach ($payslips as $ps) {
            $totalGross      += (float) ($ps['gross_pay']        ?? 0);
            $totalDeductions += (float) ($ps['total_deductions'] ?? 0);
            $totalNet        += (float) ($ps['net_pay']          ?? 0);
        }

        $csvUrl = '?' . http_build_query(array_merge($_GET, ['format' => 'csv']));
        $pdfUrl = '?' . http_build_query(array_merge($_GET, ['format' => 'pdf']));

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        $html .= '<title>Employee Payslip Report</title>';
        $html .= '<style>
            body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
            h2{margin:0 0 4px;font-size:15px}
            .meta{font-size:10px;color:#555;margin-bottom:12px}
            .payslip-card{border:1px solid #ccc;border-radius:6px;padding:14px;margin-bottom:16px;page-break-inside:avoid}
            .card-header{display:flex;justify-content:space-between;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-bottom:10px}
            .grid{display:table;width:100%;border-spacing:10px 0}
            .col{display:table-cell;width:50%;vertical-align:top}
            table{width:100%;border-collapse:collapse}
            th,td{padding:4px 6px;font-size:10px;text-align:left}
            th{border-bottom:1px solid #ccc;text-transform:uppercase;font-size:9px}
            td.amt,th.amt{text-align:right}
            .earn th{color:#166534}
            .ded th{color:#991b1b}
            .total-row td{font-weight:bold;background:#f9f9f9}
            .net-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:8px 10px;margin-top:10px;display:flex;justify-content:space-between}
            .net-label{font-size:9px;text-transform:uppercase;color:#1e40af;font-weight:bold}
            .net-amount{font-size:14px;font-weight:bold;color:#1d4ed8}
            .no-data{text-align:center;padding:20px;color:#999}
            .report-totals{border-top:3px solid #1d4ed8;padding:10px 14px;background:#f8fafc;margin-top:16px;display:flex;justify-content:space-between}
            .report-totals .t{display:inline-block;margin-left:20px}
            @media print{.no-print{display:none}}
        </style></head><body>';
        $html .= '<h2>Employee Payslip Report</h2>';
        $html .= '<div class="meta">Light Diamond Enterprises &nbsp;|&nbsp; Generated: ' . $generatedDate
               . ' &nbsp;|&nbsp; ' . count($payslips) . ' payslip(s)</div>';

        if ($includeControls) {
            $html .= '<div class="no-print" style="margin-bottom:10px;display:flex;gap:8px;align-items:center">'
                   . '<button onclick="window.print()" style="padding:4px 12px;cursor:pointer">🖨 Print</button>'
                   . '<a href="' . $e($pdfUrl) . '" style="padding:4px 10px;background:#374151;color:#fff;text-decoration:none;border-radius:4px;font-size:11px">⬇ PDF</a>'
                   . '<a href="' . $e($csvUrl) . '" style="padding:4px 10px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:4px;font-size:11px">⬇ CSV</a>'
                   . '<button onclick="history.back()" style="padding:4px 10px;cursor:pointer">← Back</button>'
                   . '</div>';
        }

        if ($payslips === []) {
            $html .= '<p class="no-data">No payslips available for the selected filters.</p>';
        } else {
            foreach ($payslips as $ps) {
                $empName    = (string) ($ps['employee_name']   ?? '');
                $empNumber  = (string) ($ps['employee_number'] ?? '');
                $position   = (string) ($ps['position']       ?? '—');
                $branchName = (string) ($ps['branch_name']    ?? '');
                $period     = (string) ($ps['period']          ?? '');
                $payDate    = $f($ps['pay_date'] ?? null);
                $gross      = (float) ($ps['gross_pay']       ?? 0);
                $deductions = (float) ($ps['total_deductions'] ?? 0);
                $net        = (float) ($ps['net_pay']         ?? 0);
                $earnings   = is_array($ps['earnings']   ?? null) ? $ps['earnings']   : [];
                $dedRows    = is_array($ps['deductions'] ?? null) ? $ps['deductions'] : [];

                $html .= '<div class="payslip-card">';
                // header
                $html .= '<div class="card-header">';
                $html .= '<div><b style="font-size:12px">' . $e($empName) . '</b>'
                       . '<br><small style="color:#555">' . $e($empNumber) . ' · ' . $e($position) . '</small></div>';
                $html .= '<div style="text-align:right"><b>' . $e($period) . '</b>'
                       . '<br><small style="color:#555">Branch: ' . $e($branchName)
                       . ($payDate !== '' ? ' · Pay date: ' . $e($payDate) : '') . '</small></div>';
                $html .= '</div>';

                // earnings + deductions side by side
                $html .= '<div class="grid"><div class="col">';
                $html .= '<table class="earn"><thead><tr><th>Earnings</th><th class="amt">Amount</th></tr></thead><tbody>';
                foreach ($earnings as $row) {
                    $html .= '<tr><td>' . $e((string)($row['description'] ?? '')) . '</td>'
                           . '<td class="amt">₱' . $n((float)($row['amount'] ?? 0)) . '</td></tr>';
                }
                if ($earnings === []) {
                    $html .= '<tr><td colspan="2" style="color:#999">No earnings recorded.</td></tr>';
                }
                $html .= '</tbody><tfoot><tr class="total-row" style="background:#f0fdf4"><td>Gross Pay</td>'
                       . '<td class="amt" style="color:#15803d">₱' . $n($gross) . '</td></tr></tfoot></table>';
                $html .= '</div>';

                $html .= '<div class="col">';
                $html .= '<table class="ded"><thead><tr><th>Deductions</th><th class="amt">Amount</th></tr></thead><tbody>';
                foreach ($dedRows as $row) {
                    $html .= '<tr><td>' . $e((string)($row['description'] ?? '')) . '</td>'
                           . '<td class="amt" style="color:#dc2626">₱' . $n((float)($row['amount'] ?? 0)) . '</td></tr>';
                }
                if ($dedRows === []) {
                    $html .= '<tr><td colspan="2" style="color:#999">No deductions recorded.</td></tr>';
                }
                $html .= '</tbody><tfoot><tr class="total-row" style="background:#fef2f2"><td>Total Deductions</td>'
                       . '<td class="amt" style="color:#dc2626">₱' . $n($deductions) . '</td></tr></tfoot></table>';
                $html .= '</div></div>';

                // net pay
                $html .= '<div class="net-box"><span class="net-label">Net Pay</span>'
                       . '<span class="net-amount">₱' . $n($net) . '</span></div>';
                $html .= '</div>';
            }

            // report totals
            $html .= '<div class="report-totals"><b>Report Totals (' . count($payslips) . ')</b>'
                   . '<div><span class="t">Total Gross: ₱' . $n($totalGross) . '</span>'
                   . '<span class="t">Total Deductions: ₱' . $n($totalDeductions) . '</span>'
                   . '<span class="t">Total Net Pay: ₱' . $n($totalNet) . '</span></div></div>';
        }

        $html .= '</body></html>';
        return $html;
    }

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
            'payslips'      => 'Employee Payslip Report',
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
