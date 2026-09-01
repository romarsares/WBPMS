<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use RuntimeException;
use Wbpms\Application\RequestService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;

/**
 * RequestsController — HR-facing request queue management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/requests                    → index()   — list with filters
 *   GET  /hr/requests/{id}               → show()    — view detail
 *   POST /hr/requests/{id}/approve       → approve() — approve request
 *   POST /hr/requests/{id}/reject        → reject()  — reject with note
 *   POST /hr/requests/{id}/archive       → archive() — soft-archive
 *
 * REQ032–REQ036 (HR side); REQ076–REQ080 handled by EmployeePortalController.
 */
final class RequestsController
{
    // -----------------------------------------------------------------------
    // GET /hr/requests
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $service = $this->makeService();

        $filters = [
            'status'  => trim((string) ($_GET['status']  ?? '')),
            'type_id' => (int) ($_GET['type_id'] ?? 0),
            'search'  => trim((string) ($_GET['search']  ?? '')),
        ];
        if ($filters['type_id'] === 0) {
            unset($filters['type_id']);
        }

        $rows     = $service->list($filters);
        $types    = $service->requestTypes();
        $total    = count($rows);
        $pending  = count(array_filter($rows, fn($r) => $r['status'] === 'Pending'));
        $approved = count(array_filter($rows, fn($r) => $r['status'] === 'Approved'));
        $rejected = count(array_filter($rows, fn($r) => $r['status'] === 'Rejected'));

        ViewRenderer::render('hr/requests/index', [
            'rows'     => $rows,
            'types'    => $types,
            'filters'  => $filters,
            'total'    => $total,
            'pending'  => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
        ], 'Request Management');
    }

    // -----------------------------------------------------------------------
    // GET /hr/requests/{id}
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $request = $this->makeService()->findOrFail($id);
        } catch (RuntimeException) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        ViewRenderer::render('hr/requests/show', [
            'request' => $request,
            'errors'  => [],
        ], 'Request Detail');
    }

    // -----------------------------------------------------------------------
    // POST /hr/requests/{id}/approve
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function approve(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();

        try {
            $this->makeService()->approve($id, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Request approved.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/requests');
    }

    // -----------------------------------------------------------------------
    // POST /hr/requests/{id}/reject
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function reject(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();
        $note     = trim((string) ($_POST['review_notes'] ?? ''));

        try {
            $this->makeService()->reject($id, (int) ($identity['user_id'] ?? 0), $note);
            ViewRenderer::flash('Request rejected.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/requests');
    }

    // -----------------------------------------------------------------------
    // POST /hr/requests/{id}/archive
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function archive(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();

        try {
            $this->makeService()->archive($id, (int) ($identity['user_id'] ?? 0));
            ViewRenderer::flash('Request archived.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/requests');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function makeService(): RequestService
    {
        return new RequestService(new Connection(require APP_ROOT . '/config/database.php'));
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
