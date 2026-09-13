<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Wbpms\Application\AttendancePolicyService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * AttendancePolicyController — HR attendance policy flag review queue.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/attendance/policy/flags                 → index()    — queue with filters
 *   POST /hr/attendance/policy/flags/evaluate        → evaluate() — run threshold evaluation
 *   POST /hr/attendance/policy/flags/{id}/review     → review()   — record HR decision
 *
 * REQ006 AC16–AC17: flags are HR-review alerts only. The system never
 * suspends or terminates an employee automatically; HR records the reviewed
 * action and supporting reason.
 */
final class AttendancePolicyController
{
    // -----------------------------------------------------------------------
    // GET /hr/attendance/policy/flags
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $service = $this->makeService();

        $filters = [
            'status' => trim((string) ($_GET['status'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'employee_number' => trim((string) ($_GET['employee_number'] ?? '')),
        ];

        $rows = $service->listFlags($filters);
        $counts = $service->counts();

        $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        ViewRenderer::render('hr/attendance/policy/index', [
            'rows' => $rows,
            'counts' => $counts,
            'filters' => $filters,
            'activePage' => 'policy-flags',
            'defaultFrom' => $today->modify('-30 days')->format('Y-m-d'),
            'defaultTo' => $today->format('Y-m-d'),
        ], 'Attendance Policy Flags');
    }

    // -----------------------------------------------------------------------
    // POST /hr/attendance/policy/flags/evaluate
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function evaluate(array $params = []): void
    {
        $from = trim((string) ($_POST['from'] ?? ''));
        $to = trim((string) ($_POST['to'] ?? ''));

        if ($from === '' || $to === '' || $to < $from || !$this->isIsoDate($from) || !$this->isIsoDate($to)) {
            ViewRenderer::flashError('Provide a valid evaluation date range (YYYY-MM-DD).');
            $this->redirect('/hr/attendance/policy/flags');
            return;
        }

        try {
            $created = $this->makeService()->evaluateAll($from, $to);
            ViewRenderer::flash("Attendance policy evaluation complete. {$created} new flag(s) created for HR review.");
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/attendance/policy/flags');
    }

    // -----------------------------------------------------------------------
    // POST /hr/attendance/policy/flags/{id}/review
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function review(array $params = []): void
    {
        $flagId = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();

        try {
            $this->makeService()->review($flagId, (int) ($identity['user_id'] ?? 0), [
                'status' => trim((string) ($_POST['status'] ?? '')),
                'action_taken' => trim((string) ($_POST['action_taken'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
                'notice_title' => trim((string) ($_POST['issue_employee_notice'] ?? '')) === '1'
                    ? trim((string) ($_POST['notice_title'] ?? ''))
                    : '',
                'notice_body' => trim((string) ($_POST['issue_employee_notice'] ?? '')) === '1'
                    ? trim((string) ($_POST['notice_body'] ?? ''))
                    : '',
            ]);
            ViewRenderer::flash('Policy flag reviewed.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/attendance/policy/flags');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function isIsoDate(string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    private function makeService(): AttendancePolicyService
    {
        return new AttendancePolicyService($this->makeConnection());
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
