<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use PDO;
use RuntimeException;
use Wbpms\Application\PayrollAdjustmentService;
use Wbpms\Application\PayrollService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * PayrollController — HR payroll run management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/payroll                 → index()   — list runs
 *   GET  /hr/payroll/create           → create()  — select period + branch
 *   POST /hr/payroll                 → store()   — create Draft run
 *   GET  /hr/payroll/{id}            → show()    — detail view
 *   POST /hr/payroll/{id}/compute    → compute() — run computation
 *   POST /hr/payroll/{id}/submit     → submit()  — submit to owner
 *
 * REQ047–REQ050.
 */
final class PayrollController
{
    // -----------------------------------------------------------------------
    // GET /hr/payroll
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        $runs = $pdo->query(
            "SELECT pr.payroll_run_id,
                    pp.period_start,
                    pp.period_end,
                    pp.pay_date,
                    b.branch_name,
                    pr.status,
                    pr.created_at,
                    pr.submitted_at,
                    pr.reviewed_at,
                    pr.return_reason,
                    pr.cancellation_reason,
                    COUNT(p.payroll_id)           AS employee_count,
                    COALESCE(SUM(p.gross_pay), 0) AS gross_total,
                    COALESCE(SUM(p.net_pay), 0)   AS net_total
               FROM payroll_run pr
               JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
               JOIN branch b          ON b.branch_id          = pr.branch_id
               LEFT JOIN payroll p    ON p.payroll_run_id     = pr.payroll_run_id
              GROUP BY pr.payroll_run_id, pp.period_start, pp.period_end, pp.pay_date,
                       b.branch_name, pr.status, pr.created_at, pr.submitted_at,
                       pr.reviewed_at, pr.return_reason, pr.cancellation_reason
              ORDER BY pr.created_at DESC
              LIMIT 100"
        )->fetchAll();

        $total    = count($runs);
        $draft    = count(array_filter($runs, fn($r) => in_array($r['status'], ['Draft', 'Computed'])));
        $pending  = count(array_filter($runs, fn($r) => $r['status'] === 'PendingOwnerApproval'));
        $approved = count(array_filter($runs, fn($r) => $r['status'] === 'Approved'));

