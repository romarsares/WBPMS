<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use PDO;
use Wbpms\Application\PayrollService;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * OwnerController — Business Owner payroll review actions.
 *
 * Routes (all require BusinessOwner role):
 *   GET /owner/payroll               → payrollList()  — list pending runs
 *   GET /owner/payroll/{id}/review   → reviewForm()   — review a single run
 *
 * Approve/Return actions (POST) are deferred until PayrollService is implemented.
 * For P0 this is read-only from the Owner's perspective.
 *
 * REQ050, REQ051 (P0 view subset).
 *
 * Schema notes (from migration 005):
 *   payroll_run: payroll_period_id, return_reason, reviewed_at
 *   payroll_period: payroll_period_id (PK), period_start, period_end
 */
final class OwnerController
{
    /**
     * GET /owner/payroll
     *
     * @param array<string, string> $params
     */
    public function payrollList(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        // Runs awaiting Owner approval
        $stmt = $pdo->query(
            "SELECT pr.payroll_run_id AS id,
                    b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period_label,
                    pr.status,
                    pr.submitted_at,
                    (SELECT COUNT(*) FROM payroll p WHERE p.payroll_run_id = pr.payroll_run_id)
                        AS employee_count,
                    COALESCE(pr.gross_pay, 0.00)         AS gross_total,
                    COALESCE(pr.net_pay, 0.00)           AS net_total
             FROM payroll_run pr
             JOIN branch b         ON b.branch_id          = pr.branch_id
             JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
             WHERE pr.status = 'PendingOwnerApproval'
             ORDER BY pr.submitted_at DESC"
        );
        $pendingRuns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recently actioned runs (last 10 Approved or Returned)
        $stmt = $pdo->query(
            "SELECT pr.payroll_run_id AS id,
                    b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period_label,
                    pr.status,
                    pr.reviewed_at AS actioned_at,
                    COALESCE(pr.net_pay, 0.00) AS net_total
             FROM payroll_run pr
             JOIN branch b         ON b.branch_id          = pr.branch_id
             JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
             WHERE pr.status IN ('Approved','Returned')
             ORDER BY pr.reviewed_at DESC
             LIMIT 10"
        );
        $recentRuns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ViewRenderer::render('owner/payroll/index', [
            'pendingRuns' => $pendingRuns,
            'recentRuns'  => $recentRuns,
        ], 'Payroll Approvals');
    }

    /**
     * GET /owner/payroll/{id}/review
     *
     * @param array<string, string> $params
     */
    public function reviewForm(array $params = []): void
    {
        $id  = (int) ($params['id'] ?? 0);
        $pdo = $this->makeConnection()->pdo();

        $stmt = $pdo->prepare(
            "SELECT pr.payroll_run_id,
                    b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period_label,
                    pr.status,
                    pr.submitted_at,
                    pr.return_reason,
                    COALESCE(pr.gross_pay, 0.00) AS gross_total,
                    COALESCE(pr.net_pay, 0.00)   AS net_total,
                    (SELECT COUNT(*) FROM payroll p WHERE p.payroll_run_id = pr.payroll_run_id)
                        AS employee_count
             FROM payroll_run pr
             JOIN branch b         ON b.branch_id          = pr.branch_id
             JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
             WHERE pr.payroll_run_id = :id"
        );
        $stmt->execute([':id' => $id]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($run === false) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        // Employee payroll rows for this run ($details matches view contract)
        $stmt = $pdo->prepare(
            "SELECT p.payroll_id,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number,
                    p.gross_pay,
                    p.total_deductions,
                    p.net_pay
             FROM payroll p
             JOIN employee e ON e.employee_id = p.employee_id
             WHERE p.payroll_run_id = :id
             ORDER BY e.last_name, e.first_name"
        );
        $stmt->execute([':id' => $id]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Owner review has the same read-only itemization as HR, so approval
        // is based on the actual earnings and deductions rather than totals alone.
        $payrollService = new PayrollService($this->makeConnection());
        $earningSummary = $payrollService->runEarningSummary($id);
        $earnings = $payrollService->runEarnings($id);
        $deductionSummary = $payrollService->runDeductionSummary($id);
        $deductions = $payrollService->runDeductions($id);

        ViewRenderer::render('owner/payroll/review', [
            'run'              => $run,
            'details'          => $details,
            'earningSummary'   => $earningSummary,
            'earnings'         => $earnings,
            'deductionSummary' => $deductionSummary,
            'deductions'       => $deductions,
            'errors'           => [],
        ], 'Review Payroll');
    }

    /**
     * POST /owner/payroll/{id}/approve
     *
     * REQ051: Business Owner approves a run.
     *
     * @param array<string, string> $params
     */
    public function approve(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = \Wbpms\Http\Middleware\AuthMiddleware::identity();

        try {
            (new \Wbpms\Application\PayrollService($this->makeConnection()))
                ->approve($id, (int) ($identity['user_id'] ?? 0));
            \Wbpms\Http\View\ViewRenderer::flash('Payroll run approved.');
        } catch (\RuntimeException $e) {
            \Wbpms\Http\View\ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/owner/payroll');
    }

    /**
     * POST /owner/payroll/{id}/return
     *
     * REQ051: Business Owner returns a run to HR with a note.
     *
     * @param array<string, string> $params
     */
    public function returnRun(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = \Wbpms\Http\Middleware\AuthMiddleware::identity();
        $reason   = trim((string) ($_POST['return_reason'] ?? ''));

        try {
            (new \Wbpms\Application\PayrollService($this->makeConnection()))
                ->returnForRevision($id, (int) ($identity['user_id'] ?? 0), $reason);
            \Wbpms\Http\View\ViewRenderer::flash('Payroll run returned to HR for revision.');
        } catch (\RuntimeException $e) {
            \Wbpms\Http\View\ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/owner/payroll');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

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
