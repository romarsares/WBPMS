<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use PDO;
use RuntimeException;
use Wbpms\Application\PayrollService;
use Wbpms\Application\RequestService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * OwnerController — Business Owner payroll and request review actions.
 *
 * Routes (all require BusinessOwner role):
 *   GET  /owner/payroll                  → payrollList()    — pending payroll runs
 *   GET  /owner/payroll/{id}/review      → reviewForm()     — review a single run
 *   POST /owner/payroll/{id}/approve     → approve()        — approve payroll run
 *   POST /owner/payroll/{id}/return      → returnRun()      — return run to HR
 *   GET  /owner/requests                 → requestList()    — HRApproved requests queue
 *   GET  /owner/requests/{id}            → requestShow()    — request detail
 *   POST /owner/requests/{id}/approve    → requestApprove() — final approval
 *   POST /owner/requests/{id}/return     → requestReturn()  — return to HR
 *
 * REQ050, REQ051 (payroll); REQ034b (requests).
 */
final class OwnerController
{
    // =========================================================================
    // Payroll
    // =========================================================================

    /**
     * GET /owner/payroll
     *
     * @param array<string, string> $params
     */
    public function payrollList(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        $stmt = $pdo->query(
            "SELECT pr.payroll_run_id AS id,
                    b.branch_name,
                    CONCAT(pp.period_start, ' – ', pp.period_end) AS period_label,
                    pr.status,
                    pr.submitted_at,
                    (SELECT COUNT(*) FROM payroll p WHERE p.payroll_run_id = pr.payroll_run_id)
                        AS employee_count,
                    COALESCE(pr.gross_pay, 0.00) AS gross_total,
                    COALESCE(pr.net_pay, 0.00)   AS net_total
             FROM payroll_run pr
             JOIN branch b         ON b.branch_id          = pr.branch_id
             JOIN payroll_period pp ON pp.payroll_period_id = pr.payroll_period_id
             WHERE pr.status = 'PendingOwnerApproval'
             ORDER BY pr.submitted_at DESC"
        );
        $pendingRuns = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        $payrollService   = new PayrollService($this->makeConnection());
        $earningSummary   = $payrollService->runEarningSummary($id);
        $earnings         = $payrollService->runEarnings($id);
        $deductionSummary = $payrollService->runDeductionSummary($id);
        $deductions       = $payrollService->runDeductions($id);

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
     * @param array<string, string> $params
     */
    public function approve(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();

        try {
            (new PayrollService($this->makeConnection()))
                ->approve($id, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Payroll run approved.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/owner/payroll');
    }

    /**
     * POST /owner/payroll/{id}/return
     *
     * @param array<string, string> $params
     */
    public function returnRun(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();
        $reason   = trim((string) ($_POST['return_reason'] ?? ''));

        try {
            (new PayrollService($this->makeConnection()))
                ->returnForRevision($id, (int) ($identity['user_id'] ?? 0), $reason);
            ViewRenderer::flash('Payroll run returned to HR for revision.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/owner/payroll');
    }

    // =========================================================================
    // Request approvals
    // =========================================================================

    /**
     * GET /owner/requests
     * Lists all HR-approved requests awaiting the Owner's final decision.
     *
     * @param array<string, string> $params
     */
    public function requestList(array $params = []): void
    {
        $service = $this->makeRequestService();
        $pending = $service->ownerPendingList();

        ViewRenderer::render('owner/requests/index', [
            'pending' => $pending,
        ], 'Request Approvals');
    }

    /**
     * GET /owner/requests/{id}
     * Shows full request detail for Owner review.
     *
     * @param array<string, string> $params
     */
    public function requestShow(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $request = $this->makeRequestService()->findOrFail($id);
        } catch (RuntimeException) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        ViewRenderer::render('owner/requests/show', [
            'request' => $request,
        ], 'Review Request');
    }

    /**
     * POST /owner/requests/{id}/approve
     * Owner finally approves an HRApproved request.
     *
     * @param array<string, string> $params
     */
    public function requestApprove(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();

        try {
            $this->makeRequestService()->ownerApprove($id, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Request approved. The employee has been notified.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/owner/requests');
    }

    /**
     * POST /owner/requests/{id}/return
     * Owner returns an HRApproved request to HR for revision.
     *
     * @param array<string, string> $params
     */
    public function requestReturn(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();
        $note     = trim((string) ($_POST['owner_notes'] ?? ''));

        try {
            $this->makeRequestService()->ownerReturn($id, (int) ($identity['user_id'] ?? 0), $note);
            ViewRenderer::flash('Request returned to HR for revision.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/owner/requests');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }

    private function makeRequestService(): RequestService
    {
        return new RequestService($this->makeConnection());
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