        ViewRenderer::render('hr/payroll/index', [
            'runs'     => $runs,
            'total'    => $total,
            'draft'    => $draft,
            'pending'  => $pending,
            'approved' => $approved,
        ], 'Payroll');
    }

    // -----------------------------------------------------------------------
    // GET /hr/payroll/periods
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function listPeriods(array $params = []): void
    {
        ViewRenderer::render('hr/payroll/periods', [
            'periods' => $this->makeService()->listPeriods(),
            'errors'  => [],
            'success' => null,
        ], 'Manage Payroll Periods');
    }

    // -----------------------------------------------------------------------
    // POST /hr/payroll/periods
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function storePeriod(array $params = []): void
    {
        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        $service     = $this->makeService();

        try {
            $service->createPeriod($periodStart);
            ViewRenderer::flash('Payroll period created successfully.');
            $this->redirect('/hr/settings/periods');
        } catch (RuntimeException $e) {
            ViewRenderer::render('hr/payroll/periods', [
                'periods'      => $service->listPeriods(),
                'errors'       => [$e->getMessage()],
                'period_start' => $periodStart,
            ], 'Manage Payroll Periods');
        }
    }

    // -----------------------------------------------------------------------
    // GET /hr/payroll/periods  (legacy — redirect to /hr/settings/periods)
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function redirectPeriods(array $params = []): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . '/hr/settings/periods', true, 301);
        exit;
    }

    // -----------------------------------------------------------------------
    // GET /hr/payroll/create
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function create(array $params = []): void
    {
        $pdo     = $this->makeConnection()->pdo();
        $periods = $this->makeService()->periods();
        $branches = $pdo->query(
            "SELECT branch_id, branch_name FROM branch WHERE status='Active' ORDER BY branch_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('hr/payroll/run', [
            'periods'  => $periods,
            'branches' => $branches,
            'errors'   => [],
        ], 'Create Payroll Run');
    }

    // -----------------------------------------------------------------------
    // POST /hr/payroll
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        $identity = AuthMiddleware::identity();
        $service  = $this->makeService();
        $data     = [
            'payroll_period_id' => (int) ($_POST['payroll_period_id'] ?? 0),
            'branch_id'         => (int) ($_POST['branch_id']         ?? 0),
        ];

        try {
            $runId = $service->createRun($data, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Payroll run created. Click Compute to calculate amounts.');
            $this->redirect('/hr/payroll/' . $runId);
        } catch (RuntimeException $e) {
            $pdo      = $this->makeConnection()->pdo();
            $periods  = $service->periods();
            $branches = $pdo->query(
                "SELECT branch_id, branch_name FROM branch WHERE status='Active' ORDER BY branch_name"
            )->fetchAll(PDO::FETCH_ASSOC);

            ViewRenderer::render('hr/payroll/run', [
                'periods'  => $periods,
                'branches' => $branches,
                'errors'   => [$e->getMessage()],
            ], 'Create Payroll Run');
        }
    }

    // -----------------------------------------------------------------------
    // GET /hr/payroll/{id}
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $service = $this->makeService();

        try {
            $run     = $service->findRunOrFail($id);
            $details = $service->runDetails($id);
            $earningSummary = $service->runEarningSummary($id);
            $earnings = $service->runEarnings($id);
            $deductionSummary = $service->runDeductionSummary($id);
            $deductions = $service->runDeductions($id);
        } catch (RuntimeException) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        ViewRenderer::render('hr/payroll/detail', [
            'run'     => $run,
            'details' => $details,
            'earningSummary' => $earningSummary,
            'earnings' => $earnings,
            'deductionSummary' => $deductionSummary,
            'deductions' => $deductions,
            'errors'  => [],
        ], 'Payroll Run Detail');
    }

    // -----------------------------------------------------------------------
    // GET /hr/payroll/{id}/employees/{payrollId}/adjust
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function adjustForm(array $params = []): void
    {
        $runId = (int) ($params['id'] ?? 0);
        $payrollId = (int) ($params['payrollId'] ?? 0);
        try {
            $payroll = $this->adjustmentService()->findForAdjustment($runId, $payrollId);
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
            $this->redirect('/hr/payroll/' . $runId);
            return;
        }

        ViewRenderer::render('hr/payroll/adjust', [
            'runId' => $runId,
            'payroll' => $payroll,
            'errors' => [],
            'old' => [],
        ], 'Adjust Payroll');
    }

    // -----------------------------------------------------------------------
    // POST /hr/payroll/{id}/employees/{payrollId}/adjust
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function adjust(array $params = []): void
    {
        $runId = (int) ($params['id'] ?? 0);
        $payrollId = (int) ($params['payrollId'] ?? 0);
        $input = [
            'adjustment_kind' => trim((string) ($_POST['adjustment_kind'] ?? '')),
            'amount' => trim((string) ($_POST['amount'] ?? '')),
            'reason' => trim((string) ($_POST['reason'] ?? '')),
        ];

        try {
            $identity = AuthMiddleware::identity();
            $this->adjustmentService()->adjust($runId, $payrollId, $input, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Manual payroll adjustment saved and payroll totals recalculated.');
            $this->redirect('/hr/payroll/' . $runId);
            return;
        } catch (RuntimeException $e) {
            try {
                $payroll = $this->adjustmentService()->findForAdjustment($runId, $payrollId);
            } catch (RuntimeException) {
                ViewRenderer::flashError('Payroll employee record not found.');
                $this->redirect('/hr/payroll/' . $runId);
                return;
            }
            ViewRenderer::render('hr/payroll/adjust', [
                'runId' => $runId,
                'payroll' => $payroll,
                'errors' => [$e->getMessage()],
                'old' => $input,
            ], 'Adjust Payroll');
        }
    }

    // -----------------------------------------------------------------------
    // POST /hr/payroll/{id}/compute
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function compute(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();

        try {
            $this->makeService()->computeRun($id, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Payroll computed successfully. Review figures before submitting.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/payroll/' . $id);
    }

    // -----------------------------------------------------------------------
    // POST /hr/payroll/{id}/submit
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function submit(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();

        try {
            $this->makeService()->submitForApproval($id, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Payroll run submitted to the Business Owner for approval.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/payroll');
    }

    // -----------------------------------------------------------------------
    // POST /hr/payroll/{id}/cancel
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function cancel(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();
        $reason   = trim((string) ($_POST['cancellation_reason'] ?? ''));

        try {
            $this->makeService()->cancelRun($id, (int) ($identity['user_id'] ?? 0), $reason);
            ViewRenderer::flash('Payroll run cancelled. Its history was retained and you can now create a corrected run for this period and branch.');
            $this->redirect('/hr/payroll');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
            $this->redirect('/hr/payroll/' . $id);
        }
    }

    // -----------------------------------------------------------------------
    // GET /hr/payroll/{id}/payslips/{payslipId}/print   (REQ074 — HR side)
    // -----------------------------------------------------------------------

    /**
     * Print-ready individual payslip accessible by HR Head and Business Owner.
     * No employee ownership check — role-based access only.
     * Confirms the payslip belongs to the referenced payroll run before rendering.
     *
     * @param array<string, string> $params
     */
    public function payslipPrint(array $params = []): void
    {
        $runId     = (int) ($params['id']        ?? 0);
        $payslipId = (int) ($params['payslipId'] ?? 0);

        $data = $this->resolvePayslipForRun($runId, $payslipId);
        if ($data === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->buildPayslipHtml($data, backPath: '/hr/payroll/' . $runId);
    }

    // -----------------------------------------------------------------------
    // GET /hr/payroll/{id}/payslips/{payslipId}/download  (REQ074 — HR side)
    // -----------------------------------------------------------------------

    /**
     * Download individual payslip as PDF, accessible by HR Head and Business Owner.
     *
     * @param array<string, string> $params
     */
    public function payslipPdfDownload(array $params = []): void
    {
        $runId     = (int) ($params['id']        ?? 0);
        $payslipId = (int) ($params['payslipId'] ?? 0);

        $data = $this->resolvePayslipForRun($runId, $payslipId);
        if ($data === null) {
            http_response_code(404);
            echo 'Payslip not found.';
            return;
        }

        $html     = $this->buildPayslipHtml($data, backPath: null);
        $filename = 'payslip-' . preg_replace('/[^a-z0-9_-]/i', '_', (string) $data['employee_number']) . '-' . str_replace([' ', '–', '/'], '-', (string) $data['period_start']) . '.pdf';

        \Wbpms\Infrastructure\Reports\PdfRenderer::download($html, $filename);
    }

    // -----------------------------------------------------------------------
    // GET /payroll/{id}/timesheet
    // -----------------------------------------------------------------------

    /**
     * Print-ready attendance evidence for the payroll run's exact cut-off.
     * The query deliberately mirrors the attendance eligibility used during
     * payroll computation: approved/completed imports and payable statuses.
     *
     * @param array<string, string> $params
     */
    public function printTimesheet(array $params = []): void
    {
        $runId = (int) ($params['id'] ?? 0);
        try {
            $run = $this->makeService()->findRunOrFail($runId);
        } catch (RuntimeException) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $stmt = $this->makeConnection()->pdo()->prepare(
            "SELECT e.employee_number,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    a.attendance_date, a.time_in, a.time_out,
                    a.hours_worked_minutes, a.late_minutes,
                    a.undertime_minutes, a.overtime_minutes, a.status
               FROM payroll p
               JOIN employee e ON e.employee_id = p.employee_id
               LEFT JOIN attendance a
                      ON a.employee_id = p.employee_id
                     AND a.attendance_date BETWEEN :period_start AND :period_end
                     AND a.status IN ('Complete', 'Approved')
                     AND (
                         a.import_batch_id IS NULL
                         OR a.import_batch_id IN (
                             SELECT import_batch_id
                               FROM attendance_import_batch
                              WHERE status IN ('Approved', 'Completed')
                         )
                     )
              WHERE p.payroll_run_id = :run_id
              ORDER BY e.last_name, e.first_name, a.attendance_date"
        );
        $stmt->execute([
            ':period_start' => $run['period_start'],
            ':period_end' => $run['period_end'],
            ':run_id' => $runId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $attendanceRows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['attendance_date'] !== null
        ));
        $summary = [
            'employee_count' => count(array_unique(array_column($rows, 'employee_number'))),
            'attendance_days' => count($attendanceRows),
            'worked_minutes' => array_sum(array_map(static fn(array $row): int => (int) $row['hours_worked_minutes'], $attendanceRows)),
            'late_minutes' => array_sum(array_map(static fn(array $row): int => (int) $row['late_minutes'], $attendanceRows)),
            'overtime_minutes' => array_sum(array_map(static fn(array $row): int => (int) $row['overtime_minutes'], $attendanceRows)),
        ];
        $isOwner = (AuthMiddleware::identity()['role_name'] ?? '') === 'BusinessOwner';

        ViewRenderer::render('payroll/timesheet-print', [
            'run' => $run,
            'rows' => $rows,
            'summary' => $summary,
            'backPath' => $isOwner ? '/owner/payroll/' . $runId . '/review' : '/hr/payroll/' . $runId,
            'backLabel' => $isOwner ? 'Back to Payroll Review' : 'Back to Payroll Run',
            'activePage' => 'payroll',
        ], 'Cut-off Timesheet');
    }

    // -----------------------------------------------------------------------
    // GET /payroll/{id}/disbursement-summary
    // -----------------------------------------------------------------------

    /**
     * Prepare the documented, manual BDO disbursement package for one payroll
     * period. A package becomes ready only when every non-cancelled branch run
     * in the period is approved; it never creates a bank upload or transfer.
     *
     * @param array<string, string> $params
     */
    public function disbursementSummary(array $params = []): void
    {
        $runId = (int) ($params['id'] ?? 0);
        try {
            $run = $this->makeService()->findRunOrFail($runId);
        } catch (RuntimeException) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $pdo = $this->makeConnection()->pdo();
        $branchRunsStmt = $pdo->prepare(
            "SELECT pr.payroll_run_id, b.branch_name, pr.status,
                    COUNT(p.payroll_id) AS employee_count,
                    COALESCE(SUM(p.gross_pay), 0) AS gross_total,
                    COALESCE(SUM(p.total_deductions), 0) AS deductions_total,
                    COALESCE(SUM(p.net_pay), 0) AS net_total
               FROM payroll_run pr
               JOIN branch b ON b.branch_id = pr.branch_id
               LEFT JOIN payroll p ON p.payroll_run_id = pr.payroll_run_id
              WHERE pr.payroll_period_id = :period_id
                AND pr.status <> 'Cancelled'
              GROUP BY pr.payroll_run_id, b.branch_name, pr.status
              ORDER BY b.branch_name"
        );
        $branchRunsStmt->execute([':period_id' => $run['payroll_period_id']]);
        $branchRuns = $branchRunsStmt->fetchAll(PDO::FETCH_ASSOC);
        $blockingRuns = array_values(array_filter(
            $branchRuns,
            static fn(array $branchRun): bool => $branchRun['status'] !== 'Approved'
        ));
        $isReady = $branchRuns !== [] && $blockingRuns === [];

        $entries = [];
        if ($isReady) {
            $entryStmt = $pdo->prepare(
                "SELECT b.branch_name,
                        e.employee_number,
                        CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                        p.net_pay,
                        bd.bank_name, bd.account_name, bd.account_number
                   FROM payroll p
                   JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
                   JOIN branch b ON b.branch_id = pr.branch_id
                   JOIN employee e ON e.employee_id = p.employee_id
                   LEFT JOIN bank_details bd
                          ON bd.employee_id = e.employee_id
                         AND bd.bank_name = 'BDO'
                         AND bd.status = 'Active'
                         AND bd.effective_from <= :pay_date
                         AND (bd.effective_to IS NULL OR bd.effective_to >= :pay_date2)
                  WHERE pr.payroll_period_id = :period_id
                    AND pr.status = 'Approved'
                  ORDER BY b.branch_name, e.last_name, e.first_name"
            );
            $entryStmt->execute([
                ':period_id' => $run['payroll_period_id'],
                ':pay_date'  => $run['pay_date'],
                ':pay_date2' => $run['pay_date'],
            ]);
            $entries = $entryStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $summary = [
            'employee_count' => count($entries),
            'gross_total' => array_sum(array_map(static fn(array $entry): float => (float) $entry['net_pay'], $entries)),
            'deductions_total' => array_sum(array_map(static fn(array $branchRun): float => (float) $branchRun['deductions_total'], $branchRuns)),
            'cheque_total' => array_sum(array_map(static fn(array $entry): float => (float) $entry['net_pay'], $entries)),
            'missing_bdo_accounts' => count(array_filter($entries, static fn(array $entry): bool => empty($entry['account_number']))),
        ];
        // Gross totals are taken from approved branch runs so the cover sheet
        // remains useful even before account maintenance is complete.
        $summary['gross_total'] = array_sum(array_map(static fn(array $branchRun): float => (float) $branchRun['gross_total'], $branchRuns));
        $isOwner = (AuthMiddleware::identity()['role_name'] ?? '') === 'BusinessOwner';

        ViewRenderer::render('payroll/disbursement-summary', [
            'run' => $run,
            'branchRuns' => $branchRuns,
            'blockingRuns' => $blockingRuns,
            'entries' => $entries,
            'summary' => $summary,
            'isReady' => $isReady,
            'backPath' => $isOwner ? '/owner/payroll/' . $runId . '/review' : '/hr/payroll/' . $runId,
            'backLabel' => $isOwner ? 'Back to Payroll Review' : 'Back to Payroll Run',
            'activePage' => 'payroll',
        ], 'Payroll Disbursement Summary');
    }

    // -----------------------------------------------------------------------
    // GET /payroll/{id}/disbursement-summary/export  (REQ076)
    // -----------------------------------------------------------------------

    /**
     * Export the disbursement deposit-slip preparation list as a CSV file.
     * Available only when all branch runs for the period are Approved.
     * REQ076: export reports as digital files.
     *
     * @param array<string, string> $params
     */
    public function disbursementExport(array $params = []): void
    {
        $runId = (int) ($params['id'] ?? 0);
        try {
            $run = $this->makeService()->findRunOrFail($runId);
        } catch (RuntimeException) {
            http_response_code(404);
            echo 'Payroll run not found.';
            return;
        }

        $pdo = $this->makeConnection()->pdo();

        // Only export when all branch runs are approved
        $branchRunsStmt = $pdo->prepare(
            "SELECT pr.status
               FROM payroll_run pr
              WHERE pr.payroll_period_id = :period_id
                AND pr.status <> 'Cancelled'"
        );
        $branchRunsStmt->execute([':period_id' => $run['payroll_period_id']]);
        $statuses = $branchRunsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($statuses) || in_array(false, array_map(fn($s) => $s === 'Approved', $statuses), true)) {
            http_response_code(403);
            echo 'Disbursement export is available only when all branch payroll runs are approved.';
            return;
        }

        $entryStmt = $pdo->prepare(
            "SELECT b.branch_name,
                    e.employee_number,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    p.net_pay,
                    COALESCE(bd.bank_name, '') AS bank_name,
                    COALESCE(bd.account_name, '') AS account_name,
                    COALESCE(bd.account_number, '') AS account_number
               FROM payroll p
               JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
               JOIN branch b       ON b.branch_id       = pr.branch_id
               JOIN employee e     ON e.employee_id     = p.employee_id
               LEFT JOIN bank_details bd
                      ON bd.employee_id = e.employee_id
                     AND bd.bank_name   = 'BDO'
                     AND bd.status      = 'Active'
                     AND bd.effective_from <= :pay_date
                     AND (bd.effective_to IS NULL OR bd.effective_to >= :pay_date2)
              WHERE pr.payroll_period_id = :period_id
                AND pr.status = 'Approved'
              ORDER BY b.branch_name, e.last_name, e.first_name"
        );
        $entryStmt->execute([
            ':period_id' => $run['payroll_period_id'],
            ':pay_date'  => $run['pay_date'],
            ':pay_date2' => $run['pay_date'],
        ]);
        $entries = $entryStmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = 'disbursement-' . str_replace([' ', '–', '/'], '-', (string) $run['period_label']) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            http_response_code(500);
            return;
        }
        // BOM for Excel UTF-8 compatibility
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Branch', 'Employee #', 'Employee Name', 'BDO Account Name', 'BDO Account Number', 'Net Pay (PHP)']);
        foreach ($entries as $entry) {
            fputcsv($out, [
                $entry['branch_name'],
                $entry['employee_number'],
                $entry['employee_name'],
                $entry['account_name'] !== '' ? $entry['account_name'] : 'MISSING',
                $entry['account_number'] !== '' ? $entry['account_number'] : 'MISSING',
                number_format((float) $entry['net_pay'], 2, '.', ''),
            ]);
        }
        fclose($out);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function makeService(): PayrollService
    {
        return new PayrollService($this->makeConnection());
    }

    private function adjustmentService(): PayrollAdjustmentService
    {
        return new PayrollAdjustmentService($this->makeConnection());
    }

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    // -----------------------------------------------------------------------
    // Payslip helpers (HR-side individual printing)
    // -----------------------------------------------------------------------

    /**
     * Load payslip data and confirm it belongs to the given payroll run.
     * Returns null when the payslip cannot be found or does not belong to $runId.
     *
     * @return array<string,mixed>|null
     */
    private function resolvePayslipForRun(int $runId, int $payslipId): ?array
    {
        try {
            $data = $this->makeService()->payslipData($payslipId);
        } catch (RuntimeException) {
            return null;
        }

        // Verify the payslip's payroll row belongs to the referenced run.
        $stmt = $this->makeConnection()->pdo()->prepare(
            "SELECT 1 FROM payroll WHERE payroll_id = :pid AND payroll_run_id = :rid LIMIT 1"
        );
        $stmt->execute([':pid' => $data['payroll_id'] ?? 0, ':rid' => $runId]);
        if (!$stmt->fetchColumn()) {
            return null;
        }

        return $data;
    }

    /**
     * Build a standalone print-ready HTML payslip document.
     * When $backPath is non-null a "Back" link and Print/Download buttons
     * are included; pass null for clean PDF generation.
     *
     * @param array<string,mixed> $data  From PayrollService::payslipData()
     */
    private function buildPayslipHtml(array $data, ?string $backPath): string
    {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $n = static fn(float $v): string  => number_format($v, 2);
        $f = static fn(\DateTimeInterface|string|null $v): string => \Wbpms\Infrastructure\Reports\PdfRenderer::formatDate($v);

        $html  = '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        $html .= '<title>Payslip — ' . $e((string) ($data['period'] ?? '')) . '</title>';
        $html .= '<style>
            *,*::before,*::after{box-sizing:border-box}
            body{font-family:"DejaVu Sans",Arial,sans-serif;font-size:12px;margin:20px;color:#111}
            h2{margin:0 0 4px}
            .header{display:flex;justify-content:space-between;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:12px}
            .meta{margin-bottom:12px;font-size:11px;line-height:1.6}
            table{width:100%;border-collapse:collapse;margin-bottom:12px}
            th,td{border:1px solid #ccc;padding:5px 8px;font-size:11px}
            th{background:#f0f0f0;text-align:left}
            .total-row td{font-weight:bold;background:#f9f9f9}
            .net-row td{font-weight:bold;font-size:13px;background:#e8f5e9}
            .controls{display:flex;gap:8px;margin-top:8px}
            @media print{.no-print{display:none!important}}
        </style></head><body>';

        $html .= '<div class="header">';
        $html .= '<div><h2>Light Diamond Enterprises</h2><small>PAYSLIP</small></div>';
        $html .= '<div style="text-align:right"><b>Pay Period:</b> ' . $e((string) ($data['period'] ?? '')) . '<br>';
        $html .= '<b>Pay Date:</b> ' . $e($f($data['pay_date'] ?? null)) . '</div>';
        $html .= '</div>';

        $html .= '<div class="meta">';
        $html .= '<b>Employee:</b> ' . $e((string) ($data['employee_name'] ?? '')) . ' (' . $e((string) ($data['employee_number'] ?? '')) . ')<br>';
        $html .= '<b>Position:</b> ' . $e((string) ($data['position'] ?? '—')) . ' &nbsp;&nbsp; <b>Branch:</b> ' . $e((string) ($data['branch_name'] ?? '')) . '<br>';
        $html .= '<b>Daily Rate:</b> ₱' . $n((float) ($data['daily_rate'] ?? 0)) . ' &nbsp;&nbsp; <b>Issue Date:</b> ' . $e($f($data['issue_date'] ?? null));
        $html .= '</div>';

        $html .= '<table><thead><tr><th>EARNINGS</th><th style="text-align:right">AMOUNT</th></tr></thead><tbody>';
        foreach (($data['earnings'] ?? []) as $row) {
            $html .= '<tr><td>' . $e((string) ($row['description'] ?? '')) . '</td><td style="text-align:right">₱' . $n((float) ($row['amount'] ?? 0)) . '</td></tr>';
        }
        $html .= '<tr class="total-row"><td>GROSS PAY</td><td style="text-align:right">₱' . $n((float) ($data['gross_pay'] ?? 0)) . '</td></tr>';
        $html .= '</tbody></table>';

        $html .= '<table><thead><tr><th>DEDUCTIONS</th><th style="text-align:right">AMOUNT</th></tr></thead><tbody>';
        foreach (($data['deductions'] ?? []) as $row) {
            $html .= '<tr><td>' . $e((string) ($row['description'] ?? '')) . '</td><td style="text-align:right">₱' . $n((float) ($row['amount'] ?? 0)) . '</td></tr>';
        }
        $html .= '<tr class="total-row"><td>TOTAL DEDUCTIONS</td><td style="text-align:right">₱' . $n((float) ($data['total_deductions'] ?? 0)) . '</td></tr>';
        $html .= '</tbody></table>';

        $html .= '<table><tbody>';
        $html .= '<tr class="net-row"><td><b>NET PAY</b></td><td style="text-align:right"><b>₱' . $n((float) ($data['net_pay'] ?? 0)) . '</b></td></tr>';
        $html .= '</tbody></table>';

        $html .= '<p style="font-size:10px;margin-top:16px;color:#666">This is a system-generated payslip. — Light Diamond Enterprises</p>';

        if ($backPath !== null) {
            $base      = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
            $payslipId = (int) ($data['payslip_id'] ?? 0);
            // Extract run ID from backPath (e.g. "/hr/payroll/42")
            preg_match('#/payroll/(\d+)#', $backPath, $m);
            $runId     = (int) ($m[1] ?? 0);
            $pdfUrl    = $base . '/hr/payroll/' . $runId . '/payslips/' . $payslipId . '/download';
            $html .= '<div class="no-print controls">';
            $html .= '<button onclick="window.print()" style="padding:6px 16px;cursor:pointer">🖨 Print</button>';
            $html .= '<a href="' . $e($pdfUrl) . '" style="padding:6px 16px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:4px">⬇ Download PDF</a>';
            $html .= '<a href="' . $e($base . $backPath) . '" style="padding:6px 16px;border:1px solid #d1d5db;border-radius:4px;text-decoration:none;color:#374151">← Back to Payroll Run</a>';
            $html .= '</div>';
        }

        $html .= '</body></html>';
        return $html;
    }
}
