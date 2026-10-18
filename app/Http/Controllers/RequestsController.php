<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use PDO;
use RuntimeException;
use Wbpms\Application\RequestService;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\EmployeeRepository;

/**
 * RequestsController — HR-facing request queue management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/requests                    → index()   — list with filters
 *   GET  /hr/requests/new                → create()  — blank submission form
 *   POST /hr/requests                    → store()   — submit on behalf of employee
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
        $approved = count(array_filter($rows, fn($r) => in_array($r['status'], ['HRApproved', 'Approved'], true)));
        $rejected = count(array_filter($rows, fn($r) => $r['status'] === 'Cancelled'));

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
    // GET /hr/requests/new
    // -----------------------------------------------------------------------

    /**
     * Render the blank form for HR to submit a request on behalf of an employee.
     *
     * @param array<string, string> $params
     */
    public function create(array $params = []): void
    {
        $connection = $this->makeConnection();
        $service    = new RequestService($connection);
        $repo       = new EmployeeRepository($connection);

        ViewRenderer::render('hr/requests/form', [
            'employees' => $this->activeEmployeeList($repo),
            'types'     => $service->requestTypes(),
            'errors'    => [],
            'old'       => [],
        ], 'New Request');
    }

    // -----------------------------------------------------------------------
    // POST /hr/requests
    // -----------------------------------------------------------------------

    /**
     * Submit a new request on behalf of the selected employee.
     *
     * The HR Head picks the employee; RequestService::submit() validates all
     * type-specific fields and enforces the sick-leave balance check (REQ078).
     *
     * @param array<string, string> $params
     */
    public function store(array $params = []): void
    {
        $connection = $this->makeConnection();
        $service    = new RequestService($connection);
        $repo       = new EmployeeRepository($connection);

        $employeeId = (int) ($_POST['employee_id'] ?? 0);

        $data = [
            'request_type_id'  => (int)    ($_POST['request_type_id']  ?? 0),
            'reason'           => trim((string) ($_POST['reason']           ?? '')),
            'start_date'       => trim((string) ($_POST['start_date']       ?? '')),
            'end_date'         => trim((string) ($_POST['end_date']         ?? '')),
            'overtime_date'    => trim((string) ($_POST['overtime_date']    ?? '')),
            'start_time'       => trim((string) ($_POST['start_time']       ?? '')),
            'end_time'         => trim((string) ($_POST['end_time']         ?? '')),
            'amount_requested' => trim((string) ($_POST['amount_requested'] ?? '')),
        ];

        if ($employeeId === 0) {
            ViewRenderer::render('hr/requests/form', [
                'employees' => $this->activeEmployeeList($repo),
                'types'     => $service->requestTypes(),
                'errors'    => ['employee_id' => 'Please select an employee.'],
                'old'       => $_POST,
            ], 'New Request');
            return;
        }

        try {
            $service->submit($employeeId, $data);
            ViewRenderer::flash('Request submitted successfully.');
            $this->redirect('/hr/requests');
        } catch (RuntimeException $e) {
            ViewRenderer::render('hr/requests/form', [
                'employees' => $this->activeEmployeeList($repo),
                'types'     => $service->requestTypes(),
                'errors'    => ['_general' => $e->getMessage()],
                'old'       => $_POST,
            ], 'New Request');
        }
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
            ViewRenderer::flash('Request pre-approved. It is now awaiting the Business Owner\'s final approval.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/requests');
    }

    // -----------------------------------------------------------------------
    // POST /hr/requests/{id}/cancel
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function cancel(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $identity = AuthMiddleware::identity();
        $note     = trim((string) ($_POST['review_notes'] ?? ''));

        try {
            $this->makeService()->cancelRequest($id, (int) ($identity['user_id'] ?? 0), $note);
            ViewRenderer::flash('Request cancelled.');
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/requests');
    }

    /**
     * @deprecated Route alias kept for backwards compatibility.
     * @param array<string, string> $params
     */
    public function reject(array $params = []): void
    {
        $this->cancel($params);
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
    // GET /hr/requests/leave-credits
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function leaveCredits(array $params = []): void
    {
        $year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
        $year = ($year !== false && $year >= 2020 && $year <= (int) date('Y') + 1)
            ? (int) $year
            : (int) date('Y');

        $service = $this->makeService();
        $rows    = $service->leaveCreditsForAll($year);

        ViewRenderer::render('hr/requests/leave-credits', [
            'rows'        => $rows,
            'year'        => $year,
            'currentYear' => (int) date('Y'),
        ], 'Leave Credit Management');
    }

    // -----------------------------------------------------------------------
    // GET  /hr/requests/leave-credits/{id}       — employee ledger + adjust form
    // POST /hr/requests/leave-credits/{id}/adjust — apply adjustment
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function leaveCreditsEmployee(array $params = []): void
    {
        $employeeId = (int) ($params['id'] ?? 0);
        $year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
        $year = ($year !== false && $year >= 2020) ? (int) $year : (int) date('Y');

        $pdo     = $this->makeConnection()->pdo();
        $service = $this->makeService();

        $empRow = $pdo->prepare(
            "SELECT employee_id, employee_number,
                    CONCAT(last_name, ', ', first_name) AS employee_name
               FROM employee WHERE employee_id = :id"
        );
        $empRow->execute([':id' => $employeeId]);
        $employee = $empRow->fetch();
        if ($employee === false) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $balance = $service->leaveBalance($employeeId, $year);
        $ledger  = $service->leaveLedger($employeeId, $year);

        ViewRenderer::render('hr/requests/leave-credits-employee', [
            'employee' => $employee,
            'balance'  => $balance,
            'ledger'   => $ledger,
            'year'     => $year,
            'errors'   => [],
        ], 'Leave Credits — ' . $employee['employee_name']);
    }

    /** @param array<string, string> $params */
    public function adjustLeaveCredit(array $params = []): void
    {
        $employeeId = (int) ($params['id'] ?? 0);
        $identity   = AuthMiddleware::identity();
        $year       = (int) ($_POST['year'] ?? date('Y'));

        $days      = (float) ($_POST['days']       ?? 0);
        $entryType = trim((string) ($_POST['entry_type'] ?? ''));
        $notes     = trim((string) ($_POST['notes']      ?? ''));

        try {
            $this->makeService()->adjustLeaveCredit(
                $employeeId,
                $days,
                $entryType,
                $notes,
                (int) ($identity['user_id'] ?? 0),
                $year
            );
            ViewRenderer::flash('Leave credit adjusted successfully.');
            $this->redirect('/hr/requests/leave-credits/' . $employeeId . '?year=' . $year);
        } catch (\RuntimeException $e) {
            $pdo      = $this->makeConnection()->pdo();
            $service  = $this->makeService();
            $empRow   = $pdo->prepare(
                "SELECT employee_id, employee_number,
                        CONCAT(last_name, ', ', first_name) AS employee_name
                   FROM employee WHERE employee_id = :id"
            );
            $empRow->execute([':id' => $employeeId]);
            $employee = $empRow->fetch() ?: [];
            ViewRenderer::render('hr/requests/leave-credits-employee', [
                'employee' => $employee,
                'balance'  => $service->leaveBalance($employeeId, $year),
                'ledger'   => $service->leaveLedger($employeeId, $year),
                'year'     => $year,
                'errors'   => [$e->getMessage()],
            ], 'Leave Credits — ' . ($employee['employee_name'] ?? ''));
        }
    }

    // -----------------------------------------------------------------------
    // GET /hr/requests/cash-advance-tracker
    // -----------------------------------------------------------------------

    /** @param array<string, string> $params */
    public function cashAdvanceTracker(array $params = []): void
    {
        $pdo = $this->makeConnection()->pdo();

        $showAll = isset($_GET['show_all']);

        $where = $showAll ? '' : "WHERE cah.status = 'Active' AND cah.remaining_balance > 0";

        $stmt = $pdo->query(
            "SELECT cah.history_id,
                    e.employee_id,
                    e.employee_number,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    b.branch_name,
                    cah.original_amount,
                    cah.remaining_balance,
                    cah.status        AS ca_status,
                    cah.approved_at,
                    r.reason          AS request_reason,
                    cah.request_id
               FROM cash_advance_history cah
               JOIN employee e ON e.employee_id  = cah.employee_id
               LEFT JOIN employee_branch_assignment eba
                      ON eba.employee_id = e.employee_id AND eba.effective_to IS NULL
               LEFT JOIN branch b ON b.branch_id = eba.branch_id
               LEFT JOIN request r ON r.request_id = cah.request_id
              {$where}
              ORDER BY cah.remaining_balance DESC, e.last_name, e.first_name"
        );
        $rows = $stmt->fetchAll();

        $totalOutstanding = array_sum(array_map(
            static fn(array $r): float => (float) $r['remaining_balance'],
            $rows
        ));

        ViewRenderer::render('hr/requests/cash-advance-tracker', [
            'rows'             => $rows,
            'totalOutstanding' => $totalOutstanding,
            'showAll'          => $showAll,
        ], 'Cash Advance Balances');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function makeService(): RequestService
    {
        return new RequestService($this->makeConnection());
    }

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }

    /**
     * Return a flat list of active employees for the employee picker.
     *
     * @return list<array{id:int,employee_number:string,last_name:string,first_name:string,branch_name:string}>
     */
    private function activeEmployeeList(EmployeeRepository $repo): array
    {
        return $repo->findAll(['status' => 'active']);
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
}
