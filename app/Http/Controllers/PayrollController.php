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
            $this->redirect('/hr/payroll/periods');
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
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
